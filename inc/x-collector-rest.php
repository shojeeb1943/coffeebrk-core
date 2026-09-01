<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'coffeebrk_x_register_rest_routes' );

function coffeebrk_x_register_rest_routes() {
    $namespace = 'coffeebrk/v1';

    register_rest_route( $namespace, '/x-posts', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_x_api_get_posts',
        'args'                => [
            'page'       => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
            'per_page'   => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
            'profile_id' => [ 'type' => 'integer', 'default' => 0 ],
            'category'   => [ 'type' => 'integer', 'default' => 0 ],
            'featured'   => [ 'type' => 'boolean' ],
            'orderby'    => [ 'type' => 'string', 'default' => 'posted_at', 'enum' => [ 'posted_at', 'created_at' ] ],
            'order'      => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
        ],
    ]);

    register_rest_route( $namespace, '/x-posts/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_x_api_get_post',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/x-profiles', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_x_api_get_profiles',
        'args'                => [
            'enabled' => [ 'type' => 'boolean', 'default' => true ],
        ],
    ]);
}

// -- response formatting -------------------------------------------------
// Only normalized fields ever leave these formatters: no Apify token,
// actor id, or raw Apify field names are read from settings/DB here.

function coffeebrk_x_format_profile_ref( array $profile ) : array {
    $category = null;
    $cid = (int) ( $profile['category_id'] ?? 0 );
    if ( $cid > 0 ) {
        $term = get_term( $cid, 'category' );
        if ( $term && ! is_wp_error( $term ) ) {
            $category = [ 'id' => (int) $term->term_id, 'name' => $term->name ];
        }
    }

    return [
        'id'           => (int) $profile['id'],
        'username'     => (string) $profile['username'],
        'display_name' => (string) ( $profile['display_name'] ?? '' ),
        'category'     => $category,
    ];
}

function coffeebrk_x_format_post_response( array $post, ?array $profile = null ) : array {
    if ( $profile === null ) {
        $profile = coffeebrk_x_get_profile( (int) $post['profile_id'] );
    }

    $media = json_decode( (string) ( $post['media_json'] ?? '' ), true );
    if ( ! is_array( $media ) ) $media = [];

    return [
        'id'          => (int) $post['id'],
        'platform'    => 'x',
        'profile'     => $profile ? coffeebrk_x_format_profile_ref( $profile ) : null,
        'external_id' => (string) $post['tweet_id'],
        'url'         => (string) $post['permalink'],
        'text'        => (string) $post['text'],
        'posted_at'   => $post['posted_at'] ? (string) $post['posted_at'] : null,
        'is_reply'    => (bool) $post['is_reply'],
        'is_featured' => (bool) $post['is_featured'],
        'status'      => (string) $post['status'],
        'metrics'     => [
            'likes'   => (int) $post['like_count'],
            'reposts' => (int) $post['retweet_count'],
            'replies' => (int) $post['reply_count'],
        ],
        'media'       => $media,
    ];
}

// -- handlers --------------------------------------------------------------

function coffeebrk_x_api_get_posts( WP_REST_Request $req ) {
    $page       = max( 1, (int) $req->get_param( 'page' ) );
    $per_page   = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
    $profile_id = (int) $req->get_param( 'profile_id' );
    $category   = (int) $req->get_param( 'category' );
    $featured   = $req->get_param( 'featured' );
    $orderby    = sanitize_key( (string) $req->get_param( 'orderby' ) );
    $order      = strtoupper( (string) $req->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';

    $args = [ 'page' => $page, 'per_page' => $per_page, 'orderby' => $orderby, 'order' => $order ];
    if ( $profile_id > 0 ) $args['profile_id'] = $profile_id;
    if ( $category > 0 )   $args['category_id'] = $category;
    if ( $featured !== null ) $args['featured'] = (bool) $featured;
    $args['status'] = 'published';

    $result = coffeebrk_x_get_posts( $args );

    $items = [];
    foreach ( $result['posts'] as $post ) {
        $items[] = coffeebrk_x_format_post_response( $post );
    }

    return new WP_REST_Response( [
        'success'     => true,
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => $result['total'],
        'total_pages' => $per_page > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
        'items'       => $items,
    ], 200 );
}

function coffeebrk_x_api_get_post( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    $post = coffeebrk_x_get_post( $id );
    if ( ! $post ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'post_not_found' ], 404 );
    }
    return new WP_REST_Response( [ 'success' => true, 'post' => coffeebrk_x_format_post_response( $post ) ], 200 );
}

function coffeebrk_x_api_get_profiles( WP_REST_Request $req ) {
    $enabled = $req->get_param( 'enabled' );
    $args = [];
    if ( $enabled !== null && $enabled !== '' ) {
        $args['enabled'] = (bool) $enabled;
    }

    $profiles = coffeebrk_x_get_profiles( $args );
    $items = [];
    foreach ( $profiles as $profile ) {
        $ref = coffeebrk_x_format_profile_ref( $profile );
        $ref['enabled'] = (bool) $profile['enabled'];
        $items[] = $ref;
    }

    return new WP_REST_Response( [ 'success' => true, 'total' => count( $items ), 'items' => $items ], 200 );
}
