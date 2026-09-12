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
            'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
            'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
            'featured' => [ 'type' => 'boolean' ],
            'orderby'  => [ 'type' => 'string', 'default' => 'date', 'enum' => [ 'date', 'modified' ] ],
            'order'    => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
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

    register_rest_route( $namespace, '/x-posts/(?P<id>\d+)', [
        'methods'             => [ 'PUT', 'PATCH' ],
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_x_api_update_post',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/x-posts/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => 'coffeebrk_api_permission_delete',
        'callback'            => 'coffeebrk_x_api_delete_post',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/x-posts/activity', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_x_api_get_activity',
        'args'                => [ 'limit' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ] ],
    ]);

    register_rest_route( $namespace, '/x-posts/stats', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_x_api_get_stats',
    ]);
}

// -- response formatting -------------------------------------------------

function coffeebrk_x_format_post_response( WP_Post $post ) : array {
    $id = $post->ID;
    $media = json_decode( (string) get_post_meta( $id, '_cbk_x_media_json', true ), true );
    if ( ! is_array( $media ) ) $media = [];

    return [
        'id'          => $id,
        'platform'    => 'x',
        'author'      => [
            'username'        => (string) get_post_meta( $id, '_cbk_x_author_username', true ),
            'display_name'    => (string) get_post_meta( $id, '_cbk_x_author_display_name', true ),
            'avatar_url'      => (string) get_post_meta( $id, '_cbk_x_author_avatar_url', true ),
            'followers_count' => (int) get_post_meta( $id, '_cbk_x_author_followers', true ),
            'is_verified'     => (bool) get_post_meta( $id, '_cbk_x_author_verified', true ),
        ],
        'external_id' => (string) get_post_meta( $id, '_cbk_x_tweet_id', true ),
        'url'         => (string) get_post_meta( $id, '_cbk_x_permalink', true ),
        'text'        => $post->post_content,
        'posted_at'   => $post->post_date_gmt,
        'lang'        => (string) get_post_meta( $id, '_cbk_x_lang', true ),
        'is_reply'    => (bool) get_post_meta( $id, '_cbk_x_is_reply', true ),
        'is_retweet'  => (bool) get_post_meta( $id, '_cbk_x_is_retweet', true ),
        'is_quote'    => (bool) get_post_meta( $id, '_cbk_x_is_quote', true ),
        'is_featured' => (bool) get_post_meta( $id, '_cbk_x_is_featured', true ),
        'status'      => $post->post_status,
        'metrics'     => [
            'likes'     => (int) get_post_meta( $id, '_cbk_x_like_count', true ),
            'reposts'   => (int) get_post_meta( $id, '_cbk_x_retweet_count', true ),
            'replies'   => (int) get_post_meta( $id, '_cbk_x_reply_count', true ),
            'views'     => (int) get_post_meta( $id, '_cbk_x_view_count', true ),
            'quotes'    => (int) get_post_meta( $id, '_cbk_x_quote_count', true ),
            'bookmarks' => (int) get_post_meta( $id, '_cbk_x_bookmark_count', true ),
        ],
        'media'       => $media,
    ];
}

// -- handlers --------------------------------------------------------------

function coffeebrk_x_api_get_posts( WP_REST_Request $req ) {
    $page     = max( 1, (int) $req->get_param( 'page' ) );
    $per_page = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
    $featured = $req->get_param( 'featured' );
    $orderby  = sanitize_key( (string) $req->get_param( 'orderby' ) );
    $order    = strtoupper( (string) $req->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';

    $args = [
        'post_type'      => 'cbk_x_post',
        'post_status'    => 'publish',
        'paged'          => $page,
        'posts_per_page' => $per_page,
        'orderby'        => $orderby === 'modified' ? 'modified' : 'date',
        'order'          => $order,
    ];
    if ( $featured !== null ) {
        $args['meta_query'] = [
            [ 'key' => '_cbk_x_is_featured', 'value' => (int) (bool) $featured, 'compare' => '=' ],
        ];
    }

    $query = new WP_Query( $args );

    $items = [];
    foreach ( $query->posts as $post ) {
        $items[] = coffeebrk_x_format_post_response( $post );
    }

    return new WP_REST_Response( [
        'success'     => true,
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'items'       => $items,
    ], 200 );
}

function coffeebrk_x_api_get_post( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'cbk_x_post' ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'post_not_found' ], 404 );
    }
    return new WP_REST_Response( [ 'success' => true, 'post' => coffeebrk_x_format_post_response( $post ) ], 200 );
}

// PUT/PATCH /x-posts/{id} - publish/draft status and featured toggle. Wraps
// the existing coffeebrk_x_set_post_status()/coffeebrk_x_set_post_featured()
// helpers already used by the admin UI (inc/x-collector-data.php).
function coffeebrk_x_api_update_post( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'cbk_x_post' ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'post_not_found' ], 404 );
    }

    $params = coffeebrk_get_request_params( $req );

    if ( array_key_exists( 'status', $params ) ) {
        coffeebrk_x_set_post_status( $id, (string) $params['status'] );
    }
    if ( array_key_exists( 'is_featured', $params ) ) {
        coffeebrk_x_set_post_featured( $id, (bool) $params['is_featured'] );
    }

    $post = get_post( $id );
    return new WP_REST_Response( [ 'success' => true, 'post' => coffeebrk_x_format_post_response( $post ) ], 200 );
}

// DELETE /x-posts/{id} - trash (wraps coffeebrk_x_delete_post()).
function coffeebrk_x_api_delete_post( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'cbk_x_post' ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'post_not_found' ], 404 );
    }

    $ok = coffeebrk_x_delete_post( $id );
    return new WP_REST_Response( [ 'success' => $ok, 'id' => $id ], $ok ? 200 : 500 );
}

// GET /x-posts/activity - recent ingestion log entries (newest first).
function coffeebrk_x_api_get_activity( WP_REST_Request $req ) {
    $limit = max( 1, min( 200, (int) $req->get_param( 'limit' ) ) );
    $log = array_reverse( coffeebrk_x_log_get_last_24h() );

    return new WP_REST_Response( [
        'success' => true,
        'total'   => count( $log ),
        'items'   => array_slice( $log, 0, $limit ),
    ], 200 );
}

// GET /x-posts/stats - aggregate counts + token usage snapshot.
function coffeebrk_x_api_get_stats( WP_REST_Request $req ) {
    return new WP_REST_Response( array_merge( [ 'success' => true ], coffeebrk_x_get_stats() ), 200 );
}

// Core of POST /x-posts and /x-posts/bulk: normalize one raw Apify-shaped
// tweet item and insert it as a cbk_x_post (idempotently, by tweet_id).
function coffeebrk_x_ingest_post_item( array $item ) : array {
    $data = coffeebrk_x_normalize_apify_item( $item );
    if ( $data === null ) {
        return [ 'ok' => false, 'error' => 'missing_tweet_id' ];
    }
    if ( $data['author_username'] === '' ) {
        return [ 'ok' => false, 'error' => 'missing_author' ];
    }

    $tweet_id = $data['tweet_id'];
    $existing_id = coffeebrk_x_post_exists_by_tweet_id( $tweet_id );
    if ( $existing_id ) {
        return [ 'ok' => true, 'skipped' => true, 'id' => $existing_id, 'tweet_id' => $tweet_id ];
    }

    $post_id = coffeebrk_x_insert_post_row( $data );
    if ( is_wp_error( $post_id ) ) {
        return [ 'ok' => false, 'error' => $post_id->get_error_message() ];
    }

    return [ 'ok' => true, 'skipped' => false, 'id' => $post_id, 'tweet_id' => $tweet_id, 'permalink' => $data['permalink'] ];
}

// POST /x-posts - single-tweet ingestion for n8n/Apify pipelines.
function coffeebrk_x_api_create_post( WP_REST_Request $req ) {
    $token_info = coffeebrk_x_get_request_token_info( $req );
    $params = coffeebrk_get_request_params( $req );
    if ( empty( $params ) ) {
        coffeebrk_x_log_append([
            'event' => 'single', 'status' => 'error', 'reason' => 'empty_body',
            'token_id' => $token_info['id'], 'token_name' => $token_info['name'],
            'created' => 0, 'skipped' => 0, 'total' => 0,
        ]);
        return new WP_REST_Response( [ 'success' => false, 'error' => 'empty_body' ], 400 );
    }

    $res = coffeebrk_x_ingest_post_item( $params );
    if ( empty( $res['ok'] ) ) {
        $reason = $res['error'] ?? 'failed';
        coffeebrk_x_log_append([
            'event' => 'single', 'status' => 'error', 'reason' => $reason,
            'token_id' => $token_info['id'], 'token_name' => $token_info['name'],
            'created' => 0, 'skipped' => 0, 'total' => 0,
        ]);
        coffeebrk_log_error( 'x ingest single failed: ' . $reason, [ 'source' => 'x_collector', 'token_id' => $token_info['id'] ] );
        return new WP_REST_Response( [ 'success' => false, 'error' => $reason ], 400 );
    }

    if ( ! empty( $res['skipped'] ) ) {
        coffeebrk_x_log_append([
            'event' => 'single', 'status' => 'skip',
            'token_id' => $token_info['id'], 'token_name' => $token_info['name'],
            'created' => 0, 'skipped' => 1, 'total' => 1,
            'tweet_ids' => [ $res['tweet_id'] ],
        ]);
        return new WP_REST_Response( [
            'success' => true, 'skipped' => true, 'id' => $res['id'], 'tweet_id' => $res['tweet_id'],
            'message' => 'Tweet already imported.',
        ], 200 );
    }

    coffeebrk_x_log_append([
        'event' => 'single', 'status' => 'ok',
        'token_id' => $token_info['id'], 'token_name' => $token_info['name'],
        'created' => 1, 'skipped' => 0, 'total' => 1,
        'tweet_ids' => [ $res['tweet_id'] ],
    ]);

    return new WP_REST_Response( [
        'success' => true, 'skipped' => false, 'id' => $res['id'],
        'tweet_id' => $res['tweet_id'], 'permalink' => $res['permalink'] ?? '',
    ], 201 );
}

// POST /x-posts/bulk - array of raw tweet items, same ingestion per item.
function coffeebrk_x_api_bulk_create_posts( WP_REST_Request $req ) {
    $token_info = coffeebrk_x_get_request_token_info( $req );
    $items = $req->get_param( 'posts' );
    if ( ! is_array( $items ) || empty( $items ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'empty_posts' ], 400 );
    }

    $created = 0;
    $skipped = 0;
    $errors = [];
    $tweet_ids = [];

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
        if ( ! empty( $res['tweet_id'] ) && count( $tweet_ids ) < 20 ) {
            $tweet_ids[] = $res['tweet_id'];
        }
    }

    coffeebrk_x_log_append([
        'event' => 'bulk', 'status' => empty( $errors ) ? 'ok' : 'partial',
        'token_id' => $token_info['id'], 'token_name' => $token_info['name'],
        'created' => $created, 'skipped' => $skipped, 'total' => $created + $skipped,
        'tweet_ids' => $tweet_ids, 'errors' => array_slice( $errors, 0, 20 ),
    ]);

    if ( ! empty( $errors ) ) {
        coffeebrk_log_error( 'x bulk ingest had errors', [
            'source' => 'x_collector', 'token_id' => $token_info['id'],
            'count' => count( $errors ), 'sample' => array_slice( $errors, 0, 5 ),
        ]);
    }

    return new WP_REST_Response( [
        'success' => true, 'created' => $created, 'skipped' => $skipped,
        'total' => $created + $skipped, 'errors' => $errors,
    ], 200 );
}
