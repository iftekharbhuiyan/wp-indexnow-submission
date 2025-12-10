<?php
/**
 * Plugin Name: IndexNow Submission
 * Plugin URI: https://github.com/iftekharbhuiyan/wp-indexnow-submission/
 * Description: Submits updated pages and posts URL to IndexNow automatically.
 * Version: 1.0
 * Requires PHP: 8.0
 * Author: Iftekhar Bhuiyan
 * Author URI: https://github.com/iftekharbhuiyan/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI: https://github.com/iftekharbhuiyan/wp-indexnow-submission/
 */
 // disable direct loading
 if (!defined('ABSPATH')) {
    die('Invalid request.');
}

if (!class_exists('BSFT_IndexNow')) :

    class BSFT_IndexNow
    {
        private $file;
        private $basename;
        private $options;

        public function __construct() {
            $this->file = plugin_dir_path(__FILE__).'bsft-indexnow.php';
            $this->basename = plugin_basename(__FILE__);
            $this->options = 10;
            // init
            $this->init();
        }

        /**
         * Page Menu
         */
        public function set_settings_page_submenu() {
            add_options_page(
                'IndexNow Settings',
                'IndexNow',
                'manage_options',
                'bsft-indexnow',
                array($this, 'set_settings_options_page')
            );
        }

        /**
         * Options Page
         */
        public function set_settings_options_page() {
            $this->options = get_option('bsft_indexnow');
            ?>
            <div class="wrap">
                <h1>IndexNow Settings</h1>
                <form method="post" action="options.php">
                    <?php // render form
                    settings_fields('bsft_indexnow_option_group');
                    do_settings_sections('bsft-indexnow');
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }

        /**
         * User Submission
         */
        public function set_settings_user_values($input) {
            $new_input = array();
            if (isset($input['api_token'])) {
                $new_input['token'] = sanitize_text_field($input['api_token']);
            }
            if (isset($input['api_endpoint'])) {
                $new_input['endpoint'] = sanitize_url($input['api_endpoint']);
            }
            return $new_input;
        }

        /**
         * Section Description
         */
        public function get_settings_section_description() {
            print 'IndexNow allows search engines to find all new and updated content URLs. Learn more from <a href="https://www.indexnow.org/" target="_blank">IndexNow</a>.';
        }

        /**
         * IndexNow API Field
         */
        public function get_settings_indexnow_api_field() {
            printf(
                '<input type="text" id="api_token" name="bsft_indexnow[api_token]" class="regular-text" value="%s">',
                isset($this->options['token']) ? esc_attr($this->options['token']) : ''
            );
            print '<p class="description">Key txt file at the root directory is required.</p>';
        }

        /**
         * API Endpoint Field
         */
        public function get_settings_api_endpoint_field() {
            printf(
                '<input type="url" id="api_endpoint" name="bsft_indexnow[api_endpoint]" class="regular-text" value="%s">',
                isset($this->options['endpoint']) ? esc_url($this->options['endpoint']) : ''
            );
            print '<p class="description">API Endpoint.</p>';
        }


        /**
         * API Status
         */
        public function get_settings_api_status_field() {
            $options = get_option('bsft_indexnow');
            if (!empty($options['code']) && !empty($options['message'])) {
                print '<p class="description">Code: '.$options['code'].', Message: '.$options['message'].'.</p>';
                print '<p class="description">Link: '.$options['count'].', Time: '.$options['time'].'.</p>';
            } else {
                print '<p class="description">Nothing yet!</p>';
            }
        }

        /**
         * Register Settings
         */
        public function set_settings_options() {
            register_setting(
                'bsft_indexnow_option_group', // option group
                'bsft_indexnow',
                array($this, 'set_settings_user_values')
            );
            add_settings_section( // section
                'bsft_indexnow_section', // id
                'Setting Options', // title
                array($this, 'get_settings_section_description'), // callback
                'bsft-indexnow' // page
            );
            add_settings_field( // field
                'api_token', // id
                'API Key', // title
                array($this, 'get_settings_indexnow_api_field'), // callback
                'bsft-indexnow', // page
                'bsft_indexnow_section' // section
            );
            add_settings_field( // field
                'api_endpoint', // id
                'Endpoint URL', // title
                array($this, 'get_settings_api_endpoint_field'), // callback
                'bsft-indexnow', // page
                'bsft_indexnow_section' // section
            );
            add_settings_field( // field
                'api_status', // id
                'Status', // title
                array($this, 'get_settings_api_status_field'), // callback
                'bsft-indexnow', // page
                'bsft_indexnow_section' // section
            );
        }

        /**
         * Settings Links
         */
        public function set_settings_link($actions, $plugin_file) {
            $new_actions = [];
            if ($this->basename === $plugin_file) {
                $new_actions['settings'] = '<a href="'.esc_url(admin_url('options-general.php?page=bsft-indexnow')).'">Settings</a>';
            }
          return $actions + $new_actions;
        }

        /**
         * URL Lists
         */
        public function get_url_list() {
            global $post;
            $max_records    = 10000; // hard lmiit
            $set_date       = date('Y-m-d', strtotime('-1 day')); // minus 1 day
            $cur_year       = date('Y', strtotime($set_date));
            $cur_month      = date('m', strtotime($set_date));
            $cur_date       = date('d', strtotime($set_date));
            $post_types     = get_post_types(array('_builtin' => false), 'names');
            $modified_posts = get_posts(array(
                'post_type'         => $post_types,
                'post_status'       => 'publish',
                'numberposts'       => $max_records,
                'date_query'        => array(
                    'column'        => 'post_modified',
                    'year'          => $cur_year,
                    'month'         => $cur_month,
                    'day'           => $cur_date,
                ),
                'nopaging'          => true,
            ));
            if (!empty($modified_posts)) {
                $data = array();
                foreach ($modified_posts as $post) {
                    setup_postdata($post);
                    $data[] = get_permalink($post->ID);
                    unset($post);
                }
                wp_reset_postdata();
                return $data;
            }
            return false;
        }

        /**
         * Do Submit
         */
        public function do_submit_urls() {
            $options    = get_option('bsft_indexnow');
            $token      = empty($options['token']) ? '' : $options['token'];
            $endpoint   = empty($options['endpoint']) ? '' : $options['endpoint'];
            // check
            if (!empty($token) && !empty($endpoint)) {
                $host       = site_url();
                $file       = $host.'/'.$token.'.txt';
                $links      = $this->get_url_list();
                $code       = '';
                $message    = '';
                // link
                if (!empty($links)) {
                    // body
                    $body = wp_json_encode(array(
                        'host'          => $host,
                        'key'           => $token,
                        'keyLocation'   => $file,
                        'urlList'       => $links
                    ));
                    // response
                    $response = wp_remote_post($endpoint, array(
                        'method'        => 'POST',
                        'httpversion'   => '1.1',
                        'headers'       => array(
                            'Content-Type' => 'application/json; charset=utf-8',
                        ),
                        'body'          => $body,
                        'data_format' => 'body',
                    ));
                    // handle error
                    if (is_wp_error($response)) {
                        $code       = $response->get_error_code();
                        $message    = $response->get_error_message();
                    } else {
                        $code       = wp_remote_retrieve_response_code($response);
                        $message    = wp_remote_retrieve_response_message($response);
                    }
                    // store response
                    if (!empty($code) && !empty($message)) {
                        $time_stamp = date('Y-m-d H:i:s');
                        $link_count = count($links);
                        // update option
                        $options['code'] = $code;
                        $options['message'] = $message;
                        $options['time'] = $time_stamp;
                        $options['count'] = $link_count;
                        update_option('bsft_indexnow', $options);
                    }
                }
            }
        }

        /**
         * Activate
         */
        public function activate() {
            //add default values. see: https://core.trac.wordpress.org/ticket/51699
            if ('not-exists' === get_option('bsft_indexnow', 'not-exists')) {
                $data = array(
                    'token'     => '',
                    'endpoint'  => 'https://www.bing.com/indexnow',
                    'code'      => '',
                    'message'   => '',
                    'time'      => '',
                    'count'     => ''
                );
                add_option('bsft_indexnow', $data, '', 'yes');
            }
            // bsft_cron does not exists
            if (!wp_next_scheduled('bsft_cron')) {
                // UTC + 4 hours to match EST timezone
                $timestamp = strtotime('04:00:00');
                // schedule the event
                wp_schedule_event($timestamp, 'daily', 'bsft_cron');
            }
        }

        /**
         * Deactivate
         */
        public function deactivate() {
            // delete option
            if (get_option('bsft_indexnow')) {
                delete_option('bsft_indexnow');
            }
            // must not remove other tasks. so...
            if (wp_next_scheduled('bsft_cron')) {
                remove_action('bsft_cron', array($this, 'do_submit_urls'));
            }
        }

        /**
         * Init
         */
        public function init() {
            // on activation
            register_activation_hook($this->file , array($this, 'activate'));
            // on deactivation
            register_deactivation_hook($this->file , array($this, 'deactivate'));
            // add settings page
            add_action('admin_menu', array($this, 'set_settings_page_submenu'));
            // add settings options
            add_action('admin_init', array($this, 'set_settings_options'));
            // add settings link
            add_filter('plugin_action_links_'. $this->basename, array($this, 'set_settings_link'), 10, 2);
            // add task
            add_action('wp_loaded', function() {
                if (wp_next_scheduled('bsft_cron')) {
                    add_action('bsft_cron', array($this, 'do_submit_urls'));
                }
            });
        }
    }

    // new instance
    $bsft_indexnow = new BSFT_IndexNow;
endif;
?>