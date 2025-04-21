<?php
/*
Plugin Name: Stupid Simple Login Check
Description: Adds a honeypot field, nonce check, and brute-force protection to the Login page to prevent automated login attempts.
Version: 1.2.1
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit; // Ensure the script is not accessed directly
}

class Stupid_Simple_Login_Checker {

    private $max_attempts = 5;
    private $lockout_duration = 300; // in seconds (5 minutes)
    private $option_key = 'sslc_locked_ips';

    public function __construct() {
        add_action('login_form', array($this, 'add_honeypot_and_nonce'));
        add_filter('authenticate', array($this, 'check_login'), 30, 3);
        add_action('wp_login_failed', array($this, 'track_failed_login'));
        add_action('admin_menu', array($this, 'setup_admin_menu'));
    }

    public function add_honeypot_and_nonce() {
        echo '<input type="hidden" name="sslc_honeypot" value="" id="sslc_honeypot" autocomplete="off" />';
        echo '<div id="sslc_honeypot_wrap" style="display:none;">';
        echo '<label for="sslc_honeypot_visual">Honeypot</label>';
        echo '<input type="text" id="sslc_honeypot_visual" name="sslc_honeypot_visual" autocomplete="off" tabindex="-1">';
        echo '</div>';
        wp_nonce_field('sslc_login_nonce', 'sslc_nonce');
    }

    public function check_login($user, $username, $password) {
        $ip = $this->get_user_ip();

        $locked_ips = get_option($this->option_key, array());
        if (isset($locked_ips[$ip]) && time() < $locked_ips[$ip]['locked_until']) {
            return new WP_Error('sslc_locked', __('<strong>ERROR</strong>: Too many failed login attempts. Try again later.', 'stupid-simple-login-checker'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log'])) {
            if (!isset($_POST['sslc_nonce']) || !wp_verify_nonce($_POST['sslc_nonce'], 'sslc_login_nonce')) {
                return new WP_Error('sslc_error', __('<strong>ERROR</strong>: Spam detected!', 'stupid-simple-login-checker'));
            }
            if (!empty($_POST['sslc_honeypot']) || !empty($_POST['sslc_honeypot_visual'])) {
                return new WP_Error('sslc_error', __('<strong>ERROR</strong>: Spam detected!', 'stupid-simple-login-checker'));
            }
        }

        return $user;
    }

    public function track_failed_login($username) {
        $ip = $this->get_user_ip();
        $locked_ips = get_option($this->option_key, array());

        if (!isset($locked_ips[$ip])) {
            $locked_ips[$ip] = array('attempts' => 0, 'locked_until' => 0);
        }

        $locked_ips[$ip]['attempts']++;
        if ($locked_ips[$ip]['attempts'] >= $this->max_attempts) {
            $locked_ips[$ip]['locked_until'] = time() + $this->lockout_duration;
            $locked_ips[$ip]['attempts'] = 0; // reset attempts after lockout
        }

        update_option($this->option_key, $locked_ips);
    }

    private function get_user_ip() {
        return $_SERVER['REMOTE_ADDR'];
    }

    public function setup_admin_menu() {
        add_menu_page(
            'Stupid Simple',
            'Stupid Simple',
            'manage_options',
            'stupidsimple',
            function () {
                wp_redirect('https://bedynamic.tech/stupid-simple/');
                exit;
            },
            'dashicons-hammer',
            99
        );

        add_submenu_page(
            'stupidsimple',
            'Login Check',
            'Login Check',
            'manage_options',
            'sslc-lockout-log',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle unblock before any output
        if (isset($_GET['unblock_ip']) && current_user_can('manage_options')) {
            $unblock_ip = sanitize_text_field($_GET['unblock_ip']);
            $locked_ips = get_option($this->option_key, array());
            unset($locked_ips[$unblock_ip]);
            update_option($this->option_key, $locked_ips);
            wp_safe_redirect(remove_query_arg('unblock_ip'));
            exit;
        }

        $locked_ips = get_option($this->option_key, array());

        echo '<div class="wrap"><h1>Currently Locked Out IPs</h1><table class="widefat"><thead><tr><th>IP Address</th><th>Locked Until</th><th>Action</th></tr></thead><tbody>';

        foreach ($locked_ips as $ip => $data) {
            if (time() < $data['locked_until']) {
                echo '<tr><td>' . esc_html($ip) . '</td><td>' . date('Y-m-d H:i:s', $data['locked_until']) . '</td><td><a href="' . esc_url(add_query_arg(['unblock_ip' => $ip])) . '" class="button">Unlock</a></td></tr>';
            }
        }

        echo '</tbody></table></div>';
    }
}

new Stupid_Simple_Login_Checker();
