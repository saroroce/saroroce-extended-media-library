<?php
/*
Plugin Name: SAROROCE | Extended Media Library (ACF | Taxonomies | Elementor | Etc.)
Description: A plugin to extend the media library functionality in WordPress, adding a "Used In" column and allowing filtering and sorting images based on their usage.
Version: 1.0
Author: SAROROCE
Author URI: https://github.com/saroroce
License: ISC
License URI: https://opensource.org/licenses/ISC
Text Domain: saroroce-extended-media-library
Requires at least: 5.8
Requires PHP: 7.4
*/

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

    // Check if image is used in the thumbnail
    $thumb_posts = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title FROM {$wpdb->prefix}posts 
        WHERE post_type NOT IN ('attachment') 
        AND ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d)
    ", $post_id));

    foreach ($thumb_posts as $post) {
        $usage[] = '<a href="' . get_edit_post_link($post->ID) . '" target="_blank">📌 ' . esc_html($post->post_title) . '</a>';
    }

    // Check if image is used in content
    $content_posts = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title FROM {$wpdb->prefix}posts 
        WHERE post_type NOT IN ('attachment') 
        AND post_content LIKE %s
    ", '%' . $wpdb->esc_like(wp_get_attachment_url($post_id)) . '%'));

    foreach ($content_posts as $post) {
        $usage[] = '<a href="' . get_edit_post_link($post->ID) . '" target="_blank">📄 ' . esc_html($post->post_title) . '</a>';
    }

    // Check if image is used in ACF
    $acf_posts = $wpdb->get_results($wpdb->prepare("
        SELECT post_id FROM {$wpdb->prefix}postmeta 
        WHERE meta_value = %d OR meta_value LIKE %s
    ", $post_id, '%"' . $post_id . '"%'));

    foreach ($acf_posts as $post) {
        $usage[] = '<a href="' . get_edit_post_link($post->post_id) . '" target="_blank">🔧 ACF: Post ID ' . $post->post_id . '</a>';
    }

    // If file is not used
    if (empty($usage)) {
        echo '<em>Not used</em>';
        update_post_meta($post_id, 'media_usage_status', 'unused');
    } else {
        echo implode('<br>', $usage);
        update_post_meta($post_id, 'media_usage_status', 'used');
    }
}
add_action('manage_media_custom_column', 'show_media_usage_column_data', 10, 2);

// 3. Filters for usage
function add_media_usage_filter() {
    if (get_current_screen()->id !== 'upload') {
        return;
    }

    $filter_value = isset($_GET['filter_media_usage']) ? $_GET['filter_media_usage'] : '';

    echo '<select name="filter_media_usage">';
    echo '<option value="">— Filter by usage —</option>';
    echo '<option value="unused"' . selected($filter_value, 'unused', false) . '>Unused</option>';
    echo '<option value="used"' . selected($filter_value, 'used', false) . '>Used</option>';
    echo '</select>';

    submit_button(__('Filter'), '', '', false);
}
add_action('restrict_manage_posts', 'add_media_usage_filter');

// 4. Implement filtering
function filter_media_by_usage($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'attachment') {
        return;
    }

    if (isset($_GET['filter_media_usage']) && $_GET['filter_media_usage'] === 'unused') {
        $query->set('meta_key', 'media_usage_status');
        $query->set('meta_value', 'unused');
    } elseif (isset($_GET['filter_media_usage']) && $_GET['filter_media_usage'] === 'used') {
        $query->set('meta_key', 'media_usage_status');
        $query->set('meta_value', 'used');
    }
}
add_action('pre_get_posts', 'filter_media_by_usage');

// 5. Implement sorting by the "Used In" column
function media_usage_column_sortable($columns) {
    $columns['media_usage'] = 'media_usage';
    return $columns;
}
add_filter('manage_edit-media_sortable_columns', 'media_usage_column_sortable');

// 6. Sorting by the "Used In" column
function sort_media_usage_column($vars) {
    if (isset($vars['orderby']) && $vars['orderby'] === 'media_usage') {
        $vars = array_merge($vars, [
            'meta_key' => 'media_usage_status',
            'orderby'  => 'meta_value'
        ]);
    }
    return $vars;
}
add_filter('request', 'sort_media_usage_column');