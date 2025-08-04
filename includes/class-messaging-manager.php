<?php
/**
 * Messaging system management class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Messaging Manager class
 */
class WPMatch_Messaging_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_wpmatch_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_wpmatch_get_conversations', array($this, 'ajax_get_conversations'));
        add_action('wp_ajax_wpmatch_get_messages', array($this, 'ajax_get_messages'));
        add_action('wp_ajax_wpmatch_mark_read', array($this, 'ajax_mark_read'));
        add_action('wp_ajax_wpmatch_delete_conversation', array($this, 'ajax_delete_conversation'));
        add_action('wp_ajax_wpmatch_block_user', array($this, 'ajax_block_user'));
    }

    /**
     * Initialize messaging system
     */
    public function init() {
        // Schedule cleanup of old messages
        add_action('wpmatch_cleanup_old_messages', array($this, 'cleanup_old_messages'));
    }

    /**
     * Send a message
     *
     * @param int $sender_id
     * @param int $receiver_id
     * @param string $message_content
     * @param string $message_type
     * @return int|WP_Error Message ID or error
     */
    public function send_message($sender_id, $receiver_id, $message_content, $message_type = 'text') {
        // Validate permissions
        if (!WPMatch_Security::user_can('send_messages', $sender_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to send messages.', 'wpmatch'));
        }

        // Check if users are blocked
        if ($this->are_users_blocked($sender_id, $receiver_id)) {
            return new WP_Error('users_blocked', __('Cannot send message to this user.', 'wpmatch'));
        }

        // Validate message content
        $message_content = WPMatch_Security::sanitize_input($message_content, 'textarea');
        $message_content = WPMatch_Security::clean_content($message_content);

        if (empty($message_content)) {
            return new WP_Error('empty_message', __('Message cannot be empty.', 'wpmatch'));
        }

        $max_length = get_option('wpmatch_messaging_settings', array())['message_max_length'] ?? 1000;
        if (strlen($message_content) > $max_length) {
            return new WP_Error('message_too_long', sprintf(__('Message cannot exceed %d characters.', 'wpmatch'), $max_length));
        }

        // Get or create conversation
        $conversation_id = $this->get_or_create_conversation($sender_id, $receiver_id);
        if (is_wp_error($conversation_id)) {
            return $conversation_id;
        }

        // Insert message
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $messages_table = $database->get_table_name('messages');

        $message_data = array(
            'conversation_id' => $conversation_id,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'message_content' => $message_content,
            'message_type' => $message_type,
            'is_read' => 0,
            'status' => 'sent',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($messages_table, $message_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to send message.', 'wpmatch'));
        }

        $message_id = $wpdb->insert_id;

        // Update conversation last activity
        $this->update_conversation_activity($conversation_id, $message_id);

        // Send notification
        do_action('wpmatch_message_sent', $message_id, $sender_id, $receiver_id);

        return $message_id;
    }

    /**
     * Get or create conversation between two users
     *
     * @param int $user1_id
     * @param int $user2_id
     * @return int|WP_Error Conversation ID or error
     */
    private function get_or_create_conversation($user1_id, $user2_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $conversations_table = $database->get_table_name('conversations');

        // Order user IDs to ensure consistent lookups
        $participant_1 = min($user1_id, $user2_id);
        $participant_2 = max($user1_id, $user2_id);

        // Check if conversation exists
        $conversation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$conversations_table} 
             WHERE participant_1 = %d AND participant_2 = %d",
            $participant_1, $participant_2
        ));

        if ($conversation_id) {
            return $conversation_id;
        }

        // Create new conversation
        $conversation_data = array(
            'participant_1' => $participant_1,
            'participant_2' => $participant_2,
            'last_activity' => current_time('mysql'),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($conversations_table, $conversation_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create conversation.', 'wpmatch'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Update conversation last activity
     *
     * @param int $conversation_id
     * @param int $last_message_id
     */
    private function update_conversation_activity($conversation_id, $last_message_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $conversations_table = $database->get_table_name('conversations');

        $wpdb->update(
            $conversations_table,
            array(
                'last_message_id' => $last_message_id,
                'last_activity' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $conversation_id)
        );
    }

    /**
     * Get user conversations
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_user_conversations($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $conversations_table = $database->get_table_name('conversations');
        $messages_table = $database->get_table_name('messages');

        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, 
                    m.message_content as last_message_content,
                    m.created_at as last_message_time,
                    m.sender_id as last_message_sender,
                    CASE 
                        WHEN c.participant_1 = %d THEN c.participant_2 
                        ELSE c.participant_1 
                    END as other_user_id
             FROM {$conversations_table} c
             LEFT JOIN {$messages_table} m ON c.last_message_id = m.id
             WHERE c.participant_1 = %d OR c.participant_2 = %d
             ORDER BY c.last_activity DESC
             LIMIT %d OFFSET %d",
            $user_id, $user_id, $user_id, $limit, $offset
        ));

        // Enhance with user data and unread counts
        foreach ($conversations as &$conversation) {
            // Get other user data
            $other_user = get_user_by('ID', $conversation->other_user_id);
            if ($other_user) {
                $conversation->other_user = array(
                    'ID' => $other_user->ID,
                    'display_name' => $other_user->display_name,
                    'avatar_url' => get_avatar_url($other_user->ID)
                );

                // Get profile photo
                $profile_manager = wpmatch_plugin()->profile_manager;
                $primary_photo = $profile_manager->get_primary_photo($other_user->ID);
                if ($primary_photo) {
                    $conversation->other_user['photo_url'] = $primary_photo->thumbnail;
                }
            }

            // Get unread message count
            $conversation->unread_count = $this->get_unread_message_count($conversation->id, $user_id);
        }

        return $conversations;
    }

    /**
     * Get messages in a conversation
     *
     * @param int $conversation_id
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_conversation_messages($conversation_id, $user_id, $limit = 50, $offset = 0) {
        // Verify user has access to this conversation
        if (!$this->user_has_conversation_access($conversation_id, $user_id)) {
            return array();
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $messages_table = $database->get_table_name('messages');

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM {$messages_table} m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.conversation_id = %d
             ORDER BY m.created_at DESC
             LIMIT %d OFFSET %d",
            $conversation_id, $limit, $offset
        ));

        // Mark messages as read for the current user
        $this->mark_messages_read($conversation_id, $user_id);

        return array_reverse($messages); // Return in chronological order
    }

    /**
     * Mark messages as read
     *
     * @param int $conversation_id
     * @param int $user_id
     */
    public function mark_messages_read($conversation_id, $user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $messages_table = $database->get_table_name('messages');

        $wpdb->update(
            $messages_table,
            array(
                'is_read' => 1,
                'read_at' => current_time('mysql')
            ),
            array(
                'conversation_id' => $conversation_id,
                'receiver_id' => $user_id,
                'is_read' => 0
            )
        );
    }

    /**
     * Get unread message count for a conversation
     *
     * @param int $conversation_id
     * @param int $user_id
     * @return int
     */
    private function get_unread_message_count($conversation_id, $user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $messages_table = $database->get_table_name('messages');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} 
             WHERE conversation_id = %d AND receiver_id = %d AND is_read = 0",
            $conversation_id, $user_id
        ));
    }

    /**
     * Check if user has access to conversation
     *
     * @param int $conversation_id
     * @param int $user_id
     * @return bool
     */
    private function user_has_conversation_access($conversation_id, $user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $conversations_table = $database->get_table_name('conversations');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversations_table} 
             WHERE id = %d AND (participant_1 = %d OR participant_2 = %d)",
            $conversation_id, $user_id, $user_id
        ));

        return $count > 0;
    }

    /**
     * Check if users are blocked
     *
     * @param int $user1_id
     * @param int $user2_id
     * @return bool
     */
    private function are_users_blocked($user1_id, $user2_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $blocks_table = $database->get_table_name('blocks');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$blocks_table} 
             WHERE (user_id = %d AND blocked_user_id = %d) 
             OR (user_id = %d AND blocked_user_id = %d)",
            $user1_id, $user2_id, $user2_id, $user1_id
        ));

        return $count > 0;
    }

    /**
     * Block a user
     *
     * @param int $user_id
     * @param int $blocked_user_id
     * @param string $reason
     * @return bool|WP_Error
     */
    public function block_user($user_id, $blocked_user_id, $reason = '') {
        if (!WPMatch_Security::user_can('block_users', $user_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to block users.', 'wpmatch'));
        }

        if ($user_id === $blocked_user_id) {
            return new WP_Error('invalid_action', __('You cannot block yourself.', 'wpmatch'));
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $blocks_table = $database->get_table_name('blocks');

        // Check if already blocked
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$blocks_table} 
             WHERE user_id = %d AND blocked_user_id = %d",
            $user_id, $blocked_user_id
        ));

        if ($existing > 0) {
            return new WP_Error('already_blocked', __('User is already blocked.', 'wpmatch'));
        }

        // Insert block record
        $result = $wpdb->insert(
            $blocks_table,
            array(
                'user_id' => $user_id,
                'blocked_user_id' => $blocked_user_id,
                'reason' => WPMatch_Security::sanitize_input($reason, 'text'),
                'created_at' => current_time('mysql')
            )
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to block user.', 'wpmatch'));
        }

        do_action('wpmatch_user_blocked', $user_id, $blocked_user_id, $reason);

        return true;
    }

    /**
     * Delete a conversation
     *
     * @param int $conversation_id
     * @param int $user_id
     * @return bool|WP_Error
     */
    public function delete_conversation($conversation_id, $user_id) {
        if (!$this->user_has_conversation_access($conversation_id, $user_id)) {
            return new WP_Error('permission_denied', __('You do not have access to this conversation.', 'wpmatch'));
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $conversations_table = $database->get_table_name('conversations');
        $messages_table = $database->get_table_name('messages');

        // Delete messages first
        $wpdb->delete($messages_table, array('conversation_id' => $conversation_id));

        // Delete conversation
        $result = $wpdb->delete($conversations_table, array('id' => $conversation_id));

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete conversation.', 'wpmatch'));
        }

        do_action('wpmatch_conversation_deleted', $conversation_id, $user_id);

        return true;
    }

    /**
     * Cleanup old messages based on retention settings
     */
    public function cleanup_old_messages() {
        $messaging_settings = get_option('wpmatch_messaging_settings', array());
        $retention_days = $messaging_settings['message_retention_days'] ?? 365;

        if ($retention_days <= 0) {
            return; // Don't delete if retention is disabled
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $messages_table = $database->get_table_name('messages');

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$messages_table} WHERE created_at < %s",
            $cutoff_date
        ));
    }

    /**
     * AJAX Handlers
     */

    /**
     * AJAX handler for sending messages
     */
    public function ajax_send_message() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to send messages.', 'wpmatch'));
        }

        $sender_id = get_current_user_id();
        $receiver_id = absint($_POST['receiver_id'] ?? 0);
        $message_content = $_POST['message_content'] ?? '';

        if (!$receiver_id || !$message_content) {
            wp_send_json_error(__('Invalid message data.', 'wpmatch'));
        }

        $result = $this->send_message($sender_id, $receiver_id, $message_content);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message_id' => $result,
            'message' => __('Message sent successfully.', 'wpmatch')
        ));
    }

    /**
     * AJAX handler for getting conversations
     */
    public function ajax_get_conversations() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $conversations = $this->get_user_conversations($user_id, $per_page, $offset);

        wp_send_json_success($conversations);
    }

    /**
     * AJAX handler for getting messages
     */
    public function ajax_get_messages() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $conversation_id = absint($_POST['conversation_id'] ?? 0);
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        if (!$conversation_id) {
            wp_send_json_error(__('Invalid conversation ID.', 'wpmatch'));
        }

        $messages = $this->get_conversation_messages($conversation_id, $user_id, $per_page, $offset);

        wp_send_json_success($messages);
    }

    /**
     * AJAX handler for marking messages as read
     */
    public function ajax_mark_read() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $conversation_id = absint($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(__('Invalid conversation ID.', 'wpmatch'));
        }

        $this->mark_messages_read($conversation_id, $user_id);

        wp_send_json_success(__('Messages marked as read.', 'wpmatch'));
    }

    /**
     * AJAX handler for deleting conversations
     */
    public function ajax_delete_conversation() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $conversation_id = absint($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(__('Invalid conversation ID.', 'wpmatch'));
        }

        $result = $this->delete_conversation($conversation_id, $user_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Conversation deleted successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for blocking users
     */
    public function ajax_block_user() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $blocked_user_id = absint($_POST['blocked_user_id'] ?? 0);
        $reason = $_POST['reason'] ?? '';

        if (!$blocked_user_id) {
            wp_send_json_error(__('Invalid user ID.', 'wpmatch'));
        }

        $result = $this->block_user($user_id, $blocked_user_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('User blocked successfully.', 'wpmatch'));
    }
}