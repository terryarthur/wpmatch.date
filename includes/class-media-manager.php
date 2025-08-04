<?php
/**
 * Media and photo management class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Media Manager class
 */
class WPMatch_Media_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_wpmatch_upload_photo', array($this, 'ajax_upload_photo'));
        add_action('wp_ajax_wpmatch_delete_photo', array($this, 'ajax_delete_photo'));
        add_action('wp_ajax_wpmatch_set_primary_photo', array($this, 'ajax_set_primary_photo'));
        add_action('wp_ajax_wpmatch_reorder_photos', array($this, 'ajax_reorder_photos'));
    }

    /**
     * Initialize media management
     */
    public function init() {
        // Add image sizes
        add_image_size('wpmatch_profile_thumb', 150, 150, true);
        add_image_size('wpmatch_profile_medium', 300, 400, true);
        add_image_size('wpmatch_profile_large', 600, 800, true);

        // Filter upload directory for WPMatch uploads
        add_filter('upload_dir', array($this, 'custom_upload_directory'));
    }

    /**
     * Upload photo for user
     *
     * @param int $user_id
     * @param array $file
     * @param array $args
     * @return int|WP_Error
     */
    public function upload_photo($user_id, $file, $args = array()) {
        // Check permissions
        if (!WPMatch_Security::user_can('upload_photos', $user_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to upload photos.', 'wpmatch'));
        }

        // Check photo limit
        $max_photos = get_option('wpmatch_max_photos_per_user', 10);
        $current_count = $this->get_user_photo_count($user_id);

        if ($current_count >= $max_photos) {
            return new WP_Error('photo_limit_exceeded', sprintf(
                __('You can only upload up to %d photos.', 'wpmatch'), 
                $max_photos
            ));
        }

        // Validate file
        $validation = WPMatch_Security::validate_file_upload($file, array('image'), 5242880); // 5MB
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Process image
        $processed_file = $this->process_image($file);
        if (is_wp_error($processed_file)) {
            return $processed_file;
        }

        // Upload to WordPress media library
        $attachment_id = $this->upload_to_media_library($processed_file, $user_id);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Add to photos table
        $photo_id = $this->add_photo_record($user_id, $attachment_id, $args);
        if (is_wp_error($photo_id)) {
            // Clean up uploaded file
            wp_delete_attachment($attachment_id, true);
            return $photo_id;
        }

        do_action('wpmatch_photo_uploaded', $photo_id, $user_id, $attachment_id);

        return $photo_id;
    }

    /**
     * Process image (resize, optimize, watermark)
     *
     * @param array $file
     * @return array|WP_Error
     */
    private function process_image($file) {
        $image_editor = wp_get_image_editor($file['tmp_name']);
        
        if (is_wp_error($image_editor)) {
            return $image_editor;
        }

        // Get image dimensions
        $size = $image_editor->get_size();
        $max_width = 1200;
        $max_height = 1600;

        // Resize if too large
        if ($size['width'] > $max_width || $size['height'] > $max_height) {
            $image_editor->resize($max_width, $max_height, false);
        }

        // Set quality
        $image_editor->set_quality(85);

        // Apply watermark if enabled
        if (get_option('wpmatch_enable_watermark', false)) {
            $this->apply_watermark($image_editor);
        }

        // Save processed image
        $saved = $image_editor->save($file['tmp_name']);
        
        if (is_wp_error($saved)) {
            return $saved;
        }

        return $file;
    }

    /**
     * Apply watermark to image
     *
     * @param WP_Image_Editor $image_editor
     */
    private function apply_watermark($image_editor) {
        $watermark_path = get_option('wpmatch_watermark_path', '');
        
        if (!$watermark_path || !file_exists($watermark_path)) {
            return;
        }

        $watermark_position = get_option('wpmatch_watermark_position', 'bottom-right');
        $watermark_opacity = get_option('wpmatch_watermark_opacity', 50);

        // This is a placeholder for watermark functionality
        // Actual implementation would depend on the image editor capabilities
        // or require additional libraries like GD or ImageMagick
    }

    /**
     * Upload processed file to media library
     *
     * @param array $file
     * @param int $user_id
     * @return int|WP_Error
     */
    private function upload_to_media_library($file, $user_id) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        // Set custom upload directory
        add_filter('upload_dir', array($this, 'set_wpmatch_upload_dir'));

        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => array($this, 'generate_unique_filename')
        );

        $uploaded_file = wp_handle_upload($file, $upload_overrides);

        // Remove filter
        remove_filter('upload_dir', array($this, 'set_wpmatch_upload_dir'));

        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_error', $uploaded_file['error']);
        }

        // Create attachment
        $attachment = array(
            'guid' => $uploaded_file['url'],
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => sanitize_file_name(basename($uploaded_file['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $user_id,
        );

        $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate metadata
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    /**
     * Set custom upload directory for WPMatch photos
     *
     * @param array $upload_dir
     * @return array
     */
    public function set_wpmatch_upload_dir($upload_dir) {
        $upload_dir['subdir'] = '/wpmatch/profiles';
        $upload_dir['path'] = $upload_dir['basedir'] . $upload_dir['subdir'];
        $upload_dir['url'] = $upload_dir['baseurl'] . $upload_dir['subdir'];

        return $upload_dir;
    }

    /**
     * Generate unique filename
     *
     * @param string $dir
     * @param string $name
     * @param string $ext
     * @return string
     */
    public function generate_unique_filename($dir, $name, $ext) {
        $user_id = get_current_user_id();
        $timestamp = time();
        $random = wp_generate_password(8, false);
        
        return "user_{$user_id}_{$timestamp}_{$random}{$ext}";
    }

    /**
     * Add photo record to database
     *
     * @param int $user_id
     * @param int $attachment_id
     * @param array $args
     * @return int|WP_Error
     */
    private function add_photo_record($user_id, $attachment_id, $args = array()) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        // Check if this is the first photo (make it primary)
        $photo_count = $this->get_user_photo_count($user_id);
        $is_primary = ($photo_count === 0) ? 1 : 0;

        // Get next order number
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(photo_order) FROM {$photos_table} WHERE user_id = %d",
            $user_id
        ));

        $photo_order = ($max_order !== null) ? $max_order + 1 : 1;

        $photo_data = array(
            'user_id' => $user_id,
            'attachment_id' => $attachment_id,
            'is_primary' => $is_primary,
            'is_verified' => 0,
            'privacy' => isset($args['privacy']) ? $args['privacy'] : 'public',
            'photo_order' => $photo_order,
            'status' => get_option('wpmatch_require_photo_approval', 1) ? 'pending' : 'approved',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $result = $wpdb->insert($photos_table, $photo_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save photo record.', 'wpmatch'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Delete photo
     *
     * @param int $photo_id
     * @param int $user_id
     * @return bool|WP_Error
     */
    public function delete_photo($photo_id, $user_id = null) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Check permissions
        if (!WPMatch_Security::user_can('upload_photos', $user_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to delete photos.', 'wpmatch'));
        }

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        // Get photo data
        $photo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$photos_table} WHERE id = %d AND user_id = %d",
            $photo_id, $user_id
        ));

        if (!$photo) {
            return new WP_Error('photo_not_found', __('Photo not found.', 'wpmatch'));
        }

        // Delete from database
        $result = $wpdb->delete($photos_table, array('id' => $photo_id));

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete photo record.', 'wpmatch'));
        }

        // Delete attachment
        wp_delete_attachment($photo->attachment_id, true);

        // If this was the primary photo, set another photo as primary
        if ($photo->is_primary) {
            $this->set_next_primary_photo($user_id);
        }

        do_action('wpmatch_photo_deleted', $photo_id, $user_id, $photo->attachment_id);

        return true;
    }

    /**
     * Set photo as primary
     *
     * @param int $photo_id
     * @param int $user_id
     * @return bool|WP_Error
     */
    public function set_primary_photo($photo_id, $user_id = null) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Check permissions
        if (!WPMatch_Security::user_can('upload_photos', $user_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to modify photos.', 'wpmatch'));
        }

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        // Verify photo belongs to user
        $photo_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$photos_table} WHERE id = %d AND user_id = %d",
            $photo_id, $user_id
        ));

        if (!$photo_exists) {
            return new WP_Error('photo_not_found', __('Photo not found.', 'wpmatch'));
        }

        // Remove primary status from all photos
        $wpdb->update(
            $photos_table,
            array('is_primary' => 0),
            array('user_id' => $user_id)
        );

        // Set new primary photo
        $result = $wpdb->update(
            $photos_table,
            array('is_primary' => 1),
            array('id' => $photo_id, 'user_id' => $user_id)
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to set primary photo.', 'wpmatch'));
        }

        do_action('wpmatch_primary_photo_changed', $photo_id, $user_id);

        return true;
    }

    /**
     * Set next photo as primary when current primary is deleted
     *
     * @param int $user_id
     */
    private function set_next_primary_photo($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        // Get the first available photo
        $next_photo_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$photos_table} 
             WHERE user_id = %d AND status = 'approved' 
             ORDER BY photo_order ASC 
             LIMIT 1",
            $user_id
        ));

        if ($next_photo_id) {
            $wpdb->update(
                $photos_table,
                array('is_primary' => 1),
                array('id' => $next_photo_id)
            );
        }
    }

    /**
     * Reorder photos
     *
     * @param array $photo_order
     * @param int $user_id
     * @return bool|WP_Error
     */
    public function reorder_photos($photo_order, $user_id = null) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Check permissions
        if (!WPMatch_Security::user_can('upload_photos', $user_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to modify photos.', 'wpmatch'));
        }

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        foreach ($photo_order as $order => $photo_id) {
            $wpdb->update(
                $photos_table,
                array('photo_order' => $order + 1),
                array('id' => absint($photo_id), 'user_id' => $user_id)
            );
        }

        do_action('wpmatch_photos_reordered', $photo_order, $user_id);

        return true;
    }

    /**
     * Get user photo count
     *
     * @param int $user_id
     * @param string $status
     * @return int
     */
    public function get_user_photo_count($user_id, $status = 'approved') {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$photos_table} WHERE user_id = %d AND status = %s",
            $user_id, $status
        ));
    }

    /**
     * Get photos pending approval
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_pending_photos($limit = 20, $offset = 0) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        $photos = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.display_name, u.user_email 
             FROM {$photos_table} p
             INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.status = 'pending'
             ORDER BY p.created_at ASC
             LIMIT %d OFFSET %d",
            $limit, $offset
        ));

        // Add attachment URLs
        foreach ($photos as &$photo) {
            $photo->url = wp_get_attachment_url($photo->attachment_id);
            $photo->thumbnail = wp_get_attachment_image_url($photo->attachment_id, 'thumbnail');
        }

        return $photos;
    }

    /**
     * Approve photo
     *
     * @param int $photo_id
     * @return bool|WP_Error
     */
    public function approve_photo($photo_id) {
        if (!WPMatch_Security::user_can('moderate_photos')) {
            return new WP_Error('permission_denied', __('You do not have permission to moderate photos.', 'wpmatch'));
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        $result = $wpdb->update(
            $photos_table,
            array('status' => 'approved', 'updated_at' => current_time('mysql')),
            array('id' => $photo_id)
        );

        if ($result !== false) {
            do_action('wpmatch_photo_approved', $photo_id);
            return true;
        }

        return new WP_Error('db_error', __('Failed to approve photo.', 'wpmatch'));
    }

    /**
     * Reject photo
     *
     * @param int $photo_id
     * @param string $reason
     * @return bool|WP_Error
     */
    public function reject_photo($photo_id, $reason = '') {
        if (!WPMatch_Security::user_can('moderate_photos')) {
            return new WP_Error('permission_denied', __('You do not have permission to moderate photos.', 'wpmatch'));
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        // Get photo data for cleanup
        $photo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$photos_table} WHERE id = %d",
            $photo_id
        ));

        if (!$photo) {
            return new WP_Error('photo_not_found', __('Photo not found.', 'wpmatch'));
        }

        // Delete photo record
        $wpdb->delete($photos_table, array('id' => $photo_id));

        // Delete attachment
        wp_delete_attachment($photo->attachment_id, true);

        do_action('wpmatch_photo_rejected', $photo_id, $photo->user_id, $reason);

        return true;
    }

    /**
     * AJAX handlers
     */

    /**
     * AJAX upload photo
     */
    public function ajax_upload_photo() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to upload photos.', 'wpmatch'));
        }

        if (empty($_FILES['photo'])) {
            wp_send_json_error(__('No file uploaded.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $file = $_FILES['photo'];

        $result = $this->upload_photo($user_id, $file);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'photo_id' => $result,
            'message' => __('Photo uploaded successfully.', 'wpmatch')
        ));
    }

    /**
     * AJAX delete photo
     */
    public function ajax_delete_photo() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $photo_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;

        if (!$photo_id) {
            wp_send_json_error(__('Invalid photo ID.', 'wpmatch'));
        }

        $result = $this->delete_photo($photo_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Photo deleted successfully.', 'wpmatch'));
    }

    /**
     * AJAX set primary photo
     */
    public function ajax_set_primary_photo() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $photo_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;

        if (!$photo_id) {
            wp_send_json_error(__('Invalid photo ID.', 'wpmatch'));
        }

        $result = $this->set_primary_photo($photo_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Primary photo updated.', 'wpmatch'));
    }

    /**
     * AJAX reorder photos
     */
    public function ajax_reorder_photos() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $photo_order = isset($_POST['photo_order']) ? array_map('absint', $_POST['photo_order']) : array();

        if (empty($photo_order)) {
            wp_send_json_error(__('Invalid photo order.', 'wpmatch'));
        }

        $result = $this->reorder_photos($photo_order);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Photos reordered successfully.', 'wpmatch'));
    }

    /**
     * Custom upload directory filter
     *
     * @param array $upload_dir
     * @return array
     */
    public function custom_upload_directory($upload_dir) {
        // Only modify for WPMatch uploads
        if (isset($_POST['action']) && strpos($_POST['action'], 'wpmatch_') === 0) {
            return $this->set_wpmatch_upload_dir($upload_dir);
        }

        return $upload_dir;
    }
}