<?php
/**
 * This file is responsible for all database realted functionality.
 */
class cpfm_database {

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.0
	 */
	public $table_name;
	public $site_table_name;
	public $primary_key;
	
	public $version;

	public function __construct()
	{

		global $wpdb;

		$this->table_name      = $wpdb->base_prefix . 'cpfm_feedbacks';
		$this->site_table_name = $wpdb->base_prefix . 'cpfm_site_info';
		$this->primary_key = 'id';
		$this->version = '1.0';

	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function get_columns()
	{
	
		return array(
			'id' => '%d',
			'plugin_version' => '%s',
			'plugin_name' => '%s',
			'review' => '%s',
			'domain' =>'%s',
            'email' => '%f',
            'reason' => '%s',
		);
		
	}

	/*
	|-----------------------------------------------------------------------
	|	Call this function to insert/update data for single or multiple coin
	|-----------------------------------------------------------------------
	*/
	function cpfm_insert_feedback($plugin_data,  $update = false, $primary_key = null){
		if(is_array($plugin_data) && count($plugin_data)>0){		
			return $this->wp_insert_rows($plugin_data,$this->table_name);
		}
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function get_column_defaults()
	{
		return array(
			'id' =>'',
			'plugin_version' => '',
			'plugin_name' => '',
			'review' => '',
            'reason'=>'',
			'domain' => '',
            'email' => '',
			'deactivation_date' => date('Y-m-d H:i:s'),
		);
	}

	public function feedback_exists_by_id($plugin_version)
	{

		global $wpdb;
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $this->table_name WHERE plugin_version ='%s'", $plugin_version));
		if ($count == 1) {
			return true;
		} else {
			return false;
		}

	}
	/**
	 * Retrieve orders from the database
	 *
	 * @access  public
	 * @since   1.0
	 * @param   array $args
	 * @param   bool  $count  Return only the total number of results found (optional)
	 */
	public function get_feedback($args = array(), $count = false)
	{

		global $wpdb;

		$defaults = array(
			'number' => 20,
			'offset' => 0,
			'id' =>'',
			'plugin_version'=>'',
			'plugin_name'=>'',
            'review' => '',
            'reason' => '',
			'domain' => '',
			'email' => '',
		);

		$args = wp_parse_args($args, $defaults);

		if ($args['number'] < 1) {
			$args['number'] = 999999999999;
		}

		$where = '';

	// specific referrals
		if (!empty($args['id'])) {

			if (is_array($args['id'])) {
				$order_ids = implode(',', $args['id']);
			} else {
				$order_ids = intval($args['id']);
			}

			$where .= "WHERE `id` IN( {$order_ids} ) ";

		}

		if (!empty($args['pugin_id'])) {

			if (empty($where)) {
				$where .= " WHERE";
			} else {
				$where .= " AND";
			}

			if (is_array($args['pugin_id'])) {
				$where .= " `pugin_id` IN('" . implode("','", $args['pugin_id']) . "') ";
			} else {
				$where .= " `pugin_id` = '" . $args['pugin_id'] . "' ";
			}

		}


		$args['orderby'] = !array_key_exists($args['orderby'], $this->get_columns()) ? $this->primary_key : $args['orderby'];

		if ('total' === $args['orderby']) {
			$args['orderby'] = 'total+0';
		} else if ('subtotal' === $args['orderby']) {
			$args['orderby'] = 'subtotal+0';
		}

		$cache_key = (true === $count) ? md5('cpfm_plugins_count' . serialize($args)) : md5('cpfm_plugins_' . serialize($args));

		$results = wp_cache_get($cache_key, 'plugins');

		if (false === $results) {

			if (true === $count) {

				$results = absint($wpdb->get_var("SELECT COUNT({$this->primary_key}) FROM {$this->table_name} {$where};"));

			} else {

				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$this->table_name} {$where} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d, %d;",
						absint($args['offset']),
						absint($args['number'])
					)
				);

			}

			wp_cache_set($cache_key, $results, 'plugins', 3600);

		}

		return $results;

	}
	
	/**
	 *  A method for inserting multiple rows into the specified table
	 *  Updated to include the ability to Update existing rows by primary key
	 *  
	 *  Usage Example for insert: 
	 *
	 *  $insert_arrays = array();
	 *  foreach($assets as $asset) {
	 *  $time = current_time( 'mysql' );
	 *  $insert_arrays[] = array(
	 *  'type' => "multiple_row_insert",
	 *  'status' => 1,
	 *  'name'=>$asset,
	 *  'added_date' => $time,
	 *  'last_update' => $time);
	 *
	 *  }
	 *
	 *
	 *  wp_insert_rows($insert_arrays, $wpdb->tablename);
	 *
	 *  Usage Example for update:
	 *
	 *  wp_insert_rows($insert_arrays, $wpdb->tablename, true, "primary_column");
	 *
	 *
	 * @param array $row_arrays
	 * @param string $wp_table_name
	 * @param boolean $update
	 * @param string $primary_key
	 * @return false|int
	 *
	 */
function wp_insert_rows($row_arrays = array(), $wp_table_name = "", $update = false, $primary_key = null) {
	global $wpdb;
	$wp_table_name = esc_sql($wp_table_name);
	// Setup arrays for Actual Values, and Placeholders
	$values        = array();
	$place_holders = array();
	$query         = "";
	$query_columns = "";

	$floatCols=array( '' );
	$query .= "INSERT INTO `{$wp_table_name}` (";
	foreach ($row_arrays as $count => $row_array) {
		foreach ($row_array as $key => $value) {
			if ($count == 0) {
				if ($query_columns) {
					$query_columns .= ", `" . $key . "`";
				} else {
					$query_columns .= "`" . $key . "`";
				}
			}
			
			$values[] = $value;
			
			$symbol = "%s";
			if (is_numeric($value)) {
						$symbol = "%d";
				}
		
			if(in_array( $key,$floatCols)){
				$symbol = "%f";
			}
			if (isset($place_holders[$count])) {
				$place_holders[$count] .= ", '$symbol'";
			} else {
				$place_holders[$count] = "( '$symbol'";
			}
		}
		// mind closing the GAP
		$place_holders[$count] .= ")";
	}
	
	$query .= " $query_columns ) VALUES ";
	
	$query .= implode(', ', $place_holders);
	
	if ($update) {
		$update = " ON DUPLICATE KEY UPDATE `$primary_key`=VALUES( `$primary_key` ),";
		$cnt    = 0;
		foreach ($row_arrays[0] as $key => $value) {
			if ($cnt == 0) {
				$update .= "`$key`=VALUES(`$key`)";
				$cnt = 1;
			} else {
				$update .= ", `$key`=VALUES(`$key`)";
			}
		}
		$query .= $update;
	}

	$sql = $wpdb->prepare($query, $values);
	
	if ($wpdb->query($sql)) {
		return true;
	} else {
		return false;
	}
}

	/**
	 * Return the number of results found for a given query
	 *
	 * @param  array  $args
	 * @return int
	 */
	public function count($args = array())
	{
		return $this->get_feedback($args, true);
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function create_table()
	{
		global $wpdb;	
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$sql = "CREATE TABLE IF NOT EXISTS " . $this->table_name . " (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `plugin_version` varchar(20) NOT NULL,
            `plugin_name` varchar(250) NOT NULL,
	        `plugin_initial` varchar(250) NOT NULL,
			`reason` varchar(250) NOT NULL,
			`review` varchar(250) NOT NULL,
			`domain` varchar(250) NOT NULL,
			`email` varchar(250),
			`extra_details` TEXT,
			`server_info` TEXT,
			`deactivation_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`site_id` varchar(250),
			PRIMARY KEY (id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";
		 
		dbDelta($sql);

		update_option($this->table_name . '_db_version', $this->version);
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function create_table_site_info()
	{
		global $wpdb;	
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		$sql = "CREATE TABLE IF NOT EXISTS " . $this->site_table_name . " (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
			`site_id` varchar(250) NOT NULL,
            `plugin_version` varchar(20) NOT NULL,
            `plugin_name` varchar(250) NOT NULL,
	        `plugin_initial` varchar(250) NOT NULL,
			`domain` varchar(250) NOT NULL,
			`email` varchar(250),
			`extra_details` TEXT,
			`server_info` TEXT,
			`created_date` timestamp DEFAULT CURRENT_TIMESTAMP,
        	`update_date` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY site_id (site_id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";
		 
		dbDelta($sql);

		update_option($this->site_table_name . '_db_version', $this->version);
	}
	/**
	 * Drop database table
	 */
	public function drop_table(){
		global $wpdb;

		$wpdb->query("DROP TABLE IF EXISTS " . $this->table_name);

	}
}