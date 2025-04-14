<?php
/*
|--------------------------------------------------------------------------------|
|   Create default WordPress table for displaying all images info                |
|--------------------------------------------------------------------------------|
|   WP_List_Table is duplicated as CPFM_WP_List_Table                            |
|--------------------------------------------------------------------------------|
|   Never Extend default WordPress Class WP_List_Table                           |
|--------------------------------------------------------------------------------|
*/

class cpfm_list_table extends CPFM_WP_List_Table
{
    public function __construct()
    {

        parent::__construct(array(
            'singular' => 'cpfm_list_label', //Singular label
            'plural' => 'cpfm_list_labels', //plural label, also this well be one of the table css class
            'ajax' => false, // Don't support Ajax for this table
        ));

        add_action( 'admin_enqueue_scripts', array($this,'enqueue_feedback_script') );
        wp_enqueue_style('feedback-style', plugin_dir_url(__FILE__) . 'feedback/css/admin-feedback.css',null,$this->plugin_version );
    }
    
    function enqueue_feedback_script() {
        
        wp_enqueue_script( 'feedback-script', plugin_dir_url(__FILE__) . 'feedback/js/admin-feedback-table.js', array('jquery'), '1.0.0', true );

        wp_localize_script('feedback-script', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('get_selected_value_nonce'),
        ));
        
    }
    
    /*
    |--------------------------------------------------------------------------------------|
    | Add extra markup in the toolbars before or after the list                            |
    | $position, helps you decide if you add the after (bottom) or before (top) the list      |
    |--------------------------------------------------------------------------------------|
    */
    public function extra_tablenav($position)
    {
        if ($position == "top") {
            ?>
                <div style="display:flow-root;margin-right:20px;">
                    <?php $this->search_box('search', 'search_id'); ?>
                </div>
                <?php
                GLOBAL $wpdb;
                $tablename = $wpdb->base_prefix . 'cpfm_feedbacks';
                $move_on_url = '&cat-filter=';
                $cats = $wpdb->get_results('select * from '.$tablename.' group by plugin_name', ARRAY_A);
                if( $cats ){
                    $x=0;
                    ?>
                    <select name="cat-filter" class="ewc-filter-cat">
                        <option value="">All Plugins</option>
                        <?php
                        foreach( $cats as $cat ){
                            $selected = '';
                            if( isset($_REQUEST['cat-filter']) && $_REQUEST['cat-filter'] == $cat['plugin_name'] ){
                                $selected = ' selected = "selected"';   
                            }
                            $has_testis = false;
                            $chk_testis = $wpdb->get_row("SELECT * FROM ".$tablename ." GROUP BY plugin_name", ARRAY_A);
                            if( $chk_testis['id'] > 0 ){
                        ?>
                        <option value="<?php echo $cat['plugin_name']; ?>" <?php echo $selected; ?>><?php echo ucwords($cat['plugin_name']); ?></option>
                        <?php 
                        $x++;  
                            }
                        }
                        ?>
                    </select>
                    <button class="button primary" id="cpfm_filter" >Filter</button>
                      <label for="export_data_date_From">From:</label>
                    <input type="date" name="export_data_date_From" value="<?php echo esc_attr($_REQUEST['export_data_date_From'] ?? ''); ?>">
                    <label for="export_data_date_to">To:</label>
                    <input type="date" name="export_data_date_to" value="<?php echo esc_attr($_REQUEST['export_data_date_to'] ?? ''); ?>">
                    <input type="submit" name="export_data" id="export_data" class="button primary" value="Export Data" style="margin-left: 10px;" />
                    <?php
                }
         
                ?>  
            <?php
    
    }
        if ($position == "bottom") {
            echo "<em>Powered by Cool Plugins Team.</em> </form>";
        }
    }

    /*
    |----------------------------------------------------------------------|
    | Define the columns that are going to be used in the table            |
    | @return array $columns, the array of columns to use with the table   |
    |----------------------------------------------------------------------|
    */
    public function get_columns()
    {
        return $columns = array(
            // 'cb' => '<input type="checkbox"/>',
            'id' => __('Sr.'),
            'date' => __('Date'),
            'plugin_initial'=> __('Plugin Initial'),
            'plugin_version' => __('Plugin Version'),
            'plugin_name' => __('Plugin Name'),
            'reason' => __('Reason'),
            'review' => __('Review'),
            'domain' => __('Domain'),
            'email' => __('Email'),
            'more_details' => __('Extra Details'),
        );
    }

    /*
    |--------------------------------------------------------------------------------|
    | Decide which columns to activate the sorting functionality on                  |
    | @return array $sortable, the array of columns that can be sorted by the user   |
    |--------------------------------------------------------------------------------|
    */
    public function get_sortable_columns()
    {
        return $sortable = array(
            // 'cb' => array( 'cb', false),
            'id' => array('id', true),
            'plugin_version' => array('plugin_version', true),
            'plugin_name' => array('plugin_name', true),
            'reason' => array('reason', true),
        );
    }

    /*
    |---------------------------------------------------------------------------------------|
    | Perform action if any row action is received
    |---------------------------------------------------------------------------------------|
    */
    function cpfm_perform_row_actions(){
        GLOBAL $wpdb;
        $giphy_table = $wpdb->base_prefix . 'cpfm_feedbacks';
        if( isset( $_REQUEST['action'] ) ){

            switch( $_REQUEST['action'] ){
                case 'delete':
                break;
            }
        }
    }

    /*
    |-----------------------------------------------------------|
    |   Process bulk action for delete query                    |
    |-----------------------------------------------------------|
    */
    public function get_bulk_actions() {
        $actions = [
          'bulk-delete' => 'Delete',
        ];
      
        // return $actions;
    }

    public function cpfm_process_bulk_action() {
        GLOBAL $wpdb;
        $table = $wpdb->base_prefix . 'cpfm_feedbacks';
        
        // If the delete bulk action is triggered
        if ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' ) {
         
        }

    }

    protected function get_views() { 
        $status_links = array(
            "all"       => __("<a href='#'>All</a>",'my-plugin-slug'),
            "published" => __("<a href='#'>Published</a>",'my-plugin-slug'),
            "trashed"   => __("<a href='#'>Trashed</a>",'my-plugin-slug')
        );
        return $status_links;
    }

    function cpfm_fetch_export_data($is_export) {
        
        if (!$is_export) {
            return;
        }
    
        global $wpdb;
        $table_name = $wpdb->base_prefix . 'cpfm_feedbacks';
        $selected_columns = 'id, plugin_version, plugin_name, plugin_initial, reason, review, domain, email, deactivation_date';
        
        // Prepare query parts
        $query = "SELECT $selected_columns FROM $table_name";
        $conditions = [];
        $params = [];
    
        // Apply plugin name filter if set
        $user_filter = isset($_REQUEST['cat-filter']) ? wp_unslash(trim($_REQUEST['cat-filter'])) : '';
        if (!empty($user_filter)) {
            $conditions[] = 'plugin_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($user_filter) . '%';
        }
    
        // Apply date range filter if both dates are set
        $from_date = isset($_REQUEST['export_data_date_From']) ? wp_unslash(trim($_REQUEST['export_data_date_From'])) : '';
        $to_date = isset($_REQUEST['export_data_date_to']) ? wp_unslash(trim($_REQUEST['export_data_date_to'])) : '';
        if (!empty($from_date) && !empty($to_date)) {
            $conditions[] = 'deactivation_date BETWEEN %s AND %s';
            $params[] = $from_date;
            $params[] = $to_date . ' 23:59:59';
        }
    
        // Add conditions to query if any exist
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
            $query = $wpdb->prepare($query, $params);
        }
    
        return $wpdb->get_results($query, ARRAY_A);
    }

    /*
    |---------------------------------------------------------------------------------------|
    | Prepare the table with different parameters, pagination, columns and table elements   |
    |---------------------------------------------------------------------------------------|
    */
    public function prepare_items()
    {
        
        global $wpdb, $_wp_column_headers;
        $screen = get_current_screen();
        $user = get_current_user_id();
        echo '<h1>List All Reviews</h1><form method="post">';
        $query = 'SELECT * FROM ' . $wpdb->base_prefix . 'cpfm_feedbacks';

       /*  $this->cpfm_process_bulk_action();
        $this->cpfm_perform_row_actions(); */

        // search keyword
        $user_search_keyword = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        if (!empty($user_search_keyword)) {
            $query .= ' WHERE plugin_name LIKE "%' . $user_search_keyword . '%" OR email LIKE "%' . $user_search_keyword . '%"
            OR reason LIKE "%' . $user_search_keyword . '%" OR domain LIKE "%' . $user_search_keyword . '%"';
        }

        $user_filter = isset($_REQUEST['cat-filter']) ? wp_unslash(trim($_REQUEST['cat-filter'])) : '';
        if (!empty($user_filter)) {
            // $user_filter = str_replace('-',' ',$user_filter);
            $query .= ' WHERE plugin_name LIKE "%' . $user_filter . '%"';
        }

        $filter_from_date = isset($_REQUEST['export_data_date_From']) ? wp_unslash(trim($_REQUEST['export_data_date_From'])) : '';
        $filter_to_date = isset($_REQUEST['export_data_date_to']) ? wp_unslash(trim($_REQUEST['export_data_date_to'])) : '';
        if (!empty($filter_from_date) && !empty($filter_to_date)) {
            if(empty($user_filter) ){
                $query .= ' WHERE ';
            }else{
                $query .= ' AND ';
            }
            $query .= " deactivation_date BETWEEN '" . esc_sql($filter_from_date) . "' AND '" . esc_sql($filter_to_date) . " 23:59:59'";
        }

        // Ordering parameters
        $orderby = !empty($_REQUEST["orderby"]) ? esc_sql($_REQUEST["orderby"]) : 'id';
        $order = !empty($_REQUEST["order"]) ? esc_sql($_REQUEST["order"]) : 'DESC';
        if (!empty($orderby) & !empty($order)) {
            $query .= ' ORDER BY ' . $orderby . ' ' . $order;
        }

        // Pagination parameters
        $totalitems = $wpdb->query($query);
        $option = $screen->get_option('per_page', 'option');
        $perpage = (int)get_user_meta($user, $option, true);
        if (!is_numeric($perpage) || empty($perpage)) {
            $perpage = 10;
        }

        $paged = !empty($_REQUEST["paged"]) ? esc_sql($_REQUEST["paged"]) : false;

        if (empty($paged) || !is_numeric($paged) || $paged <= 0) {
            $paged = 1;
        }

        $totalpages = ceil($totalitems / $perpage);

        if (!empty($paged) && !empty($perpage)) {
            $offset = ($paged - 1) * $perpage;
            $query .= ' LIMIT ' . (int) $offset . ',' . (int) $perpage;
        }

        // Register the pagination & build link
        $this->set_pagination_args(array(
            "total_items" => $totalitems,
            "total_pages" => $totalpages,
            "per_page" => $perpage,
        )
        );
         
        $this->_column_headers =  $this->get_column_info();

        // Get feedback data from database
        $this->items = $wpdb->get_results($query);
    }


    /*
    |-----------------------------------------------------|
    | Display the checkbox for all records                |
    |-----------------------------------------------------|
    */
    function column_cb( $item ) {
      /*   return sprintf(
          '<input type="checkbox" name="bulk-action[]" value="%s" />', $item->id
        ); */
    }

    /*
    |-----------------------------------------------------|
    | Display the columns of records in the table         |
    | A common function for all custom columns            |
    |-----------------------------------------------------|
    */
    public function column_default( $item, $column_name )
    {

        //Get the records registered in the prepare_items method
        $records = $this->items;
        //Get the columns registered in the get_columns and get_sortable_columns methods
        $columns = $this->get_column_info();

                    switch ($column_name) {
                        case "id":
                        // wp_create_nonce('cpfm_bulk_delete');
                            return $item->id;
                        break;
                        case "date":
                            return date("F j, Y", strtotime($item->deactivation_date));
                        break;  
                        case "plugin_initial":
                            return !empty($item->plugin_initial)?$item->plugin_initial:'N/A';
                        break;                     
                        case "plugin_version":
                           return $item->plugin_version;
                        break;
                        case "plugin_name":
                            return   ucwords( $item->plugin_name );
                        break;
                        case "review":
                            return  $item->review;
                        break;
                        case "reason":
                            return  $item->reason;
                        break;
                        case "domain":
                            return '<a href="'.$item->domain.'" target="_new">'.$item->domain.'</a>';
                        break;
                        case "email":
                            return '<a href="mailto:'.$item->email.';">'.$item->email.'</a>';
                        break;
                        case "more_details":
                            return '<a href="#" class="more-details-link" data-id="' . $item->id . '">View More</a>';
                        break;
                        
                        default:
                            return 'unknown column '.$column_name;
                    }
    }

    static function get_select_html($id,$selected_value = 'default') {

        $options = [
            'default' => 'Server Info',
            'plugin' => 'Plugins Info',
            'theme' => 'Themes Info'
        ];
        
        $select = '<select id="popup-select"  data-id="' . $id . '">';
        foreach ($options as $value => $label) {
            $selected = ($value == $selected_value) ? ' selected' : '';
            $select .= "<option value='{$value}'{$selected}>{$label}</option>";
        }
        $select .= '</select>';
        
        return $select;
    }
    
    static function cpfm_feedback_load_extra_data($value, $id) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'cpfm_feedbacks';   

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT extra_details, server_info FROM $table_name WHERE id = %d",
            $id
        ), ARRAY_A);

        if ($result["extra_details"] === NULL || empty($result["extra_details"]) || $result["server_info"] === NULL || empty($result["server_info"])) {
            return '<h2>No data found.</h2>';
        }
        $extra_details = unserialize(stripslashes($result['extra_details'])) ?: [];
        $serve_info = unserialize(stripslashes($result['server_info'])) ?: [];
       

    
        $table_attrs    = 'border="1" style="border-collapse: collapse; width: 100%;"';
        $cell_attrs     = 'style="padding: 10px; border: 1px solid #ddd;"';
        $header_style   = 'style="background-color: #f2f2f2; padding: 10px; border: 1px solid #ddd;"';

        switch ($value) {
            case 'plugin':
                return self::get_select_html($id,'plugin') . 
                       self::cpfm_render_extra_table($extra_details, $value, $table_attrs, $cell_attrs, $header_style);
                        
            case 'theme':
                return self::get_select_html($id,'theme') . 
                       self::cpfm_render_extra_table($extra_details, $value, $table_attrs, $cell_attrs, $header_style);
                        
            default:
                return self::get_select_html($id,'default') . 
                       self::cpfm_render_system_info_table($serve_info, $table_attrs, $cell_attrs, $header_style);
        }
 
    }
  
         private static function cpfm_render_extra_table($extra_details, $type, $table_attrs, $cell_attrs, $header_style) { 
        
            if ($type === 'plugin' && empty($extra_details['active_plugins'])) {
                return '<p>No active plugins found.</p>';
            }
            
            if ($type === 'theme' && empty($extra_details['wp_theme'])) {
                return '<p>No active theme found.</p>';
            }
        
            $output = "<table $table_attrs>
            <thead>
            <tr>
            <th $header_style>SR. No.</th>
            <th $header_style>Name</th>
            <th $header_style>Version</th>
            <th $header_style>URL</th>
            </tr>
            </thead>
            <tbody>";
        
            if ($type === 'plugin') {
                foreach ($extra_details['active_plugins'] as $index => $plugin) {
                    $count = $index + 1;
                    $plugin_name = esc_html($plugin['name'] ?? 'N/A');
                    $plugin_version = esc_html($plugin['version'] ?? 'N/A');
                    $plugin_url = !empty($plugin['plugin_uri']) 
                        ? '<a href="' . esc_url($plugin['plugin_uri']) . '" target="_blank">' . esc_html($plugin['plugin_uri']) . '</a>' 
                        : 'N/A';
        
                    $output .= "<tr>
                        <td $cell_attrs>$count</td>
                        <td $cell_attrs>$plugin_name</td>
                        <td $cell_attrs>$plugin_version</td>
                        <td $cell_attrs>$plugin_url</td>
                    </tr>";
                }
            } elseif ($type === 'theme') {
                $theme = isset($extra_details['wp_theme']) ? $extra_details['wp_theme'] : [];
                $theme_name = isset($theme['name']) ? esc_html($theme['name']) : 'N/A';
                $theme_version = isset($theme['version']) ? esc_html($theme['version']) : 'N/A';
                $theme_url = !empty($theme['theme_uri']) 
                    ? '<a href="' . esc_url($theme['theme_uri']) . '" target="_blank">' . esc_html($theme['theme_uri']) . '</a>' 
                    : 'N/A';
        
                $output .= "<tr>
                    <td $cell_attrs>1</td>
                    <td $cell_attrs>$theme_name</td>
                    <td $cell_attrs>$theme_version</td>
                    <td $cell_attrs>$theme_url</td>
                </tr>";
            }
        
            $output .= '</tbody></table>';
            return $output;
        }
    
    private static function cpfm_render_system_info_table($serve_info, $table_attrs, $cell_attrs, $header_style) {

       $system_data = [

            'Server Software' => isset($serve_info['server_software']) ? $serve_info['server_software'] : 'N/A',
            'MySQL Version' => isset($serve_info['mysql_version']) ? $serve_info['mysql_version'] : 'N/A',
            'PHP Version' => isset($serve_info['php_version']) ? $serve_info['php_version'] : 'N/A',
            'WP Version' => isset($serve_info['wp_version']) ? $serve_info['wp_version'] : 'N/A',
            'WP Debug' => isset($serve_info['wp_debug']) ? $serve_info['wp_debug'] : 'N/A',
            'WP Memory Limit' => isset($serve_info['wp_memory_limit']) ? $serve_info['wp_memory_limit'] : 'N/A',
            'WP Max Upload Size' => isset($serve_info['wp_max_upload_size']) ? $serve_info['wp_max_upload_size'] : 'N/A',
            'WP Permalink Structure' => isset($serve_info['wp_permalink_structure']) ? $serve_info['wp_permalink_structure'] : 'N/A',
            'WP Multisite' => isset($serve_info['wp_multisite']) ? $serve_info['wp_multisite'] : 'N/A',
            'WP Language' => isset($serve_info['wp_language']) ? $serve_info['wp_language'] : 'N/A',
            'WP Prefix' => isset($serve_info['wp_prefix']) ? $serve_info['wp_prefix'] : 'N/A'
       ];
    
        $output = "<table $table_attrs><thead><tr>";
        
        // Headers
        foreach (array_keys($system_data) as $header) {
            $output .= "<th $header_style>" . esc_html($header) . "</th>";
        }
        
        $output .= "</tr></thead><tbody><tr>";
        
        // Values
        foreach ($system_data as $value) {
            $output .= "<td $cell_attrs>" . esc_html($value) . "</td>";
        }
        
        $output .= "</tr></tbody></table>";
        
        return $output;
    }


}

/*
|--------------------------------------------------------------------------------------------|
|  XXXXXXXXXXXXXXXXXXXXXXXX-----DO NOT MODIFY BELOW THIS POINT------------XXXXXXXXXXXXXXXXXXX|
|--------------------------------------------------------------------------------------------|
 */

/*
|------------------------------------------------------------------------------------|
|   THIS CLASS ACTUALLY BELONGS TO WORDPRESS BUT NEVER EXTENED THE WORDPRESS CLASS   |
|    ALWAYS COPY THE WORDPRESS CLASS AND USE IT IN YOUR OWN PLUGIN FOR STABILITY     |
|------------------------------------------------------------------------------------|
| Base class for displaying a list of items in an ajaxified HTML table.              |
|------------------------------------------------------------------------------------|
| @since 3.1.0                                                                       |
| @access private                                                                    |
|------------------------------------------------------------------------------------|
 */
class CPFM_WP_List_Table
{

    /**
     * The current list of items.
     *
     * @since 3.1.0
     * @var array
     */
    public $items;

    /**
     * Various information about the current table.
     *
     * @since 3.1.0
     * @var array
     */
    protected $_args;

    /**
     * Various information needed for displaying the pagination.
     *
     * @since 3.1.0
     * @var array
     */
    protected $_pagination_args = array();

    /**
     * The current screen.
     *
     * @since 3.1.0
     * @var object
     */
    protected $screen;

    /**
     * Cached bulk actions.
     *
     * @since 3.1.0
     * @var array
     */
    private $_actions;

    /**
     * Cached pagination output.
     *
     * @since 3.1.0
     * @var string
     */
    private $_pagination;

    /**
     * The view switcher modes.
     *
     * @since 4.1.0
     * @var array
     */
    protected $modes = array();

    /**
     * Stores the value returned by ->get_column_info().
     *
     * @since 4.1.0
     * @var array
     */
    protected $_column_headers;

    /**
     * {@internal Missing Summary}
     *
     * @var array
     */
    protected $compat_fields = array('_args', '_pagination_args', 'screen', '_actions', '_pagination');

    /**
     * {@internal Missing Summary}
     *
     * @var array
     */
    protected $compat_methods = array(
        'set_pagination_args',
        'get_views',
        'get_bulk_actions',
        'bulk_actions',
        'row_actions',
        'months_dropdown',
        'view_switcher',
        'comments_bubble',
        'get_items_per_page',
        'pagination',
        'get_sortable_columns',
        'get_column_info',
        'get_table_classes',
        'display_tablenav',
        'extra_tablenav',
        'single_row_columns',
    );

    /**
     * Constructor.
     *
     * The child class should call this constructor from its own constructor to override
     * the default $args.
     *
     * @since 3.1.0
     *
     * @param array|string $args {
     *     Array or string of arguments.
     *
     *     @type string $plural   Plural value used for labels and the objects being listed.
     *                            This affects things such as CSS class-names and nonces used
     *                            in the list table, e.g. 'posts'. Default empty.
     *     @type string $singular Singular label for an object being listed, e.g. 'post'.
     *                            Default empty
     *     @type bool   $ajax     Whether the list table supports Ajax. This includes loading
     *                            and sorting data, for example. If true, the class will call
     *                            the _js_vars() method in the footer to provide variables
     *                            to any scripts handling Ajax events. Default false.
     *     @type string $screen   String containing the hook name used to determine the current
     *                            screen. If left null, the current screen will be automatically set.
     *                            Default null.
     * }
     */
    public function __construct($args = array())
    {
        $args = wp_parse_args(
            $args,
            array(
                'plural' => '',
                'singular' => '',
                'ajax' => false,
                'screen' => null,
            )
        );

        $this->screen = convert_to_screen($args['screen']);

        add_filter("manage_{$this->screen->id}_columns", array($this, 'get_columns'), 0);

        if (!$args['plural']) {
            $args['plural'] = $this->screen->base;
        }

        $args['plural'] = sanitize_key($args['plural']);
        $args['singular'] = sanitize_key($args['singular']);

        $this->_args = $args;

        if ($args['ajax']) {
            // wp_enqueue_script( 'list-table' );
            add_action('admin_footer', array($this, '_js_vars'));
        }

        if (empty($this->modes)) {
            $this->modes = array(
                'list' => __('List View'),
                'excerpt' => __('Excerpt View'),
            );
        }
    }

    /**
     * Make private properties readable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name Property to get.
     * @return mixed Property.
     */
    public function __get($name)
    {
        if (in_array($name, $this->compat_fields)) {
            return $this->$name;
        }
    }

    /**
     * Make private properties settable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name  Property to check if set.
     * @param mixed  $value Property value.
     * @return mixed Newly-set property.
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->compat_fields)) {
            return $this->$name = $value;
        }
    }

    /**
     * Make private properties checkable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name Property to check if set.
     * @return bool Whether the property is set.
     */
    public function __isset($name)
    {
        if (in_array($name, $this->compat_fields)) {
            return isset($this->$name);
        }
    }

    /**
     * Make private properties un-settable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name Property to unset.
     */
    public function __unset($name)
    {
        if (in_array($name, $this->compat_fields)) {
            unset($this->$name);
        }
    }

    /**
     * Make private/protected methods readable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string   $name      Method to call.
     * @param array    $arguments Arguments to pass when calling.
     * @return mixed|bool Return value of the callback, false otherwise.
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, $this->compat_methods)) {
            return call_user_func_array(array($this, $name), $arguments);
        }
        return false;
    }

    /**
     * Checks the current user's permissions
     *
     * @since 3.1.0
     * @abstract
     */
    public function ajax_user_can()
    {
        die('function WP_List_Table::ajax_user_can() must be over-ridden in a sub-class.');
    }

    /**
     * Prepares the list of items for displaying.
     *
     * @uses WP_List_Table::set_pagination_args()
     *
     * @since 3.1.0
     * @abstract
     */
    public function prepare_items()
    {
        die('function WP_List_Table::prepare_items() must be over-ridden in a sub-class.');
    }

    /**
     * An internal method that sets all the necessary pagination arguments
     *
     * @since 3.1.0
     *
     * @param array|string $args Array or string of arguments with information about the pagination.
     */
    protected function set_pagination_args($args)
    {
        $args = wp_parse_args(
            $args,
            array(
                'total_items' => 0,
                'total_pages' => 0,
                'per_page' => 0,
            )
        );

        if (!$args['total_pages'] && $args['per_page'] > 0) {
            $args['total_pages'] = ceil($args['total_items'] / $args['per_page']);
        }

        // Redirect if page number is invalid and headers are not already sent.
        if (!headers_sent() && !wp_doing_ajax() && $args['total_pages'] > 0 && $this->get_pagenum() > $args['total_pages']) {
            wp_redirect(add_query_arg('paged', $args['total_pages']));
            exit;
        }

        $this->_pagination_args = $args;
    }

    /**
     * Access the pagination args.
     *
     * @since 3.1.0
     *
     * @param string $key Pagination argument to retrieve. Common values include 'total_items',
     *                    'total_pages', 'per_page', or 'infinite_scroll'.
     * @return int Number of items that correspond to the given pagination argument.
     */
    public function get_pagination_arg($key)
    {
        if ('page' === $key) {
            return $this->get_pagenum();
        }

        if (isset($this->_pagination_args[$key])) {
            return $this->_pagination_args[$key];
        }
    }

    /**
     * Whether the table has items to display or not
     *
     * @since 3.1.0
     *
     * @return bool
     */
    public function has_items()
    {
        return !empty($this->items);
    }

    /**
     * Message to be displayed when there are no items
     *
     * @since 3.1.0
     */
    public function no_items()
    {
        _e('No items found.');
    }

    /**
     * Displays the search box.
     *
     * @since 3.1.0
     *
     * @param string $text     The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     */
    public function search_box($text, $input_id)
    {
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if (!empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if (!empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        if (!empty($_REQUEST['post_mime_type'])) {
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr($_REQUEST['post_mime_type']) . '" />';
        }
        if (!empty($_REQUEST['detached'])) {
            echo '<input type="hidden" name="detached" value="' . esc_attr($_REQUEST['detached']) . '" />';
        }
        ?>
<p class="search-box">
	<label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo $text; ?>:</label>
	<input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query();?>" />
		<?php submit_button($text, '', '', false, array('id' => 'search-submit'));?>
</p>
		<?php
}

    /**
     * Get an associative array ( id => link ) with the list
     * of views available on this table.
     *
     * @since 3.1.0
     *
     * @return array
     */
    protected function get_views()
    {
        return array();
    }

    /**
     * Display the list of views available on this table.
     *
     * @since 3.1.0
     */
    public function views()
    {
        $views = $this->get_views();
        /**
         * Filters the list of available list table views.
         *
         * The dynamic portion of the hook name, `$this->screen->id`, refers
         * to the ID of the current screen, usually a string.
         *
         * @since 3.5.0
         *
         * @param string[] $views An array of available list table views.
         */
        $views = apply_filters("views_{$this->screen->id}", $views);

        if (empty($views)) {
            return;
        }

        $this->screen->render_screen_reader_content('heading_views');

        echo "<ul class='subsubsub'>\n";
        foreach ($views as $class => $view) {
            $views[$class] = "\t<li class='$class'>$view";
        }
        echo implode(" |</li>\n", $views) . "</li>\n";
        echo '</ul>';
    }

    /**
     * Get an associative array ( option_name => option_title ) with the list
     * of bulk actions available on this table.
     *
     * @since 3.1.0
     *
     * @return array
     */
    protected function get_bulk_actions()
    {
        return array();
    }

    /**
     * Display the bulk actions dropdown.
     *
     * @since 3.1.0
     *
     * @param string $which The location of the bulk actions: 'top' or 'bottom'.
     *                      This is designated as optional for backward compatibility.
     */
    protected function bulk_actions($which = '')
    {
        if (is_null($this->_actions)) {
            $this->_actions = $this->get_bulk_actions();
            /**
             * Filters the list table Bulk Actions drop-down.
             *
             * The dynamic portion of the hook name, `$this->screen->id`, refers
             * to the ID of the current screen, usually a string.
             *
             * This filter can currently only be used to remove bulk actions.
             *
             * @since 3.5.0
             *
             * @param string[] $actions An array of the available bulk actions.
             */
            $this->_actions = apply_filters("bulk_actions-{$this->screen->id}", $this->_actions);
            $two = '';
        } else {
            $two = '2';
        }

        if (empty($this->_actions)) {
            return;
        }

        echo '<label for="bulk-action-selector-' . esc_attr($which) . '" class="screen-reader-text">' . __('Select bulk action') . '</label>';
        echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr($which) . "\">\n";
        echo '<option value="-1">' . __('Bulk Actions') . "</option>\n";

        foreach ($this->_actions as $name => $title) {
            $class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

            echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
        }

        echo "</select>\n";

        submit_button(__('Apply'), 'action', '', false, array('id' => "doaction$two"));
        echo "\n";
    }

    /**
     * Get the current action selected from the bulk actions dropdown.
     *
     * @since 3.1.0
     *
     * @return string|false The action name or False if no action was selected
     */
    public function current_action()
    {
        if (isset($_REQUEST['filter_action']) && !empty($_REQUEST['filter_action'])) {
            return false;
        }

        if (isset($_REQUEST['action']) && -1 != $_REQUEST['action']) {
            return $_REQUEST['action'];
        }

        if (isset($_REQUEST['action2']) && -1 != $_REQUEST['action2']) {
            return $_REQUEST['action2'];
        }

        return false;
    }

    /**
     * Generate row actions div
     *
     * @since 3.1.0
     *
     * @param string[] $actions        An array of action links.
     * @param bool     $always_visible Whether the actions should be always visible.
     * @return string
     */
    protected function row_actions($actions, $always_visible = false)
    {
        $action_count = count($actions);
        $i = 0;

        if (!$action_count) {
            return '';
        }

        $out = '<div class="' . ($always_visible ? 'row-actions visible' : 'row-actions') . '">';
        foreach ($actions as $action => $link) {
            ++$i;
            ($i == $action_count) ? $sep = '' : $sep = ' | ';
            $out .= "<span class='$action'>$link$sep</span>";
        }
        $out .= '</div>';

        $out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details') . '</span></button>';

        return $out;
    }

    /**
     * Display a monthly dropdown for filtering items
     *
     * @since 3.1.0
     *
     * @global wpdb      $wpdb
     * @global WP_Locale $wp_locale
     *
     * @param string $post_type
     */
    protected function months_dropdown($post_type)
    {
        global $wpdb, $wp_locale;

        /**
         * Filters whether to remove the 'Months' drop-down from the post list table.
         *
         * @since 4.2.0
         *
         * @param bool   $disable   Whether to disable the drop-down. Default false.
         * @param string $post_type The post type.
         */
        if (apply_filters('disable_months_dropdown', false, $post_type)) {
            return;
        }

        $extra_checks = "AND post_status != 'auto-draft'";
        if (!isset($_GET['post_status']) || 'trash' !== $_GET['post_status']) {
            $extra_checks .= " AND post_status != 'trash'";
        } elseif (isset($_GET['post_status'])) {
            $extra_checks = $wpdb->prepare(' AND post_status = %s', $_GET['post_status']);
        }

        $months = $wpdb->get_results(
            $wpdb->prepare(
                "
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
			FROM $wpdb->posts
			WHERE post_type = %s
			$extra_checks
			ORDER BY post_date DESC
		",
                $post_type
            )
        );

        /**
         * Filters the 'Months' drop-down results.
         *
         * @since 3.7.0
         *
         * @param object $months    The months drop-down query results.
         * @param string $post_type The post type.
         */
        $months = apply_filters('months_dropdown_results', $months, $post_type);

        $month_count = count($months);

        if (!$month_count || (1 == $month_count && 0 == $months[0]->month)) {
            return;
        }

        $m = isset($_GET['m']) ? (int) $_GET['m'] : 0;
        ?>
		<label for="filter-by-date" class="screen-reader-text"><?php _e('Filter by date');?></label>
		<select name="m" id="filter-by-date">
			<option<?php selected($m, 0);?> value="0"><?php _e('All dates');?></option>
		<?php
foreach ($months as $arc_row) {
            if (0 == $arc_row->year) {
                continue;
            }

            $month = zeroise($arc_row->month, 2);
            $year = $arc_row->year;

            printf(
                "<option %s value='%s'>%s</option>\n",
                selected($m, $year . $month, false),
                esc_attr($arc_row->year . $month),
                /* translators: 1: month name, 2: 4-digit year */
                sprintf(__('%1$s %2$d'), $wp_locale->get_month($month), $year)
            );
        }
        ?>
		</select>
		<?php
}

    /**
     * Display a view switcher
     *
     * @since 3.1.0
     *
     * @param string $current_mode
     */
    protected function view_switcher($current_mode)
    {
        ?>
		<input type="hidden" name="mode" value="<?php echo esc_attr($current_mode); ?>" />
		<div class="view-switch">
		<?php
foreach ($this->modes as $mode => $title) {
            $classes = array('view-' . $mode);
            if ($current_mode === $mode) {
                $classes[] = 'current';
            }
            printf(
                "<a href='%s' class='%s' id='view-switch-$mode'><span class='screen-reader-text'>%s</span></a>\n",
                esc_url(add_query_arg('mode', $mode)),
                implode(' ', $classes),
                $title
            );
        }
        ?>
		</div>
		<?php
}

    /**
     * Display a comment count bubble
     *
     * @since 3.1.0
     *
     * @param int $post_id          The post ID.
     * @param int $pending_comments Number of pending comments.
     */
    protected function comments_bubble($post_id, $pending_comments)
    {
        $approved_comments = get_comments_number();

        $approved_comments_number = number_format_i18n($approved_comments);
        $pending_comments_number = number_format_i18n($pending_comments);

        $approved_only_phrase = sprintf(_n('%s comment', '%s comments', $approved_comments), $approved_comments_number);
        $approved_phrase = sprintf(_n('%s approved comment', '%s approved comments', $approved_comments), $approved_comments_number);
        $pending_phrase = sprintf(_n('%s pending comment', '%s pending comments', $pending_comments), $pending_comments_number);

        // No comments at all.
        if (!$approved_comments && !$pending_comments) {
            printf(
                '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>',
                __('No comments')
            );
            // Approved comments have different display depending on some conditions.
        } elseif ($approved_comments) {
            printf(
                '<a href="%s" class="post-com-count post-com-count-approved"><span class="comment-count-approved" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
                esc_url(
                    add_query_arg(
                        array(
                            'p' => $post_id,
                            'comment_status' => 'approved',
                        ),
                        admin_url('edit-comments.php')
                    )
                ),
                $approved_comments_number,
                $pending_comments ? $approved_phrase : $approved_only_phrase
            );
        } else {
            printf(
                '<span class="post-com-count post-com-count-no-comments"><span class="comment-count comment-count-no-comments" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
                $approved_comments_number,
                $pending_comments ? __('No approved comments') : __('No comments')
            );
        }

        if ($pending_comments) {
            printf(
                '<a href="%s" class="post-com-count post-com-count-pending"><span class="comment-count-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
                esc_url(
                    add_query_arg(
                        array(
                            'p' => $post_id,
                            'comment_status' => 'moderated',
                        ),
                        admin_url('edit-comments.php')
                    )
                ),
                $pending_comments_number,
                $pending_phrase
            );
        } else {
            printf(
                '<span class="post-com-count post-com-count-pending post-com-count-no-pending"><span class="comment-count comment-count-no-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
                $pending_comments_number,
                $approved_comments ? __('No pending comments') : __('No comments')
            );
        }
    }

    /**
     * Get the current page number
     *
     * @since 3.1.0
     *
     * @return int
     */
    public function get_pagenum()
    {
        $pagenum = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 0;

        if (isset($this->_pagination_args['total_pages']) && $pagenum > $this->_pagination_args['total_pages']) {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max(1, $pagenum);
    }

    /**
     * Get number of items to display on a single page
     *
     * @since 3.1.0
     *
     * @param string $option
     * @param int    $default
     * @return int
     */
    protected function get_items_per_page($option, $default = 20)
    {
        $per_page = (int) get_user_option($option);
        if (empty($per_page) || $per_page < 1) {
            $per_page = $default;
        }

        /**
         * Filters the number of items to be displayed on each page of the list table.
         *
         * The dynamic hook name, $option, refers to the `per_page` option depending
         * on the type of list table in use. Possible values include: 'edit_comments_per_page',
         * 'sites_network_per_page', 'site_themes_network_per_page', 'themes_network_per_page',
         * 'users_network_per_page', 'edit_post_per_page', 'edit_page_per_page',
         * 'edit_{$post_type}_per_page', etc.
         *
         * @since 2.9.0
         *
         * @param int $per_page Number of items to be displayed. Default 20.
         */
        return (int) apply_filters("{$option}", $per_page);
    }

    /**
     * Display the pagination.
     *
     * @since 3.1.0
     *
     * @param string $which
     */
    protected function pagination($which)
    {
        if (empty($this->_pagination_args)) {
            return;
        }
        $total_items = $this->_pagination_args['total_items'];
        $total_pages = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;
        if (isset($this->_pagination_args['infinite_scroll'])) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }
    
        if ('top' === $which && $total_pages > 1) {
            $this->screen->render_screen_reader_content('heading_pagination');
        }
    
        $output = '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items), number_format_i18n($total_items)) . '</span>';
    
        $current = $this->get_pagenum();
        $removable_query_args = wp_removable_query_args();
    
        $current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $current_url = remove_query_arg($removable_query_args, $current_url);

        $current_url = remove_query_arg(['cat-filter', 'export_data_date_From', 'export_data_date_to'], $current_url);

        $cat_filter = isset($_REQUEST['cat-filter']) ? $_REQUEST['cat-filter'] : '';
        if ($cat_filter) {
            $current_url = add_query_arg('cat-filter', $cat_filter, $current_url);
        }
        
        $filter_from_date = isset($_REQUEST['export_data_date_From']) ? wp_unslash(trim($_REQUEST['export_data_date_From'])) : '';
        $filter_to_date = isset($_REQUEST['export_data_date_to']) ? wp_unslash(trim($_REQUEST['export_data_date_to'])) : '';
        
        if ($filter_from_date) {
            $current_url = add_query_arg('export_data_date_From', $filter_from_date, $current_url);
        }
        if ($filter_to_date) {
            $current_url = add_query_arg('export_data_date_to', $filter_to_date, $current_url);
        }
    
        $page_links = array();
    
        $total_pages_before = '<span class="paging-input">';
        $total_pages_after = '</span></span>';
    
        $disable_first = $disable_last = $disable_prev = $disable_next = false;
    
        if ($current == 1) {
            $disable_first = true;
            $disable_prev = true;
        }
        if ($current == 2) {
            $disable_first = true;
        }
        if ($current == $total_pages) {
            $disable_last = true;
            $disable_next = true;
        }
        if ($current == $total_pages - 1) {
            $disable_last = true;
        }
    
        if ($disable_first) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='first-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(remove_query_arg('paged', $current_url)),
                __('First page'),
                '&laquo;'
            );
        }
    
        if ($disable_prev) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', max(1, $current - 1), $current_url)),
                __('Previous page'),
                '&lsaquo;'
            );
        }
    
        if ('bottom' === $which) {
            $html_current_page = $current;
            $total_pages_before = '<span class="screen-reader-text">' . __('Current Page') . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
        } else {
            $html_current_page = sprintf(
                "%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
                '<label for="current-page-selector" class="screen-reader-text">' . __('Current Page') . '</label>',
                $current,
                strlen($total_pages)
            );
        }
        $html_total_pages = sprintf("<span class='total-pages'>%s</span>", number_format_i18n($total_pages));
        $page_links[] = $total_pages_before . sprintf(_x('%1$s of %2$s', 'paging'), $html_current_page, $html_total_pages) . $total_pages_after;
    
        if ($disable_next) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='next-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', min($total_pages, $current + 1), $current_url)),
                __('Next page'),
                '&rsaquo;'
            );
        }
    
        if ($disable_last) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', $total_pages, $current_url)),
                __('Last page'),
                '&raquo;'
            );
        }
    
        $pagination_links_class = 'pagination-links';
        if (!empty($infinite_scroll)) {
            $pagination_links_class .= ' hide-if-js';
        }
        $output .= "\n<span class='$pagination_links_class'>" . join("\n", $page_links) . '</span>';
    
        if ($total_pages) {
            $page_class = $total_pages < 2 ? ' one-page' : '';
        } else {
            $page_class = ' no-pages';
        }
        $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";
    
        echo $this->_pagination;
    }
    

    /**
     * Get a list of columns. The format is:
     * 'internal-name' => 'Title'
     *
     * @since 3.1.0
     * @abstract
     *
     * @return array
     */
    public function get_columns()
    {
        die('function WP_List_Table::get_columns() must be over-ridden in a sub-class.');
    }

    /**
     * Get a list of sortable columns. The format is:
     * 'internal-name' => 'orderby'
     * or
     * 'internal-name' => array( 'orderby', true )
     *
     * The second format will make the initial sorting order be descending
     *
     * @since 3.1.0
     *
     * @return array
     */
    protected function get_sortable_columns()
    {
        return array();
    }

    /**
     * Gets the name of the default primary column.
     *
     * @since 4.3.0
     *
     * @return string Name of the default primary column, in this case, an empty string.
     */
    protected function get_default_primary_column_name()
    {
        $columns = $this->get_columns();
        $column = '';

        if (empty($columns)) {
            return $column;
        }

        // We need a primary defined so responsive views show something,
        // so let's fall back to the first non-checkbox column.
        foreach ($columns as $col => $column_name) {
            if ('cb' === $col) {
                continue;
            }

            $column = $col;
            break;
        }

        return $column;
    }

    /**
     * Public wrapper for WP_List_Table::get_default_primary_column_name().
     *
     * @since 4.4.0
     *
     * @return string Name of the default primary column.
     */
    public function get_primary_column()
    {
        return $this->get_primary_column_name();
    }

    /**
     * Gets the name of the primary column.
     *
     * @since 4.3.0
     *
     * @return string The name of the primary column.
     */
    protected function get_primary_column_name()
    {
        $columns = get_column_headers($this->screen);
        $default = $this->get_default_primary_column_name();

        // If the primary column doesn't exist fall back to the
        // first non-checkbox column.
        if (!isset($columns[$default])) {
            $default = CPFM_WP_List_Table::get_default_primary_column_name();
        }

        /**
         * Filters the name of the primary column for the current list table.
         *
         * @since 4.3.0
         *
         * @param string $default Column name default for the specific list table, e.g. 'name'.
         * @param string $context Screen ID for specific list table, e.g. 'plugins'.
         */
        $column = apply_filters('list_table_primary_column', $default, $this->screen->id);

        if (empty($column) || !isset($columns[$column])) {
            $column = $default;
        }

        return $column;
    }

    /**
     * Get a list of all, hidden and sortable columns, with filter applied
     *
     * @since 3.1.0
     *
     * @return array
     */
    protected function get_column_info()
    {
        // $_column_headers is already set / cached
        if (isset($this->_column_headers) && is_array($this->_column_headers)) {
            // Back-compat for list tables that have been manually setting $_column_headers for horse reasons.
            // In 4.3, we added a fourth argument for primary column.
            $column_headers = array(array(), array(), array(), $this->get_primary_column_name());
            foreach ($this->_column_headers as $key => $value) {
                $column_headers[$key] = $value;
            }

            return $column_headers;
        }

        $columns = get_column_headers($this->screen);
        $hidden = get_hidden_columns($this->screen);

        $sortable_columns = $this->get_sortable_columns();
        /**
         * Filters the list table sortable columns for a specific screen.
         *
         * The dynamic portion of the hook name, `$this->screen->id`, refers
         * to the ID of the current screen, usually a string.
         *
         * @since 3.5.0
         *
         * @param array $sortable_columns An array of sortable columns.
         */
        $_sortable = apply_filters("manage_{$this->screen->id}_sortable_columns", $sortable_columns);

        $sortable = array();
        foreach ($_sortable as $id => $data) {
            if (empty($data)) {
                continue;
            }

            $data = (array) $data;
            if (!isset($data[1])) {
                $data[1] = false;
            }

            $sortable[$id] = $data;
        }

        $primary = $this->get_primary_column_name();
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

        return $this->_column_headers;
    }

    /**
     * Return number of visible columns
     *
     * @since 3.1.0
     *
     * @return int
     */
    public function get_column_count()
    {
        list($columns, $hidden) = $this->get_column_info();
        $hidden = array_intersect(array_keys($columns), array_filter($hidden));
        return count($columns) - count($hidden);
    }

    /**
     * Print column headers, accounting for hidden and sortable columns.
     *
     * @since 3.1.0
     *
     * @staticvar int $cb_counter
     *
     * @param bool $with_id Whether to set the id attribute or not
     */
    public function print_column_headers($with_id = true)
    {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();

        $current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $current_url = remove_query_arg('paged', $current_url);

        if (isset($_GET['orderby'])) {
            $current_orderby = $_GET['orderby'];
        } else {
            $current_orderby = '';
        }

        if (isset($_GET['order']) && 'desc' === $_GET['order']) {
            $current_order = 'desc';
        } else {
            $current_order = 'asc';
        }

        if (!empty($columns['cb'])) {
            static $cb_counter = 1;
            $columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __('Select All') . '</label>'
                . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
            $cb_counter++;
        }

        foreach ($columns as $column_key => $column_display_name) {
            $class = array('manage-column', "column-$column_key");

            if (in_array($column_key, $hidden)) {
                $class[] = 'hidden';
            }

            if ('cb' === $column_key) {
                $class[] = 'check-column';
            } elseif (in_array($column_key, array('posts', 'comments', 'links'))) {
                $class[] = 'num';
            }

            if ($column_key === $primary) {
                $class[] = 'column-primary';
            }

            if (isset($sortable[$column_key])) {
                list($orderby, $desc_first) = $sortable[$column_key];

                if ($current_orderby === $orderby) {
                    $order = 'asc' === $current_order ? 'desc' : 'asc';
                    $class[] = 'sorted';
                    $class[] = $current_order;
                } else {
                    $order = $desc_first ? 'desc' : 'asc';
                    $class[] = 'sortable';
                    $class[] = $desc_first ? 'asc' : 'desc';
                }

                $column_display_name = '<a href="' . esc_url(add_query_arg(compact('orderby', 'order'), $current_url)) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
            }

            $tag = ('cb' === $column_key) ? 'td' : 'th';
            $scope = ('th' === $tag) ? 'scope="col"' : '';
            $id = $with_id ? "id='$column_key'" : '';

            if (!empty($class)) {
                $class = "class='" . join(' ', $class) . "'";
            }

            echo "<$tag $scope $id $class>$column_display_name</$tag>";
        }
    }

    /**
     * Display the table
     *
     * @since 3.1.0
     */
    public function display()
    {
        $singular = $this->_args['singular'];

        $this->display_tablenav('top');

        $this->screen->render_screen_reader_content('heading_list');
        ?>
<table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
	<thead>
	<tr>
		<?php $this->print_column_headers();?>
	</tr>
	</thead>

	<tbody id="the-list"
		<?php
if ($singular) {
            echo " data-wp-lists='list:$singular'";
        }
        ?>
		>
		<?php $this->display_rows_or_placeholder();?>
	</tbody>

	<tfoot>
	<tr>
		<?php $this->print_column_headers(false);?>
	</tr>
	</tfoot>

</table>
		<?php
$this->display_tablenav('bottom');
    }

    /**
     * Get a list of CSS classes for the WP_List_Table table tag.
     *
     * @since 3.1.0
     *
     * @return array List of CSS classes for the table tag.
     */
    protected function get_table_classes()
    {
        return array('widefat', 'fixed', 'striped', $this->_args['plural']);
    }

    /**
     * Generate the table navigation above or below the table
     *
     * @since 3.1.0
     * @param string $which
     */
    protected function display_tablenav($which)
    {
        if ('top' === $which) {
            wp_nonce_field('bulk-' . $this->_args['plural']);
        }
        ?>
	<div class="tablenav <?php echo esc_attr($which); ?>">

		<?php if ($this->has_items()): ?>
		<div class="alignleft actions bulkactions">
			<?php $this->bulk_actions($which);?>
		</div>
			<?php
endif;
        $this->extra_tablenav($which);
        $this->pagination($which);
        ?>

		<br class="clear" />
	</div>
		<?php
}

    /**
     * Extra controls to be displayed between bulk actions and pagination
     *
     * @since 3.1.0
     *
     * @param string $which
     */
    protected function extra_tablenav($which)
    {}

    /**
     * Generate the tbody element for the list table.
     *
     * @since 3.1.0
     */
    public function display_rows_or_placeholder()
    {
        if ($this->has_items()) {
            $this->display_rows();
        } else {
            echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">';
            $this->no_items();
            echo '</td></tr>';
        }
    }

    /**
     * Generate the table rows
     *
     * @since 3.1.0
     */
    public function display_rows()
    {
        foreach ($this->items as $item) {
            $this->single_row($item);
        }
    }

    /**
     * Generates content for a single row of the table
     *
     * @since 3.1.0
     *
     * @param object $item The current item
     */
    public function single_row($item)
    {
        echo '<tr>';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     * @param object $item
     * @param string $column_name
     */
    protected function column_default($item, $column_name)
    {}

    /**
     * @param object $item
     */
    protected function column_cb($item)
    {}

    /**
     * Generates the columns for a single row of the table
     *
     * @since 3.1.0
     *
     * @param object $item The current item
     */
    protected function single_row_columns($item)
    {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();

        foreach ($columns as $column_name => $column_display_name) {
            $classes = "$column_name column-$column_name";
            if ($primary === $column_name) {
                $classes .= ' has-row-actions column-primary';
            }

            if (in_array($column_name, $hidden)) {
                $classes .= ' hidden';
            }

            // Comments column uses HTML in the display name with screen reader text.
            // Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
            $data = 'data-colname="' . wp_strip_all_tags($column_display_name) . '"';

            $attributes = "class='$classes' $data";

            if ('cb' === $column_name) {
                echo '<th scope="row" class="check-column">';
                echo $this->column_cb($item);
                echo '</th>';
            } elseif (method_exists($this, '_column_' . $column_name)) {
                echo call_user_func(
                    array($this, '_column_' . $column_name),
                    $item,
                    $classes,
                    $data,
                    $primary
                );
            } elseif (method_exists($this, 'column_' . $column_name)) {
                echo "<td $attributes>";
                echo call_user_func(array($this, 'column_' . $column_name), $item);
                echo $this->handle_row_actions($item, $column_name, $primary);
                echo '</td>';
            } else {
                echo "<td $attributes>";
                echo $this->column_default($item, $column_name);
                echo $this->handle_row_actions($item, $column_name, $primary);
                echo '</td>';
            }
        }
    }

    /**
     * Generates and display row actions links for the list table.
     *
     * @since 4.3.0
     *
     * @param object $item        The item being acted upon.
     * @param string $column_name Current column name.
     * @param string $primary     Primary column name.
     * @return string The row actions HTML, or an empty string if the current column is the primary column.
     */
    protected function handle_row_actions($item, $column_name, $primary)
    {
        return $column_name === $primary ? '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details') . '</span></button>' : '';
    }

    /**
     * Handle an incoming ajax request (called from admin-ajax.php)
     *
     * @since 3.1.0
     */
    public function ajax_response()
    {
        $this->prepare_items();

        ob_start();
        if (!empty($_REQUEST['no_placeholder'])) {
            $this->display_rows();
        } else {
            $this->display_rows_or_placeholder();
        }

        $rows = ob_get_clean();

        $response = array('rows' => $rows);

        if (isset($this->_pagination_args['total_items'])) {
            $response['total_items_i18n'] = sprintf(
                _n('%s item', '%s items', $this->_pagination_args['total_items']),
                number_format_i18n($this->_pagination_args['total_items'])
            );
        }
        if (isset($this->_pagination_args['total_pages'])) {
            $response['total_pages'] = $this->_pagination_args['total_pages'];
            $response['total_pages_i18n'] = number_format_i18n($this->_pagination_args['total_pages']);
        }

        die(wp_json_encode($response));
    }

    /**
     * Send required variables to JavaScript land
     */
    public function _js_vars()
    {
        $args = array(
            'class' => get_class($this),
            'screen' => array(
                'id' => $this->screen->id,
                'base' => $this->screen->base,
            ),
        );

        printf("<script type='text/javascript'>list_args = %s;</script>\n", wp_json_encode($args));
    }
}
