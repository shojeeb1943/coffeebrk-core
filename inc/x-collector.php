<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// X (Twitter) collector module. Posts are pushed in via REST from an
// external n8n/Apify pipeline (inc/x-collector-rest.php) and stored as a
// real CPT (cbk_x_post) so Elementor's native Loop Grid can list it as a
// query source — the same way Stories (cbk_story) already works.

add_action( 'init', function() {
    register_post_type( 'cbk_x_post', [
        'labels' => [
            'name'               => __( 'X / Social', 'coffeebrk-core' ),
            'singular_name'      => __( 'X Post', 'coffeebrk-core' ),
            'add_new'            => __( 'Add New X Post', 'coffeebrk-core' ),
            'add_new_item'       => __( 'Add New X Post', 'coffeebrk-core' ),
            'edit_item'          => __( 'Edit X Post', 'coffeebrk-core' ),
            'new_item'           => __( 'New X Post', 'coffeebrk-core' ),
            'view_item'          => __( 'View X Post', 'coffeebrk-core' ),
            'search_items'       => __( 'Search X Posts', 'coffeebrk-core' ),
            'not_found'          => __( 'No X posts found', 'coffeebrk-core' ),
            'not_found_in_trash' => __( 'No X posts found in Trash', 'coffeebrk-core' ),
            'all_items'          => __( 'X / Social', 'coffeebrk-core' ),
        ],
        'public'              => true, // Must be public for Elementor's Loop Grid to list it as a query source
        'exclude_from_search' => true, // ...but keep it out of the site's default search results
        'show_ui'             => true,
        'show_in_menu'        => 'coffeebrk-core', // Nest under Coffeebrk Core
        'menu_position'       => 21,
        'supports'            => [ 'title', 'editor' ],
        'hierarchical'        => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
});

// One-time cleanup: pre-n8n versions scheduled a recurring Apify cron sync.
// It's no longer used, so clear it if a site still has it scheduled.
add_action( 'init', function() {
    $ts = wp_next_scheduled( 'coffeebrk_x_sync_all' );
    if ( $ts ) {
        wp_unschedule_event( $ts, 'coffeebrk_x_sync_all' );
    }
});

require_once __DIR__ . '/x-collector-data.php';
require_once __DIR__ . '/x-collector-normalizer.php';
require_once __DIR__ . '/x-collector-rest.php';

if ( is_admin() ) {
    require_once __DIR__ . '/x-collector-admin.php';
}
