<?php
/*
Plugin Name: Stupid Simple Login Logo
description: Easily change the logo displayed on the Login page.
Version: 1.2
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Admin Menu
add_action('admin_menu', 'ssll_add_menu');

function ssll_add_menu() {
    add_menu_page(
        'Stupid Simple',
        'Stupid Simple',
        'manage_options',
        'stupidsimple',
        '',
        'dashicons-hammer',
        99
    );

    add_submenu_page(
        'stupidsimple',
        'Login Logo',
        'Login Logo',
        'manage_options',
        'login-logo',
        'ssll_settings_page_content' // Consistent function name
    );
}

// Settings Page Content
function ssll_settings_page_content() {
    wp_enqueue_script('jquery');
    wp_enqueue_media();
    ?>
    <div class="wrap">
        <h1>Login Logo</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ssll_settings_group');
            do_settings_sections('login-logo'); // Consistent slug
            submit_button();
            ?>
        </form>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var logoUrlInput = $('#wp_logo_url');
                var logoBtn = $('#logo-btn');

                function updateButtonText() {
                    logoBtn.val(logoUrlInput.val() ? 'Remove Logo' : 'Upload Logo');
                }

                updateButtonText();

                logoBtn.click(function(e) {
                    e.preventDefault();
                    if (logoBtn.val() === 'Remove Logo') {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'remove_login_logo',
                                nonce: '<?php echo wp_create_nonce('remove_login_logo_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    logoUrlInput.val('');
                                    updateButtonText();
                                    location.reload();
                                } else {
                                    alert('Error removing logo.');
                                }
                            }
                        });
                    } else {
                        var image = wp.media({
                            title: 'Select Image',
                            multiple: false
                        }).open()
                        .on('select', function(e) {
                            var uploaded_image = image.state().get('selection').first();
                            var image_url = uploaded_image.toJSON().url;
                            logoUrlInput.val(image_url);
                            updateButtonText();
                            $('form').submit();
                        });
                    }
                });
            });
        </script>
    </div>
    <?php
}

// Register Settings
add_action('admin_init', 'ssll_register_settings');

function ssll_register_settings() {
    register_setting('ssll_settings_group', 'wp_logo_url', 'esc_url_raw');

    add_settings_section(
        'ssll_logo_section',
        'Login Logo',
        'ssll_logo_section_callback',
        'login-logo' // Consistent slug
    );
}

// Settings Section Callback
function ssll_logo_section_callback() {
    $logo_url = get_option('wp_logo_url');
    echo '<table class="form-table">'; // Added table for consistency
    echo '<tr>';
    echo '<th scope="row"><label for="wp_logo_url">Logo URL:</label></th>'; // Added label
    echo '<td><input type="text" id="wp_logo_url" name="wp_logo_url" value="' . esc_attr($logo_url) . '" size="50" />';
    echo '<input type="button" name="logo-btn" id="logo-btn" class="button-secondary" value="' . ($logo_url ? 'Remove Logo' : 'Upload Logo') . '" />';
    echo '<p class="description">The selected image will replace the default WordPress logo on the login page.</p></td>';
    echo '</tr>';
    echo '</table>';
}

// AJAX handler to remove the logo (no changes needed here)
function remove_login_logo() {
    check_ajax_referer('remove_login_logo_nonce', 'nonce');
    update_option('wp_logo_url', '');
    wp_send_json_success();
}
add_action('wp_ajax_remove_login_logo', 'remove_login_logo');

// Custom WordPress admin login header logo (no changes needed here)
function wordpress_custom_login_logo() {
    $logo_url = esc_url(get_option('wp_logo_url'));

    if (!empty($logo_url)) {
        echo '<style type="text/css">
            h1 a {
                background-image:url(' . esc_url($logo_url) . ') !important;
                background-size: contain !important;
                background-repeat: no-repeat !important;
                background-position: center center !important;
                height: 100px !important;
                width: auto !important;
                display: block !important;
                text-indent: -9999px !important;
                overflow: hidden !important;
            }
        </style>';
    }
}
add_action('login_head', 'wordpress_custom_login_logo');

// Change login logo URL (no changes needed here)
add_filter('login_headerurl', 'change_login_logo_url');

function change_login_logo_url($url) {
    return esc_url(home_url());
}
?>