<?php
/*
Plugin Name: Stupid Simple Plugins Updater
Plugin URI: https://github.com/bedynamictech/StupidSimplePlugins
Description: Enables updates for Stupid Simple plugins from the GitHub repo.
Version: 1.0
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
GitHub ID: sspu
*/

defined('ABSPATH') || exit;

class SS_Plugins_Updater {

    private $username = 'bedynamictech';
    private $repository = 'StupidSimplePlugins';
    private $branch = 'main';
    private $github_api_url;
    private $cache_prefix = 'ss_plugin_';

    public function __construct() {
        $this->github_api_url = "https://api.github.com/repos/{$this->username}/{$this->repository}";
        
        // Automatic update hooks
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_updates']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_source_directory'], 10, 4);
        
        // Check for updates on admin login
        add_action('wp_login', [$this, 'on_admin_login'], 10, 2);
    }

    /**
     * Trigger update check when admin logs in
     */
    public function on_admin_login($user_login, $user) {
        if (user_can($user, 'update_plugins')) {
            $this->force_update_check();
        }
    }

    /**
     * Force an update check and clear caches
     */
    private function force_update_check() {
        // Clear caches
        $this->clear_cache();
        
        // Force WordPress to check for updates
        $update_data = $this->check_updates(get_site_transient('update_plugins'));
        set_site_transient('update_plugins', $update_data);
        
        // Store last checked time
        update_option('ss_last_checked', time());
    }

    /**
     * Clear all caches related to plugin updates
     */
    private function clear_cache() {
        global $wpdb;
        
        // Clear our plugin caches
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $this->cache_prefix . '%',
                '_transient_timeout_' . $this->cache_prefix . '%'
            )
        );
        
        // Clear WordPress update cache
        delete_site_transient('update_plugins');
    }

    /**
     * Main update check function
     */
    public function check_updates($transient) {
        if (empty($transient->checked)) return $transient;

        foreach ($this->get_local_plugins() as $plugin_file => $plugin_data) {
            if (!empty($plugin_data['GitHub ID'])) {
                $remote_data = $this->get_remote_plugin($plugin_data['GitHub ID']);
                if ($remote_data && version_compare($plugin_data['Version'], $remote_data['version'], '<')) {
                    $transient->response[$plugin_file] = (object) [
                        'slug' => $plugin_data['GitHub ID'],
                        'plugin' => $plugin_file,
                        'new_version' => $remote_data['version'],
                        'package' => $this->get_zip_url($plugin_data['GitHub ID']),
                        'tested' => $remote_data['tested'] ?? '',
                        'requires' => $remote_data['requires'] ?? ''
                    ];
                }
            }
        }

        return $transient;
    }

    /**
     * Plugin information for the WordPress plugin install screen
     */
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') return $false;
        
        foreach ($this->get_local_plugins() as $plugin_file => $plugin_data) {
            if ($plugin_data['GitHub ID'] === $response->slug) {
                $remote_data = $this->get_remote_plugin($plugin_data['GitHub ID']);
                if ($remote_data) {
                    $response = (object) [
                        'name' => $remote_data['name'],
                        'slug' => $plugin_data['GitHub ID'],
                        'version' => $remote_data['version'],
                        'author' => $remote_data['author'],
                        'author_profile' => $remote_data['author_uri'],
                        'last_updated' => $remote_data['last_updated'],
                        'requires' => $remote_data['requires'] ?? '',
                        'tested' => $remote_data['tested'] ?? '',
                        'homepage' => $remote_data['plugin_uri'],
                        'download_link' => $this->get_zip_url($plugin_data['GitHub ID']),
                        'sections' => [
                            'description' => $remote_data['description'],
                            'changelog' => $this->get_changelog($plugin_data['GitHub ID'])
                        ],
                    ];
                    return $response;
                }
            }
        }
        
        return $false;
    }

    /**
     * Fix the source directory during plugin update
     */
    public function fix_source_directory($source, $remote_source, $upgrader, $hook_extra) {
        global $wp_filesystem;

        if (isset($hook_extra['plugin']) && is_string($hook_extra['plugin'])) {
            $plugin_slug = dirname($hook_extra['plugin']);
            $new_source = trailingslashit($remote_source) . $plugin_slug;
            
            if ($wp_filesystem->move($source, $new_source)) {
                return $new_source;
            }
        }

        return $source;
    }

    /* HELPER FUNCTIONS */

    /**
     * Get all locally installed plugins from our GitHub repo
     */
    private function get_local_plugins() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        
        $plugins = [];
        foreach (get_plugins() as $file => $data) {
            if (strpos($data['PluginURI'], "github.com/{$this->username}/{$this->repository}") !== false) {
                $plugin_path = WP_PLUGIN_DIR . '/' . $file;
                if (file_exists($plugin_path)) {
                    $file_content = file_get_contents($plugin_path);
                    if (preg_match('/GitHub ID:\s*(.+)/i', $file_content, $matches)) {
                        $data['GitHub ID'] = trim($matches[1]);
                        $plugins[$file] = $data;
                    }
                }
            }
        }

        return $plugins;
    }

    /**
     * Get plugin data from GitHub
     */
    private function get_remote_plugin($plugin_id) {
        $cache_key = $this->cache_prefix . md5($plugin_id);
        $data = get_transient($cache_key);

        if (false === $data) {
            $response = wp_remote_get("{$this->github_api_url}/contents/{$plugin_id}", [
                'headers' => ['Accept' => 'application/vnd.github.v3+json']
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $files = json_decode(wp_remote_retrieve_body($response), true);
                
                foreach ($files as $file) {
                    if ($file['type'] === 'file' && pathinfo($file['name'], PATHINFO_EXTENSION) === 'php') {
                        $file_response = wp_remote_get($file['download_url']);
                        if (!is_wp_error($file_response)) {
                            $file_content = wp_remote_retrieve_body($file_response);
                            if (strpos($file_content, 'Plugin Name:') !== false) {
                                $data = $this->parse_plugin_headers($file_content);
                                $data['last_updated'] = $this->get_last_commit_date($plugin_id);
                                set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Parse plugin headers from file content
     */
    private function parse_plugin_headers($content) {
        $headers = [
            'name' => 'Plugin Name',
            'version' => 'Version',
            'description' => 'description',
            'author' => 'Author',
            'author_uri' => 'Author URI',
            'plugin_uri' => 'Plugin URI',
            'tested' => 'Tested up to',
            'requires' => 'Requires at least'
        ];

        $data = [];
        foreach ($headers as $key => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $content, $match)) {
                $data[$key] = trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $match[1]));
            }
        }

        return $data;
    }

    /**
     * Get last commit date from GitHub
     */
    private function get_last_commit_date($plugin_id) {
        $response = wp_remote_get("{$this->github_api_url}/commits?path={$plugin_id}&per_page=1");
        if (!is_wp_error($response)) {
            $commits = json_decode(wp_remote_retrieve_body($response), true);
            return $commits[0]['commit']['author']['date'] ?? '';
        }
        return '';
    }

    /**
     * Get changelog from plugin directory
     */
    private function get_changelog($plugin_id) {
        $response = wp_remote_get("{$this->github_api_url}/contents/{$plugin_id}/changelog.txt");
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = json_decode(wp_remote_retrieve_body($response), true);
            return isset($content['content']) ? base64_decode($content['content']) : __('No changelog available.', 'ss-updater');
        }
        return __('No changelog available.', 'ss-updater');
    }

    /**
     * Get GitHub zip download URL
     */
    private function get_zip_url($plugin_id) {
        return "https://github.com/{$this->username}/{$this->repository}/archive/refs/heads/{$this->branch}.zip";
    }
}

new SS_Plugins_Updater();