<?php
/**
 * Plugin Name: Stupid Simple Admin Color Scheme
 * Description: Set the default admin color scheme for all users, including new ones, and hide the color scheme selector.
 * Version: 1.2
 * Author: Dynamic Technologies
 * Author URI: http://bedynamic.tech
 * Plugin URI: https://github.com/bedynamictech/StupidSimplePlugins
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define option key
define('SSACS_OPTION', 'ssacs_default_scheme');

// Add main menu and submenu
add_action('admin_menu', 'ssacs_add_menu');

function ssacs_add_menu() {
    global $menu;
    $parent_exists = false;
    foreach ($menu as $item) {
        if (!empty($item[2]) && $item[2] === 'stupidsimple') {
            $parent_exists = true;
            break;
        }
    }

    if (!$parent_exists) {
        add_menu_page(
            'Stupid Simple',
            'Stupid Simple',
            'manage_options',
            'stupidsimple',
            'ssacs_settings_page_content',
            'dashicons-hammer',
            99
        );
    }

    add_submenu_page(
        'stupidsimple',
        'Admin Color Scheme',
        'Admin Color Scheme',
        'manage_options',
        'admin-color-scheme',
        'ssacs_settings_page_content'
    );
}

// Settings page content
function ssacs_settings_page_content() {
    ?>
    <div class="wrap">
        <h1><?php _e('Admin Color Scheme', 'ssacs'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ssacs_settings_group');
            do_settings_sections('admin-color-scheme');
            $current_scheme = get_option(SSACS_OPTION, 'default');
            global $_wp_admin_css_colors;
            ?>
            <select name="<?php echo esc_attr(SSACS_OPTION); ?>" id="ssacs_default_scheme">
                <?php
                foreach (array_keys($_wp_admin_css_colors) as $scheme) {
                    echo '<option value="' . esc_attr($scheme) . '" ' . selected($scheme, $current_scheme, false) . '>' . esc_html(ucfirst($scheme)) . '</option>';
                }
                ?>
            </select>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'ssacs_register_settings');

function ssacs_register_settings() {
    register_setting('ssacs_settings_group', SSACS_OPTION, 'ssacs_update_all_users_scheme');
}

// Update all users' color scheme when setting is saved
function ssacs_update_all_users_scheme($new_value) {
    if (is_admin() && current_user_can('manage_options')) {
        $sanitized = sanitize_text_field($new_value);
        foreach (get_users(array('fields' => array('ID'))) as $user) {
            update_user_meta($user->ID, 'admin_color', $sanitized);
        }
        return $sanitized;
    }
    return $new_value;
}

// Apply scheme to new users
add_action('user_register', 'ssacs_set_default_scheme_for_new_user');

function ssacs_set_default_scheme_for_new_user($user_id) {
    $default = get_option(SSACS_OPTION);
    if ($default) {
        update_user_meta($user_id, 'admin_color', sanitize_text_field($default));
    }
}

// Apply color scheme styling override using inline CSS only
add_action('admin_enqueue_scripts', 'ssacs_enqueue_styles');

function ssacs_enqueue_styles() {
    $scheme = get_option(SSACS_OPTION);
    if ($scheme) {
        wp_register_style('ssacs-inline-style', false);
        wp_enqueue_style('ssacs-inline-style');
        $inline_css = "#wpwrap { --wp-admin-theme-color: var(--wp-admin-color-" . esc_attr($scheme) . "); }";
        wp_add_inline_style('ssacs-inline-style', $inline_css);
    }
}

// Hide color scheme selector
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
    </style>';
}

// Admin notice if no scheme set
add_action('admin_notices', 'ssacs_admin_notice');

function ssacs_admin_notice() {
    if (current_user_can('manage_options') && !get_option(SSACS_OPTION)) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . __('The Stupid Simple Admin Color Scheme plugin is active, but you haven\'t set a default scheme yet. Please visit the <a href="' . esc_url(admin_url('admin.php?page=admin-color-scheme')) . '">settings page</a> to configure it.', 'ssacs') . '</p>';
        echo '</div>';
    }
}

// Add Settings link
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ssacs_action_links');

function ssacs_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=admin-color-scheme') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
?>
