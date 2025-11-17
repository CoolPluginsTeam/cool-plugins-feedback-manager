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
     * Display the data overview page with bar graphs
     */
    public static function display_overview_page() {
        global $wpdb;
        
        wp_enqueue_script('chart-js', plugin_dir_url(CPFM_FILE) . 'assets/js/chart.min.js', array('jquery'), '1.0.0', true);
        
        $tablename = $wpdb->base_prefix . 'cpfm_site_info';
        
        $cache_key = 'cpfm_plugin_names_insights';
        $cats = get_transient($cache_key);
        if ($cats === false) {
            $cats = $wpdb->get_col("
                SELECT DISTINCT TRIM(plugin_name) as plugin_name
                FROM {$tablename}
                WHERE plugin_name IS NOT NULL AND plugin_name <> ''
                ORDER BY plugin_name ASC
            ");
            set_transient($cache_key, $cats, 60 * MINUTE_IN_SECONDS);
        }
        
        $where_conditions = array();
        $where_params = array();
        
        $plugin_filter = isset($_REQUEST['cat-filter']) ? sanitize_text_field($_REQUEST['cat-filter']) : '';
        if (!empty($plugin_filter)) {
            $where_conditions[] = "TRIM(plugin_name) = %s";
            $where_params[] = trim($plugin_filter);
        }
        
        $date_from = isset($_REQUEST['export_data_date_From']) ? sanitize_text_field($_REQUEST['export_data_date_From']) : '';
        $date_to = isset($_REQUEST['export_data_date_to']) ? sanitize_text_field($_REQUEST['export_data_date_to']) : '';
        if (!empty($date_from) && !empty($date_to)) {
            $where_conditions[] = "update_date BETWEEN %s AND %s";
            $where_params[] = $date_from . ' 00:00:00';
            $where_params[] = $date_to . ' 23:59:59';
        }
        
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $plugin_counts_query = "SELECT TRIM(plugin_name) as plugin_name, COUNT(*) as count 
                                 FROM {$tablename} 
                                 {$where_sql}
                                 GROUP BY TRIM(plugin_name)
                                 ORDER BY count DESC 
                                 LIMIT 10";
        
        $plugin_counts = $wpdb->get_results(
            !empty($where_params) ? $wpdb->prepare($plugin_counts_query, $where_params) : $plugin_counts_query,
            ARRAY_A
        );
        
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
        
        $feedback_table = $wpdb->base_prefix . 'cpfm_feedbacks';
        $status_where_conditions = array();
        $status_where_params = array();
        
        if (!empty($plugin_filter)) {
            $status_where_conditions[] = "TRIM(si.plugin_name) = %s";
            $status_where_params[] = trim($plugin_filter);
        }
        
        if (!empty($date_from) && !empty($date_to)) {
            $status_where_conditions[] = "si.update_date BETWEEN %s AND %s";
            $status_where_params[] = $date_from . ' 00:00:00';
            $status_where_params[] = $date_to . ' 23:59:59';
        }
        
        $status_where_sql = !empty($status_where_conditions) ? 'WHERE ' . implode(' AND ', $status_where_conditions) : '';
        
        $status_query = "SELECT TRIM(si.plugin_name) as plugin_name, si.site_id, si.update_date, fb.deactivation_date 
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
            }else{
                $status = 'Activated';
            }
            
            if($status === 'Activated'){
                $activated_count++;
            }else{
                $deactivated_count++;
            }
        }
        
        $top_plugins_time_filter = isset($_REQUEST['top-plugins-time-filter']) ? sanitize_text_field($_REQUEST['top-plugins-time-filter']) : 'all-time';
        $top_plugins_status_filter = isset($_REQUEST['top-plugins-status-filter']) ? sanitize_text_field($_REQUEST['top-plugins-status-filter']) : 'total';
        
        $top_plugins_where_conditions = array("TRIM(si.plugin_name) IS NOT NULL AND TRIM(si.plugin_name) <> ''");
        $top_plugins_query_params = array();
        
        if ($top_plugins_time_filter !== 'all-time') {
            $time_periods = array(
                '24hours' => '-24 hours',
                '1week' => '-1 week',
                '1month' => '-1 month',
                '1year' => '-1 year'
            );
            
            if (isset($time_periods[$top_plugins_time_filter])) {
                $date_from_filter = date('Y-m-d H:i:s', strtotime($time_periods[$top_plugins_time_filter]));
                $top_plugins_where_conditions[] = "si.update_date >= %s";
                $top_plugins_query_params[] = $date_from_filter;
            }
        }
        
        $top_plugins_where_sql = 'WHERE ' . implode(' AND ', $top_plugins_where_conditions);
        
        $top_plugins_query = "SELECT TRIM(si.plugin_name) as plugin_name, si.site_id, si.update_date, fb.deactivation_date 
                             FROM {$tablename} si 
                             LEFT JOIN {$feedback_table} fb ON si.site_id = fb.site_id 
                             {$top_plugins_where_sql}";
        
        if (!empty($top_plugins_query_params)) {
            $all_plugin_records_unfiltered = $wpdb->get_results($wpdb->prepare($top_plugins_query, ...$top_plugins_query_params), ARRAY_A);
        } else {
            $all_plugin_records_unfiltered = $wpdb->get_results($top_plugins_query, ARRAY_A);
        }
        
        $plugin_status_counts_unfiltered = array();
        
        foreach ($all_plugin_records_unfiltered as $record) {
            if(isset($record['deactivation_date']) && !empty($record['deactivation_date'])){
                $status = (strtotime($record['update_date']) > strtotime($record['deactivation_date'])) 
                    ? 'Activated' 
                    : 'Deactivated';
            }else{
                $status = 'Activated';
            }
            
            $plugin_name = trim($record['plugin_name']);
            if (!empty($plugin_name)) {
                if (!isset($plugin_status_counts_unfiltered[$plugin_name])) {
                    $plugin_status_counts_unfiltered[$plugin_name] = array(
                        'activated' => 0,
                        'deactivated' => 0,
                        'total' => 0
                    );
                }
                
                if($status === 'Activated'){
                    $plugin_status_counts_unfiltered[$plugin_name]['activated']++;
                }else{
                    $plugin_status_counts_unfiltered[$plugin_name]['deactivated']++;
                }
                $plugin_status_counts_unfiltered[$plugin_name]['total']++;
            }
        }
        
        uasort($plugin_status_counts_unfiltered, function($a, $b) use ($top_plugins_status_filter) {
            if ($top_plugins_status_filter === 'activated') {
                return $b['activated'] - $a['activated'];
            } elseif ($top_plugins_status_filter === 'deactivated') {
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
        
        $top_5_plugins = array_slice($plugin_status_counts_unfiltered, 0, 5, true);
        
        $plugin_labels = array();
        $plugin_data = array();
        foreach ($plugin_counts as $row) {
            $plugin_labels[] = ucwords(strtolower(trim($row['plugin_name'])));
            $plugin_data[] = (int)$row['count'];
        }
        
        $date_labels = array();
        $date_data = array();
        foreach (array_reverse($date_counts) as $row) {
            $date_labels[] = date('M j', strtotime($row['date']));
            $date_data[] = (int)$row['count'];
        }
        
        $plugin_insights = array();
        
        if (!empty($plugin_filter)) {
            $insights_query = "SELECT server_info, plugin_version, extra_details, site_id
                              FROM {$tablename} 
                              WHERE LOWER(TRIM(plugin_name)) = LOWER(%s)";
            $insights_records = $wpdb->get_results($wpdb->prepare($insights_query, trim($plugin_filter)), ARRAY_A);
            $unique_sites_query = "SELECT COUNT(DISTINCT site_id) FROM {$tablename} WHERE LOWER(TRIM(plugin_name)) = LOWER(%s)";
            $total_sites = (int) $wpdb->get_var($wpdb->prepare($unique_sites_query, trim($plugin_filter)));
        } else {
            $insights_query = "SELECT server_info, plugin_version, extra_details, site_id FROM {$tablename}";
            $insights_records = $wpdb->get_results($insights_query, ARRAY_A);
            $total_sites = count($insights_records);
        }
        
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
                if (!isset($version_counts[$version])) {
                    $version_counts[$version] = 0;
                }
                $version_counts[$version]++;
            }
            
            $server_info = maybe_unserialize($record['server_info']);
            
            if (isset($server_info['wp_version'])) {
                $wp_version = $server_info['wp_version'];
                if (!isset($wp_version_counts[$wp_version])) {
                    $wp_version_counts[$wp_version] = 0;
                }
                $wp_version_counts[$wp_version]++;
            }
            
            if (isset($server_info['php_version'])) {
                $php_version = $server_info['php_version'];
                if (!isset($php_version_counts[$php_version])) {
                    $php_version_counts[$php_version] = 0;
                }
                $php_version_counts[$php_version]++;
            }
            
            if (!empty($record['extra_details'])) {
                $extra_details = maybe_unserialize($record['extra_details']);
                
                if (is_array($extra_details) && isset($extra_details['wp_theme']) && isset($extra_details['wp_theme']['name'])) {
                    $theme_name = $extra_details['wp_theme']['name'];
                    if (!empty($theme_name)) {
                        $theme_key = $site_id ? $site_id . '_' . $theme_name : null;
                        if ($theme_key && !isset($site_plugins_tracked[$theme_key])) {
                            if (!isset($theme_counts[$theme_name])) {
                                $theme_counts[$theme_name] = 0;
                            }
                            $theme_counts[$theme_name]++;
                            $site_plugins_tracked[$theme_key] = true;
                        } elseif (!$site_id) {
                            if (!isset($theme_counts[$theme_name])) {
                                $theme_counts[$theme_name] = 0;
                            }
                            $theme_counts[$theme_name]++;
                        }
                    }
                }
                
                if (is_array($extra_details) && isset($extra_details['active_plugins']) && is_array($extra_details['active_plugins'])) {
                    foreach ($extra_details['active_plugins'] as $plugin) {
                        if (is_array($plugin) && isset($plugin['name']) && !empty($plugin['name'])) {
                            $plugin_name = $plugin['name'];
                            $plugin_key = $site_id ? $site_id . '_' . $plugin_name : null;
                            
                            if ($plugin_key && !isset($site_plugins_tracked[$plugin_key])) {
                                if (!isset($active_plugins_counts[$plugin_name])) {
                                    $active_plugins_counts[$plugin_name] = 0;
                                }
                                $active_plugins_counts[$plugin_name]++;
                                $site_plugins_tracked[$plugin_key] = true;
                            } elseif (!$site_id) {
                                if (!isset($active_plugins_counts[$plugin_name])) {
                                    $active_plugins_counts[$plugin_name] = 0;
                                }
                                $active_plugins_counts[$plugin_name]++;
                            }
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
        
        if (!empty($plugin_filter)) {
            foreach ($active_plugins_counts as $plugin_name => $count) {
                if (self::is_same_plugin($plugin_name, $plugin_filter)) {
                    unset($active_plugins_counts[$plugin_name]);
                }
            }
        }
        
        $plugin_insights['total_sites'] = $total_sites;
        $plugin_insights['version_distribution'] = $version_counts;
        $plugin_insights['wp_versions'] = array_slice($wp_version_counts, 0, 5, true);
        $plugin_insights['php_versions'] = array_slice($php_version_counts, 0, 5, true);
        $plugin_insights['themes'] = array_slice($theme_counts, 0, 5, true);
        $plugin_insights['active_plugins'] = array_slice($active_plugins_counts, 0, 10, true);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'cpfm-data-overview'); ?>">
                
                <?php if ($cats) : ?>
                    <select name="cat-filter" class="ewc-filter-cat" style="margin-right: 10px;">
                        <option value="">All Plugins</option>
                        <?php 
                        $selected_filter = isset($_REQUEST['cat-filter']) ? trim($_REQUEST['cat-filter']) : '';
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
            
            <div style="margin-top: 20px; background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-radius: 8px;">
                <h2 style="margin-top: 0; margin-bottom: 25px; font-size: 22px; font-weight: 600; color: #1d2327;">Plugin Installations Over Time</h2>
                <div style="position: relative; height: 450px;">
                    <canvas id="dateChart"></canvas>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Plugin Count Chart -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2>Top Plugins by Count</h2>
                    <canvas id="pluginChart" style="max-height: 400px;"></canvas>
                </div>
                
                <!-- Activation Status Chart -->
                <div style="background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-radius: 8px;">
                    <h2 style="margin-top: 0; margin-bottom: 25px; font-size: 22px; font-weight: 600; color: #1d2327;">Activation Status</h2>
                    <div style="position: relative; height: 400px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top 5 Plugins by Status -->
            <div style="margin-top: 20px; background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 style="margin: 0; font-size: 22px; font-weight: 600; color: #1d2327;">Top 5 Plugins by Activation Status</h2>
                    <form method="get" style="margin: 0; display: inline-flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'cpfm-data-overview'); ?>">
                        <?php if (!empty($plugin_filter)) : ?>
                            <input type="hidden" name="cat-filter" value="<?php echo esc_attr($plugin_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($date_from)) : ?>
                            <input type="hidden" name="export_data_date_From" value="<?php echo esc_attr($date_from); ?>">
                        <?php endif; ?>
                        <?php if (!empty($date_to)) : ?>
                            <input type="hidden" name="export_data_date_to" value="<?php echo esc_attr($date_to); ?>">
                        <?php endif; ?>
                        <select name="top-plugins-status-filter" id="top-plugins-status-filter" onchange="this.form.submit();" style="padding: 6px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: pointer;">
                            <option value="total" <?php echo ($top_plugins_status_filter === 'total') ? 'selected' : ''; ?>>Sort by Total</option>
                            <option value="activated" <?php echo ($top_plugins_status_filter === 'activated') ? 'selected' : ''; ?>>Sort by Activated</option>
                            <option value="deactivated" <?php echo ($top_plugins_status_filter === 'deactivated') ? 'selected' : ''; ?>>Sort by Deactivated</option>
                        </select>
                        <select name="top-plugins-time-filter" id="top-plugins-time-filter" onchange="this.form.submit();" style="padding: 6px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: pointer;">
                            <option value="all-time" <?php echo ($top_plugins_time_filter === 'all-time') ? 'selected' : ''; ?>>All Time</option>
                            <option value="24hours" <?php echo ($top_plugins_time_filter === '24hours') ? 'selected' : ''; ?>>Last 24 Hours</option>
                            <option value="1week" <?php echo ($top_plugins_time_filter === '1week') ? 'selected' : ''; ?>>Last 1 Week</option>
                            <option value="1month" <?php echo ($top_plugins_time_filter === '1month') ? 'selected' : ''; ?>>Last 1 Month</option>
                            <option value="1year" <?php echo ($top_plugins_time_filter === '1year') ? 'selected' : ''; ?>>Last 1 Year</option>
                        </select>
                    </form>
                </div>
                <?php if (!empty($top_5_plugins)) : ?>
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
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($top_5_plugins as $plugin_name => $counts) : 
                                $total = $counts['total'];
                                $activated = $counts['activated'];
                                $deactivated = $counts['deactivated'];
                                $activation_rate = $total > 0 ? round(($activated / $total) * 100, 1) : 0;
                                $display_name = ucwords(strtolower($plugin_name));
                            ?>
                            <tr>
                                <td style="padding: 12px; font-weight: 600; color: #646970;"><?php echo esc_html($rank); ?></td>
                                <td style="padding: 12px; font-weight: 500; color: #1d2327;"><?php echo esc_html($display_name); ?></td>
                                <td style="text-align: center; padding: 12px; color: #22c55e; font-weight: 600;">
                                    <span style="display: inline-block; padding: 4px 12px; background: rgba(34, 197, 94, 0.1); border-radius: 4px;">
                                        <?php echo esc_html($activated); ?>
                                    </span>
                                </td>
                                <td style="text-align: center; padding: 12px; color: #ef4444; font-weight: 600;">
                                    <span style="display: inline-block; padding: 4px 12px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">
                                        <?php echo esc_html($deactivated); ?>
                                    </span>
                                </td>
                                <td style="text-align: center; padding: 12px; font-weight: 600; color: #1d2327;">
                                    <?php echo esc_html($total); ?>
                                </td>
                                <td style="text-align: center; padding: 12px;">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                        <div style="flex: 1; max-width: 100px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; width: <?php echo esc_attr($activation_rate); ?>%; background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%); transition: width 0.3s ease;"></div>
                                        </div>
                                        <span style="font-weight: 600; color: #1d2327; min-width: 45px; text-align: left;">
                                            <?php echo esc_html($activation_rate); ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                            $rank++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="padding: 20px; color: #646970; text-align: center;">No plugin data available.</p>
                <?php endif; ?>
            </div>
            
            
            <script>
            jQuery(document).ready(function($) {
                // Plugin Count Chart
                var pluginCtx = document.getElementById('pluginChart').getContext('2d');
                new Chart(pluginCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($plugin_labels); ?>,
                        datasets: [{
                            label: 'Number of Sites',
                            data: <?php echo json_encode($plugin_data); ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
                
                
                // Date Chart
                var dateCtx = document.getElementById('dateChart').getContext('2d');
                var dateChartGradient = dateCtx.createLinearGradient(0, 0, 0, 450);
                dateChartGradient.addColorStop(0, 'rgba(99, 102, 241, 0.8)');
                dateChartGradient.addColorStop(1, 'rgba(139, 92, 246, 0.6)');
                
                new Chart(dateCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($date_labels); ?>,
                        datasets: [{
                            label: 'Installations',
                            data: <?php echo json_encode($date_data); ?>,
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
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: {
                                        size: 13,
                                        weight: '500',
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#1d2327'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14,
                                    weight: '600',
                                    family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                },
                                bodyFont: {
                                    size: 13,
                                    family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                },
                                borderColor: 'rgba(99, 102, 241, 0.5)',
                                borderWidth: 1,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        return 'Installations: ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date',
                                    font: {
                                        size: 14,
                                        weight: '600',
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#50575e',
                                    padding: { top: 10, bottom: 0 }
                                },
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: true,
                                    borderColor: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 11,
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#646970',
                                    maxRotation: 45,
                                    minRotation: 0
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Number of Installations',
                                    font: {
                                        size: 14,
                                        weight: '600',
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#50575e',
                                    padding: { top: 0, bottom: 10 }
                                },
                                beginAtZero: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: true,
                                    borderColor: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 11,
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#646970',
                                    stepSize: 1,
                                    precision: 0,
                                    callback: function(value) {
                                        return Number.isInteger(value) ? value : '';
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
                
                // Activation Status Chart
                var statusCtx = document.getElementById('statusChart').getContext('2d');
                var statusChartGradient1 = statusCtx.createLinearGradient(0, 0, 0, 400);
                statusChartGradient1.addColorStop(0, 'rgba(34, 197, 94, 0.8)');
                statusChartGradient1.addColorStop(1, 'rgba(22, 163, 74, 0.6)');
                
                var statusChartGradient2 = statusCtx.createLinearGradient(0, 0, 0, 400);
                statusChartGradient2.addColorStop(0, 'rgba(239, 68, 68, 0.8)');
                statusChartGradient2.addColorStop(1, 'rgba(220, 38, 38, 0.6)');
                
                new Chart(statusCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Activated', 'Deactivated'],
                        datasets: [{
                            label: 'Number of Sites',
                            data: [<?php echo $activated_count; ?>, <?php echo $deactivated_count; ?>],
                            backgroundColor: [statusChartGradient1, statusChartGradient2],
                            borderColor: ['rgba(34, 197, 94, 1)', 'rgba(239, 68, 68, 1)'],
                            borderWidth: 2,
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: {
                                        size: 13,
                                        weight: '500',
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#1d2327'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14,
                                    weight: '600',
                                    family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                },
                                bodyFont: {
                                    size: 13,
                                    family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                },
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        var value = context.parsed.y || 0;
                                        var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return label + ': ' + value + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Status',
                                    font: {
                                        size: 14,
                                        weight: '600',
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#50575e',
                                    padding: { top: 10, bottom: 0 }
                                },
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: true,
                                    borderColor: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 11,
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#646970'
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Number of Sites',
                                    font: {
                                        size: 14,
                                        weight: '600',
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#50575e',
                                    padding: { top: 0, bottom: 10 }
                                },
                                beginAtZero: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: true,
                                    borderColor: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 11,
                                        family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                                    },
                                    color: '#646970',
                                    stepSize: 1,
                                    precision: 0,
                                    callback: function(value) {
                                        return Number.isInteger(value) ? value : '';
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            });
            </script>
            
            <?php if (!empty($plugin_insights) && $plugin_insights['total_sites'] > 0) : ?>
            <!-- Plugin Insight Section -->
            <div style="margin-top: 20px; background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-radius: 8px;">
                <h2 style="margin-top: 0; margin-bottom: 30px; font-size: 24px; font-weight: 700; color: #1d2327; border-bottom: 3px solid #667eea; padding-bottom: 15px;">
                    📊 Plugin Insight: <?php echo !empty($plugin_filter) ? esc_html(ucwords(strtolower($plugin_filter))) : 'All Plugins'; ?>
                    <span style="font-size: 16px; font-weight: 400; color: #646970; margin-left: 10px;">
                        (<?php echo esc_html(number_format($plugin_insights['total_sites'])); ?> sites analyzed)
                    </span>
                </h2>
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 30px; margin-bottom: 30px;">
                    <!-- Plugin Version Distribution -->
                    <?php if (!empty($plugin_insights['version_distribution'])) : 
                        $version_labels_insight = array();
                        $version_data_insight = array();
                        foreach ($plugin_insights['version_distribution'] as $version => $count) {
                            $version_labels_insight[] = $version;
                            $version_data_insight[] = $count;
                        }
                    ?>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">Plugin Version Distribution</h3>
                        <div style="position: relative; height: 300px;">
                            <canvas id="pluginVersionChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px;">
                    <!-- WordPress Versions -->
                    <?php if (!empty($plugin_insights['wp_versions'])) : 
                        $wp_labels = array();
                        $wp_data = array();
                        foreach ($plugin_insights['wp_versions'] as $version => $count) {
                            $wp_labels[] = $version;
                            $wp_data[] = $count;
                        }
                    ?>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">WordPress Versions</h3>
                        <div style="position: relative; height: 300px;">
                            <canvas id="wpVersionChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- PHP Versions -->
                    <?php if (!empty($plugin_insights['php_versions'])) : 
                        $php_labels = array();
                        $php_data = array();
                        foreach ($plugin_insights['php_versions'] as $version => $count) {
                            $php_labels[] = $version;
                            $php_data[] = $count;
                        }
                    ?>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">PHP Versions</h3>
                        <div style="position: relative; height: 300px;">
                            <canvas id="phpVersionChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Top 5 Active Themes -->
                    <?php if (!empty($plugin_insights['themes'])) : 
                        $theme_labels = array();
                        $theme_data = array();
                        foreach ($plugin_insights['themes'] as $theme => $count) {
                            $theme_labels[] = $theme;
                            $theme_data[] = $count;
                        }
                    ?>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">Top 5 Active Themes</h3>
                        <div style="position: relative; height: 300px;">
                            <canvas id="themeChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Top 10 Active Plugins -->
                <?php if (!empty($plugin_insights['active_plugins'])) : 
                    $active_plugin_labels = array();
                    $active_plugin_data = array();
                    foreach ($plugin_insights['active_plugins'] as $plugin => $count) {
                        $active_plugin_labels[] = $plugin;
                        $active_plugin_data[] = $count;
                    }
                ?>
                <div style="margin-top: 30px; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                    <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #1d2327;">Top 10 Active Plugins</h3>
                    <div style="position: relative; height: 400px;">
                        <canvas id="activePluginsChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                <?php if (!empty($plugin_insights['version_distribution'])) : ?>
                // Plugin Version Chart
                var versionCtx = document.getElementById('pluginVersionChart');
                if (versionCtx) {
                    new Chart(versionCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($version_labels_insight); ?>,
                            datasets: [{
                                label: 'Installations',
                                data: <?php echo json_encode($version_data_insight); ?>,
                                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                                borderColor: 'rgba(99, 102, 241, 1)',
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
                                            var total = <?php echo $plugin_insights['total_sites']; ?>;
                                            var percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                            return context.parsed.y + ' sites (' + percentage + '%)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                }
                <?php endif; ?>
                
                <?php if (!empty($plugin_insights['wp_versions'])) : ?>
                // WordPress Version Chart
                var wpVersionCtx = document.getElementById('wpVersionChart');
                if (wpVersionCtx) {
                    new Chart(wpVersionCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($wp_labels); ?>,
                            datasets: [{
                                label: 'Sites',
                                data: <?php echo json_encode($wp_data); ?>,
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderColor: 'rgba(59, 130, 246, 1)',
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
                                            var total = <?php echo $plugin_insights['total_sites']; ?>;
                                            var percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                            return context.parsed.y + ' sites (' + percentage + '%)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                }
                <?php endif; ?>
                
                <?php if (!empty($plugin_insights['php_versions'])) : ?>
                // PHP Version Chart
                var phpVersionCtx = document.getElementById('phpVersionChart');
                if (phpVersionCtx) {
                    new Chart(phpVersionCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($php_labels); ?>,
                            datasets: [{
                                label: 'Sites',
                                data: <?php echo json_encode($php_data); ?>,
                                backgroundColor: 'rgba(168, 85, 247, 0.8)',
                                borderColor: 'rgba(168, 85, 247, 1)',
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
                                            var total = <?php echo $plugin_insights['total_sites']; ?>;
                                            var percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                            return context.parsed.y + ' sites (' + percentage + '%)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                }
                <?php endif; ?>
                
                <?php if (!empty($plugin_insights['themes'])) : ?>
                // Top 5 Active Themes Chart
                var themeCtx = document.getElementById('themeChart');
                if (themeCtx) {
                    new Chart(themeCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($theme_labels); ?>,
                            datasets: [{
                                label: 'Sites',
                                data: <?php echo json_encode($theme_data); ?>,
                                backgroundColor: 'rgba(236, 72, 153, 0.8)',
                                borderColor: 'rgba(236, 72, 153, 1)',
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
                                            var total = <?php echo $plugin_insights['total_sites']; ?>;
                                            var percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                            return context.parsed.y + ' sites (' + percentage + '%)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 0
                                    }
                                }
                            }
                        }
                    });
                }
                <?php endif; ?>
                
                <?php if (!empty($plugin_insights['active_plugins'])) : ?>
                // Top 10 Active Plugins Chart
                var activePluginsCtx = document.getElementById('activePluginsChart');
                if (activePluginsCtx) {
                    new Chart(activePluginsCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($active_plugin_labels); ?>,
                            datasets: [{
                                label: 'Sites',
                                data: <?php echo json_encode($active_plugin_data); ?>,
                                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                                borderColor: 'rgba(34, 197, 94, 1)',
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
                                            var total = <?php echo $plugin_insights['total_sites']; ?>;
                                            var percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                            return context.parsed.y + ' sites (' + percentage + '%)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 0
                                    }
                                }
                            }
                        }
                    });
                }
                <?php endif; ?>
            });
            </script>
            <?php endif; ?>
        </div>
        <?php
    }
}
