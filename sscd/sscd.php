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

// Disable comments and trackbacks support from all post types
function sscd_disable_comments() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }

    // Remove comment feed and related headers
    remove_action('wp_head', 'feed_links_extra', 3);
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
}
add_action('init', 'sscd_disable_comments', 10);

// Remove comments menu from admin
function sscd_remove_comments_menu() {
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'sscd_remove_comments_menu');

// Block comment submissions but still allow viewing posts
function sscd_block_comment_submission() {
    if (
        is_singular() &&
        isset($_POST['comment']) &&
        'POST' === $_SERVER['REQUEST_METHOD']
    ) {
        wp_die(__('Comments are disabled on this site.', 'your-text-domain'), '', array('response' => 403));
    }
}
add_action('template_redirect', 'sscd_block_comment_submission');

// Prevent access to comment-related admin settings
function sscd_disable_comments_admin() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }

    global $pagenow;
    if ($pagenow === 'options-discussion.php') {
        wp_die(__('Comments are disabled on this site.', 'your-text-domain'), '', array('response' => 403));
    }
}
add_action('admin_init', 'sscd_disable_comments_admin');

// Block access to comments popup page
function sscd_kill_comment_pages() {
    if (is_singular() && is_comments_popup()) {
        wp_die(__('Comments are closed.'), '', array('response' => 403));
    }
}
add_action('template_redirect', 'sscd_kill_comment_pages');

// Force comments and pings to be closed across the site
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// Override default options to keep comments off for new posts
add_filter('pre_option_default_comment_status', '__return_false');
add_filter('pre_option_default_ping_status', '__return_false');

// Hide comment-related widgets
function sscd_disable_comment_widgets() {
    unregister_widget('WP_Widget_Recent_Comments');
}
add_action('widgets_init', 'sscd_disable_comment_widgets', 1);
