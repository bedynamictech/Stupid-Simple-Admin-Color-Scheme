<?php
/*
Plugin Name: Stupid Simple Admin Color Scheme
description: Set the default admin color scheme for all users, including new ones, and hide the color scheme selector.
Version: 1.1
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SSACS_OPTION', 'ssacs_default_scheme');

// Admin Menu
add_action('admin_menu', 'ssacs_add_menu');

function ssacs_add_menu() {
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
        'Admin Color Scheme',
        'Admin Color Scheme',
        'manage_options',
        'admin-color-scheme',
        'ssacs_settings_page_content'
    );
}

function ssacs_settings_page_content() {
    ?>
    <div class="wrap">
        <h1><?php _e('Admin Color Scheme', 'ssacs'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('ssacs_settings_group'); ?>
            <?php do_settings_sections('admin-color-scheme'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ssacs_default_scheme"><?php _e('Default Scheme:', 'ssacs'); ?></label></th>
                    <td>
                        <select name="<?php echo esc_attr(SSACS_OPTION); ?>" id="ssacs_default_scheme">
                            <?php
                            global $_wp_admin_css_colors;
                            $schemes = array_keys($_wp_admin_css_colors);
                            $current_scheme = get_option(SSACS_OPTION, 'default');
                            foreach ($schemes as $scheme) {
                                $selected = selected($scheme, $current_scheme, false);
                                echo '<option value="' . esc_attr($scheme) . '" ' . $selected . '>' . esc_html(ucfirst($scheme)) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register the settings with a callback function.
add_action('admin_init', 'ssacs_register_settings');

function ssacs_register_settings() {
    register_setting('ssacs_settings_group', SSACS_OPTION, 'ssacs_update_all_users_scheme');
}

// Callback function to update all existing users when the setting is saved.
function ssacs_update_all_users_scheme($new_value) {
    if (is_admin() && current_user_can('manage_options')) {
        $sanitized_value = sanitize_text_field($new_value);
        $users = get_users(array('fields' => array('ID')));
        foreach ($users as $user) {
            update_user_meta($user->ID, 'admin_color', $sanitized_value);
        }
        return $sanitized_value;
    }
    return $new_value;
}

// Set the default admin color for NEW users.
add_action('user_register', 'ssacs_set_default_scheme_for_new_user');

function ssacs_set_default_scheme_for_new_user($user_id) {
    $default_scheme = get_option(SSACS_OPTION);
    if ($default_scheme) {
        update_user_meta($user_id, 'admin_color', sanitize_text_field($default_scheme));
    }
}

// Enqueue admin styles to override user-set color scheme.
add_action('admin_enqueue_scripts', 'ssacs_enqueue_styles');

function ssacs_enqueue_styles() {
    $default_scheme = get_option(SSACS_OPTION);
    if ($default_scheme) {
        wp_enqueue_style('ssacs-admin-styles', plugins_url('ssacs-styles.css', __FILE__), array(), '1.0');
        $inline_css = "#wpwrap { --wp-admin-theme-color: var(--wp-admin-color-" . esc_attr($default_scheme) . "); }";
        wp_add_inline_style('ssacs-admin-styles', $inline_css);
    }
}

// Hide the admin color scheme selector for all users.
add_action('admin_init', 'ssacs_hide_color_scheme_selector');

function ssacs_hide_color_scheme_selector() {
    if (!current_user_can('manage_options')) {
        add_action('admin_print_styles', 'ssacs_hide_color_scheme_css');
    }
}

function ssacs_hide_color_scheme_css() {
    echo '<style>
        #color-picker, #admin_color, label[for="admin_color"] {
            display: none;
        }
        .appearance-php #wpcontent > .wrap > h1 {
            margin-bottom: 20px;
        }
    </style>';
}

// Optional: Add an admin notice if the default scheme setting is not set.
add_action('admin_notices', 'ssacs_admin_notice');

function ssacs_admin_notice() {
    if (current_user_can('manage_options') && !get_option(SSACS_OPTION)) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . __('The Stupid Simple Admin Color Scheme plugin is active, but you haven\'t set a default scheme yet. Please visit the <a href="' . esc_url(admin_url('admin.php?page=admin-color-scheme')) . '">Admin Color Scheme settings page</a> to configure it.', 'ssacs') . '</p>';
        echo '</div>';
    }
}
?>
