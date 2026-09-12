<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// REST wiring for the RSS importer module. All business logic already
// exists in inc/rss.php / inc/rss-importer.php (feed CRUD, manual trigger,
// rolling log) — this file only exposes it over coffeebrk/v1, since
// previously it was only reachable via wp-admin form posts.

add_action( 'rest_api_init', 'coffeebrk_rss_register_rest_routes' );

function coffeebrk_rss_register_rest_routes() {
    $namespace = 'coffeebrk/v1';

    register_rest_route( $namespace, '/rss-feeds', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_rss_api_get_feeds',
        'args'                => [
            'orderby' => [ 'type' => 'string', 'default' => 'id' ],
            'order'   => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
            'enabled' => [ 'type' => 'boolean' ],
        ],
    ]);

    register_rest_route( $namespace, '/rss-feeds/activity', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_rss_api_get_activity',
        'args'                => [ 'limit' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ] ],
    ]);

    register_rest_route( $namespace, '/rss-feeds/stats', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_rss_api_get_stats',
    ]);

    register_rest_route( $namespace, '/rss-feeds/run-all', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_rss_api_run_all',
    ]);

    register_rest_route( $namespace, '/rss-feeds', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_rss_api_create_feed',
    ]);

    register_rest_route( $namespace, '/rss-feeds/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_rss_api_get_feed',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/rss-feeds/(?P<id>\d+)', [
        'methods'             => [ 'PUT', 'PATCH' ],
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_rss_api_update_feed',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/rss-feeds/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => 'coffeebrk_api_permission_delete',
        'callback'            => 'coffeebrk_rss_api_delete_feed',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/rss-feeds/(?P<id>\d+)/run', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_rss_api_run_feed',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);
}

function coffeebrk_rss_api_get_feeds( WP_REST_Request $req ) {
    $args = [ 'orderby' => (string) $req->get_param( 'orderby' ), 'order' => (string) $req->get_param( 'order' ) ];
    $enabled = $req->get_param( 'enabled' );
    if ( $enabled !== null ) {
        $args['enabled'] = (bool) $enabled;
    }

    $feeds = coffeebrk_rss_get_feeds( $args );
    return new WP_REST_Response( [ 'success' => true, 'total' => count( $feeds ), 'items' => $feeds ], 200 );
}

function coffeebrk_rss_api_get_feed( WP_REST_Request $req ) {
    $feed = coffeebrk_rss_get_feed( (int) $req->get_param( 'id' ) );
    if ( ! $feed ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'feed_not_found' ], 404 );
    }
    return new WP_REST_Response( [ 'success' => true, 'feed' => $feed ], 200 );
}

function coffeebrk_rss_api_create_feed( WP_REST_Request $req ) {
    $params = coffeebrk_get_request_params( $req );
    $res = coffeebrk_rss_save_feed( $params );
    if ( empty( $res['ok'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => $res['error'] ?? 'save_failed' ], 400 );
    }
    return new WP_REST_Response( [ 'success' => true, 'id' => $res['id'], 'feed' => coffeebrk_rss_get_feed( (int) $res['id'] ) ], 201 );
}

function coffeebrk_rss_api_update_feed( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    $existing = coffeebrk_rss_get_feed( $id );
    if ( ! $existing ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'feed_not_found' ], 404 );
    }

    $params = array_merge( $existing, coffeebrk_get_request_params( $req ) );
    $res = coffeebrk_rss_save_feed( $params, $id );
    if ( empty( $res['ok'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => $res['error'] ?? 'save_failed' ], 400 );
    }
    return new WP_REST_Response( [ 'success' => true, 'feed' => coffeebrk_rss_get_feed( $id ) ], 200 );
}

function coffeebrk_rss_api_delete_feed( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    if ( ! coffeebrk_rss_get_feed( $id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'feed_not_found' ], 404 );
    }
    $ok = coffeebrk_rss_delete_feed( $id );
    return new WP_REST_Response( [ 'success' => $ok, 'id' => $id ], $ok ? 200 : 500 );
}

function coffeebrk_rss_api_run_feed( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    if ( ! coffeebrk_rss_get_feed( $id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'feed_not_found' ], 404 );
    }
    $res = coffeebrk_rss_import_feed( $id, 'manual' );
    return new WP_REST_Response( array_merge( [ 'success' => (bool) ( $res['ok'] ?? false ) ], $res ), 200 );
}

function coffeebrk_rss_api_run_all( WP_REST_Request $req ) {
    $res = coffeebrk_rss_import_all_enabled_feeds( 'manual' );
    return new WP_REST_Response( array_merge( [ 'success' => (bool) ( $res['ok'] ?? false ) ], $res ), 200 );
}

function coffeebrk_rss_api_get_activity( WP_REST_Request $req ) {
    $limit = max( 1, min( 200, (int) $req->get_param( 'limit' ) ) );
    $log = array_reverse( coffeebrk_rss_log_get_last_24h() );
    return new WP_REST_Response( [ 'success' => true, 'total' => count( $log ), 'items' => array_slice( $log, 0, $limit ) ], 200 );
}

function coffeebrk_rss_api_get_stats( WP_REST_Request $req ) {
    $feeds = coffeebrk_rss_get_feeds();
    $enabled = count( array_filter( $feeds, fn( $f ) => (int) $f['enabled'] === 1 ) );
    $next = wp_next_scheduled( 'coffeebrk_rss_import_all' );

    return new WP_REST_Response( [
        'success'      => true,
        'total_feeds'  => count( $feeds ),
        'enabled_feeds'=> $enabled,
        'next_cron_at' => $next ? gmdate( 'c', (int) $next ) : null,
    ], 200 );
}
