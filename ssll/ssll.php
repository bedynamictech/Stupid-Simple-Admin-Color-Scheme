<?php
/*
Plugin Name: Stupid Simple Login Logo
Description: Easily change the logo displayed on the Login page.
Version: 1.3.2
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Update URI: https://github.com/bedynamictech/StupidSimplePlugins/tree/main/ssll
*/

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define constant for option key
define('SSLL_OPTION', 'wp_logo_url');

// Admin Menu
add_action('admin_menu', 'ssll_add_menu');

function ssll_add_menu() {
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
        'Login Logo',
        'Login Logo',
        'manage_options',
        'login-logo',
        'ssll_settings_page_content'
    );
}

// Settings Page Content
function ssll_settings_page_content() {
    wp_enqueue_script('jquery');
    wp_enqueue_media();
    ?>
    <div class="wrap">
        <h1><?php _e('Login Logo', 'ssll'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ssll_settings_group');
            do_settings_sections('login-logo');
            submit_button();
            ?>
        </form>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var logoUrlInput = $('#wp_logo_url');
                var logoBtn = $('#logo-btn');

                function updateButtonText() {
                    logoBtn.val(logoUrlInput.val() ? '<?php echo esc_js(__('Remove Logo', 'ssll')); ?>' : '<?php echo esc_js(__('Upload Logo', 'ssll')); ?>');
                }

                updateButtonText();

                logoBtn.click(function(e) {
                    e.preventDefault();
                    if (logoBtn.val() === '<?php echo esc_js(__('Remove Logo', 'ssll')); ?>') {
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
                                    alert('<?php echo esc_js(__('Error removing logo.', 'ssll')); ?>');
                                }
                            }
                        });
                    } else {
                        var image = wp.media({
                            title: '<?php echo esc_js(__('Select Image', 'ssll')); ?>',
                            multiple: false
                        }).open()
                        .on('select', function() {
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
    register_setting('ssll_settings_group', SSLL_OPTION, 'esc_url_raw');

    add_settings_section(
        'ssll_logo_section',
        '', // Removed title to avoid extra heading
        'ssll_logo_section_callback',
        'login-logo'
    );
}

// Settings Section Callback
function ssll_logo_section_callback() {
    $logo_url = get_option(SSLL_OPTION);
    echo '<div style="margin-top: 20px;">';
    echo '<input type="text" id="wp_logo_url" name="' . esc_attr(SSLL_OPTION) . '" value="' . esc_attr($logo_url) . '" class="regular-text" />';
    echo ' <input type="button" name="logo-btn" id="logo-btn" class="button" value="' . esc_attr($logo_url ? __('Remove Logo', 'ssll') : __('Upload Logo', 'ssll')) . '" />';
    echo '<p class="description">' . esc_html(__('Use the upload button, or enter the URL of an image.', 'ssll')) . '</p>';
    echo '</div>';
}


// AJAX handler to remove the logo
function remove_login_logo() {
    check_ajax_referer('remove_login_logo_nonce', 'nonce');
    update_option(SSLL_OPTION, '');
    wp_send_json_success();
}
add_action('wp_ajax_remove_login_logo', 'remove_login_logo');

// Custom WordPress admin login header logo
function wordpress_custom_login_logo() {
    $logo_url = esc_url(get_option(SSLL_OPTION));

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

// Change login logo URL
add_filter('login_headerurl', 'change_login_logo_url');

function change_login_logo_url($url) {
    return esc_url(home_url());
}
