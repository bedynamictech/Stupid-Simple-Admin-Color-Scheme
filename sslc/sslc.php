<?php
/*
Plugin Name: Stupid Simple Login Check
description: Adds a honeypot field and nonce check to the Login page.
Version: 1.0
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

class Stupid_Simple_Login_Checker {

    public function __construct() {
        add_action('login_form', array($this, 'add_honeypot_and_nonce'));
        add_filter('authenticate', array($this, 'check_login'), 30, 3);
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

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log'])) {

            if (!isset($_POST['sslc_nonce']) || !wp_verify_nonce($_POST['sslc_nonce'], 'sslc_login_nonce')) {
                return new WP_Error('sslc_error', __('<strong>ERROR</strong>: Spam detected!', 'stupid-simple-login-checker'));
            }

            if (isset($_POST['sslc_honeypot']) && !empty($_POST['sslc_honeypot'])) {
                return new WP_Error('sslc_error', __('<strong>ERROR</strong>: Spam detected!', 'stupid-simple-login-checker'));
            }

        }

        return $user;
    }
}

new Stupid_Simple_Login_Checker();