<?php
/*
Plugin Name: Stupid Simple Login Check
Description: Adds a honeypot field and nonce check to the Login page to prevent automated login attempts.
Version: 1.1
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit; // Ensure the script is not accessed directly
}

class Stupid_Simple_Login_Checker {

    public function __construct() {
        // Hook to add a honeypot and nonce field to the login form
        add_action('login_form', array($this, 'add_honeypot_and_nonce'));
        // Hook to check for the nonce and honeypot before allowing login
        add_filter('authenticate', array($this, 'check_login'), 30, 3);
    }

    /**
     * Adds a honeypot field and a nonce check to the login form
     */
    public function add_honeypot_and_nonce() {
        // Output hidden honeypot field
        echo '<input type="hidden" name="sslc_honeypot" value="" id="sslc_honeypot" autocomplete="off" />';
        
        // Output hidden visual honeypot field (to trick bots)
        echo '<div id="sslc_honeypot_wrap" style="display:none;">';
        echo '<label for="sslc_honeypot_visual">Honeypot</label>';
        echo '<input type="text" id="sslc_honeypot_visual" name="sslc_honeypot_visual" autocomplete="off" tabindex="-1">';
        echo '</div>';
        
        // Output nonce field to protect against CSRF
        wp_nonce_field('sslc_login_nonce', 'sslc_nonce');
    }

    /**
     * Validates the login form input for nonce and honeypot fields.
     *
     * @param WP_User|WP_Error|null $user The user object or WP_Error instance.
     * @param string $username The username submitted.
     * @param string $password The password submitted.
     * @return WP_User|WP_Error
     */
    public function check_login($user, $username, $password) {

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log'])) {
            // Validate nonce to prevent CSRF attacks
            if (!isset($_POST['sslc_nonce']) || !wp_verify_nonce($_POST['sslc_nonce'], 'sslc_login_nonce')) {
                return new WP_Error('sslc_error', __('<strong>ERROR</strong>: Spam detected!', 'stupid-simple-login-checker'));
            }

            // Honeypot check: If the honeypot field has a value, it’s likely a bot
            if (!empty($_POST['sslc_honeypot'])) {
                return new WP_Error('sslc_error', __('<strong>ERROR</strong>: Spam detected!', 'stupid-simple-login-checker'));
            }

            // Check if the visual honeypot field is filled out (it shouldn’t be for humans)
            if (!empty($_POST['sslc_honeypot_visual'])) {
                return new WP_Error('sslc_error', __('<strong>ERROR</strong>: Spam detected!', 'stupid-simple-login-checker'));
            }
        }

        return $user;
    }
}

// Initialize the plugin
new Stupid_Simple_Login_Checker();
