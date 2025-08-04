<?php
/**
 * Admin interface class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Admin class
 */
class WPMatch_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_wpmatch_approve_photo', array($this, 'ajax_approve_photo'));
        add_action('wp_ajax_wpmatch_reject_photo', array($this, 'ajax_reject_photo'));
        add_action('wp_ajax_wpmatch_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_wpmatch_generate_demo_content', array($this, 'ajax_generate_demo_content'));
        add_action('wp_ajax_wpmatch_delete_user', array($this, 'ajax_delete_user'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('WPMatch', 'wpmatch'),
            __('WPMatch', 'wpmatch'),
            'manage_dating_settings',
            'wpmatch',
            array($this, 'admin_page_dashboard'),
            'dashicons-heart',
            30
        );

        // Dashboard
        add_submenu_page(
            'wpmatch',
            __('Dashboard', 'wpmatch'),
            __('Dashboard', 'wpmatch'),
            'manage_dating_settings',
            'wpmatch',
            array($this, 'admin_page_dashboard')
        );

        // Users
        add_submenu_page(
            'wpmatch',
            __('Dating Members', 'wpmatch'),
            __('Members', 'wpmatch'),
            'manage_dating_settings',
            'wpmatch-users',
            array($this, 'admin_page_users')
        );

        // Profile Fields
        add_submenu_page(
            'wpmatch',
            __('Profile Fields', 'wpmatch'),
            __('Profile Fields', 'wpmatch'),
            'manage_profile_fields',
            'wpmatch-profile-fields',
            array($this, 'admin_page_profile_fields')
        );

        // Photo Moderation
        add_submenu_page(
            'wpmatch',
            __('Photo Moderation', 'wpmatch'),
            __('Photos', 'wpmatch'),
            'moderate_photos',
            'wpmatch-photos',
            array($this, 'admin_page_photos')
        );

        // Reports
        add_submenu_page(
            'wpmatch',
            __('Reports', 'wpmatch'),
            __('Reports', 'wpmatch'),
            'view_reports',
            'wpmatch-reports',
            array($this, 'admin_page_reports')
        );

        // Settings
        add_submenu_page(
            'wpmatch',
            __('Settings', 'wpmatch'),
            __('Settings', 'wpmatch'),
            'manage_dating_settings',
            'wpmatch-settings',
            array($this, 'admin_page_settings')
        );
    }

    /**
     * Initialize admin
     */
    public function admin_init() {
        // Register settings
        $this->register_settings();
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on WPMatch admin pages
        if (strpos($hook, 'wpmatch') === false) {
            return;
        }

        wp_enqueue_script(
            'wpmatch-admin-js',
            WPMATCH_PLUGIN_ASSETS_URL . 'js/admin.js',
            array('jquery', 'wp-util'),
            WPMATCH_VERSION,
            true
        );

        wp_localize_script('wpmatch-admin-js', 'wpmatchAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmatch_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this?', 'wpmatch'),
                'processing' => __('Processing...', 'wpmatch'),
                'success' => __('Success!', 'wpmatch'),
                'error' => __('An error occurred. Please try again.', 'wpmatch'),
            ),
        ));

        wp_enqueue_style(
            'wpmatch-admin-css',
            WPMATCH_PLUGIN_ASSETS_URL . 'css/admin.css',
            array(),
            WPMATCH_VERSION
        );
    }

    /**
     * Register plugin settings
     */
    private function register_settings() {
        // General settings
        register_setting('wpmatch_general_settings', 'wpmatch_general_settings');
        
        // Messaging settings
        register_setting('wpmatch_messaging_settings', 'wpmatch_messaging_settings');
        
        // Privacy settings
        register_setting('wpmatch_privacy_settings', 'wpmatch_privacy_settings');
        
        // Security settings
        register_setting('wpmatch_security_settings', 'wpmatch_security_settings');
        
        // Notification settings
        register_setting('wpmatch_notification_settings', 'wpmatch_notification_settings');
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show activation notice
        if (get_option('wpmatch_activated')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php _e('WPMatch has been activated successfully!', 'wpmatch'); ?>
                    <a href="<?php echo admin_url('admin.php?page=wpmatch-settings'); ?>">
                        <?php _e('Configure Settings', 'wpmatch'); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_option('wpmatch_activated');
        }

        // Check for pending photos
        if (current_user_can('moderate_photos')) {
            $media_manager = new WPMatch_Media_Manager();
            $pending_photos = $media_manager->get_pending_photos(1, 0);
            
            if (!empty($pending_photos)) {
                $count = count($pending_photos);
                ?>
                <div class="notice notice-warning">
                    <p>
                        <?php 
                        printf(
                            _n(
                                'There is %d photo waiting for approval.',
                                'There are %d photos waiting for approval.',
                                $count,
                                'wpmatch'
                            ),
                            $count
                        ); 
                        ?>
                        <a href="<?php echo admin_url('admin.php?page=wpmatch-photos'); ?>">
                            <?php _e('Review Photos', 'wpmatch'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Dashboard admin page
     */
    public function admin_page_dashboard() {
        // Get statistics
        $stats = $this->get_dashboard_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('WPMatch Dashboard', 'wpmatch'); ?></h1>
            
            <div class="wpmatch-dashboard">
                <!-- Quick Actions -->
                <div class="wpmatch-quick-actions">
                    <h2><?php _e('Quick Actions', 'wpmatch'); ?></h2>
                    <div class="action-buttons">
                        <button class="button button-primary" id="generate-demo-content">
                            <?php _e('Generate Demo Content', 'wpmatch'); ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=wpmatch-photos'); ?>" class="button">
                            <?php _e('Review Pending Photos', 'wpmatch'); ?> 
                            <?php if ($stats['pending_photos'] > 0): ?>
                                <span class="count">(<?php echo $stats['pending_photos']; ?>)</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wpmatch-settings'); ?>" class="button">
                            <?php _e('Plugin Settings', 'wpmatch'); ?>
                        </a>
                        <button class="button button-secondary" id="upgrade-to-premium">
                            <?php _e('Upgrade to Premium', 'wpmatch'); ?>
                        </button>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="wpmatch-stats-grid">
                    <div class="wpmatch-stat-card">
                        <h3><?php _e('Total Members', 'wpmatch'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['total_members']); ?></div>
                        <div class="stat-change positive">+<?php echo esc_html($stats['new_members_week']); ?> <?php _e('this week', 'wpmatch'); ?></div>
                    </div>
                    
                    <div class="wpmatch-stat-card">
                        <h3><?php _e('Active Members', 'wpmatch'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['active_members']); ?></div>
                        <div class="stat-change"><?php echo esc_html(round($stats['activity_rate'], 1)); ?>% <?php _e('activity rate', 'wpmatch'); ?></div>
                    </div>
                    
                    <div class="wpmatch-stat-card">
                        <h3><?php _e('Messages Today', 'wpmatch'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['messages_today']); ?></div>
                        <div class="stat-change"><?php echo esc_html($stats['total_messages']); ?> <?php _e('total', 'wpmatch'); ?></div>
                    </div>
                    
                    <div class="wpmatch-stat-card">
                        <h3><?php _e('Pending Photos', 'wpmatch'); ?></h3>
                        <div class="stat-number <?php echo $stats['pending_photos'] > 0 ? 'needs-attention' : ''; ?>">
                            <?php echo esc_html($stats['pending_photos']); ?>
                        </div>
                        <div class="stat-change"><?php echo esc_html($stats['approved_photos']); ?> <?php _e('approved', 'wpmatch'); ?></div>
                    </div>

                    <div class="wpmatch-stat-card">
                        <h3><?php _e('Successful Matches', 'wpmatch'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['matches']); ?></div>
                        <div class="stat-change"><?php echo esc_html($stats['match_rate']); ?>% <?php _e('success rate', 'wpmatch'); ?></div>
                    </div>

                    <div class="wpmatch-stat-card">
                        <h3><?php _e('Reports', 'wpmatch'); ?></h3>
                        <div class="stat-number <?php echo $stats['pending_reports'] > 0 ? 'needs-attention' : ''; ?>">
                            <?php echo esc_html($stats['pending_reports']); ?>
                        </div>
                        <div class="stat-change"><?php _e('pending review', 'wpmatch'); ?></div>
                    </div>
                </div>
                
                <div class="wpmatch-dashboard-row">
                    <div class="wpmatch-recent-activity">
                        <h2><?php _e('Recent Activity', 'wpmatch'); ?></h2>
                        <?php $this->display_recent_activity(); ?>
                    </div>

                    <div class="wpmatch-premium-features">
                        <h2><?php _e('Premium Features', 'wpmatch'); ?></h2>
                        <div class="premium-feature-list">
                            <div class="feature-item">
                                <span class="dashicons dashicons-star-filled"></span>
                                <strong><?php _e('Advanced Matching Algorithm', 'wpmatch'); ?></strong>
                                <p><?php _e('AI-powered compatibility scoring', 'wpmatch'); ?></p>
                            </div>
                            <div class="feature-item">
                                <span class="dashicons dashicons-video-alt3"></span>
                                <strong><?php _e('Video Chat Integration', 'wpmatch'); ?></strong>
                                <p><?php _e('Built-in video calling system', 'wpmatch'); ?></p>
                            </div>
                            <div class="feature-item">
                                <span class="dashicons dashicons-money-alt"></span>
                                <strong><?php _e('Monetization Tools', 'wpmatch'); ?></strong>
                                <p><?php _e('Subscription and payment management', 'wpmatch'); ?></p>
                            </div>
                            <div class="feature-item">
                                <span class="dashicons dashicons-chart-line"></span>
                                <strong><?php _e('Advanced Analytics', 'wpmatch'); ?></strong>
                                <p><?php _e('Detailed insights and reporting', 'wpmatch'); ?></p>
                            </div>
                        </div>
                        <a href="#" class="button button-primary upgrade-btn" id="upgrade-premium">
                            <?php _e('Upgrade to Premium - $99/year', 'wpmatch'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wpmatch-dashboard {
            max-width: 1200px;
        }

        .wpmatch-quick-actions {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }

        .wpmatch-quick-actions h2 {
            margin-top: 0;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-buttons .count {
            background: #e91e63;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .wpmatch-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .wpmatch-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            transition: box-shadow 0.2s;
        }

        .wpmatch-stat-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .wpmatch-stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #e91e63;
            margin-bottom: 5px;
        }

        .stat-number.needs-attention {
            color: #ff6b35;
        }

        .stat-change {
            font-size: 12px;
            color: #666;
        }

        .stat-change.positive {
            color: #4caf50;
        }

        .wpmatch-dashboard-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .wpmatch-recent-activity, .wpmatch-premium-features {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }

        .wpmatch-premium-features h2 {
            margin-top: 0;
            color: #e91e63;
        }

        .premium-feature-list {
            margin: 15px 0;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .feature-item .dashicons {
            color: #e91e63;
            margin-right: 10px;
            margin-top: 2px;
        }

        .feature-item strong {
            display: block;
            margin-bottom: 5px;
        }

        .feature-item p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }

        .upgrade-btn {
            width: 100%;
            text-align: center;
            padding: 10px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .wpmatch-dashboard-row {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }

    /**
     * Users admin page
     */
    public function admin_page_users() {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Get dating members
        $members = $this->get_dating_members($per_page, $offset);
        $total_members = $this->get_total_dating_members();
        $total_pages = ceil($total_members / $per_page);
        
        // Process bulk actions
        $this->process_bulk_actions();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Dating Members', 'wpmatch'); ?></h1>
            
            <div class="wpmatch-users-page">
                <form method="post" action="">
                    <?php wp_nonce_field('wpmatch_bulk_members', 'wpmatch_bulk_nonce'); ?>
                    <div class="tablenav top">
                        <div class="alignleft actions">
                            <select name="action" id="bulk-action-selector-top">
                                <option value="-1"><?php _e('Bulk Actions', 'wpmatch'); ?></option>
                                <option value="approve"><?php _e('Approve Profiles', 'wpmatch'); ?></option>
                                <option value="suspend"><?php _e('Suspend Users', 'wpmatch'); ?></option>
                                <option value="delete"><?php _e('Delete Users', 'wpmatch'); ?></option>
                            </select>
                            <input type="submit" class="button action" value="<?php _e('Apply', 'wpmatch'); ?>">
                        </div>
                    
                    <div class="alignright">
                        <div class="search-box">
                            <input type="search" id="user-search-input" name="s" value="">
                            <input type="submit" id="search-submit" class="button" value="<?php _e('Search Members', 'wpmatch'); ?>">
                        </div>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th class="manage-column column-avatar"><?php _e('Photo', 'wpmatch'); ?></th>
                            <th class="manage-column column-username"><?php _e('Member', 'wpmatch'); ?></th>
                            <th class="manage-column column-profile"><?php _e('Profile Info', 'wpmatch'); ?></th>
                            <th class="manage-column column-activity"><?php _e('Activity', 'wpmatch'); ?></th>
                            <th class="manage-column column-status"><?php _e('Status', 'wpmatch'); ?></th>
                            <th class="manage-column column-actions"><?php _e('Actions', 'wpmatch'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($members)): ?>
                            <?php foreach ($members as $member): ?>
                                <tr data-user-id="<?php echo esc_attr($member->ID); ?>">
                                    <th class="check-column">
                                        <input type="checkbox" name="users[]" value="<?php echo esc_attr($member->ID); ?>">
                                    </th>
                                    <td class="column-avatar">
                                        <?php if ($member->primary_photo): ?>
                                            <img src="<?php echo esc_url($member->primary_photo); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center;">
                                                <span class="dashicons dashicons-admin-users"></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-username">
                                        <strong><?php echo esc_html($member->display_name); ?></strong><br>
                                        <small><?php echo esc_html($member->user_email); ?></small><br>
                                        <small>@<?php echo esc_html($member->user_login); ?></small>
                                    </td>
                                    <td class="column-profile">
                                        <?php if ($member->age): ?>
                                            <?php echo esc_html($member->age); ?> <?php _e('years old', 'wpmatch'); ?><br>
                                        <?php endif; ?>
                                        <?php if ($member->location): ?>
                                            📍 <?php echo esc_html($member->location); ?><br>
                                        <?php endif; ?>
                                        <?php if ($member->gender): ?>
                                            <?php echo esc_html(ucfirst($member->gender)); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-activity">
                                        <?php if ($member->last_active): ?>
                                            <?php echo esc_html(human_time_diff(strtotime($member->last_active), current_time('timestamp'))); ?> <?php _e('ago', 'wpmatch'); ?><br>
                                        <?php endif; ?>
                                        <small><?php echo esc_html($member->message_count); ?> <?php _e('messages', 'wpmatch'); ?></small>
                                    </td>
                                    <td class="column-status">
                                        <?php if ($member->status === 'active'): ?>
                                            <span class="status-approved">✓ <?php _e('Active', 'wpmatch'); ?></span>
                                        <?php elseif ($member->status === 'inactive'): ?>
                                            <span class="status-inactive">⏸ <?php _e('Inactive', 'wpmatch'); ?></span>
                                        <?php else: ?>
                                            <span class="status-pending">⏳ <?php _e('Pending', 'wpmatch'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($this->is_user_online($member->ID)): ?>
                                            <br><span class="status-online">🟢 <?php _e('Online', 'wpmatch'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-actions">
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $member->ID); ?>" class="button button-small">
                                            <?php _e('Edit', 'wpmatch'); ?>
                                        </a>
                                        <button class="button button-small view-profile" data-user-id="<?php echo esc_attr($member->ID); ?>">
                                            <?php _e('View Profile', 'wpmatch'); ?>
                                        </button>
                                        <button class="button button-small suspend-user" data-user-id="<?php echo esc_attr($member->ID); ?>">
                                            <?php _e('Suspend', 'wpmatch'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">
                                    <?php _e('No dating members found.', 'wpmatch'); ?>
                                    <a href="#" id="generate-demo-users" class="button button-primary" style="margin-left: 10px;">
                                        <?php _e('Generate Demo Users', 'wpmatch'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $page,
                                'total' => $total_pages,
                                'prev_text' => '‹',
                                'next_text' => '›',
                            );
                            echo paginate_links($pagination_args);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                </form>
            </div>
        </div>

        <style>
        .status-approved { color: #4caf50; font-weight: 600; }
        .status-pending { color: #ff9800; font-weight: 600; }
        .status-online { color: #4caf50; font-size: 12px; }
        .column-avatar { width: 60px; }
        .column-username { width: 150px; }
        .column-profile { width: 150px; }
        .column-activity { width: 120px; }
        .column-status { width: 100px; }
        .column-actions { width: 180px; }
        </style>
        <?php
    }

    /**
     * Profile Fields admin page
     */
    public function admin_page_profile_fields() {
        // Load the profile fields admin class
        require_once WPMATCH_ADMIN_PATH . 'class-profile-fields-admin.php';
        
        // Initialize and render the admin interface
        $profile_fields_admin = new WPMatch_Profile_Fields_Admin();
        $profile_fields_admin->admin_page_profile_fields();
    }

    /**
     * Photos admin page
     */
    public function admin_page_photos() {
        $media_manager = new WPMatch_Media_Manager();
        $pending_photos = $media_manager->get_pending_photos(20, 0);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Photo Moderation', 'wpmatch'); ?></h1>
            
            <div class="wpmatch-photos-page">
                <?php if (!empty($pending_photos)): ?>
                    <div class="wpmatch-pending-photos">
                        <h2><?php _e('Photos Pending Approval', 'wpmatch'); ?></h2>
                        
                        <div class="photos-grid">
                            <?php foreach ($pending_photos as $photo): ?>
                                <div class="photo-item" data-photo-id="<?php echo esc_attr($photo->id); ?>">
                                    <img src="<?php echo esc_url($photo->thumbnail); ?>" alt="<?php _e('User Photo', 'wpmatch'); ?>">
                                    <div class="photo-info">
                                        <p><strong><?php echo esc_html($photo->display_name); ?></strong></p>
                                        <p><?php echo esc_html($photo->user_email); ?></p>
                                        <p><?php echo esc_html($photo->created_at); ?></p>
                                    </div>
                                    <div class="photo-actions">
                                        <button class="button button-primary approve-photo" data-photo-id="<?php echo esc_attr($photo->id); ?>">
                                            <?php _e('Approve', 'wpmatch'); ?>
                                        </button>
                                        <button class="button button-secondary reject-photo" data-photo-id="<?php echo esc_attr($photo->id); ?>">
                                            <?php _e('Reject', 'wpmatch'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success">
                        <p><?php _e('No photos pending approval.', 'wpmatch'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .photo-item {
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px;
            background: #fff;
        }
        
        .photo-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .photo-info {
            margin: 10px 0;
        }
        
        .photo-info p {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .photo-actions {
            display: flex;
            gap: 10px;
        }
        
        .photo-actions button {
            flex: 1;
        }
        </style>
        <?php
    }

    /**
     * Reports admin page
     */
    public function admin_page_reports() {
        ?>
        <div class="wrap">
            <h1><?php _e('Reports', 'wpmatch'); ?></h1>
            
            <div class="wpmatch-reports-page">
                <p><?php _e('View and manage user reports and safety issues.', 'wpmatch'); ?></p>
                
                <!-- Reports management will be implemented here -->
                <div class="notice notice-info">
                    <p><?php _e('Reports management interface coming soon.', 'wpmatch'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Settings admin page
     */
    public function admin_page_settings() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        
        ?>
        <div class="wrap">
            <h1><?php _e('WPMatch Settings', 'wpmatch'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=wpmatch-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'wpmatch'); ?>
                </a>
                <a href="?page=wpmatch-settings&tab=messaging" class="nav-tab <?php echo $active_tab == 'messaging' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Messaging', 'wpmatch'); ?>
                </a>
                <a href="?page=wpmatch-settings&tab=privacy" class="nav-tab <?php echo $active_tab == 'privacy' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Privacy', 'wpmatch'); ?>
                </a>
                <a href="?page=wpmatch-settings&tab=security" class="nav-tab <?php echo $active_tab == 'security' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Security', 'wpmatch'); ?>
                </a>
                <a href="?page=wpmatch-settings&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Notifications', 'wpmatch'); ?>
                </a>
            </nav>
            
            <form method="post" action="options.php">
                <?php
                switch ($active_tab) {
                    case 'general':
                        settings_fields('wpmatch_general_settings');
                        $this->render_general_settings();
                        break;
                    case 'messaging':
                        settings_fields('wpmatch_messaging_settings');
                        $this->render_messaging_settings();
                        break;
                    case 'privacy':
                        settings_fields('wpmatch_privacy_settings');
                        $this->render_privacy_settings();
                        break;
                    case 'security':
                        settings_fields('wpmatch_security_settings');
                        $this->render_security_settings();
                        break;
                    case 'notifications':
                        settings_fields('wpmatch_notification_settings');
                        $this->render_notification_settings();
                        break;
                }
                
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings
     */
    private function render_general_settings() {
        $options = get_option('wpmatch_general_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Site Name', 'wpmatch'); ?></th>
                <td>
                    <input type="text" name="wpmatch_general_settings[site_name]" 
                           value="<?php echo esc_attr($options['site_name'] ?? get_bloginfo('name')); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('The name of your dating site.', 'wpmatch'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Enable Registration', 'wpmatch'); ?></th>
                <td>
                    <input type="checkbox" name="wpmatch_general_settings[enable_registration]" 
                           value="1" <?php checked($options['enable_registration'] ?? '1', '1'); ?> />
                    <p class="description"><?php _e('Allow new users to register on your site.', 'wpmatch'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Require Email Verification', 'wpmatch'); ?></th>
                <td>
                    <input type="checkbox" name="wpmatch_general_settings[require_email_verification]" 
                           value="1" <?php checked($options['require_email_verification'] ?? '1', '1'); ?> />
                    <p class="description"><?php _e('Require users to verify their email address before accessing the site.', 'wpmatch'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Minimum Age', 'wpmatch'); ?></th>
                <td>
                    <input type="number" name="wpmatch_general_settings[min_age]" 
                           value="<?php echo esc_attr($options['min_age'] ?? '18'); ?>" 
                           min="18" max="99" class="small-text" />
                    <p class="description"><?php _e('Minimum age for registration.', 'wpmatch'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Maximum Photos per User', 'wpmatch'); ?></th>
                <td>
                    <input type="number" name="wpmatch_general_settings[max_photos_per_user]" 
                           value="<?php echo esc_attr($options['max_photos_per_user'] ?? '10'); ?>" 
                           min="1" max="50" class="small-text" />
                    <p class="description"><?php _e('Maximum number of photos each user can upload.', 'wpmatch'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render messaging settings
     */
    private function render_messaging_settings() {
        $options = get_option('wpmatch_messaging_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Messaging', 'wpmatch'); ?></th>
                <td>
                    <input type="checkbox" name="wpmatch_messaging_settings[enable_messaging]" 
                           value="1" <?php checked($options['enable_messaging'] ?? '1', '1'); ?> />
                    <p class="description"><?php _e('Allow users to send private messages.', 'wpmatch'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Message Max Length', 'wpmatch'); ?></th>
                <td>
                    <input type="number" name="wpmatch_messaging_settings[message_max_length]" 
                           value="<?php echo esc_attr($options['message_max_length'] ?? '1000'); ?>" 
                           min="100" max="5000" class="small-text" />
                    <p class="description"><?php _e('Maximum characters per message.', 'wpmatch'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render privacy settings
     */
    private function render_privacy_settings() {
        $options = get_option('wpmatch_privacy_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable User Blocking', 'wpmatch'); ?></th>
                <td>
                    <input type="checkbox" name="wpmatch_privacy_settings[enable_blocking]" 
                           value="1" <?php checked($options['enable_blocking'] ?? '1', '1'); ?> />
                    <p class="description"><?php _e('Allow users to block other users.', 'wpmatch'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Enable Reporting', 'wpmatch'); ?></th>
                <td>
                    <input type="checkbox" name="wpmatch_privacy_settings[enable_reporting]" 
                           value="1" <?php checked($options['enable_reporting'] ?? '1', '1'); ?> />
                    <p class="description"><?php _e('Allow users to report inappropriate content or behavior.', 'wpmatch'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render security settings
     */
    private function render_security_settings() {
        $options = get_option('wpmatch_security_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Rate Limiting', 'wpmatch'); ?></th>
                <td>
                    <input type="checkbox" name="wpmatch_security_settings[enable_rate_limiting]" 
                           value="1" <?php checked($options['enable_rate_limiting'] ?? '1', '1'); ?> />
                    <p class="description"><?php _e('Limit the number of actions users can perform to prevent spam.', 'wpmatch'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render notification settings
     */
    private function render_notification_settings() {
        $options = get_option('wpmatch_notification_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Email Notifications', 'wpmatch'); ?></th>
                <td>
                    <input type="checkbox" name="wpmatch_notification_settings[enable_email_notifications]" 
                           value="1" <?php checked($options['enable_email_notifications'] ?? '1', '1'); ?> />
                    <p class="description"><?php _e('Send email notifications to users.', 'wpmatch'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('From Email', 'wpmatch'); ?></th>
                <td>
                    <input type="email" name="wpmatch_notification_settings[from_email]" 
                           value="<?php echo esc_attr($options['from_email'] ?? get_option('admin_email')); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Email address to send notifications from.', 'wpmatch'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */


    /**
     * Display recent activity
     */
    private function display_recent_activity() {
        global $wpdb;
        
        // Get recent registrations
        $recent_users = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_registered 
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
            WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
            AND um.meta_value LIKE '%dating_member%'
            ORDER BY u.user_registered DESC 
            LIMIT 5
        ");

        if ($recent_users) {
            echo '<ul>';
            foreach ($recent_users as $user) {
                echo '<li>';
                echo '<strong>' . esc_html($user->display_name) . '</strong> ';
                echo __('joined', 'wpmatch') . ' ';
                echo human_time_diff(strtotime($user->user_registered), current_time('timestamp')) . ' ' . __('ago', 'wpmatch');
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No recent activity.', 'wpmatch') . '</p>';
        }
    }

    /**
     * Get dating members with profile data
     */
    private function get_dating_members($limit = 20, $offset = 0) {
        global $wpdb;
        
        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');
        
        $members = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                   p.age, p.gender, p.location, p.status, p.last_active,
                   COUNT(m.id) as message_count
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
            LEFT JOIN {$profiles_table} p ON u.ID = p.user_id
            LEFT JOIN {$database->get_table_name('messages')} m ON u.ID = m.sender_id
            WHERE um.meta_key = %s
            AND um.meta_value LIKE %s
            GROUP BY u.ID
            ORDER BY u.user_registered DESC
            LIMIT %d OFFSET %d
        ", $wpdb->prefix . 'capabilities', '%dating_member%', $limit, $offset));

        // Add primary photo URLs
        foreach ($members as $member) {
            $photo = $wpdb->get_var($wpdb->prepare("
                SELECT attachment_id FROM {$database->get_table_name('photos')} 
                WHERE user_id = %d AND is_primary = 1 AND status = 'approved' 
                LIMIT 1
            ", $member->ID));
            
            $member->primary_photo = $photo ? wp_get_attachment_image_url($photo, 'thumbnail') : null;
        }

        return $members;
    }

    /**
     * Get total dating members count
     */
    private function get_total_dating_members() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
            WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
            AND um.meta_value LIKE '%dating_member%'
        ");
    }

    /**
     * Check if user is online
     */
    private function is_user_online($user_id) {
        $last_active = get_user_meta($user_id, 'wpmatch_last_active', true);
        if (!$last_active) return false;
        
        $online_threshold = 15 * MINUTE_IN_SECONDS; // 15 minutes
        return (current_time('timestamp') - strtotime($last_active)) <= $online_threshold;
    }

    /**
     * Enhanced dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        
        // Total members
        $total_members = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->users} u 
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
            WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
            AND um.meta_value LIKE '%dating_member%'
        ");

        // New members this week
        $new_members_week = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->users} u 
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
            WHERE um.meta_key = %s
            AND um.meta_value LIKE %s
            AND u.user_registered > %s
        ", $wpdb->prefix . 'capabilities', '%dating_member%', date('Y-m-d H:i:s', strtotime('-7 days'))));

        // Active members (logged in within 30 days)
        $active_members = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$database->get_table_name('profiles')} 
            WHERE last_active > %s
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));

        // Activity rate
        $activity_rate = $total_members > 0 ? ($active_members / $total_members) * 100 : 0;

        // Messages today
        $messages_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$database->get_table_name('messages')} 
            WHERE DATE(created_at) = %s
        ", date('Y-m-d')));

        // Total messages
        $total_messages = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$database->get_table_name('messages')}
        ");

        // Pending photos
        $pending_photos = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$database->get_table_name('photos')} 
            WHERE status = 'pending'
        ");

        // Approved photos
        $approved_photos = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$database->get_table_name('photos')} 
            WHERE status = 'approved'
        ");

        // Likes/Interactions (as matches)
        $matches = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$database->get_table_name('interactions')} 
            WHERE interaction_type = 'like'
        ");

        // Users with interactions (as match rate)
        $users_with_matches = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$database->get_table_name('interactions')} 
            WHERE interaction_type = 'like'
        ");
        $match_rate = $total_members > 0 ? ($users_with_matches / $total_members) * 100 : 0;

        // Pending reports
        $pending_reports = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$database->get_table_name('reports')} 
            WHERE status = 'pending'
        ");

        return array(
            'total_members' => $total_members ?: 0,
            'new_members_week' => $new_members_week ?: 0,
            'active_members' => $active_members ?: 0,
            'activity_rate' => $activity_rate,
            'messages_today' => $messages_today ?: 0,
            'total_messages' => $total_messages ?: 0,
            'pending_photos' => $pending_photos ?: 0,
            'approved_photos' => $approved_photos ?: 0,
            'matches' => $matches ?: 0,
            'match_rate' => round($match_rate, 1),
            'pending_reports' => $pending_reports ?: 0,
        );
    }

    /**
     * AJAX Handlers
     */

    /**
     * AJAX handler for approving photos
     */
    public function ajax_approve_photo() {
        check_ajax_referer('wpmatch_admin_nonce', 'nonce');
        
        if (!current_user_can('moderate_photos')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $photo_id = absint($_POST['photo_id'] ?? 0);
        if (!$photo_id) {
            wp_send_json_error(__('Invalid photo ID.', 'wpmatch'));
        }

        $media_manager = new WPMatch_Media_Manager();
        $result = $media_manager->approve_photo($photo_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Photo approved successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for rejecting photos
     */
    public function ajax_reject_photo() {
        check_ajax_referer('wpmatch_admin_nonce', 'nonce');
        
        if (!current_user_can('moderate_photos')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $photo_id = absint($_POST['photo_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        if (!$photo_id) {
            wp_send_json_error(__('Invalid photo ID.', 'wpmatch'));
        }

        $media_manager = new WPMatch_Media_Manager();
        $result = $media_manager->reject_photo($photo_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Photo rejected successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for generating demo content
     */
    public function ajax_generate_demo_content() {
        check_ajax_referer('wpmatch_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_dating_settings')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $count = $this->generate_demo_users(10);
        wp_send_json_success(sprintf(__('Generated %d demo users successfully.', 'wpmatch'), $count));
    }

    /**
     * Generate demo users for testing
     */
    private function generate_demo_users($count = 10) {
        $demo_users = array(
            array('name' => 'Sarah Johnson', 'age' => 28, 'gender' => 'female', 'location' => 'New York, NY', 'bio' => 'Love traveling and photography. Looking for someone to explore the world with.'),
            array('name' => 'Mike Davis', 'age' => 32, 'gender' => 'male', 'location' => 'Los Angeles, CA', 'bio' => 'Software engineer who loves hiking and cooking. Seeking meaningful connections.'),
            array('name' => 'Emily Chen', 'age' => 26, 'gender' => 'female', 'location' => 'San Francisco, CA', 'bio' => 'Artist and coffee enthusiast. Passionate about sustainability and yoga.'),
            array('name' => 'David Wilson', 'age' => 35, 'gender' => 'male', 'location' => 'Chicago, IL', 'bio' => 'Teacher and musician. Love live music, books, and weekend adventures.'),
            array('name' => 'Jessica Martinez', 'age' => 29, 'gender' => 'female', 'location' => 'Miami, FL', 'bio' => 'Marketing professional who loves beach volleyball and trying new restaurants.'),
            array('name' => 'Alex Thompson', 'age' => 31, 'gender' => 'male', 'location' => 'Seattle, WA', 'bio' => 'Tech startup founder. Enjoy craft beer, rock climbing, and good conversations.'),
            array('name' => 'Rachel Green', 'age' => 27, 'gender' => 'female', 'location' => 'Boston, MA', 'bio' => 'Medical student with a passion for helping others. Love running and board games.'),
            array('name' => 'James Brown', 'age' => 33, 'gender' => 'male', 'location' => 'Austin, TX', 'bio' => 'Graphic designer and foodie. Always up for trying new cuisines and live music.'),
            array('name' => 'Lisa Wang', 'age' => 30, 'gender' => 'female', 'location' => 'Portland, OR', 'bio' => 'Environmental scientist who loves camping, craft coffee, and indie films.'),
            array('name' => 'Chris Rodriguez', 'age' => 34, 'gender' => 'male', 'location' => 'Denver, CO', 'bio' => 'Fitness trainer and outdoor enthusiast. Passionate about skiing and healthy living.'),
        );

        $created = 0;
        for ($i = 0; $i < min($count, count($demo_users)); $i++) {
            $demo = $demo_users[$i];
            
            // Create WordPress user
            $username = sanitize_user(strtolower(str_replace(' ', '', $demo['name']))) . rand(100, 999);
            $email = $username . '@example.com';
            
            $user_id = wp_create_user($username, wp_generate_password(), $email);
            
            if (!is_wp_error($user_id)) {
                // Update user data
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $demo['name'],
                    'first_name' => explode(' ', $demo['name'])[0],
                    'last_name' => explode(' ', $demo['name'])[1] ?? '',
                ));

                // Add dating member role
                $user = new WP_User($user_id);
                $user->add_role('dating_member');

                // Create dating profile
                global $wpdb;
                $database = wpmatch_plugin()->database;
                $profiles_table = $database->get_table_name('profiles');
                
                $wpdb->insert($profiles_table, array(
                    'user_id' => $user_id,
                    'display_name' => $demo['name'],
                    'age' => $demo['age'],
                    'gender' => $demo['gender'],
                    'location' => $demo['location'],
                    'about_me' => $demo['bio'],
                    'is_complete' => 1,
                    'is_approved' => 1,
                    'last_active' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                ));

                $created++;
            }
        }

        return $created;
    }

    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('wpmatch_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_dating_settings')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $settings_group = sanitize_text_field($_POST['settings_group'] ?? '');
        $settings_data = $_POST['settings'] ?? array();

        if (!$settings_group) {
            wp_send_json_error(__('Invalid settings group.', 'wpmatch'));
        }

        // Sanitize settings data
        $settings_data = array_map('sanitize_text_field', $settings_data);

        // Save settings
        update_option($settings_group, $settings_data);

        wp_send_json_success(__('Settings saved successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for deleting users
     */
    public function ajax_delete_user() {
        check_ajax_referer('wpmatch_admin_nonce', 'nonce');
        
        if (!current_user_can('delete_users')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $user_id = absint($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error(__('Invalid user ID.', 'wpmatch'));
        }

        // Don't allow deleting current user
        if ($user_id === get_current_user_id()) {
            wp_send_json_error(__('Cannot delete your own account.', 'wpmatch'));
        }

        $result = wp_delete_user($user_id);
        if ($result) {
            wp_send_json_success(__('User deleted successfully.', 'wpmatch'));
        } else {
            wp_send_json_error(__('Failed to delete user.', 'wpmatch'));
        }
    }

    /**
     * Process bulk actions for members
     */
    private function process_bulk_actions() {
        // Check if bulk action was submitted
        if (!isset($_POST['action']) || $_POST['action'] === '-1') {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['wpmatch_bulk_nonce'], 'wpmatch_bulk_members')) {
            wp_die(__('Security check failed', 'wpmatch'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'wpmatch'));
        }

        // Get selected users
        $user_ids = isset($_POST['users']) ? array_map('intval', $_POST['users']) : array();
        
        if (empty($user_ids)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     __('No users selected for bulk action.', 'wpmatch') . 
                     '</p></div>';
            });
            return;
        }

        $action = sanitize_text_field($_POST['action']);
        $processed = 0;
        $errors = 0;

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        foreach ($user_ids as $user_id) {
            switch ($action) {
                case 'approve':
                    // Update profile status to active
                    $result = $wpdb->update(
                        $profiles_table,
                        array('status' => 'active'),
                        array('user_id' => $user_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        $processed++;
                        
                        // Send approval notification email
                        $user = get_user_by('id', $user_id);
                        if ($user) {
                            wp_mail(
                                $user->user_email,
                                __('Profile Approved', 'wpmatch'),
                                sprintf(
                                    __('Congratulations! Your profile on %s has been approved and is now active.', 'wpmatch'),
                                    get_bloginfo('name')
                                )
                            );
                        }
                    } else {
                        $errors++;
                    }
                    break;

                case 'suspend':
                    // Update profile status to inactive
                    $result = $wpdb->update(
                        $profiles_table,
                        array('status' => 'inactive'),
                        array('user_id' => $user_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        $processed++;
                        
                        // Send suspension notification email
                        $user = get_user_by('id', $user_id);
                        if ($user) {
                            wp_mail(
                                $user->user_email,
                                __('Profile Suspended', 'wpmatch'),
                                sprintf(
                                    __('Your profile on %s has been suspended. Please contact support if you believe this is an error.', 'wpmatch'),
                                    get_bloginfo('name')
                                )
                            );
                        }
                    } else {
                        $errors++;
                    }
                    break;

                case 'delete':
                    // Don't allow deleting admin users
                    if (user_can($user_id, 'manage_options')) {
                        $errors++;
                        continue 2;
                    }
                    
                    // Delete user and all associated data
                    $result = wp_delete_user($user_id);
                    
                    if ($result) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                    break;
            }
        }

        // Show results
        if ($processed > 0) {
            $message = '';
            switch ($action) {
                case 'approve':
                    $message = sprintf(
                        _n('%d profile approved successfully.', '%d profiles approved successfully.', $processed, 'wpmatch'),
                        $processed
                    );
                    break;
                case 'suspend':
                    $message = sprintf(
                        _n('%d user suspended successfully.', '%d users suspended successfully.', $processed, 'wpmatch'),
                        $processed
                    );
                    break;
                case 'delete':
                    $message = sprintf(
                        _n('%d user deleted successfully.', '%d users deleted successfully.', $processed, 'wpmatch'),
                        $processed
                    );
                    break;
            }

            if ($errors > 0) {
                $message .= ' ' . sprintf(
                    _n('%d error occurred.', '%d errors occurred.', $errors, 'wpmatch'),
                    $errors
                );
            }

            add_action('admin_notices', function() use ($message) {
                $class = $errors > 0 ? 'notice-warning' : 'notice-success';
                echo '<div class="notice ' . $class . ' is-dismissible"><p>' . 
                     esc_html($message) . 
                     '</p></div>';
            });
        } else if ($errors > 0) {
            add_action('admin_notices', function() use ($errors) {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     sprintf(__('Failed to process %d users.', 'wpmatch'), $errors) . 
                     '</p></div>';
            });
        }
    }
}