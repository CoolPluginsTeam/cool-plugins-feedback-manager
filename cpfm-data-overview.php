<?php
/**
 * Data Overview Page
 * Displays insights data in bar graph format
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CPFM_Data_Overview {
    
    /**
     * Check if two plugin names match (handles variations)
     */
    private static function is_same_plugin($plugin_name, $filtered_name) {
        $normalized_plugin = strtolower(preg_replace('/\s+/', '', trim($plugin_name)));
        $normalized_filtered = strtolower(preg_replace('/\s+/', '', trim($filtered_name)));
        
        if (strcasecmp(trim($plugin_name), trim($filtered_name)) === 0) {
            return true;
        }
        
        if ($normalized_plugin === $normalized_filtered) {
            return true;
        }
        
        similar_text($normalized_plugin, $normalized_filtered, $similarity);
        return $similarity >= 85;
    }
    
    /**
     * Get data for top plugins based on filters
     */
    public static function get_top_plugins_data($limit, $status_filter, $time_filter, $plugin_filter = '') {
        global $wpdb;
        $tablename = $wpdb->base_prefix . 'cpfm_site_info';
        $feedback_table = $wpdb->base_prefix . 'cpfm_feedbacks';

        $top_plugins_where_conditions = array("si.plugin_name IS NOT NULL AND si.plugin_name <> ''");
        $top_plugins_query_params = array();
        
        if ($time_filter !== 'all-time') {
            $time_periods = array(
                '24hours' => '-24 hours',
                '1week' => '-1 week',
                '1month' => '-1 month',
                '1year' => '-1 year'
            );
            
            if (isset($time_periods[$time_filter])) {
                $date_from_filter = date('Y-m-d H:i:s', strtotime($time_periods[$time_filter]));
                $top_plugins_where_conditions[] = "si.update_date >= %s";
                $top_plugins_query_params[] = $date_from_filter;
            }
        }
        
        $top_plugins_where_sql = 'WHERE ' . implode(' AND ', $top_plugins_where_conditions);
        
        // Optimize: Aggregate in SQL directly
        $top_plugins_query = "SELECT si.plugin_name, 
                             COUNT(si.site_id) as total,
                             SUM(CASE 
                                WHEN fb.deactivation_date IS NULL OR si.update_date > fb.deactivation_date THEN 1 
                                ELSE 0 
                             END) as activated,
                             SUM(CASE 
                                WHEN fb.deactivation_date IS NOT NULL AND si.update_date <= fb.deactivation_date THEN 1 
                                ELSE 0 
                             END) as deactivated
                             FROM {$tablename} si 
                             LEFT JOIN {$feedback_table} fb ON si.site_id = fb.site_id 
                             {$top_plugins_where_sql}
                             GROUP BY si.plugin_name";
        
        if (!empty($top_plugins_query_params)) {
            $all_plugin_records_unfiltered = $wpdb->get_results($wpdb->prepare($top_plugins_query, ...$top_plugins_query_params), ARRAY_A);
        } else {
            $all_plugin_records_unfiltered = $wpdb->get_results($top_plugins_query, ARRAY_A);
        }
        
        $plugin_status_counts_unfiltered = array();
        
        foreach ($all_plugin_records_unfiltered as $record) {
            $plugin_name = trim($record['plugin_name']);
            if (!empty($plugin_name)) {
                if (!isset($plugin_status_counts_unfiltered[$plugin_name])) {
                    $plugin_status_counts_unfiltered[$plugin_name] = array(
                        'activated' => 0,
                        'deactivated' => 0,
                        'total' => 0
                    );
                }
                
                $plugin_status_counts_unfiltered[$plugin_name]['activated'] += (int)$record['activated'];
                $plugin_status_counts_unfiltered[$plugin_name]['deactivated'] += (int)$record['deactivated'];
                $plugin_status_counts_unfiltered[$plugin_name]['total'] += (int)$record['total'];
            }
        }
        
        uasort($plugin_status_counts_unfiltered, function($a, $b) use ($status_filter) {
            if ($status_filter === 'activated') {
                return $b['activated'] - $a['activated'];
            } elseif ($status_filter === 'deactivated') {
                return $b['deactivated'] - $a['deactivated'];
            } else {
                return $b['total'] - $a['total'];
            }
        });
        
        if (!empty($plugin_filter)) {
            foreach ($plugin_status_counts_unfiltered as $plugin_name => $counts) {
                if (self::is_same_plugin($plugin_name, $plugin_filter)) {
                    unset($plugin_status_counts_unfiltered[$plugin_name]);
                }
            }
        }
        
        if ($limit == -1) {
            return $plugin_status_counts_unfiltered;
        }
        
        return array_slice($plugin_status_counts_unfiltered, 0, $limit, true);
    }

    /**
     * Render HTML rows for top plugins table
     */
    public static function render_top_plugins_rows($top_plugins) {
        if (empty($top_plugins)) {
            return '<tr><td colspan="6" style="padding: 20px; color: #646970; text-align: center;">No plugin data available.</td></tr>';
        }
        
        $html = '';
        $rank = 1;
        foreach ($top_plugins as $plugin_name => $counts) {
            $total = $counts['total'];
            $activated = $counts['activated'];
            $deactivated = $counts['deactivated'];
            $activation_rate = $total > 0 ? round(($activated / $total) * 100, 1) : 0;
            $display_name = ucwords(strtolower($plugin_name));
            
            $html .= '<tr>
                <td style="padding: 12px; font-weight: 600; color: #646970;">' . esc_html($rank) . '</td>
                <td style="padding: 12px; font-weight: 500; color: #1d2327;">' . esc_html($display_name) . '</td>
                <td style="text-align: center; padding: 12px; color: #22c55e; font-weight: 600;">
                    <span style="display: inline-block; padding: 4px 12px; background: rgba(34, 197, 94, 0.1); border-radius: 4px;">
                        ' . esc_html($activated) . '
                    </span>
                </td>
                <td style="text-align: center; padding: 12px; color: #ef4444; font-weight: 600;">
                    <span style="display: inline-block; padding: 4px 12px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">
                        ' . esc_html($deactivated) . '
                    </span>
                </td>
                <td style="text-align: center; padding: 12px; font-weight: 600; color: #1d2327;">
                    ' . esc_html($total) . '
                </td>
                <td style="text-align: center; padding: 12px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <div style="flex: 1; max-width: 100px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: ' . esc_attr($activation_rate) . '%; background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%); transition: width 0.3s ease;"></div>
                        </div>
                        <span style="font-weight: 600; color: #1d2327; min-width: 45px; text-align: left;">
                            ' . esc_html($activation_rate) . '%
                        </span>
                    </div>
                </td>
            </tr>';
            $rank++;
        }
        return $html;
    }
    
    /**
     * Get plugin data with caching and batching
     * Stores optimized data structure to reduce memory usage
     */
    private static function get_cached_plugin_data($plugin_name = 'all') {
        global $wpdb;
        $tablename = $wpdb->base_prefix . 'cpfm_site_info';
        
        $cache_key = 'cpfm_data_' . sanitize_title($plugin_name);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Increase memory limit temporarily for processing
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        
        $batch_size = 2500;
        $offset = 0;
        $all_data = array();
        
        // Select necessary columns
        $query = "SELECT server_info, plugin_version, extra_details, site_id, update_date FROM {$tablename}";
        $params = array();
        
        if (!empty($plugin_name)) {
            $query .= " WHERE plugin_name = %s";
            $params[] = trim($plugin_name);
        }
        
        $query .= " ORDER BY site_id ASC";
        
        while (true) {
            $limit_query = $query . " LIMIT %d OFFSET %d";
            $current_params = array_merge($params, array($batch_size, $offset));
            
            $batch_results = $wpdb->get_results(
                $wpdb->prepare($limit_query, ...$current_params), 
                ARRAY_A
            );
            
            if (empty($batch_results)) {
                break;
            }
            
            // Process and compact data immediately
            foreach ($batch_results as $record) {
                $server_info = maybe_unserialize($record['server_info']);
                $extra_details = maybe_unserialize($record['extra_details']);
                
                $compact_record = array(
                    'site_id' => $record['site_id'],
                    'update_date' => $record['update_date'],
                    'plugin_version' => $record['plugin_version'],
                    'wp_version' => isset($server_info['wp_version']) ? $server_info['wp_version'] : '',
                    'php_version' => isset($server_info['php_version']) ? $server_info['php_version'] : '',
                    'theme_name' => '',
                    'active_plugins_list' => array()
                );

                if (is_array($extra_details)) {
                    if (isset($extra_details['wp_theme']['name'])) {
                        $compact_record['theme_name'] = $extra_details['wp_theme']['name'];
                    }
                    
                    if (isset($extra_details['active_plugins']) && is_array($extra_details['active_plugins'])) {
                         foreach ($extra_details['active_plugins'] as $p) {
                             if (is_array($p) && isset($p['name'])) {
                                 $compact_record['active_plugins_list'][] = $p['name'];
                             }
                         }
                    }
                }
                
                $all_data[] = $compact_record;
            }
            
            $count_results = count($batch_results);
            unset($batch_results); // Free memory
            
            if ($count_results < $batch_size) {
                break;
            }
            
            $offset += $batch_size;
            
            // Safety break
            if ($offset > 200000) break;
        }
        
        set_transient($cache_key, $all_data, 30 * MINUTE_IN_SECONDS);
        
        return $all_data;
    }

    public static function ajax_get_overview_data() {
        global $wpdb;
        
        $plugin_filter = isset($_POST['cat_filter']) ? sanitize_text_field($_POST['cat_filter']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        
        $tablename = $wpdb->base_prefix . 'cpfm_site_info';
        $feedback_table = $wpdb->base_prefix . 'cpfm_feedbacks';
        
        // 1. Stats & Date Chart Data
        $where_conditions = array();
        $where_params = array();
        
        if (!empty($plugin_filter)) {
            $where_conditions[] = "plugin_name = %s";
            $where_params[] = trim($plugin_filter);
        }
        
        if (!empty($date_from) && !empty($date_to)) {
            $where_conditions[] = "update_date BETWEEN %s AND %s";
            $where_params[] = $date_from . ' 00:00:00';
            $where_params[] = $date_to . ' 23:59:59';
        }
        
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Date counts for chart
        $date_counts_query = "SELECT DATE(update_date) as date, COUNT(*) as count 
                              FROM {$tablename} 
                              {$where_sql}
                              GROUP BY DATE(update_date) 
                              ORDER BY date DESC 
                              LIMIT 30";
        
        $date_counts = $wpdb->get_results(
            !empty($where_params) ? $wpdb->prepare($date_counts_query, $where_params) : $date_counts_query,
            ARRAY_A
        );
        
        $date_labels = array();
        $date_data = array();
        foreach (array_reverse($date_counts) as $row) {
            $date_labels[] = date('M j', strtotime($row['date']));
            $date_data[] = (int)$row['count'];
        }
        
        // Stats (Active/Deactivated)
        $status_where_conditions = array();
        $status_where_params = array();
        
        if (!empty($plugin_filter)) {
            $status_where_conditions[] = "si.plugin_name = %s";
            $status_where_params[] = trim($plugin_filter);
        }
        
        if (!empty($date_from) && !empty($date_to)) {
            $status_where_conditions[] = "si.update_date BETWEEN %s AND %s";
            $status_where_params[] = $date_from . ' 00:00:00';
            $status_where_params[] = $date_to . ' 23:59:59';
        }
        
        $status_where_sql = !empty($status_where_conditions) ? 'WHERE ' . implode(' AND ', $status_where_conditions) : '';
        
        $status_query = "SELECT si.plugin_name, si.update_date, fb.deactivation_date 
                         FROM {$tablename} si 
                         LEFT JOIN {$feedback_table} fb ON si.site_id = fb.site_id 
                         {$status_where_sql}";
        
        $site_info_records = $wpdb->get_results(
            !empty($status_where_params) ? $wpdb->prepare($status_query, $status_where_params) : $status_query,
            ARRAY_A
        );
        
        $activated_count = 0;
        $deactivated_count = 0;
        
        foreach ($site_info_records as $record) {
            if(isset($record['deactivation_date']) && !empty($record['deactivation_date'])){
                $status = (strtotime($record['update_date']) > strtotime($record['deactivation_date'])) 
                    ? 'Activated' 
                    : 'Deactivated';
            } else {
                $status = 'Activated';
            }
            
            if($status === 'Activated'){
                $activated_count++;
            } else {
                $deactivated_count++;
            }
        }
        
        // 2. Top Plugins Table (Initial Load)
        // We default to limit 5, total, all-time as per existing defaults, or use what logic requires.
        // For now, let's fetch default top 5 sorted by total
        $top_5_plugins = self::get_top_plugins_data(5, 'total', 'all-time', $plugin_filter);
        $top_plugins_html = self::render_top_plugins_rows($top_5_plugins);
        
        // 3. Insights Data
        $insights_records = self::get_cached_plugin_data($plugin_filter);
        
        // Apply date filter on cached data
        if (!empty($date_from) && !empty($date_to)) {
            $start_timestamp = strtotime($date_from . ' 00:00:00');
            $end_timestamp = strtotime($date_to . ' 23:59:59');
            
            $insights_records = array_filter($insights_records, function($record) use ($start_timestamp, $end_timestamp) {
                if (empty($record['update_date'])) return false;
                $record_timestamp = strtotime($record['update_date']);
                return ($record_timestamp >= $start_timestamp && $record_timestamp <= $end_timestamp);
            });
        }
        
        $total_sites = count($insights_records);
        
        $version_counts = array();
        $wp_version_counts = array();
        $php_version_counts = array();
        $theme_counts = array();
        $active_plugins_counts = array();
        $site_plugins_tracked = array();
        
        foreach ($insights_records as $record) {
            $site_id = isset($record['site_id']) ? $record['site_id'] : null;
            
            if (!empty($plugin_filter) && !empty($record['plugin_version'])) {
                $version = $record['plugin_version'];
                if (!isset($version_counts[$version])) $version_counts[$version] = 0;
                $version_counts[$version]++;
            }
            
            if (isset($record['wp_version']) && !empty($record['wp_version'])) {
                $wp_version = $record['wp_version'];
                if (!isset($wp_version_counts[$wp_version])) $wp_version_counts[$wp_version] = 0;
                $wp_version_counts[$wp_version]++;
            }
            
            if (isset($record['php_version']) && !empty($record['php_version'])) {
                $php_version = $record['php_version'];
                if (!isset($php_version_counts[$php_version])) $php_version_counts[$php_version] = 0;
                $php_version_counts[$php_version]++;
            }
            
            if (isset($record['theme_name']) && !empty($record['theme_name'])) {
                $theme_name = $record['theme_name'];
                $theme_key = $site_id ? $site_id . '_' . $theme_name : null;
                if ($theme_key && !isset($site_plugins_tracked[$theme_key])) {
                    if (!isset($theme_counts[$theme_name])) $theme_counts[$theme_name] = 0;
                    $theme_counts[$theme_name]++;
                    $site_plugins_tracked[$theme_key] = true;
                } elseif (!$site_id) {
                    if (!isset($theme_counts[$theme_name])) $theme_counts[$theme_name] = 0;
                    $theme_counts[$theme_name]++;
                }
            }
            
            if (isset($record['active_plugins_list']) && is_array($record['active_plugins_list'])) {
                foreach ($record['active_plugins_list'] as $p_name) {
                    if (!empty($p_name)) {
                        $plugin_key = $site_id ? $site_id . '_' . $p_name : null;
                        if ($plugin_key && !isset($site_plugins_tracked[$plugin_key])) {
                            if (!isset($active_plugins_counts[$p_name])) $active_plugins_counts[$p_name] = 0;
                            $active_plugins_counts[$p_name]++;
                            $site_plugins_tracked[$plugin_key] = true;
                        } elseif (!$site_id) {
                            if (!isset($active_plugins_counts[$p_name])) $active_plugins_counts[$p_name] = 0;
                            $active_plugins_counts[$p_name]++;
                        }
                    }
                }
            }
        }
        
        arsort($version_counts);
        arsort($wp_version_counts);
        arsort($php_version_counts);
        arsort($theme_counts);
        arsort($active_plugins_counts);
        
        // Remove self from active plugins if filtered
        if (!empty($plugin_filter)) {
            foreach ($active_plugins_counts as $p_name => $count) {
                if (self::is_same_plugin($p_name, $plugin_filter)) {
                    unset($active_plugins_counts[$p_name]);
                }
            }
        }
        
        // Prepare chart data
        $prepare_chart_data = function($data, $limit = 5) {
            $sliced = array_slice($data, 0, $limit, true);
            return [
                'labels' => array_keys($sliced),
                'data' => array_values($sliced)
            ];
        };
        
        $response = [
            'stats' => [
                'total' => $activated_count + $deactivated_count,
                'activated' => $activated_count,
                'deactivated' => $deactivated_count
            ],
            'date_chart' => [
                'labels' => $date_labels,
                'data' => $date_data
            ],
            'top_plugins_html' => $top_plugins_html,
            'insights' => [
                'total_sites' => $total_sites,
                'versions' => $prepare_chart_data($version_counts, 10), // Versions might need more space
                'wp_versions' => $prepare_chart_data($wp_version_counts, 5),
                'php_versions' => $prepare_chart_data($php_version_counts, 5),
                'themes' => $prepare_chart_data($theme_counts, 5),
                'active_plugins' => $prepare_chart_data($active_plugins_counts, 10)
            ],
            'filter_name' => !empty($plugin_filter) ? ucwords(strtolower($plugin_filter)) : 'All Plugins'
        ];
        
        wp_send_json_success($response);
    }

    /**
     * Display the data overview page with bar graphs
     */
    public static function display_overview_page() {
        global $wpdb;
        
        wp_enqueue_script('chart-js', plugin_dir_url(CPFM_FILE) . 'assets/js/chart.min.js', array('jquery'), '1.0.0', true);
        
        $tablename = $wpdb->base_prefix . 'cpfm_site_info';
        
        $cats = $wpdb->get_col("
            SELECT DISTINCT plugin_name
            FROM {$tablename}
            WHERE plugin_name IS NOT NULL AND plugin_name <> ''
            ORDER BY plugin_name ASC
        ");
        if ($cats) {
            $cats = array_map('trim', $cats);
            $cats = array_unique($cats);
        }
        
        $plugin_filter = isset($_REQUEST['cat-filter']) ? sanitize_text_field($_REQUEST['cat-filter']) : 'Cool Timeline';
        
        // Initialize variables for filters
        $top_plugins_time_filter = isset($_REQUEST['top-plugins-time-filter']) ? sanitize_text_field($_REQUEST['top-plugins-time-filter']) : 'all-time';
        $top_plugins_status_filter = isset($_REQUEST['top-plugins-status-filter']) ? sanitize_text_field($_REQUEST['top-plugins-status-filter']) : 'total';
        $top_plugins_limit_filter = isset($_REQUEST['top-plugins-limit-filter']) ? intval($_REQUEST['top-plugins-limit-filter']) : 5;
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="get" id="overview-filter-form" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'cpfm-data-overview'); ?>">
                
                <?php if ($cats) : ?>
                    <select name="cat-filter" id="cat-filter" class="ewc-filter-cat" style="margin-right: 10px;">
                        <?php 
                        $selected_filter = isset($_REQUEST['cat-filter']) ? trim($_REQUEST['cat-filter']) : 'Cool Timeline';
                        ?>
                        <option value="" <?php echo ($selected_filter === '') ? 'selected="selected"' : ''; ?>>All Plugins</option>
                        <?php
                        foreach ($cats as $plugin_name) :
                            $plugin_name_trimmed = trim($plugin_name);
                            $selected = ($selected_filter === $plugin_name_trimmed) ? ' selected="selected"' : '';
                        ?>
                        <option value="<?php echo esc_attr($plugin_name_trimmed); ?>" <?php echo $selected; ?>>
                            <?php echo esc_html(ucwords(strtolower($plugin_name_trimmed))); ?>
                        </option>
                        <?php endforeach; ?>
                    </select> 
                    <label for="dateRange">Filter by Date:</label>
                    <input type="text" id="dateRange" placeholder="Select date range" style="padding: 4px; width: 200px; font-size: 12px; margin-right: 10px;" value="<?php 
                        $from = $_REQUEST['export_data_date_From'] ?? '';
                        $to = $_REQUEST['export_data_date_to'] ?? '';
                        if ($from && $to) {
                            echo esc_attr($from . ' to ' . $to);
                        }
                    ?>" />
                    <input type="hidden" name="export_data_date_From" id="export_data_date_From" value="<?php echo esc_attr($_REQUEST['export_data_date_From'] ?? ''); ?>">
                    <input type="hidden" name="export_data_date_to" id="export_data_date_to" value="<?php echo esc_attr($_REQUEST['export_data_date_to'] ?? ''); ?>">
                    <input type="submit" id="search_id-search-plugin-submit" class="button" value="Apply filters" />
                    
                    <script>
                    jQuery(document).ready(function($) {
                        if (typeof flatpickr !== 'undefined') {
                            var dateRangePicker = flatpickr("#dateRange", {
                                mode: "range",
                                dateFormat: "Y-m-d",
                                allowInput: true,
                                onChange: function(selectedDates, dateStr, instance) {
                                    if (selectedDates.length === 2) {
                                        var fromDate = selectedDates[0].toISOString().split('T')[0];
                                        var toDate = selectedDates[1].toISOString().split('T')[0];
                                        $('#export_data_date_From').val(fromDate);
                                        $('#export_data_date_to').val(toDate);
                                    }
                                },
                                onReady: function(selectedDates, dateStr, instance) {
                                    var clearBtn = document.createElement("button");
                                    clearBtn.innerHTML = "Clear";
                                    clearBtn.type = "button";
                                    clearBtn.className = "flatpickr-clear-btn";
                                    clearBtn.style.cssText = "width: 100%; padding: 8px; margin-top: 5px; background: #dc3545; color: white; border: none; cursor: pointer; font-size: 13px; border-radius: 3px;";
                                    clearBtn.addEventListener("click", function() {
                                        instance.clear();
                                        $('#export_data_date_From').val('');
                                        $('#export_data_date_to').val('');
                                        instance.close();
                                    });
                                    instance.calendarContainer.appendChild(clearBtn);
                                }
                            });
                        }
                    });
                    </script>
                <?php endif; ?>
            </form>
            
            <!-- Loading Indicator -->
            <div id="overview-loading" style="text-align: center; padding: 50px; font-size: 18px; background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; margin-top: 20px;">
                <div style="max-width: 400px; margin: 0 auto;">
                    <div style="height: 4px; width: 100%; background: #f0f0f1; border-radius: 2px; overflow: hidden; position: relative;">
                        <div id="cpfm-loading-bar" style="height: 100%; width: 0%; background: #2271b1; transition: width 0.2s ease-out; border-radius: 2px;"></div>
                    </div>
                    <p style="margin-top: 10px; font-size: 13px; color: #646970;">Loading Data...</p>
                </div>
            </div>

            <!-- Content Wrapper (Hidden initially) -->
            <div id="overview-content" style="display: none;">
                
                <!-- Stats & Date Chart -->
                <div style="margin-top: 20px; background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-radius: 8px;">
                    <h2 style="margin-top: 0; margin-bottom: 25px; font-size: 22px; font-weight: 600; color: #1d2327;">Plugin Installations Over Time</h2>
                    
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; flex: 1; text-align: center; border: 1px solid #e5e7eb;">
                            <h3 style="margin: 0; font-size: 14px; color: #646970;">Total Sites</h3>
                            <p id="stat-total" style="margin: 5px 0 0; font-size: 24px; font-weight: bold; color: #1d2327;">-</p>
                        </div>
                        <div style="background: #f0fdf4; padding: 15px; border-radius: 5px; flex: 1; text-align: center; border: 1px solid #bbf7d0;">
                            <h3 style="margin: 0; font-size: 14px; color: #166534;">Activated</h3>
                            <p id="stat-activated" style="margin: 5px 0 0; font-size: 24px; font-weight: bold; color: #16a34a;">-</p>
                        </div>
                        <div style="background: #fef2f2; padding: 15px; border-radius: 5px; flex: 1; text-align: center; border: 1px solid #fecaca;">
                            <h3 style="margin: 0; font-size: 14px; color: #991b1b;">Deactivated</h3>
                            <p id="stat-deactivated" style="margin: 5px 0 0; font-size: 24px; font-weight: bold; color: #dc2626;">-</p>
                        </div>
                    </div>

                    <div style="position: relative; height: 450px;">
                        <canvas id="dateChart"></canvas>
                    </div>
                </div>
                
                <!-- Top Plugins by Status -->
                <div style="margin-top: 20px; background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <h2 style="margin: 0; font-size: 22px; font-weight: 600; color: #1d2327;">Top Plugins by Activation Status</h2>
                        <form method="get" style="margin: 0; display: inline-flex; gap: 10px; align-items: center;">
                            <select name="top-plugins-limit-filter" id="top-plugins-limit-filter" class="top-plugins-filter" style="padding: 6px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: pointer;">
                                <option value="5" <?php echo ($top_plugins_limit_filter == 5) ? 'selected' : ''; ?>>Top 5</option>
                                <option value="10" <?php echo ($top_plugins_limit_filter == 10) ? 'selected' : ''; ?>>Top 10</option>
                                <option value="-1" <?php echo ($top_plugins_limit_filter == -1) ? 'selected' : ''; ?>>All</option>
                            </select>
                            <select name="top-plugins-status-filter" id="top-plugins-status-filter" class="top-plugins-filter" style="padding: 6px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: pointer;">
                                <option value="total" <?php echo ($top_plugins_status_filter === 'total') ? 'selected' : ''; ?>>Sort by Total</option>
                                <option value="activated" <?php echo ($top_plugins_status_filter === 'activated') ? 'selected' : ''; ?>>Sort by Activated</option>
                                <option value="deactivated" <?php echo ($top_plugins_status_filter === 'deactivated') ? 'selected' : ''; ?>>Sort by Deactivated</option>
                            </select>
                            <select name="top-plugins-time-filter" id="top-plugins-time-filter" class="top-plugins-filter" style="padding: 6px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: pointer;">
                                <option value="all-time" <?php echo ($top_plugins_time_filter === 'all-time') ? 'selected' : ''; ?>>All Time</option>
                                <option value="24hours" <?php echo ($top_plugins_time_filter === '24hours') ? 'selected' : ''; ?>>Last 24 Hours</option>
                                <option value="1week" <?php echo ($top_plugins_time_filter === '1week') ? 'selected' : ''; ?>>Last 1 Week</option>
                                <option value="1month" <?php echo ($top_plugins_time_filter === '1month') ? 'selected' : ''; ?>>Last 1 Month</option>
                                <option value="1year" <?php echo ($top_plugins_time_filter === '1year') ? 'selected' : ''; ?>>Last 1 Year</option>
                            </select>
                        </form>
                    </div>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 50px; padding: 12px;">#</th>
                                <th style="padding: 12px;">Plugin Name</th>
                                <th style="text-align: center; padding: 12px; color: #22c55e; font-weight: 600;">Activated</th>
                                <th style="text-align: center; padding: 12px; color: #ef4444; font-weight: 600;">Deactivated</th>
                                <th style="text-align: center; padding: 12px; font-weight: 600;">Total</th>
                                <th style="text-align: center; padding: 12px; font-weight: 600;">Activation Rate</th>
                            </tr>
                        </thead>
                        <tbody id="top-plugins-tbody">
                            <tr><td colspan="6" style="text-align: center; padding: 20px;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Plugin Insight Section -->
                <div id="insights-section" style="margin-top: 20px; background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-radius: 8px;">
                    <h2 style="margin-top: 0; margin-bottom: 30px; font-size: 24px; font-weight: 700; color: #1d2327; border-bottom: 3px solid #667eea; padding-bottom: 15px;">
                        📊 Plugin Insight: <span id="insight-plugin-name">...</span>
                        <span style="font-size: 16px; font-weight: 400; color: #646970; margin-left: 10px;">
                            (<span id="insight-total-sites">...</span> sites analyzed)
                        </span>
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr; gap: 30px; margin-bottom: 30px;">
                        <!-- Plugin Version Distribution -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;" id="container-pluginVersionChart">
                            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">Plugin Version Distribution</h3>
                            <div style="position: relative; height: 300px;">
                                <canvas id="pluginVersionChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px;">
                        <!-- WordPress Versions -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;" id="container-wpVersionChart">
                            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">WordPress Versions</h3>
                            <div style="position: relative; height: 300px;">
                                <canvas id="wpVersionChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- PHP Versions -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;" id="container-phpVersionChart">
                            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">PHP Versions</h3>
                            <div style="position: relative; height: 300px;">
                                <canvas id="phpVersionChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Top 5 Active Themes -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;" id="container-themeChart">
                            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">Top 5 Active Themes</h3>
                            <div style="position: relative; height: 300px;">
                                <canvas id="themeChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top 10 Active Plugins -->
                    <div style="margin-top: 30px; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;" id="container-activePluginsChart">
                        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">Top 10 Active Plugins</h3>
                        <div style="position: relative; height: 400px;">
                            <canvas id="activePluginsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                
                function loadOverviewData() {
                    var cat_filter = $('#cat-filter').val();
                    var date_from = $('#export_data_date_From').val();
                    var date_to = $('#export_data_date_to').val();
                    
                    $('#overview-content').hide();
                    $('#overview-loading').show();
                    
                    // Reset progress
                    var $bar = $('#cpfm-loading-bar');
                    $bar.css('width', '0%');
                    
                    // Fake progress animation
                    var progress = 0;
                    var progressInterval = setInterval(function() {
                        progress += Math.random() * 10;
                        if (progress > 90) progress = 90;
                        $bar.css('width', progress + '%');
                    }, 200);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cpfm_get_overview_data',
                            cat_filter: cat_filter,
                            date_from: date_from,
                            date_to: date_to,
                            nonce: '<?php echo wp_create_nonce("cpfm_overview_nonce"); ?>'
                        },
                        success: function(response) {
                            clearInterval(progressInterval);
                            $bar.css('width', '100%');
                            
                            // Give a small delay to show 100% before hiding
                            setTimeout(function() {
                                if (response.success) {
                                    renderOverview(response.data);
                                } else {
                                    alert('Error loading data: ' + (response.data || 'Unknown error'));
                                }
                                $('#overview-loading').hide();
                                $('#overview-content').fadeIn();
                            }, 300);
                        },
                        error: function() {
                            clearInterval(progressInterval);
                            $bar.css('width', '0%');
                            alert('Failed to load data. Please try again.');
                            $('#overview-loading').hide();
                        }
                    });
                }
                
                // Load initial data
                loadOverviewData();
                
                // Handle Filter Form
                $('#overview-filter-form').on('submit', function(e) {
                    e.preventDefault();
                    loadOverviewData();
                    // Update URL without reload
                    var params = $(this).serialize();
                    var newUrl = window.location.pathname + '?' + params;
                    window.history.pushState({path: newUrl}, '', newUrl);
                });
                
                // Top Plugins Table Filters
                $('.top-plugins-filter').on('change', function() {
                    var limit = $('#top-plugins-limit-filter').val();
                    var status = $('#top-plugins-status-filter').val();
                    var time = $('#top-plugins-time-filter').val();
                    var plugin_filter = $('#cat-filter').val();
                    
                    $('#top-plugins-tbody').css('opacity', '0.5');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cpfm_get_top_plugins',
                            limit: limit,
                            status: status,
                            time: time,
                            cat_filter: plugin_filter,
                            nonce: '<?php echo wp_create_nonce("cpfm_top_plugins_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#top-plugins-tbody').html(response.data);
                            }
                            $('#top-plugins-tbody').css('opacity', '1');
                        },
                        error: function() {
                            $('#top-plugins-tbody').css('opacity', '1');
                        }
                    });
                });
                
                function renderOverview(data) {
                    // Stats
                    $('#stat-total').text(data.stats.total);
                    $('#stat-activated').text(data.stats.activated);
                    $('#stat-deactivated').text(data.stats.deactivated);
                    
                    // Date Chart
                    var dateCtx = document.getElementById('dateChart').getContext('2d');
                    var dateChartGradient = dateCtx.createLinearGradient(0, 0, 0, 450);
                    dateChartGradient.addColorStop(0, 'rgba(99, 102, 241, 0.8)');
                    dateChartGradient.addColorStop(1, 'rgba(139, 92, 246, 0.6)');
                    
                    // Destroy existing chart if exists
                    if (window.overviewDateChart) window.overviewDateChart.destroy();
                    
                    window.overviewDateChart = new Chart(dateCtx, {
                        type: 'bar',
                        data: {
                            labels: data.date_chart.labels,
                            datasets: [{
                                label: 'Installations',
                                data: data.date_chart.data,
                                backgroundColor: dateChartGradient,
                                borderColor: 'rgba(99, 102, 241, 1)',
                                borderWidth: 2,
                                borderRadius: 6,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top' },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    callbacks: {
                                        label: function(context) { return 'Installations: ' + context.parsed.y; }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                    
                    // Top Plugins Table
                    $('#top-plugins-tbody').html(data.top_plugins_html);
                    
                    // Insights Header
                    $('#insight-plugin-name').text(data.filter_name);
                    $('#insight-total-sites').text(new Intl.NumberFormat().format(data.insights.total_sites));
                    
                    // Helper to render charts
                    function renderInsightChart(id, chartData, color, label) {
                        var ctx = document.getElementById(id);
                        if (!ctx) return;
                        
                        // Container visibility
                        var container = $('#container-' + id);
                        if (!chartData || chartData.labels.length === 0) {
                            container.hide();
                            return;
                        }
                        container.show();
                        
                        // Store chart instance on canvas element to destroy later
                        if (ctx.chartInstance) ctx.chartInstance.destroy();
                        
                        var bgColor = color.replace('1)', '0.8)');
                        
                        ctx.chartInstance = new Chart(ctx.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: chartData.labels,
                                datasets: [{
                                    label: label,
                                    data: chartData.data,
                                    backgroundColor: bgColor,
                                    borderColor: color,
                                    borderWidth: 2,
                                    borderRadius: 6
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                var total = data.insights.total_sites;
                                                var percentage = total > 0 ? ((context.parsed.y / total) * 100).toFixed(1) : 0;
                                                return context.parsed.y + ' sites (' + percentage + '%)';
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                                }
                            }
                        });
                    }
                    
                    renderInsightChart('pluginVersionChart', data.insights.versions, 'rgba(99, 102, 241, 1)', 'Installations');
                    renderInsightChart('wpVersionChart', data.insights.wp_versions, 'rgba(59, 130, 246, 1)', 'Sites');
                    renderInsightChart('phpVersionChart', data.insights.php_versions, 'rgba(168, 85, 247, 1)', 'Sites');
                    renderInsightChart('themeChart', data.insights.themes, 'rgba(236, 72, 153, 1)', 'Sites');
                    renderInsightChart('activePluginsChart', data.insights.active_plugins, 'rgba(34, 197, 94, 1)', 'Sites');
                    
                    if (data.insights.total_sites === 0) {
                        $('#insights-section').hide();
                    } else {
                         $('#insights-section').show();
                    }
                }
            });
            </script>
        </div>
        <?php
    }
}
