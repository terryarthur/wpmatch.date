<?php
/**
 * Public-facing functionality
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Public class
 */
class WPMatch_Public {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('template_redirect', array($this, 'handle_template_redirect'));
        add_filter('template_include', array($this, 'template_include'));
    }

    /**
     * Initialize public functionality
     */
    public function init() {
        // Add shortcodes
        $this->add_shortcodes();
        
        // Handle custom query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Update user activity
        if (is_user_logged_in()) {
            $user_manager = wpmatch_plugin()->user_manager;
            $user_manager->update_last_active();
        }
    }

    /**
     * Add shortcodes
     */
    private function add_shortcodes() {
        add_shortcode('wpmatch_profile_search', array($this, 'shortcode_profile_search'));
        add_shortcode('wpmatch_user_profile', array($this, 'shortcode_user_profile'));
        add_shortcode('wpmatch_messages', array($this, 'shortcode_messages'));
        add_shortcode('wpmatch_registration_form', array($this, 'shortcode_registration_form'));
        add_shortcode('wpmatch_login_form', array($this, 'shortcode_login_form'));
        add_shortcode('wpmatch_success_stories', array($this, 'shortcode_success_stories'));
    }

    /**
     * Add custom query vars
     *
     * @param array $vars
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'wpmatch_profile';
        $vars[] = 'wpmatch_search';
        $vars[] = 'wpmatch_messages';
        return $vars;
    }

    /**
     * Handle template redirects
     */
    public function handle_template_redirect() {
        global $wp_query;

        // Handle profile pages
        if (get_query_var('wpmatch_profile')) {
            $this->handle_profile_page();
        }

        // Handle search pages
        if (get_query_var('wpmatch_search')) {
            $this->handle_search_page();
        }

        // Handle messages pages
        if (get_query_var('wpmatch_messages')) {
            $this->handle_messages_page();
        }
    }

    /**
     * Include custom templates
     *
     * @param string $template
     * @return string
     */
    public function template_include($template) {
        // Check for WPMatch pages
        if (get_query_var('wpmatch_profile') || 
            get_query_var('wpmatch_search') || 
            get_query_var('wpmatch_messages')) {
            
            $wpmatch_template = $this->locate_template('wpmatch-page.php');
            if ($wpmatch_template) {
                return $wpmatch_template;
            }
        }

        return $template;
    }

    /**
     * Locate template file
     *
     * @param string $template_name
     * @return string|false
     */
    private function locate_template($template_name) {
        // Check theme directory first
        $theme_template = get_template_directory() . '/wpmatch/' . $template_name;
        if (file_exists($theme_template)) {
            return $theme_template;
        }

        // Check plugin templates directory
        $plugin_template = WPMATCH_PLUGIN_PATH . 'templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return false;
    }

    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on WPMatch pages or posts with shortcodes
        if ($this->is_wpmatch_page()) {
            wp_enqueue_style(
                'wpmatch-public',
                WPMATCH_PLUGIN_ASSETS_URL . 'css/public.css',
                array(),
                WPMATCH_VERSION
            );

            wp_enqueue_script(
                'wpmatch-public',
                WPMATCH_PLUGIN_ASSETS_URL . 'js/public.js',
                array('jquery'),
                WPMATCH_VERSION,
                true
            );

            // Localize script
            wp_localize_script('wpmatch-public', 'wpMatch', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpmatch_nonce'),
                'userId' => get_current_user_id(),
                'isLoggedIn' => is_user_logged_in(),
                'strings' => array(
                    'loading' => __('Loading...', 'wpmatch'),
                    'error' => __('An error occurred. Please try again.', 'wpmatch'),
                    'confirmDelete' => __('Are you sure you want to delete this?', 'wpmatch'),
                    'profileUpdated' => __('Profile updated successfully.', 'wpmatch'),
                    'messageSent' => __('Message sent successfully.', 'wpmatch'),
                ),
            ));
        }
    }

    /**
     * Check if current page is a WPMatch page
     *
     * @return bool
     */
    private function is_wpmatch_page() {
        // Check query vars
        if (get_query_var('wpmatch_profile') || 
            get_query_var('wpmatch_search') || 
            get_query_var('wpmatch_messages')) {
            return true;
        }

        // Check for shortcodes in content
        global $post;
        if (is_object($post) && has_shortcode($post->post_content, 'wpmatch_')) {
            return true;
        }

        return false;
    }

    /**
     * Handle profile page
     */
    private function handle_profile_page() {
        $username = get_query_var('wpmatch_profile');
        
        if (!$username) {
            wp_redirect(home_url());
            exit;
        }

        $user = get_user_by('login', $username);
        
        if (!$user) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Set page title
        add_filter('wp_title', function($title) use ($user) {
            return $user->display_name . ' - ' . get_bloginfo('name');
        });
    }

    /**
     * Handle search page
     */
    private function handle_search_page() {
        // Set page title
        add_filter('wp_title', function($title) {
            return __('Search Profiles', 'wpmatch') . ' - ' . get_bloginfo('name');
        });
    }

    /**
     * Handle messages page
     */
    private function handle_messages_page() {
        // Require login
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/messages/')));
            exit;
        }

        // Set page title
        add_filter('wp_title', function($title) {
            return __('Messages', 'wpmatch') . ' - ' . get_bloginfo('name');
        });
    }

    /**
     * Profile search shortcode
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_profile_search($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'grid',
            'per_page' => 12,
            'show_filters' => 'true',
        ), $atts);

        ob_start();
        ?>
        <div class="wpmatch-profile-search" data-layout="<?php echo esc_attr($atts['layout']); ?>">
            <?php if ($atts['show_filters'] === 'true'): ?>
                <div class="wpmatch-search-filters">
                    <?php $this->render_search_filters(); ?>
                </div>
            <?php endif; ?>
            
            <div class="wpmatch-search-results">
                <div class="loading"><?php _e('Loading profiles...', 'wpmatch'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * User profile shortcode
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_user_profile($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your profile.', 'wpmatch') . '</p>';
        }

        $user_id = get_current_user_id();
        $profile_manager = wpmatch_plugin()->profile_manager;
        $profile = $profile_manager->get_profile($user_id);

        ob_start();
        ?>
        <div class="wpmatch-user-profile">
            <?php $this->render_user_profile($profile); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Messages shortcode
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_messages($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your messages.', 'wpmatch') . '</p>';
        }

        ob_start();
        ?>
        <div class="wpmatch-messages">
            <?php $this->render_messages_interface(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Registration form shortcode
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_registration_form($atts) {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already logged in.', 'wpmatch') . '</p>';
        }

        ob_start();
        ?>
        <div class="wpmatch-registration-form">
            <?php $this->render_registration_form(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Login form shortcode
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_login_form($atts) {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already logged in.', 'wpmatch') . '</p>';
        }

        ob_start();
        wp_login_form(array(
            'redirect' => home_url('/profile/'),
            'form_id' => 'wpmatch-login-form',
            'label_username' => __('Email or Username', 'wpmatch'),
            'label_password' => __('Password', 'wpmatch'),
            'label_remember' => __('Remember Me', 'wpmatch'),
            'label_log_in' => __('Log In', 'wpmatch'),
        ));
        return ob_get_clean();
    }

    /**
     * Success stories shortcode
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_success_stories($atts) {
        $atts = shortcode_atts(array(
            'number' => 6,
            'layout' => 'grid',
        ), $atts);

        $stories = get_posts(array(
            'post_type' => 'dating_story',
            'posts_per_page' => $atts['number'],
            'post_status' => 'publish',
        ));

        ob_start();
        ?>
        <div class="wpmatch-success-stories layout-<?php echo esc_attr($atts['layout']); ?>">
            <?php if ($stories): ?>
                <?php foreach ($stories as $story): ?>
                    <div class="success-story">
                        <?php if (has_post_thumbnail($story->ID)): ?>
                            <div class="story-image">
                                <?php echo get_the_post_thumbnail($story->ID, 'medium'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="story-content">
                            <h3><?php echo esc_html($story->post_title); ?></h3>
                            <div class="story-excerpt">
                                <?php echo wp_trim_words($story->post_content, 30); ?>
                            </div>
                            <a href="<?php echo get_permalink($story->ID); ?>" class="read-more">
                                <?php _e('Read More', 'wpmatch'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php _e('No success stories available yet.', 'wpmatch'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render search filters
     */
    private function render_search_filters() {
        ?>
        <form class="wpmatch-search-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label><?php _e('Age Range', 'wpmatch'); ?></label>
                    <select name="age_min">
                        <option value="18"><?php _e('18+', 'wpmatch'); ?></option>
                        <?php for ($age = 20; $age <= 80; $age += 5): ?>
                            <option value="<?php echo $age; ?>"><?php echo $age; ?>+</option>
                        <?php endfor; ?>
                    </select>
                    <span><?php _e('to', 'wpmatch'); ?></span>
                    <select name="age_max">
                        <?php for ($age = 25; $age <= 99; $age += 5): ?>
                            <option value="<?php echo $age; ?>" <?php selected($age, 99); ?>><?php echo $age; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php _e('Gender', 'wpmatch'); ?></label>
                    <select name="gender">
                        <option value=""><?php _e('Any', 'wpmatch'); ?></option>
                        <option value="male"><?php _e('Male', 'wpmatch'); ?></option>
                        <option value="female"><?php _e('Female', 'wpmatch'); ?></option>
                        <option value="other"><?php _e('Other', 'wpmatch'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php _e('Looking For', 'wpmatch'); ?></label>
                    <select name="looking_for">
                        <option value=""><?php _e('Any', 'wpmatch'); ?></option>
                        <option value="male"><?php _e('Male', 'wpmatch'); ?></option>
                        <option value="female"><?php _e('Female', 'wpmatch'); ?></option>
                        <option value="other"><?php _e('Other', 'wpmatch'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="button button-primary">
                        <?php _e('Search', 'wpmatch'); ?>
                    </button>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label>
                        <input type="checkbox" name="online_only" value="1">
                        <?php _e('Online now', 'wpmatch'); ?>
                    </label>
                </div>
                
                <div class="filter-group">
                    <label>
                        <input type="checkbox" name="has_photo" value="1">
                        <?php _e('Has photo', 'wpmatch'); ?>
                    </label>
                </div>
            </div>
        </form>
        <?php
    }

    /**
     * Render user profile
     *
     * @param object $profile
     */
    private function render_user_profile($profile) {
        ?>
        <div class="profile-header">
            <h2><?php echo esc_html($profile->display_name); ?></h2>
            <div class="profile-completion">
                <?php printf(__('Profile %d%% complete', 'wpmatch'), $profile->profile_completion); ?>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-photos">
                <h3><?php _e('Photos', 'wpmatch'); ?></h3>
                <!-- Photo management interface -->
                <div class="photo-upload">
                    <button class="button" id="upload-photo-btn">
                        <?php _e('Upload Photo', 'wpmatch'); ?>
                    </button>
                </div>
            </div>

            <div class="profile-info">
                <h3><?php _e('About Me', 'wpmatch'); ?></h3>
                <textarea name="about_me" rows="5"><?php echo esc_textarea($profile->about_me); ?></textarea>
                
                <h3><?php _e('Basic Information', 'wpmatch'); ?></h3>
                <div class="info-fields">
                    <label>
                        <?php _e('Age', 'wpmatch'); ?>
                        <input type="number" name="age" value="<?php echo esc_attr($profile->age); ?>" min="18" max="120">
                    </label>
                    
                    <label>
                        <?php _e('Location', 'wpmatch'); ?>
                        <input type="text" name="location" value="<?php echo esc_attr($profile->location); ?>">
                    </label>
                    
                    <label>
                        <?php _e('Gender', 'wpmatch'); ?>
                        <select name="gender">
                            <option value="male" <?php selected($profile->gender, 'male'); ?>><?php _e('Male', 'wpmatch'); ?></option>
                            <option value="female" <?php selected($profile->gender, 'female'); ?>><?php _e('Female', 'wpmatch'); ?></option>
                            <option value="other" <?php selected($profile->gender, 'other'); ?>><?php _e('Other', 'wpmatch'); ?></option>
                        </select>
                    </label>
                </div>

                <button class="button button-primary" id="save-profile-btn">
                    <?php _e('Save Profile', 'wpmatch'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render messages interface
     */
    private function render_messages_interface() {
        ?>
        <div class="messages-interface">
            <div class="conversations-list">
                <h3><?php _e('Conversations', 'wpmatch'); ?></h3>
                <div class="conversations">
                    <!-- Conversations will be loaded here -->
                </div>
            </div>
            
            <div class="message-thread">
                <div class="thread-header">
                    <h3><?php _e('Select a conversation', 'wpmatch'); ?></h3>
                </div>
                <div class="thread-messages">
                    <!-- Messages will be loaded here -->
                </div>
                <div class="message-compose">
                    <form id="send-message-form">
                        <textarea name="message" placeholder="<?php esc_attr_e('Type your message...', 'wpmatch'); ?>"></textarea>
                        <button type="submit" class="button button-primary">
                            <?php _e('Send', 'wpmatch'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render registration form
     */
    private function render_registration_form() {
        ?>
        <form id="wpmatch-registration-form" class="wpmatch-form">
            <h3><?php _e('Join Our Dating Community', 'wpmatch'); ?></h3>
            
            <div class="form-group">
                <label for="reg_username"><?php _e('Username', 'wpmatch'); ?> *</label>
                <input type="text" name="username" id="reg_username" required>
            </div>
            
            <div class="form-group">
                <label for="reg_email"><?php _e('Email', 'wpmatch'); ?> *</label>
                <input type="email" name="email" id="reg_email" required>
            </div>
            
            <div class="form-group">
                <label for="reg_password"><?php _e('Password', 'wpmatch'); ?> *</label>
                <input type="password" name="password" id="reg_password" required>
            </div>
            
            <div class="form-group">
                <label for="reg_display_name"><?php _e('Display Name', 'wpmatch'); ?> *</label>
                <input type="text" name="display_name" id="reg_display_name" required>
            </div>
            
            <div class="form-group">
                <label for="reg_age"><?php _e('Age', 'wpmatch'); ?> *</label>
                <input type="number" name="age" id="reg_age" min="18" max="120" required>
            </div>
            
            <div class="form-group">
                <label for="reg_gender"><?php _e('Gender', 'wpmatch'); ?> *</label>
                <select name="gender" id="reg_gender" required>
                    <option value=""><?php _e('Select Gender', 'wpmatch'); ?></option>
                    <option value="male"><?php _e('Male', 'wpmatch'); ?></option>
                    <option value="female"><?php _e('Female', 'wpmatch'); ?></option>
                    <option value="other"><?php _e('Other', 'wpmatch'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="terms" required>
                    <?php _e('I agree to the Terms of Service and Privacy Policy', 'wpmatch'); ?> *
                </label>
            </div>
            
            <div class="form-group">
                <?php wp_nonce_field('wpmatch_registration', 'wpmatch_registration_nonce'); ?>
                <button type="submit" class="button button-primary button-large">
                    <?php _e('Create Account', 'wpmatch'); ?>
                </button>
            </div>
        </form>
        <?php
    }
}