<?php
/*
Plugin Name: SAROROCE | Extended Media Library (ACF | Taxonomies | Elementor | Etc.)
Plugin URI: https://github.com/saroroce/saroroce-extended-media-library
Description: A plugin to extend the media library functionality in WordPress, adding a "Used In" column and allowing filtering and sorting images based on their usage.
Version: 1.0
Author: SAROROCE | Web Development 
Author URI: https://github.com/saroroce
License: ISC
License URI: https://opensource.org/licenses/ISC
Text Domain: saroroce-extended-media-library
Requires at least: 5.8
Requires PHP: 7.4
*/

// Ensure WordPress context
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Ensure WordPress functions are available
if (!function_exists('wp_get_attachment_url')) {
    require_once(ABSPATH . 'wp-load.php');
}

// 1. Add the "Used In" column in the third position
function add_media_usage_column_third($columns) {
    $columns = array_merge(
        array_slice($columns, 0, 2),
        ['media_usage' => 'Used In'],
        array_slice($columns, 2)
    );
    return $columns;
}
add_filter('manage_media_columns', 'add_media_usage_column_third');

// 2. Display the data in the new column
function show_media_usage_column_data($column_name, $post_id) {
    if ($column_name !== 'media_usage') {
        return;
    }

    global $wpdb;
    $usage = [];
    $used_ids = [];

    // Check if image is used in the thumbnail
    $thumb_posts = $wpdb->get_results($wpdb->prepare("\n        SELECT ID, post_title FROM {$wpdb->prefix}posts \n        WHERE post_type NOT IN ('attachment', 'revision') \n        AND ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d)\n    ", $post_id));

    foreach ($thumb_posts as $post) {
        if (!in_array($post->ID, $used_ids)) {
            $usage[] = '<a href="' . get_edit_post_link($post->ID) . '" target="_blank">📌 ' . esc_html($post->post_title) . '</a>';
            $used_ids[] = $post->ID;
        }
    }

    // Check if image is used in any post or page content by ID
    $content_posts = $wpdb->get_results($wpdb->prepare("\n        SELECT ID, post_title FROM {$wpdb->prefix}posts \n        WHERE post_type NOT IN ('attachment', 'revision') \n        AND post_content LIKE %s\n    ", '%wp-image-' . $post_id . '%'));

    foreach ($content_posts as $post) {
        if (!in_array($post->ID, $used_ids)) {
            $usage[] = '<a href="' . get_edit_post_link($post->ID) . '" target="_blank">📄 ' . esc_html($post->post_title) . '</a>';
            $used_ids[] = $post->ID;
        }
    }

    // Check if image is used in ACF without revisions
    $acf_posts = $wpdb->get_results($wpdb->prepare("\n        SELECT post_id FROM {$wpdb->prefix}postmeta \n        WHERE post_id NOT IN (SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'revision')\n        AND (meta_value = %d OR meta_value LIKE %s OR meta_value LIKE %s)\n    ", $post_id, '%"' . $post_id . '"%', '%' . wp_get_attachment_url($post_id) . '%'));

    foreach ($acf_posts as $post) {
        if (!in_array($post->post_id, $used_ids)) {
            $usage[] = '<a href="' . get_edit_post_link($post->post_id) . '" target="_blank">🔧 ACF: Post ID ' . $post->post_id . '</a>';
            $used_ids[] = $post->post_id;
        }
    }

    // Check if image is used in any taxonomy terms by ID
    $term_posts = $wpdb->get_results($wpdb->prepare("\n        SELECT term_id FROM {$wpdb->prefix}termmeta \n        WHERE meta_value = %d\n    ", $post_id));

    foreach ($term_posts as $term) {
        if (!in_array($term->term_id, $used_ids)) {
            $taxonomy = get_term($term->term_id)->taxonomy;
            $usage[] = '<a href="' . get_edit_term_link($term->term_id, $taxonomy) . '" target="_blank">🏷️ Term: ' . $term->term_id . ' (' . $taxonomy . ')</a>';
            $used_ids[] = $term->term_id;
        }
    }

    // Check if image is used in Advanced Ads plugin
    $advanced_ads_posts = $wpdb->get_results($wpdb->prepare("\n        SELECT post_id FROM {$wpdb->prefix}postmeta \n        WHERE meta_key LIKE %s AND meta_value LIKE %s\n    ", 'advads_%', '%' . $post_id . '%'));

    foreach ($advanced_ads_posts as $post) {
        if (!in_array($post->post_id, $used_ids)) {
            $usage[] = '<a href="' . get_edit_post_link($post->post_id) . '" target="_blank">📢 Advanced Ads: Post ID ' . $post->post_id . '</a>';
            $used_ids[] = $post->post_id;
        }
    }

    // If file is not used
    if (empty($usage)) {
        echo '<em>Not used</em>';
    } else {
        echo implode('<br>', $usage);
    }
}
add_action('manage_media_custom_column', 'show_media_usage_column_data', 10, 2);

// 3. Enqueue custom script for selecting unused media
function enqueue_custom_media_script() {
    wp_enqueue_script('custom-media-script', plugin_dir_url(__FILE__) . 'js/custom-media-script.js', ['jquery'], '1.0', true);
}
add_action('admin_enqueue_scripts', 'enqueue_custom_media_script');

