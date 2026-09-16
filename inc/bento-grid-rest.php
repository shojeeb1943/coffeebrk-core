<?php
/**
 * Public "load more" endpoint for the Coffeebrk Bento Grid widget's
 * pagination (Load More button / infinite scroll). Read-only, no auth -
 * it only ever returns published post/cbk_story cards, same as the
 * widget's own server-side render.
 *
 * @package Coffeebrk_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function() {
    register_rest_route( 'coffeebrk/v1', '/bento-grid', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'coffeebrk_bento_grid_rest_load_more',
        'args' => [
            'paged' => [ 'type' => 'integer', 'default' => 2, 'minimum' => 2 ],
            'content_types' => [ 'type' => 'string', 'default' => 'both', 'enum' => [ 'both', 'post', 'cbk_story' ] ],
            'video_ratio_min' => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 10 ],
            'video_ratio_max' => [ 'type' => 'integer', 'default' => 4, 'minimum' => 1, 'maximum' => 10 ],
            'interleave_seed' => [ 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 999999 ],
            'posts_per_page' => [ 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 50 ],
            'orderby' => [ 'type' => 'string', 'default' => 'date', 'enum' => [ 'date', 'title', 'rand' ] ],
            'order' => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
            'article_template_id' => [ 'type' => 'integer', 'default' => 0 ],
            'video_template_id' => [ 'type' => 'integer', 'default' => 0 ],
            'source_display' => [ 'type' => 'string', 'default' => 'author', 'enum' => [ 'author', 'category', 'meta', 'none' ] ],
            'source_meta_key' => [ 'type' => 'string', 'default' => '_source_name' ],
            'show_date' => [ 'type' => 'string', 'default' => 'yes' ],
            'date_format' => [ 'type' => 'string', 'default' => 'F j, Y' ],
            'show_external_icon' => [ 'type' => 'string', 'default' => 'yes' ],
            'video_aspect' => [ 'type' => 'string', 'default' => '9-16', 'enum' => [ '9-16', '16-9', '1-1', '4-5' ] ],
        ],
    ] );
} );

function coffeebrk_bento_grid_rest_load_more( WP_REST_Request $req ) {
    if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'elementor_unavailable' ], 500 );
    }

    if ( ! class_exists( '\\Coffeebrk_Bento_Grid_Widget' ) ) {
        require_once COFFEEBRK_CORE_PATH . 'inc/widgets/class-coffeebrk-bento-grid-widget.php';
    }

    $settings = [
        'content_types' => sanitize_key( $req->get_param( 'content_types' ) ),
        'video_ratio_min' => max( 1, (int) $req->get_param( 'video_ratio_min' ) ),
        'video_ratio_max' => max( 1, (int) $req->get_param( 'video_ratio_max' ) ),
        'interleave_seed' => (int) $req->get_param( 'interleave_seed' ),
        'posts_per_page' => (int) $req->get_param( 'posts_per_page' ),
        'orderby' => sanitize_key( $req->get_param( 'orderby' ) ),
        'order' => strtoupper( (string) $req->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC',
        'article_template_id' => (int) $req->get_param( 'article_template_id' ),
        'video_template_id' => (int) $req->get_param( 'video_template_id' ),
        'source_display' => sanitize_key( $req->get_param( 'source_display' ) ),
        'source_meta_key' => sanitize_text_field( (string) $req->get_param( 'source_meta_key' ) ),
        'show_date' => $req->get_param( 'show_date' ) === 'yes' ? 'yes' : '',
        'date_format' => sanitize_text_field( (string) $req->get_param( 'date_format' ) ),
        'show_external_icon' => $req->get_param( 'show_external_icon' ) === 'yes' ? 'yes' : '',
        'video_aspect' => sanitize_key( $req->get_param( 'video_aspect' ) ),
    ];

    $paged = max( 2, (int) $req->get_param( 'paged' ) );

    $widget = new \Coffeebrk_Bento_Grid_Widget();
    $result = $widget->render_items_page( $settings, $paged );

    return new WP_REST_Response( [
        'success' => true,
        'html' => $result['html'],
        'current_page' => $paged,
        'total_pages' => $result['total_pages'],
        'has_more' => $paged < $result['total_pages'],
    ], 200 );
}
