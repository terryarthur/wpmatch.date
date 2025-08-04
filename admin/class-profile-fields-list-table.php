<?php
/**
 * Profile Fields List Table class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the base WP_List_Table class if not available
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * WPMatch Profile Fields List Table class
 * 
 * Extends WP_List_Table to display profile fields in admin
 */
class WPMatch_Profile_Fields_List_Table extends WP_List_Table {

    /**
     * Profile field manager instance
     *
     * @var WPMatch_Profile_Field_Manager
     */
    private $field_manager;

    /**
     * Field type registry instance
     *
     * @var WPMatch_Field_Type_Registry
     */
    private $type_registry;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'profile_field',
            'plural'   => 'profile_fields',
            'ajax'     => true,
        ));

        $this->field_manager = new WPMatch_Profile_Field_Manager();
        $this->type_registry = new WPMatch_Field_Type_Registry();
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'order'       => __('Order', 'wpmatch'),
            'name'        => __('Field Name', 'wpmatch'),
            'label'       => __('Label', 'wpmatch'),
            'type'        => __('Type', 'wpmatch'),
            'group'       => __('Group', 'wpmatch'),
            'required'    => __('Required', 'wpmatch'),
            'searchable'  => __('Searchable', 'wpmatch'),
            'status'      => __('Status', 'wpmatch'),
            'actions'     => __('Actions', 'wpmatch'),
        );
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'order'       => array('field_order', false),
            'name'        => array('field_name', false),
            'label'       => array('field_label', false),
            'type'        => array('field_type', false),
            'group'       => array('field_group', false),
            'status'      => array('status', false),
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return array(
            'activate'   => __('Activate', 'wpmatch'),
            'deactivate' => __('Deactivate', 'wpmatch'),
            'delete'     => __('Delete', 'wpmatch'),
        );
    }

    /**
     * Prepare items for display
     */
    public function prepare_items() {
        $per_page = $this->get_items_per_page('profile_fields_per_page', 20);
        $current_page = $this->get_pagenum();

        // Get search and filter parameters
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $status_filter = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $type_filter = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : '';
        $group_filter = isset($_REQUEST['group']) ? sanitize_text_field($_REQUEST['group']) : '';

        // Get orderby and order
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'field_order';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC';

        // Build query args
        $args = array(
            'per_page'    => $per_page,
            'offset'      => ($current_page - 1) * $per_page,
            'orderby'     => $orderby,
            'order'       => $order,
            'search'      => $search,
            'status'      => $status_filter,
            'field_type'  => $type_filter,
            'field_group' => $group_filter,
        );

        // Get items and total count
        $results = $this->field_manager->get_fields($args);
        $total_items = $this->field_manager->get_fields_count($args);

        $this->items = $results;

        // Set pagination
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ));
    }

    /**
     * Display when no items found
     */
    public function no_items() {
        _e('No profile fields found.', 'wpmatch');
    }

    /**
     * Render checkbox column
     *
     * @param object $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="field_ids[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Render order column
     *
     * @param object $item
     * @return string
     */
    public function column_order($item) {
        return sprintf(
            '<span class="sortable-handle dashicons dashicons-menu" title="%s"></span>' .
            '<span class="field-order-number">%d</span>',
            __('Drag to reorder', 'wpmatch'),
            $item->field_order
        );
    }

    /**
     * Render field name column
     *
     * @param object $item
     * @return string
     */
    public function column_name($item) {
        $edit_url = admin_url('admin.php?page=wpmatch-profile-fields&action=edit&field_id=' . $item->id);
        $view_url = admin_url('admin.php?page=wpmatch-profile-fields&action=view&field_id=' . $item->id);

        $title = sprintf(
            '<strong><a href="%s">%s</a></strong>',
            esc_url($edit_url),
            esc_html($item->field_name)
        );

        // Add row actions
        $actions = array();
        
        $actions['edit'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($edit_url),
            __('Edit', 'wpmatch')
        );

        $actions['view'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($view_url),
            __('View', 'wpmatch')
        );

        if (!$item->is_system) {
            $actions['duplicate'] = sprintf(
                '<a href="#" class="wpmatch-duplicate-field" data-field-id="%d">%s</a>',
                $item->id,
                __('Duplicate', 'wpmatch')
            );

            $actions['delete'] = sprintf(
                '<a href="#" class="wpmatch-delete-field submitdelete" data-field-id="%d">%s</a>',
                $item->id,
                __('Delete', 'wpmatch')
            );
        }

        $actions['preview'] = sprintf(
            '<a href="#" class="wpmatch-preview-field" data-field-id="%d">%s</a>',
            $item->id,
            __('Preview', 'wpmatch')
        );

        return $title . $this->row_actions($actions);
    }

    /**
     * Render field label column
     *
     * @param object $item
     * @return string
     */
    public function column_label($item) {
        $label = esc_html($item->field_label);
        
        if ($item->is_required) {
            $label .= ' <span class="required-asterisk" title="' . __('Required field', 'wpmatch') . '">*</span>';
        }

        if ($item->is_system) {
            $label .= ' <span class="system-field-badge" title="' . __('System field', 'wpmatch') . '">' . __('System', 'wpmatch') . '</span>';
        }

        return $label;
    }

    /**
     * Render field type column
     *
     * @param object $item
     * @return string
     */
    public function column_type($item) {
        $type_config = $this->type_registry->get_field_type($item->field_type);
        $type_label = $type_config ? $type_config['label'] : ucfirst($item->field_type);

        return sprintf(
            '<span class="field-type-badge field-type-%s">%s</span>',
            esc_attr($item->field_type),
            esc_html($type_label)
        );
    }

    /**
     * Render field group column
     *
     * @param object $item
     * @return string
     */
    public function column_group($item) {
        $group_name = $item->field_group ?: 'basic';
        $group_label = $this->get_group_label($group_name);

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(add_query_arg('group', $group_name, admin_url('admin.php?page=wpmatch-profile-fields'))),
            esc_html($group_label)
        );
    }

    /**
     * Render required column
     *
     * @param object $item
     * @return string
     */
    public function column_required($item) {
        return $item->is_required ? 
            '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>' : 
            '<span class="dashicons dashicons-minus" style="color: #999;"></span>';
    }

    /**
     * Render searchable column
     *
     * @param object $item
     * @return string
     */
    public function column_searchable($item) {
        return $item->is_searchable ? 
            '<span class="dashicons dashicons-search" style="color: #0073aa;"></span>' : 
            '<span class="dashicons dashicons-minus" style="color: #999;"></span>';
    }

    /**
     * Render status column
     *
     * @param object $item
     * @return string
     */
    public function column_status($item) {
        $status_labels = array(
            'active'     => __('Active', 'wpmatch'),
            'inactive'   => __('Inactive', 'wpmatch'),
            'draft'      => __('Draft', 'wpmatch'),
            'deprecated' => __('Deprecated', 'wpmatch'),
            'archived'   => __('Archived', 'wpmatch'),
        );

        $status = $item->status ?: 'active';
        $label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);

        return sprintf(
            '<span class="status-badge status-%s">%s</span>',
            esc_attr($status),
            esc_html($label)
        );
    }

    /**
     * Render actions column
     *
     * @param object $item
     * @return string
     */
    public function column_actions($item) {
        $actions = array();

        // Toggle status button
        $current_status = $item->status ?: 'active';
        $toggle_text = ($current_status === 'active') ? __('Deactivate', 'wpmatch') : __('Activate', 'wpmatch');
        
        $actions[] = sprintf(
            '<button type="button" class="button button-small wpmatch-toggle-status" data-field-id="%d" data-current-status="%s">%s</button>',
            $item->id,
            esc_attr($current_status),
            esc_html($toggle_text)
        );

        // Preview button
        $actions[] = sprintf(
            '<button type="button" class="button button-small wpmatch-preview-field" data-field-id="%d">%s</button>',
            $item->id,
            __('Preview', 'wpmatch')
        );

        return '<div class="action-buttons">' . implode('', $actions) . '</div>';
    }

    /**
     * Display table navigation
     *
     * @param string $which
     */
    protected function display_tablenav($which) {
        if ('top' === $which) {
            $this->views();
            $this->extra_tablenav($which);
        }

        parent::display_tablenav($which);
    }

    /**
     * Display status views
     */
    public function views() {
        $status_counts = $this->field_manager->get_status_counts();
        $current_status = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';

        $status_links = array();

        // All link
        $class = (empty($current_status)) ? ' class="current"' : '';
        $all_url = remove_query_arg('status');
        $total_count = array_sum($status_counts);
        $status_links['all'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            esc_url($all_url),
            $class,
            __('All', 'wpmatch'),
            $total_count
        );

        // Status-specific links
        $status_labels = array(
            'active'   => __('Active', 'wpmatch'),
            'inactive' => __('Inactive', 'wpmatch'),
            'draft'    => __('Draft', 'wpmatch'),
        );

        foreach ($status_labels as $status => $label) {
            if (isset($status_counts[$status]) && $status_counts[$status] > 0) {
                $class = ($current_status === $status) ? ' class="current"' : '';
                $status_url = add_query_arg('status', $status);
                $status_links[$status] = sprintf(
                    '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                    esc_url($status_url),
                    $class,
                    esc_html($label),
                    $status_counts[$status]
                );
            }
        }

        if (!empty($status_links)) {
            echo '<ul class="subsubsub">';
            echo '<li>' . implode(' |</li><li>', $status_links) . '</li>';
            echo '</ul>';
        }
    }

    /**
     * Display extra table navigation
     *
     * @param string $which
     */
    protected function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }
        ?>
        <div class="alignleft actions">
            <?php
            // Status filter
            $status_filter = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
            $status_options = array(
                ''         => __('All statuses', 'wpmatch'),
                'active'   => __('Active', 'wpmatch'),
                'inactive' => __('Inactive', 'wpmatch'),
                'draft'    => __('Draft', 'wpmatch'),
            );
            ?>
            <select name="status" id="filter-by-status">
                <?php foreach ($status_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php
            // Type filter
            $type_filter = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
            $field_types = $this->type_registry->get_field_types_for_select();
            ?>
            <select name="type" id="filter-by-type">
                <option value=""><?php _e('All types', 'wpmatch'); ?></option>
                <?php foreach ($field_types as $type => $label): ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected($type_filter, $type); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php
            // Group filter
            $group_filter = isset($_REQUEST['group']) ? $_REQUEST['group'] : '';
            $field_groups = $this->get_field_groups();
            ?>
            <select name="group" id="filter-by-group">
                <option value=""><?php _e('All groups', 'wpmatch'); ?></option>
                <?php foreach ($field_groups as $group => $label): ?>
                    <option value="<?php echo esc_attr($group); ?>" <?php selected($group_filter, $group); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php submit_button(__('Filter', 'wpmatch'), 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Get field groups for filter
     *
     * @return array
     */
    private function get_field_groups() {
        return array(
            'basic'        => __('Basic Information', 'wpmatch'),
            'physical'     => __('Physical Attributes', 'wpmatch'),
            'lifestyle'    => __('Lifestyle', 'wpmatch'),
            'interests'    => __('Interests & Hobbies', 'wpmatch'),
            'relationship' => __('Relationship Goals', 'wpmatch'),
            'background'   => __('Background', 'wpmatch'),
        );
    }

    /**
     * Get group label
     *
     * @param string $group_name
     * @return string
     */
    private function get_group_label($group_name) {
        $groups = $this->get_field_groups();
        return isset($groups[$group_name]) ? $groups[$group_name] : ucfirst($group_name);
    }

    /**
     * Display the table
     */
    public function display() {
        wp_nonce_field('bulk-' . $this->_args['plural']);
        
        // Make table sortable
        echo '<div id="wpmatch-fields-sortable">';
        parent::display();
        echo '</div>';
    }

    /**
     * Generate table rows
     *
     * @return string
     */
    public function single_row($item) {
        echo sprintf('<tr data-field-id="%d">', $item->id);
        $this->single_row_columns($item);
        echo '</tr>';
    }
}