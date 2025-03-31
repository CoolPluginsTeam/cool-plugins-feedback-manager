<?php
/**
 * Plugin Name: Cool Plugins Feedback Manager
 * Version: 1.3.0
 * Author: Cool Plugins Team
 * Description: This plugin manage all feedback data received from users who deactivate 'Cool Plugins'.
 */

 define('CPFM_FILE', __FILE__);
 define("CPFM_DIR", plugin_dir_path(CPFM_FILE));

 class Cool_Plugins_Feedback_Manager{

        function __construct(){
            require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
            require_once CPFM_DIR . 'cpfm-feedback-db.php';

            add_action('admin_init', array($this, 'cpfm_init') );
            add_action('admin_menu', array($this, 'cpfm_add_menu' ) );
            add_filter('set-screen-option', array( $this, 'cpfm_save_screen_options'), 15, 3);
            add_action( 'rest_api_init', array( $this, 'cpfm_register_feedback_api') );
            add_action('wp_ajax_get_selected_value', array($this,'get_selected_value'));
            add_action( 'admin_enqueue_scripts', array($this,'enqueue_feedback_script') );
           
        }

        
        public static function get_selected_value() {
            $value = sanitize_text_field($_POST['value']); 
            require_once CPFM_DIR . 'cpfm-display-table.php';
            
            $html = cpfm_list_table::print_item($value, $_POST['item_id']); 
        
            echo json_encode(['html' => $html]);
            
            exit();
        }

        function enqueue_feedback_script() {
            wp_enqueue_script( 'feedback-script', plugin_dir_url(__FILE__) . 'feedback/js/admin-feedback.js', array('jquery'), '1.0.0', true );
            
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
            register_rest_route( 'coolplugins-feedback/v1', 'feedbacktest', array(
                'methods' => 'POST',
                'callback' => array($this, 'get_custom_users_data' )
            ));
        }

        function get_user_info() {
            global $wpdb;
        
            $data = [
            'server_software'        => sanitize_text_field($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'),
            'mysql_version'          => sanitize_text_field($wpdb->get_var("SELECT VERSION()")),
            'php_version'            => sanitize_text_field(phpversion()),
            'wp_version'             => sanitize_text_field(get_bloginfo('version')),
            'wp_debug'               => sanitize_text_field(defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled'),
            'wp_memory_limit'        => sanitize_text_field(ini_get('memory_limit')),
            'wp_max_upload_size'     => sanitize_text_field(ini_get('upload_max_filesize')),
            'wp_permalink_structure' => sanitize_text_field(get_option('permalink_structure', 'Default')),
            'wp_multisite'           => sanitize_text_field(is_multisite() ? 'Enabled' : 'Disabled'),
            'wp_language'            => sanitize_text_field(get_option('WPLANG', get_locale()) ?: get_locale()),
            'wp_prefix'              => sanitize_key($wpdb->prefix), // Sanitizing database prefix
            'wp_theme'               => [
                'name'      => sanitize_text_field(wp_get_theme()->get('Name')),
                'version'   => sanitize_text_field(wp_get_theme()->get('Version')),
                'theme_uri' => esc_url(wp_get_theme()->get('ThemeURI'))
            ],
            ];
        
            if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $data['active_plugins'] = array_map(function ($plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . sanitize_text_field($plugin));
            return [
                'name'       => sanitize_text_field($plugin_data['Name']),
                'version'    => sanitize_text_field($plugin_data['Version']),
                'plugin_uri' => esc_url($plugin_data['PluginURI'])
            ];
            }, get_option('active_plugins', []));
        
            return json_encode($data); 
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
                    'extra_details'   => $this->get_user_info(),
                    'plugin_version'  => isset($_REQUEST['plugin_version']) ? sanitize_text_field($_REQUEST['plugin_version']) : '',
                    'plugin_name'     => isset($_REQUEST['plugin_name']) ? sanitize_text_field($_REQUEST['plugin_name']) : '',
                    'reason'         => isset($_REQUEST['reason']) ? sanitize_text_field($_REQUEST['reason']) : '',
                    'review'         => isset($review) ? sanitize_textarea_field($review) : '',
                    'domain'         => isset($_REQUEST['domain']) ? esc_url($_REQUEST['domain']) : '',
                    'date'           => date('Y-m-d'),
                    'email'          => (!empty($_REQUEST['email']) && is_email($_REQUEST['email'])) ? sanitize_email($_REQUEST['email']) : 'N/A',
                )));              
            }
            
            die(json_encode($response));
        }

        function cpfm_add_menu(){
            $hook = add_menu_page('Cool Plugins Feedback Data', 'Cool Plugins Feedback Manager', 'manage_options', 'cpfm', array($this,'CPFM_feedback_page'), '', 7);
            add_action( "load-".$hook, array( $this, 'cpfm_add_options' ) ); 
        }

        function cpfm_add_options(){

            $option = 'per_page';
     
            $args = array(
                'label' => 'Result per page',
                'default' => 10,
                'option' => 'results_per_page'
            );
             require_once CPFM_DIR . 'cpfm-display-table.php';
            add_screen_option( $option, $args );
            // create columns field for screen options
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
            $list = new cpfm_list_table();
            $list->prepare_items();
            $list->display();
            $list->cpfm_default_tables($this,$id="");
        }

        function cpfm_init(){

            $database = new cpfm_database();
            $database->create_table();

        }

 }
 new Cool_Plugins_Feedback_Manager();
