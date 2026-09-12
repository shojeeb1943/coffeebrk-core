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

    // Single-tweet ingestion — mirrors youtube-importer-rest.php's pattern so
    // n8n (or any pipeline) can POST one already-scraped tweet per call.
    // Accepts the raw/near-raw Apify tweet object; no schema is enforced via
    // 'args' here since coffeebrk_x_normalize_apify_item() reads it defensively.
    register_rest_route( $namespace, '/x-posts', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_x_api_create_post',
    ]);

    register_rest_route( $namespace, '/x-posts/bulk', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_x_api_bulk_create_posts',
        'args'                => [
            'posts' => [
                'type'     => 'array',
                'required' => true,
                'items'    => [ 'type' => 'object' ],
            ],
        ],
    ]);

    register_rest_route( $namespace, '/x-posts/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_x_api_get_post',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
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
        'id'              => (int) $profile['id'],
        'username'        => (string) $profile['username'],
        'display_name'    => (string) ( $profile['display_name'] ?? '' ),
        'avatar_url'      => (string) ( $profile['avatar_url'] ?? '' ),
        'followers_count' => (int) ( $profile['followers_count'] ?? 0 ),
        'is_verified'     => (bool) ( $profile['is_verified'] ?? 0 ),
        'category'        => $category,
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
        'lang'        => (string) ( $post['lang'] ?? '' ),
        'is_reply'    => (bool) $post['is_reply'],
        'is_retweet'  => (bool) ( $post['is_retweet'] ?? 0 ),
        'is_quote'    => (bool) ( $post['is_quote'] ?? 0 ),
        'is_featured' => (bool) $post['is_featured'],
        'status'      => (string) $post['status'],
        'metrics'     => [
            'likes'     => (int) $post['like_count'],
            'reposts'   => (int) $post['retweet_count'],
            'replies'   => (int) $post['reply_count'],
            'views'     => (int) ( $post['view_count'] ?? 0 ),
            'quotes'    => (int) ( $post['quote_count'] ?? 0 ),
            'bookmarks' => (int) ( $post['bookmark_count'] ?? 0 ),
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

// Core of POST /x-posts and /x-posts/bulk: normalize one raw Apify-shaped
// tweet item, resolve/auto-create its profile, snapshot the author onto
// that profile, and insert the post (idempotently, by tweet_id).
function coffeebrk_x_ingest_post_item( array $item ) : array {
    $tweet_id_probe = coffeebrk_x_normalize_apify_item( $item, 0 );
    if ( $tweet_id_probe === null ) {
        return [ 'ok' => false, 'error' => 'missing_tweet_id' ];
    }

    $author_username = (string) $tweet_id_probe['author_username'];
    if ( $author_username === '' ) {
        return [ 'ok' => false, 'error' => 'missing_author' ];
    }

    $profile = coffeebrk_x_get_or_create_profile_by_username( $author_username );
    if ( ! $profile ) {
        return [ 'ok' => false, 'error' => 'profile_save_failed' ];
    }

    $author = coffeebrk_x_extract_apify_author( $item );
    coffeebrk_x_update_profile_author_snapshot( (int) $profile['id'], $author );

    $tweet_id = (string) $tweet_id_probe['tweet_id'];
    $existing_id = coffeebrk_x_post_exists_by_tweet_id( $tweet_id );
    if ( $existing_id ) {
        return [ 'ok' => true, 'skipped' => true, 'id' => $existing_id, 'tweet_id' => $tweet_id ];
    }

    $row = coffeebrk_x_normalize_apify_item( $item, (int) $profile['id'] );
    if ( $row === null || ! coffeebrk_x_insert_post_if_new( $row ) ) {
        return [ 'ok' => false, 'error' => 'insert_failed' ];
    }

    $new_id = coffeebrk_x_post_exists_by_tweet_id( $tweet_id );

    return [ 'ok' => true, 'skipped' => false, 'id' => $new_id, 'tweet_id' => $tweet_id, 'permalink' => $row['permalink'] ];
}

// POST /x-posts - single-tweet ingestion for n8n/Apify pipelines.
function coffeebrk_x_api_create_post( WP_REST_Request $req ) {
    $params = coffeebrk_get_request_params( $req );
    if ( empty( $params ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'empty_body' ], 400 );
    }

    $res = coffeebrk_x_ingest_post_item( $params );
    if ( empty( $res['ok'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => $res['error'] ?? 'failed' ], 400 );
    }

    if ( ! empty( $res['skipped'] ) ) {
        return new WP_REST_Response( [
            'success' => true, 'skipped' => true, 'id' => $res['id'], 'tweet_id' => $res['tweet_id'],
            'message' => 'Tweet already imported.',
        ], 200 );
    }

    return new WP_REST_Response( [
        'success' => true, 'skipped' => false, 'id' => $res['id'],
        'tweet_id' => $res['tweet_id'], 'permalink' => $res['permalink'] ?? '',
    ], 201 );
}

// POST /x-posts/bulk - array of raw tweet items, same ingestion per item.
function coffeebrk_x_api_bulk_create_posts( WP_REST_Request $req ) {
    $items = $req->get_param( 'posts' );
    if ( ! is_array( $items ) || empty( $items ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'empty_posts' ], 400 );
    }

    $created = 0;
    $skipped = 0;
    $errors = [];

    foreach ( $items as $item ) {
        if ( ! is_array( $item ) ) continue;
        $res = coffeebrk_x_ingest_post_item( $item );
        if ( empty( $res['ok'] ) ) {
            $errors[] = [ 'error' => $res['error'] ?? 'failed' ];
            continue;
        }
        if ( ! empty( $res['skipped'] ) ) {
            $skipped++;
        } else {
            $created++;
        }
    }

    return new WP_REST_Response( [
        'success' => true, 'created' => $created, 'skipped' => $skipped,
        'total' => $created + $skipped, 'errors' => $errors,
    ], 200 );
}

