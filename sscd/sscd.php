<?php
/*
Plugin Name: Stupid Simple Comments Disabler
Description: Disables the ability to add comments on the entire site.
Version: 1.0
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

function sscd_disable_comments() {
    if (is_admin()) {
        add_filter('show_admin_bar', '__return_false');
    }

    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}
add_action('init', 'sscd_disable_comments', 99);

function sscd_remove_comments_menu() {
    if (is_admin()) {
        remove_menu_page('edit-comments.php');
    }
}
add_action('admin_menu', 'sscd_remove_comments_menu');

function sscd_redirect_comment_form() {
    if (is_singular() && comments_open() && 'POST' === $_SERVER['REQUEST_METHOD']) {
        wp_safe_redirect(home_url());
        exit;
    }
}
add_action('template_redirect', 'sscd_redirect_comment_form');

function sscd_disable_comments_admin() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
    global $wp_admin_canonical;
    if ($wp_admin_canonical == 'options-discussion.php') {
        wp_die(__('Comments are disabled on this site.', 'your-text-domain'), '', array('response' => 403));
    }
}

add_action('admin_init', 'sscd_disable_comments_admin');

function sscd_kill_comment_pages() {
    if (is_singular() && is_comments_popup()) {
        wp_die(__('Comments are closed.'), '', array('response' => 403));
    }
}
add_action('template_redirect', 'sscd_kill_comment_pages');

function sscd_remove_comment_headers() {
    remove_filter('wp_headers', 'wc_cart_count_fragment_callback');
    remove_action('wp_head', 'feed_links_extra', 3);
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
}
add_action('init', 'sscd_remove_comment_headers');
