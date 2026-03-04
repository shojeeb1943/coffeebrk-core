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
 * Handle CORS preflight requests early before WordPress processes anything.
 * This catches OPTIONS requests at the init stage.
 */
add_action( 'init', function() {
    // Check if this is a REST API request to our public endpoints
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $request_uri, '/wp-json/coffeebrk/v1/public' ) !== false ) {
        // Remove any existing CORS headers WordPress might have set
        header_remove( 'Access-Control-Allow-Origin' );

        // Set proper CORS headers
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With' );
        header( 'Access-Control-Max-Age: 86400' );

        // Handle preflight OPTIONS request
        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            status_header( 204 );
            exit;
        }
    }
}, 1 );

/**
 * Force CORS headers for all public routes via REST filter.
 * This runs before WordPress's own CORS handler.
 */
add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
    $route = $request->get_route();
    // Check both with and without leading slash
    if ( strpos( $route, '/coffeebrk/v1/public' ) === 0 ||
         strpos( $route, 'coffeebrk/v1/public' ) === 0 ) {
        // Remove any existing CORS headers to prevent duplicates
        header_remove( 'Access-Control-Allow-Origin' );

        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With' );

        if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            status_header( 204 );
            exit;
        }
    }
    return $served;
}, 1, 3 );

/**
 * Also hook into rest_send_cors_headers to ensure proper CORS.
 */
add_filter( 'rest_send_cors_headers', function( $value ) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $request_uri, '/wp-json/coffeebrk/v1/public' ) !== false ) {
        header_remove( 'Access-Control-Allow-Origin' );
        header( 'Access-Control-Allow-Origin: *' );
        return false; // Prevent WordPress from sending its own headers
    }
    return $value;
}, 1 );

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

    // GET /public/stories — list of stories for carousel
    register_rest_route( $ns, '/public/stories', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'coffeebrk_public_get_stories',
        'args'                => [
            'limit' => [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 20 ],
        ],
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

/**
 * GET /public/stories
 */
function coffeebrk_public_get_stories( WP_REST_Request $req ) {
    $limit = max( 1, min( 20, (int) $req->get_param( 'limit' ) ) );

    $args = [
        'post_type'      => 'cbk_story',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => '_cbk_story_show_frontend',
                'value'   => 'yes',
                'compare' => '=',
            ],
            [
                'key'     => '_cbk_story_show_frontend',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ];

    $query = new WP_Query( $args );
    $items = [];

    foreach ( $query->posts as $post ) {
        $thumb_id  = get_post_thumbnail_id( $post->ID );
        $image_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
        $video_url = get_post_meta( $post->ID, '_cbk_story_video_url', true );
        $gradient  = get_post_meta( $post->ID, '_cbk_story_gradient', true ) ?: '#F5F5FF';
        $text_color = get_post_meta( $post->ID, '_cbk_story_text_color', true ) ?: '#323232';
        $gradient_intensity = get_post_meta( $post->ID, '_cbk_story_gradient_intensity', true );
        if ( $gradient_intensity === '' ) {
            $gradient_intensity = 50;
        }

        $items[] = [
            'id'                 => (int) $post->ID,
            'title'              => get_the_title( $post ),
            'image'              => $image_url ?: null,
            'video_url'          => $video_url ?: null,
            'gradient'           => $gradient,
            'text_color'         => $text_color,
            'gradient_intensity' => (int) $gradient_intensity,
            'date'               => $post->post_date,
        ];
    }

    return new WP_REST_Response( [
        'total' => (int) $query->found_posts,
        'items' => $items,
    ], 200 );
}
