<?php
/**
 * Plugin Name: Cool Plugins Feedback Manager
 * Version: 1.3.1
 * Author: Cool Plugins Team
 * Description: This plugin manage all feedback data received from users who deactivate 'Cool Plugins'.
 */

 define('CPFM_FILE', __FILE__);
 define("CPFM_DIR", plugin_dir_path(CPFM_FILE));

 class Cool_Plugins_Feedback_Manager{
        private $cpfm_current_view;
        function __construct(){
            require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
            require_once CPFM_DIR . 'cpfm-feedback-db.php';

            add_action('admin_init', array($this, 'cpfm_init') );
            add_action('admin_menu', array($this, 'cpfm_add_menu' ) );
            add_filter('set-screen-option', array( $this, 'cpfm_save_screen_options'), 15, 3);
            add_action( 'rest_api_init', array( $this, 'cpfm_register_feedback_api') );
            add_action('wp_ajax_cpfm_get_extra_data', array($this,'cpfm_get_extra_data'));
            add_action('admin_init', array($this, 'cpfm_download_csv') );
            add_action('rest_api_init', array($this,'cpfm_site_register_rest_routes'));

        }

        /**
        * Register REST API routes for Site Info Tracker
        */
        function cpfm_site_register_rest_routes() {

            register_rest_route('coolplugins-feedback/v1', 'site-info', array(

                'methods' => 'POST', 
                'callback' => array($this,'cpfm_site_info_request'),
                  'permission_callback' => '__return_true'
            ));
        }
        function cpfm_site_info_request(WP_REST_Request $request) {

            global $wpdb;
            $table_name     = $wpdb->prefix . 'cpfm_site_info';
            $current_date   = current_time('mysql');
            $plugin_version = sanitize_text_field($request->get_param('plugin_version'));
            $plugin_name    = sanitize_text_field($request->get_param('plugin_name'));
            $plugin_initial = sanitize_text_field($request->get_param('plugin_initial'));
            $email          = sanitize_email($request->get_param('email'));
            $extra_details  = maybe_serialize($request->get_param('extra_details'));
            $server_info    = maybe_serialize($_SERVER);
            $site_id        = sanitize_text_field($request->get_param('site_id'));
            $site_url       = sanitize_text_field($request->get_param('site_url'));

            $site_info = array(
                'site_id'           => $site_id,
                'plugin_version'    => !empty($plugin_version) ? $plugin_version : '1.0.0',
                'plugin_name'       => !empty($plugin_name) ? $plugin_name : 'Site Info Tracker',
                'plugin_initial'    => !empty($plugin_initial) ? $plugin_initial : '1.0',
                'domain'            => $site_url,
                'email'             => $email,
                'extra_details'     => $extra_details,
                'server_info'       => $server_info,
                'update_date'       => $current_date,
                'created_date'      => $current_date
            );
        
            // Check if record exists using prepared statement
            $existing_id = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM $table_name WHERE site_id = %s", $site_id)
            );
            
            if ($existing_id) {
                    // Update existing record with prepared statement
                    unset($site_info['created_date']);
                    
                    $set_clause = implode(', ', array_map(function($key) {
                        return "$key = %s";
                    }, array_keys($site_info)));
                    
                    $query = $wpdb->prepare(
                        "UPDATE $table_name SET $set_clause WHERE id = %d",
                        array_merge(array_values($site_info), [$existing_id])
                    );
                    
                    $result = $wpdb->query($query);
            } else {
                  
                    $columns = implode(', ', array_keys($site_info));
                    $placeholders = implode(', ', array_fill(0, count($site_info), '%s'));
                    
                    $query = $wpdb->prepare(
                        "INSERT INTO $table_name ($columns) VALUES ($placeholders)",
                        array_values($site_info)
                    );
                    $result = $wpdb->query($query);
            }

            if (false === $result) {

                return new WP_Error(
                    'something_went_wrong', 
                    'Something Went Wrong',
                    array(
                        'status' => 500,
                        
                    )
                );
            }

            return new WP_REST_Response(array(
                'status' => 'success',
            ), 200);
            
        }
      
        public function cpfm_download_csv() {

            $is_export = isset($_REQUEST['export_data']) && $_REQUEST['export_data'] === 'Export Data';

            if (!$is_export) {
                return; 
            }

            require_once CPFM_DIR . 'cpfm-display-table.php';

            $current_page = isset($_REQUEST['page']) ? sanitize_text_field($_REQUEST['page']) : '';
            $view = ($current_page === 'cpfm-plugin-insights') ? 'insights' : 'main';
        
            $args = [
                'view' => $view
            ];
        
            $list = new cpfm_list_table($args);
            $data = $list->cpfm_fetch_export_data($is_export);
          
            if (empty($data)) {
                wp_die('No data to export.');
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="exported-data.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($data[0]));

            foreach ($data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit; 
        }
    
        public static function cpfm_get_extra_data() {

            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_selected_value_nonce')) {
                wp_send_json_error(['message' => 'Nonce verification failed.']);
                wp_die();
            }

            $value = isset($_POST['value'])?sanitize_text_field($_POST['value']):'default'; 
            $referer = wp_get_referer();
            $view = (strpos($referer, 'cpfm-plugin-insights') !== false) ? 'insights' : 'main';
           
            require_once CPFM_DIR . 'cpfm-display-table.php';
            
            $html = cpfm_list_table::cpfm_feedback_load_extra_data($value, $_POST['item_id'],$view); 
        
            echo json_encode(['html' => $html]);
            
            wp_die();
        }
        
        function verify_email($email) {
            $client = 
            new QuickEmailVerification\Client('15f916123f1d123318522dd301f40a49020e6bb9a8e06f9954907474597a');
            $quickemailverification = $client->quickemailverification();
            $response = $quickemailverification->verify($email);
        
            return $response->body; 
        }

        function send_deactivation_feedback_to_fluent_crm($user_email,$user_feedback,$user_domain) {

            $webhook_url = 'https://my.coolplugins.net/?fluentcrm=1&route=contact&hash=e45c3373-30c3-4809-bf03-13b98a61926b';
        
            $data = array(
                'email'      => $user_email,   
                'first_name' => $user_email,              
                // 'first_name' => 'feedback',              
                // 'last_name'  => 'manager',               
                'feedback'   => $user_feedback,
                'tag'        => 'Deactivation Feedback', 
                'user_domain' => $user_domain, 
            );
        
            $response = wp_remote_post($webhook_url, array(
                'method'    => 'POST',
                'headers'   => array(
                    'Content-Type' => 'application/json',
                ),
                'body'      => json_encode($data),
                'sslverify' => true, // Use true in production and false in development
            ));

            if (is_wp_error($response)) {
                echo 'Error: ' . $response->get_error_message();
                return;
            }
        
            $response_body = wp_remote_retrieve_body($response);
        }        
            
        function add_product_to_ticket($ticket_id,$product_name) {

            $product_id;  

            switch(strtolower($product_name)) {
                case "conditional-fields-for-elementor-form":
                    $product_id = 29;
                    break;
                case "cool timeline":
                    $product_id = 16;
                    break;
                case "events shortcodes":
                    $product_id = 19;
                    break;
                case "timeline widget addon for elementor":
                    $product_id = 17;
                    break;
                case "loco automatic translate addon":
                    $product_id = 22;
                    break;
                case "cryptocurrency widgets for elementor":
                    $product_id = 24;
                    break;
                default:
                    break;
            }

        
            $api_url = "https://my.coolplugins.net/wp-json/fluent-support/v2/tickets/{$ticket_id}/property"; 

            // $api_url = "https://primesite.com/wp-json/fluent-support/v2/tickets/{$ticket_id}/property"; 
        
            $username = 'admin';
            // my.coolplugins site password
            $application_password = 'Dt3i VprH pQaB fHY4 At1V tSLo';

            // primesite site password
            // $application_password = '40cN dFeU 2Yt8 b41o DhLB LYvt';
        
            $auth = base64_encode("$username:$application_password");
        
            // Request parameters with JSON encoding
            $params = json_encode(array(
                'prop_name'  => 'product_id',
                'prop_value' => $product_id,
            ));
        
            // Send the PUT request with 'body' parameter included
            $response = wp_remote_request($api_url, array(
                'method'    => 'PUT',
                'headers'   => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type'  => 'application/json',
                ),
                'body'      => $params,
                'sslverify' => true,
                'timeout'   => 15,
            ));
        
            if (is_wp_error($response)) {
                error_log('API Request Error: ' . $response->get_error_message());
            } else {
                $response_body = json_decode(wp_remote_retrieve_body($response), true);
                
                return $response_body;
            }
        }
        

        function create_new_ticket($title, $content, $email,$domain,$client_priority = 'normal', $custom_data = []){


            $webhook_url = 'https://my.coolplugins.net/wp-json/fluent-support/v2/public/incoming_webhook/b29405dd-c467-4574-87f2-d6109e0259d2';

            $data = array(
                'sender[first_name]' => $email,
                'title'              => $title,
                'content'            => $content,
                'sender[email]'      => $email,
                'custom_fields[cf_website]'      => $domain,
            );

            $is_mail_valid = $this->verify_email($email);
            if($is_mail_valid['result'] === 'valid' && $content !== "N/A"){
                $response = wp_remote_post($webhook_url, array(
                    'method'    => 'POST',
                    'headers'   => array(
                    ),
                    'body'      => $data, 
                    'sslverify' => true, 
                ));
    
                if (is_wp_error($response)) {
                    return 'Error: ' . $response->get_error_message();
                }

                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
    
                $this->send_deactivation_feedback_to_fluent_crm($email,$content,$domain);
                if($data['type'] === "new_ticket" && isset($data['ticket_id'])){
                    return $data['ticket_id'];
                }
                
                return 'Error: Failed to create ticket. Response: ' . $body;
            }else{
                return "";
            }

        }

        function cpfm_register_feedback_api(){
            register_rest_route( 'coolplugins-feedback/v1', 'feedback', array(
                'methods' => 'POST',
                'callback' => array($this, 'get_custom_users_data' ),
                 'permission_callback' => '__return_true'
            ));
        }

        function get_custom_users_data(){
            GLOBAL $wpdb;
            $response = false;

            if( isset($_REQUEST['plugin_version']) && isset($_REQUEST['domain']) &&
             isset($_REQUEST['reason']) ){
                 
                 if( isset($_REQUEST['review']) ){
                        $review = esc_sql( sanitize_text_field(trim($_REQUEST['review'])) );
                 }else{
                     $review = '';
                 }

            if($_REQUEST['reason'] === 'other'){
                if(strlen($review) >= 20){
                    $ticket_id = $this->create_new_ticket("Feedback from plugin deactivation : ".$_REQUEST['plugin_name']."",$review,$_REQUEST['email'],$_REQUEST['domain']);
                    
                    if(!empty($ticket_id) && isset($ticket_id)){
                        $this->add_product_to_ticket($ticket_id,$_REQUEST['plugin_name']);
                    } 
                }
            }   



           

                $DB = new cpfm_database();
                $response = $DB->cpfm_insert_feedback( array(array(
                   'server_info' =>isset($_REQUEST['server_info']) ? sanitize_text_field($_REQUEST['server_info']) : '',
                    'extra_details' =>isset($_REQUEST['extra_details']) ? sanitize_text_field($_REQUEST['extra_details']) : '',
                    'plugin_version'  => isset($_REQUEST['plugin_version']) ? sanitize_text_field($_REQUEST['plugin_version']) : '',
                    'plugin_name'     => isset($_REQUEST['plugin_name']) ? sanitize_text_field($_REQUEST['plugin_name']) : '',
                    'plugin_initial'  => isset($_REQUEST['plugin_initial']) ? sanitize_text_field($_REQUEST['plugin_initial']) : '',
                    'reason'         => isset($_REQUEST['reason']) ? sanitize_text_field($_REQUEST['reason']) : '',
                    'review'         => isset($review) ? sanitize_textarea_field($review) : '',
                    'domain'         => isset($_REQUEST['domain']) ? esc_url($_REQUEST['domain']) : '',
                    'email'          => (!empty($_REQUEST['email']) && is_email($_REQUEST['email'])) ? sanitize_email($_REQUEST['email']) : 'N/A',
                )));              
            }
            
            
            die(json_encode($response));
        }

        function cpfm_add_menu(){

            $hook = add_menu_page('Cool Plugins Feedback Data', 'Cool Plugins Feedback Manager', 'manage_options', 'cpfm', array($this,'CPFM_feedback_page'), '', 7);
            add_action( "load-".$hook, array( $this, 'cpfm_add_options' ) ); 
           
            $submenu_hook = add_submenu_page(
                'cpfm',                                
                'Plugin Insights',                       
                'Plugin Insights',                   
                'manage_options',                    
                'cpfm-plugin-insights',                      
                array($this, 'cpfm_all_feedbacks_page') 
            );

            add_action("load-".$submenu_hook, array($this, 'cpfm_add_submenu_options'));
         
        }
        public function cpfm_add_submenu_options() {

            $option = 'per_page';
            $this->cpfm_current_view = 'insights';
            
            $args = array(
                'label' => 'Result per page',
                'default' => 10,
                'option' => 'results_per_page'
            );
          
            require_once CPFM_DIR . 'cpfm-display-table.php';
            add_screen_option( $option, $args );
           
            new cpfm_list_table;
           
        }
        
        function cpfm_add_options(){

            $option = 'per_page';
            $this->cpfm_current_view = 'main';
            $args = array(
                'label' => 'Result per page',
                'default' => 10,
                'option' => 'results_per_page'
            );
             require_once CPFM_DIR . 'cpfm-display-table.php';
            add_screen_option( $option, $args );
         
            new cpfm_list_table;

        }

		function cpfm_save_screen_options($status, $option, $value) {
			if( $option == "results_per_page" ){
				return $value;
			}
			return $status;
		}
		
        function cpfm_feedback_page(){

            require_once CPFM_DIR . 'cpfm-display-table.php';
          
            $args = [
                'view' => isset($this->cpfm_current_view) ? $this->cpfm_current_view : 'main'
            ];
        
            $list = new cpfm_list_table($args);
            $list->prepare_items();
            $list->display();
           
        }

        function cpfm_all_feedbacks_page(){

            require_once CPFM_DIR . 'cpfm-display-table.php';
            
            $args = [
                'view' => isset($this->cpfm_current_view) ? $this->cpfm_current_view : 'insights'
            ];
        
            $list = new cpfm_list_table($args);
            $list->prepare_items();
            $list->display();
        
        }

        function cpfm_init(){

            $database = new cpfm_database();
            $database->create_table();
            $database->create_table_site_info();

        }

 }
 new Cool_Plugins_Feedback_Manager();
