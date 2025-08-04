<?php
/**
 * Database management class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Database class
 */
class WPMatch_Database {

    /**
     * Database version
     *
     * @var string
     */
    private $db_version = '1.1.0';

    /**
     * Table names
     *
     * @var array
     */
    private $tables = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_table_names();
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize table names
     */
    private function init_table_names() {
        global $wpdb;

        $this->tables = array(
            'profiles' => $wpdb->prefix . 'wpmatch_profiles',
            'profile_fields' => $wpdb->prefix . 'wpmatch_profile_fields',
            'profile_values' => $wpdb->prefix . 'wpmatch_profile_values',
            'field_groups' => $wpdb->prefix . 'wpmatch_field_groups',
            'field_history' => $wpdb->prefix . 'wpmatch_field_history',
            'photos' => $wpdb->prefix . 'wpmatch_photos',
            'messages' => $wpdb->prefix . 'wpmatch_messages',
            'conversations' => $wpdb->prefix . 'wpmatch_conversations',
            'interactions' => $wpdb->prefix . 'wpmatch_interactions',
            'privacy_settings' => $wpdb->prefix . 'wpmatch_privacy_settings',
            'verification' => $wpdb->prefix . 'wpmatch_verification',
            'blocks' => $wpdb->prefix . 'wpmatch_blocks',
            'reports' => $wpdb->prefix . 'wpmatch_reports',
        );
    }

    /**
     * Initialize database
     */
    public function init() {
        $installed_version = get_option('wpmatch_db_version');

        if ($installed_version !== $this->db_version) {
            $this->create_tables();
            update_option('wpmatch_db_version', $this->db_version);
        }
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Profiles table
        $sql_profiles = "CREATE TABLE {$this->tables['profiles']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            display_name varchar(255) NOT NULL DEFAULT '',
            about_me text,
            age int(3) unsigned DEFAULT NULL,
            gender varchar(20) DEFAULT '',
            looking_for varchar(20) DEFAULT '',
            location varchar(255) DEFAULT '',
            latitude decimal(10,8) DEFAULT NULL,
            longitude decimal(11,8) DEFAULT NULL,
            height int(3) unsigned DEFAULT NULL,
            weight int(3) unsigned DEFAULT NULL,
            relationship_status varchar(50) DEFAULT '',
            children varchar(50) DEFAULT '',
            education varchar(100) DEFAULT '',
            profession varchar(255) DEFAULT '',
            interests text,
            is_verified tinyint(1) DEFAULT 0,
            is_featured tinyint(1) DEFAULT 0,
            is_online tinyint(1) DEFAULT 0,
            last_active datetime DEFAULT NULL,
            profile_views bigint(20) unsigned DEFAULT 0,
            profile_completion int(3) unsigned DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY status (status),
            KEY gender (gender),
            KEY age (age),
            KEY location (location),
            KEY is_online (is_online),
            KEY last_active (last_active),
            KEY latitude_longitude (latitude, longitude),
            CONSTRAINT fk_profiles_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Enhanced profile fields table
        $sql_profile_fields = "CREATE TABLE {$this->tables['profile_fields']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            field_name varchar(100) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_type varchar(50) NOT NULL DEFAULT 'text',
            field_options JSON,
            field_description text,
            placeholder_text varchar(255),
            help_text text,
            validation_rules JSON,
            is_required tinyint(1) DEFAULT 0,
            is_searchable tinyint(1) DEFAULT 0,
            is_public tinyint(1) DEFAULT 1,
            is_editable tinyint(1) DEFAULT 1,
            field_order int(10) unsigned DEFAULT 0,
            field_group varchar(100) DEFAULT 'basic',
            status varchar(20) DEFAULT 'active',
            min_value decimal(10,2) DEFAULT NULL,
            max_value decimal(10,2) DEFAULT NULL,
            min_length int(10) unsigned DEFAULT NULL,
            max_length int(10) unsigned DEFAULT NULL,
            regex_pattern varchar(500) DEFAULT NULL,
            default_value text,
            field_width varchar(20) DEFAULT 'full',
            field_class varchar(255) DEFAULT NULL,
            conditional_logic JSON,
            import_mapping varchar(255) DEFAULT NULL,
            is_system tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_name (field_name),
            KEY field_type (field_type),
            KEY field_group (field_group),
            KEY field_order (field_order),
            KEY status (status),
            KEY is_searchable (is_searchable),
            KEY is_system (is_system),
            CONSTRAINT fk_profile_fields_created_by FOREIGN KEY (created_by) REFERENCES {$wpdb->users} (ID) ON DELETE SET NULL,
            CONSTRAINT fk_profile_fields_updated_by FOREIGN KEY (updated_by) REFERENCES {$wpdb->users} (ID) ON DELETE SET NULL
        ) $charset_collate;";

        // Enhanced profile values table
        $sql_profile_values = "CREATE TABLE {$this->tables['profile_values']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            field_id bigint(20) unsigned NOT NULL,
            field_value LONGTEXT,
            field_value_numeric decimal(15,4) DEFAULT NULL,
            field_value_date datetime DEFAULT NULL,
            privacy varchar(20) DEFAULT 'public',
            is_verified tinyint(1) DEFAULT 0,
            verification_data JSON,
            search_weight float DEFAULT 1.0,
            last_updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_field (user_id, field_id),
            KEY field_id (field_id),
            KEY privacy (privacy),
            KEY field_value_numeric (field_value_numeric),
            KEY field_value_date (field_value_date),
            KEY is_verified (is_verified),
            KEY updated_at (updated_at),
            FULLTEXT KEY field_value_fulltext (field_value),
            CONSTRAINT fk_profile_values_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_profile_values_field_id FOREIGN KEY (field_id) REFERENCES {$this->tables['profile_fields']} (id) ON DELETE CASCADE,
            CONSTRAINT fk_profile_values_updated_by FOREIGN KEY (last_updated_by) REFERENCES {$wpdb->users} (ID) ON DELETE SET NULL
        ) $charset_collate;";

        // Field groups table
        $sql_field_groups = "CREATE TABLE {$this->tables['field_groups']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_name varchar(100) NOT NULL,
            group_label varchar(255) NOT NULL,
            group_description text,
            group_icon varchar(50) DEFAULT NULL,
            group_order int(10) unsigned DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY group_name (group_name),
            KEY group_order (group_order),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Field change history table
        $sql_field_history = "CREATE TABLE {$this->tables['field_history']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            field_id bigint(20) unsigned NOT NULL,
            change_type varchar(50) NOT NULL,
            old_value JSON,
            new_value JSON,
            changed_by bigint(20) unsigned NOT NULL,
            change_reason text,
            change_ip varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY field_id (field_id),
            KEY change_type (change_type),
            KEY changed_by (changed_by),
            KEY created_at (created_at),
            CONSTRAINT fk_field_history_field_id FOREIGN KEY (field_id) REFERENCES {$this->tables['profile_fields']} (id) ON DELETE CASCADE,
            CONSTRAINT fk_field_history_changed_by FOREIGN KEY (changed_by) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Photos table
        $sql_photos = "CREATE TABLE {$this->tables['photos']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            is_primary tinyint(1) DEFAULT 0,
            is_verified tinyint(1) DEFAULT 0,
            privacy varchar(20) DEFAULT 'public',
            photo_order int(10) unsigned DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY attachment_id (attachment_id),
            KEY is_primary (is_primary),
            KEY status (status),
            KEY photo_order (photo_order),
            CONSTRAINT fk_photos_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Conversations table
        $sql_conversations = "CREATE TABLE {$this->tables['conversations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            participant_1 bigint(20) unsigned NOT NULL,
            participant_2 bigint(20) unsigned NOT NULL,
            last_message_id bigint(20) unsigned DEFAULT NULL,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY participants (participant_1, participant_2),
            KEY last_activity (last_activity),
            KEY status (status),
            CONSTRAINT fk_conversations_participant_1 FOREIGN KEY (participant_1) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_conversations_participant_2 FOREIGN KEY (participant_2) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Messages table
        $sql_messages = "CREATE TABLE {$this->tables['messages']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender_id bigint(20) unsigned NOT NULL,
            receiver_id bigint(20) unsigned NOT NULL,
            message_content text NOT NULL,
            message_type varchar(20) DEFAULT 'text',
            is_read tinyint(1) DEFAULT 0,
            read_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'sent',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id),
            KEY is_read (is_read),
            KEY created_at (created_at),
            KEY status (status),
            CONSTRAINT fk_messages_conversation_id FOREIGN KEY (conversation_id) REFERENCES {$this->tables['conversations']} (id) ON DELETE CASCADE,
            CONSTRAINT fk_messages_sender_id FOREIGN KEY (sender_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_messages_receiver_id FOREIGN KEY (receiver_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Interactions table (likes, views, etc.)
        $sql_interactions = "CREATE TABLE {$this->tables['interactions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            target_user_id bigint(20) unsigned NOT NULL,
            interaction_type varchar(20) NOT NULL,
            interaction_value varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_target_type (user_id, target_user_id, interaction_type),
            KEY target_user_id (target_user_id),
            KEY interaction_type (interaction_type),
            KEY created_at (created_at),
            CONSTRAINT fk_interactions_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_interactions_target_user_id FOREIGN KEY (target_user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Privacy settings table
        $sql_privacy = "CREATE TABLE {$this->tables['privacy_settings']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            setting_name varchar(100) NOT NULL,
            setting_value varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_setting (user_id, setting_name),
            KEY setting_name (setting_name),
            CONSTRAINT fk_privacy_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Verification table
        $sql_verification = "CREATE TABLE {$this->tables['verification']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            verification_type varchar(50) NOT NULL,
            verification_token varchar(255) NOT NULL,
            verification_data text,
            is_verified tinyint(1) DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            verified_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY verification_type (verification_type),
            KEY verification_token (verification_token),
            KEY expires_at (expires_at),
            CONSTRAINT fk_verification_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Blocks table
        $sql_blocks = "CREATE TABLE {$this->tables['blocks']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            blocked_user_id bigint(20) unsigned NOT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_blocked (user_id, blocked_user_id),
            KEY blocked_user_id (blocked_user_id),
            KEY created_at (created_at),
            CONSTRAINT fk_blocks_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_blocks_blocked_user_id FOREIGN KEY (blocked_user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        // Reports table
        $sql_reports = "CREATE TABLE {$this->tables['reports']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reporter_id bigint(20) unsigned NOT NULL,
            reported_user_id bigint(20) unsigned NOT NULL,
            report_type varchar(50) NOT NULL,
            report_reason varchar(255) NOT NULL,
            report_details text,
            status varchar(20) DEFAULT 'pending',
            admin_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY reporter_id (reporter_id),
            KEY reported_user_id (reported_user_id),
            KEY report_type (report_type),
            KEY status (status),
            KEY created_at (created_at),
            CONSTRAINT fk_reports_reporter_id FOREIGN KEY (reporter_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_reports_reported_user_id FOREIGN KEY (reported_user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create tables
        dbDelta($sql_profiles);
        dbDelta($sql_profile_fields);
        dbDelta($sql_profile_values);
        dbDelta($sql_field_groups);
        dbDelta($sql_field_history);
        dbDelta($sql_photos);
        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        dbDelta($sql_interactions);
        dbDelta($sql_privacy);
        dbDelta($sql_verification);
        dbDelta($sql_blocks);
        dbDelta($sql_reports);

        // Insert default data
        $this->insert_default_field_groups();
        $this->insert_default_profile_fields();
    }

    /**
     * Insert default field groups
     */
    private function insert_default_field_groups() {
        global $wpdb;

        $default_groups = array(
            array(
                'group_name' => 'basic',
                'group_label' => __('Basic Information', 'wpmatch'),
                'group_description' => __('Essential profile information', 'wpmatch'),
                'group_icon' => 'dashicons-admin-users',
                'group_order' => 10,
            ),
            array(
                'group_name' => 'physical',
                'group_label' => __('Physical Attributes', 'wpmatch'),
                'group_description' => __('Physical characteristics and appearance', 'wpmatch'),
                'group_icon' => 'dashicons-universal-access',
                'group_order' => 20,
            ),
            array(
                'group_name' => 'lifestyle',
                'group_label' => __('Lifestyle', 'wpmatch'),
                'group_description' => __('Lifestyle choices and habits', 'wpmatch'),
                'group_icon' => 'dashicons-heart',
                'group_order' => 30,
            ),
            array(
                'group_name' => 'interests',
                'group_label' => __('Interests & Hobbies', 'wpmatch'),
                'group_description' => __('Activities, hobbies, and interests', 'wpmatch'),
                'group_icon' => 'dashicons-star-filled',
                'group_order' => 40,
            ),
            array(
                'group_name' => 'relationship',
                'group_label' => __('Relationship Goals', 'wpmatch'),
                'group_description' => __('Dating intentions and relationship preferences', 'wpmatch'),
                'group_icon' => 'dashicons-groups',
                'group_order' => 50,
            ),
            array(
                'group_name' => 'background',
                'group_label' => __('Background', 'wpmatch'),
                'group_description' => __('Education, career, and cultural background', 'wpmatch'),
                'group_icon' => 'dashicons-building',
                'group_order' => 60,
            ),
        );

        foreach ($default_groups as $group) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->tables['field_groups']} WHERE group_name = %s",
                $group['group_name']
            ));

            if (!$existing) {
                $wpdb->insert($this->tables['field_groups'], $group);
            }
        }
    }

    /**
     * Insert default profile fields
     */
    private function insert_default_profile_fields() {
        global $wpdb;

        $default_fields = array(
            array(
                'field_name' => 'zodiac_sign',
                'field_label' => __('Zodiac Sign', 'wpmatch'),
                'field_type' => 'select',
                'field_options' => wp_json_encode(array(
                    'choices' => array(
                        'aries' => __('Aries', 'wpmatch'),
                        'taurus' => __('Taurus', 'wpmatch'),
                        'gemini' => __('Gemini', 'wpmatch'),
                        'cancer' => __('Cancer', 'wpmatch'),
                        'leo' => __('Leo', 'wpmatch'),
                        'virgo' => __('Virgo', 'wpmatch'),
                        'libra' => __('Libra', 'wpmatch'),
                        'scorpio' => __('Scorpio', 'wpmatch'),
                        'sagittarius' => __('Sagittarius', 'wpmatch'),
                        'capricorn' => __('Capricorn', 'wpmatch'),
                        'aquarius' => __('Aquarius', 'wpmatch'),
                        'pisces' => __('Pisces', 'wpmatch'),
                    ),
                    'allow_other' => false,
                    'multiple' => false
                )),
                'field_description' => __('Your zodiac sign', 'wpmatch'),
                'field_group' => 'basic',
                'field_order' => 10,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
            array(
                'field_name' => 'smoking',
                'field_label' => __('Smoking Habits', 'wpmatch'),
                'field_type' => 'select',
                'field_options' => wp_json_encode(array(
                    'choices' => array(
                        'non_smoker' => __('Non-smoker', 'wpmatch'),
                        'occasional' => __('Occasional smoker', 'wpmatch'),
                        'regular' => __('Regular smoker', 'wpmatch'),
                        'trying_to_quit' => __('Trying to quit', 'wpmatch'),
                    ),
                    'allow_other' => false,
                    'multiple' => false
                )),
                'field_description' => __('Your smoking habits', 'wpmatch'),
                'field_group' => 'lifestyle',
                'field_order' => 10,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
            array(
                'field_name' => 'drinking',
                'field_label' => __('Drinking Habits', 'wpmatch'),
                'field_type' => 'select',
                'field_options' => wp_json_encode(array(
                    'choices' => array(
                        'non_drinker' => __('Non-drinker', 'wpmatch'),
                        'social' => __('Social drinker', 'wpmatch'),
                        'regular' => __('Regular drinker', 'wpmatch'),
                        'trying_to_quit' => __('Trying to quit', 'wpmatch'),
                    ),
                    'allow_other' => false,
                    'multiple' => false
                )),
                'field_description' => __('Your drinking habits', 'wpmatch'),
                'field_group' => 'lifestyle',
                'field_order' => 20,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
            array(
                'field_name' => 'languages',
                'field_label' => __('Languages Spoken', 'wpmatch'),
                'field_type' => 'text',
                'field_description' => __('Languages you can speak', 'wpmatch'),
                'placeholder_text' => __('e.g., English, Spanish, French', 'wpmatch'),
                'field_group' => 'basic',
                'field_order' => 20,
                'max_length' => 255,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
            array(
                'field_name' => 'hobbies',
                'field_label' => __('Hobbies & Interests', 'wpmatch'),
                'field_type' => 'textarea',
                'field_description' => __('Tell us about your hobbies and interests', 'wpmatch'),
                'placeholder_text' => __('What do you enjoy doing in your free time?', 'wpmatch'),
                'field_group' => 'interests',
                'field_order' => 10,
                'max_length' => 1000,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
            array(
                'field_name' => 'ideal_age_range',
                'field_label' => __('Preferred Age Range', 'wpmatch'),
                'field_type' => 'range',
                'field_description' => __('Age range you are interested in', 'wpmatch'),
                'field_group' => 'relationship',
                'field_order' => 10,
                'min_value' => 18,
                'max_value' => 99,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
            array(
                'field_name' => 'height_preference',
                'field_label' => __('Height (cm)', 'wpmatch'),
                'field_type' => 'number',
                'field_description' => __('Your height in centimeters', 'wpmatch'),
                'field_group' => 'physical',
                'field_order' => 10,
                'min_value' => 100,
                'max_value' => 250,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
            array(
                'field_name' => 'education_level',
                'field_label' => __('Education Level', 'wpmatch'),
                'field_type' => 'select',
                'field_options' => wp_json_encode(array(
                    'choices' => array(
                        'high_school' => __('High School', 'wpmatch'),
                        'some_college' => __('Some College', 'wpmatch'),
                        'bachelor' => __("Bachelor's Degree", 'wpmatch'),
                        'master' => __("Master's Degree", 'wpmatch'),
                        'doctorate' => __('Doctorate', 'wpmatch'),
                        'other' => __('Other', 'wpmatch'),
                    ),
                    'allow_other' => true,
                    'multiple' => false
                )),
                'field_description' => __('Your highest education level', 'wpmatch'),
                'field_group' => 'background',
                'field_order' => 10,
                'is_searchable' => 1,
                'is_system' => 1,
            ),
        );

        foreach ($default_fields as $field) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->tables['profile_fields']} WHERE field_name = %s",
                $field['field_name']
            ));

            if (!$existing) {
                $wpdb->insert($this->tables['profile_fields'], $field);
            }
        }
    }

    /**
     * Get table name
     *
     * @param string $table_key
     * @return string|null
     */
    public function get_table_name($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : null;
    }

    /**
     * Get all table names
     *
     * @return array
     */
    public function get_all_tables() {
        return $this->tables;
    }

    /**
     * Drop all plugin tables
     */
    public function drop_tables() {
        global $wpdb;

        // Disable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        delete_option('wpmatch_db_version');
    }

    /**
     * Get database version
     *
     * @return string
     */
    public function get_db_version() {
        return $this->db_version;
    }

    /**
     * Get all user profiles with optional pagination
     *
     * @param int $limit Maximum number of profiles to return
     * @param int $offset Number of profiles to skip
     * @return array Array of profile objects
     */
    public function get_all_user_profiles($limit = 0, $offset = 0) {
        global $wpdb;

        $sql = "SELECT p.*, u.user_login, u.user_email, u.display_name as user_display_name 
                FROM {$this->tables['profiles']} p
                INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
                WHERE p.status = 'active'
                ORDER BY p.updated_at DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Bulk insert multiple fields
     *
     * @param array $fields Array of field data arrays
     * @return int|WP_Error Number of fields inserted or WP_Error on failure
     */
    public function bulk_insert_fields($fields) {
        global $wpdb;

        if (empty($fields) || !is_array($fields)) {
            return new WP_Error('invalid_data', __('Invalid fields data provided.', 'wpmatch'));
        }

        $inserted = 0;
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($fields as $field_data) {
                $result = $wpdb->insert($this->tables['profile_fields'], $field_data);
                if ($result === false) {
                    throw new Exception('Failed to insert field: ' . $wpdb->last_error);
                }
                $inserted++;
            }
            
            $wpdb->query('COMMIT');
            return $inserted;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bulk_insert_failed', $e->getMessage());
        }
    }

    /**
     * Get paginated fields with filtering
     *
     * @param array $args Query arguments
     * @return array Array of field objects
     */
    public function get_paginated_fields($args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'field_order',
            'order' => 'ASC',
            'status' => 'active',
            'field_type' => '',
            'field_group' => '',
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array("1=1");
        $where_values = array();

        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if (!empty($args['field_type'])) {
            $where_conditions[] = "field_type = %s";
            $where_values[] = $args['field_type'];
        }

        if (!empty($args['field_group'])) {
            $where_conditions[] = "field_group = %s";
            $where_values[] = $args['field_group'];
        }

        if (!empty($args['search'])) {
            $where_conditions[] = "(field_name LIKE %s OR field_label LIKE %s OR field_description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);
        $order_clause = sprintf("ORDER BY %s %s", 
            sanitize_sql_orderby($args['orderby']), 
            sanitize_sql_orderby($args['order'])
        );

        $sql = "SELECT * FROM {$this->tables['profile_fields']} 
                WHERE {$where_clause} 
                {$order_clause}
                LIMIT %d OFFSET %d";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Execute a custom database query
     *
     * @param string $query SQL query to execute
     * @param array $params Parameters for prepared statement
     * @return mixed Query results
     */
    public function execute_query($query, $params = array()) {
        global $wpdb;

        if (empty($query)) {
            return new WP_Error('empty_query', __('Query cannot be empty.', 'wpmatch'));
        }

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        // Determine query type
        $query_type = strtoupper(substr(trim($query), 0, 6));

        switch ($query_type) {
            case 'SELECT':
                return $wpdb->get_results($query);
            case 'INSERT':
            case 'UPDATE':
            case 'DELETE':
                return $wpdb->query($query);
            default:
                return $wpdb->query($query);
        }
    }

    /**
     * Insert a new field
     *
     * @param array $field_data Field data array
     * @return int|WP_Error Field ID on success, WP_Error on failure
     */
    public function insert_field($field_data) {
        global $wpdb;

        if (empty($field_data) || !is_array($field_data)) {
            return new WP_Error('invalid_data', __('Invalid field data provided.', 'wpmatch'));
        }

        // Ensure required fields are present
        $required_fields = array('field_name', 'field_label', 'field_type');
        foreach ($required_fields as $required_field) {
            if (empty($field_data[$required_field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'wpmatch'), $required_field));
            }
        }

        $result = $wpdb->insert($this->tables['profile_fields'], $field_data);

        if ($result === false) {
            return new WP_Error('insert_failed', __('Failed to insert field: ', 'wpmatch') . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Get field by ID
     *
     * @param int $field_id Field ID
     * @return object|null Field object or null if not found
     */
    public function get_field_by_id($field_id) {
        global $wpdb;

        if (empty($field_id) || !is_numeric($field_id)) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['profile_fields']} WHERE id = %d",
            $field_id
        ));
    }

    /**
     * Check if field exists by name
     *
     * @param string $field_name Field name
     * @param int $exclude_id Optional field ID to exclude from check
     * @return bool True if field exists, false otherwise
     */
    public function field_exists($field_name, $exclude_id = 0) {
        global $wpdb;

        if (empty($field_name)) {
            return false;
        }

        $sql = "SELECT id FROM {$this->tables['profile_fields']} WHERE field_name = %s";
        $params = array($field_name);

        if ($exclude_id > 0) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }

        $result = $wpdb->get_var($wpdb->prepare($sql, $params));
        return !empty($result);
    }

    /**
     * Update field
     *
     * @param int $field_id Field ID
     * @param array $field_data Updated field data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_field($field_id, $field_data) {
        global $wpdb;

        if (empty($field_id) || !is_numeric($field_id)) {
            return new WP_Error('invalid_id', __('Invalid field ID provided.', 'wpmatch'));
        }

        if (empty($field_data) || !is_array($field_data)) {
            return new WP_Error('invalid_data', __('Invalid field data provided.', 'wpmatch'));
        }

        $result = $wpdb->update(
            $this->tables['profile_fields'],
            $field_data,
            array('id' => $field_id)
        );

        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update field: ', 'wpmatch') . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Delete field values for a specific field
     *
     * @param int $field_id Field ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_field_values($field_id) {
        global $wpdb;

        if (empty($field_id) || !is_numeric($field_id)) {
            return new WP_Error('invalid_id', __('Invalid field ID provided.', 'wpmatch'));
        }

        $result = $wpdb->delete(
            $this->tables['profile_values'],
            array('field_id' => $field_id)
        );

        if ($result === false) {
            return new WP_Error('delete_failed', __('Failed to delete field values: ', 'wpmatch') . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Delete field
     *
     * @param int $field_id Field ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_field($field_id) {
        global $wpdb;

        if (empty($field_id) || !is_numeric($field_id)) {
            return new WP_Error('invalid_id', __('Invalid field ID provided.', 'wpmatch'));
        }

        // First delete associated field values
        $values_result = $this->delete_field_values($field_id);
        if (is_wp_error($values_result)) {
            return $values_result;
        }

        // Then delete the field itself
        $result = $wpdb->delete(
            $this->tables['profile_fields'],
            array('id' => $field_id)
        );

        if ($result === false) {
            return new WP_Error('delete_failed', __('Failed to delete field: ', 'wpmatch') . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Bulk update field status
     *
     * @param array $field_ids Array of field IDs
     * @param string $status New status
     * @return int|WP_Error Number of fields updated or WP_Error on failure
     */
    public function bulk_update_field_status($field_ids, $status) {
        global $wpdb;

        if (empty($field_ids) || !is_array($field_ids)) {
            return new WP_Error('invalid_data', __('Invalid field IDs provided.', 'wpmatch'));
        }

        if (empty($status)) {
            return new WP_Error('invalid_status', __('Invalid status provided.', 'wpmatch'));
        }

        $field_ids = array_map('absint', $field_ids);
        $field_ids = array_filter($field_ids);

        if (empty($field_ids)) {
            return new WP_Error('no_valid_ids', __('No valid field IDs provided.', 'wpmatch'));
        }

        $placeholders = implode(',', array_fill(0, count($field_ids), '%d'));
        $params = array_merge(array($status), $field_ids);

        $sql = "UPDATE {$this->tables['profile_fields']} 
                SET status = %s, updated_at = NOW() 
                WHERE id IN ({$placeholders})";

        $result = $wpdb->query($wpdb->prepare($sql, $params));

        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update field status: ', 'wpmatch') . $wpdb->last_error);
        }

        return $result;
    }
}