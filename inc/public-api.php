<?php
/**
 * Coffeebrk Public REST API
 *
 * Read-only public endpoints for the Chrome extension and other
 * unauthenticated consumers.
 *
 * @package Coffeebrk_Core
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'coffeebrk_register_public_routes' );

/**
 * Force CORS headers for all public routes BEFORE WordPress's own handler
 * (which fires at priority 10 and can emit an empty Access-Control-Allow-Origin).
 */
add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
    if ( strpos( $request->get_route(), '/coffeebrk/v1/public/' ) === 0 ) {
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type' );
        if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            status_header( 204 );
            exit;
        }
    }
    return $served;
}, 5, 3 );

/**
 * Register public (no-auth) read-only routes.
 */
function coffeebrk_register_public_routes() {
    $ns = 'coffeebrk/v1';

    // GET /public/posts — paginated list of published posts
    register_rest_route( $ns, '/public/posts', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'coffeebrk_public_get_posts',
        'args'                => [
            'page'     => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
            'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50 ],
            'category' => [ 'type' => 'string',  'default' => '' ],
            'search'   => [ 'type' => 'string',  'default' => '' ],
        ],
    ]);

    // GET /public/categories — list of categories
    register_rest_route( $ns, '/public/categories', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'coffeebrk_public_get_categories',
    ]);
}

/**
 * GET /public/posts
 */
function coffeebrk_public_get_posts( WP_REST_Request $req ) {
    $page     = max( 1, (int) $req->get_param( 'page' ) );
    $per_page = max( 1, min( 50, (int) $req->get_param( 'per_page' ) ) );
    $cat_slug = sanitize_title( $req->get_param( 'category' ) );
    $search   = sanitize_text_field( $req->get_param( 'search' ) );

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'paged'          => $page,
        'posts_per_page' => $per_page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ( $cat_slug !== '' ) {
        $args['category_name'] = $cat_slug;
    }
    if ( $search !== '' ) {
        $args['s'] = $search;
    }

    $query = new WP_Query( $args );
    $items = [];

    foreach ( $query->posts as $post ) {
        $thumb_id  = get_post_thumbnail_id( $post->ID );
        $image_url = $thumb_id
            ? wp_get_attachment_image_url( $thumb_id, 'medium_large' )
            : get_post_meta( $post->ID, '_image', true );

        $categories = [];
        $cats = wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] );
        if ( is_array( $cats ) ) {
            foreach ( $cats as $cat ) {
                $categories[] = [
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ];
            }
        }

        $items[] = [
            'id'         => (int) $post->ID,
            'title'      => get_the_title( $post ),
            'excerpt'    => wp_trim_words( wp_strip_all_tags( $post->post_content ), 25 ),
            'permalink'  => get_permalink( $post ),
            'image'      => $image_url ?: null,
            'date'       => $post->post_date,
            'source'     => get_post_meta( $post->ID, '_source_name', true ) ?: null,
            'source_url' => get_post_meta( $post->ID, '_source_url', true ) ?: null,
            'categories' => $categories,
        ];
    }

    return new WP_REST_Response( [
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'items'       => $items,
    ], 200 );
}

/**
 * GET /public/categories
 */
function coffeebrk_public_get_categories( WP_REST_Request $req ) {
    $cats = get_categories( [
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ] );

    $items = [];
    foreach ( $cats as $cat ) {
        $items[] = [
            'name'  => $cat->name,
            'slug'  => $cat->slug,
            'count' => (int) $cat->count,
        ];
    }

    return new WP_REST_Response( $items, 200 );
}
