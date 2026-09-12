<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Site-wide monitoring: error/login log tailing (wraps the existing
// coffeebrk_tail_file_json() reader, already used by the wp-admin Logs page)
// and a stats aggregator combining counts across every module.

add_action( 'rest_api_init', 'coffeebrk_monitoring_register_rest_routes' );

function coffeebrk_monitoring_register_rest_routes() {
    $namespace = 'coffeebrk/v1';

    // Error/login logs contain IPs, emails, and user-agents — gated behind
    // the 'manage' scope like token management, not the generic 'read' scope.
    register_rest_route( $namespace, '/logs/errors', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_manage',
        'callback'            => 'coffeebrk_monitoring_api_get_error_log',
        'args'                => [ 'limit' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ] ],
    ]);

    register_rest_route( $namespace, '/logs/logins', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_manage',
        'callback'            => 'coffeebrk_monitoring_api_get_login_log',
        'args'                => [ 'limit' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ] ],
    ]);

    // Aggregate counts only, no PII — stays at the generic 'read' scope.
    register_rest_route( $namespace, '/site-activity', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_monitoring_api_get_site_activity',
    ]);
}

function coffeebrk_monitoring_api_get_error_log( WP_REST_Request $req ) {
    $limit = max( 1, min( 200, (int) $req->get_param( 'limit' ) ) );
    $d = coffeebrk_logger_dirs();
    return new WP_REST_Response( [ 'success' => true, 'items' => coffeebrk_tail_file_json( $d['error'], $limit ) ], 200 );
}

function coffeebrk_monitoring_api_get_login_log( WP_REST_Request $req ) {
    $limit = max( 1, min( 200, (int) $req->get_param( 'limit' ) ) );
    $d = coffeebrk_logger_dirs();
    return new WP_REST_Response( [ 'success' => true, 'items' => coffeebrk_tail_file_json( $d['logins'], $limit ) ], 200 );
}

function coffeebrk_get_site_stats() : array {
    $post_counts  = wp_count_posts( 'post' );
    $story_counts = wp_count_posts( 'cbk_story' );
    $x_counts     = wp_count_posts( 'cbk_x_post' );

    $feeds = function_exists( 'coffeebrk_rss_get_feeds' ) ? coffeebrk_rss_get_feeds() : [];
    $feeds_enabled = count( array_filter( $feeds, fn( $f ) => (int) $f['enabled'] === 1 ) );
    $next_cron = wp_next_scheduled( 'coffeebrk_rss_import_all' );

    $tokens = coffeebrk_get_api_tokens();
    $tokens_active = count( array_filter( $tokens, fn( $t ) => ( $t['status'] ?? '' ) === 'active' ) );

    $d = coffeebrk_logger_dirs();
    $errors_24h = array_filter( coffeebrk_tail_file_json( $d['error'], 200 ), function( $row ) {
        $ts = strtotime( $row['ts'] ?? '' );
        return $ts && $ts >= ( time() - DAY_IN_SECONDS );
    } );
    $logins_24h = array_filter( coffeebrk_tail_file_json( $d['logins'], 200 ), function( $row ) {
        $ts = strtotime( $row['ts'] ?? '' );
        return $ts && $ts >= ( time() - DAY_IN_SECONDS );
    } );

    return [
        'posts'       => [ 'published' => (int) ( $post_counts->publish ?? 0 ), 'draft' => (int) ( $post_counts->draft ?? 0 ) ],
        'stories'     => [ 'published' => (int) ( $story_counts->publish ?? 0 ) ],
        'x_posts'     => [ 'published' => (int) ( $x_counts->publish ?? 0 ), 'draft' => (int) ( $x_counts->draft ?? 0 ) ],
        'categories'  => (int) wp_count_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] ),
        'rss_feeds'   => [ 'total' => count( $feeds ), 'enabled' => $feeds_enabled, 'next_cron_at' => $next_cron ? gmdate( 'c', (int) $next_cron ) : null ],
        'api_tokens'  => [ 'total' => count( $tokens ), 'active' => $tokens_active ],
        'errors_last_24h' => count( $errors_24h ),
        'logins_last_24h' => count( $logins_24h ),
    ];
}

function coffeebrk_monitoring_api_get_site_activity( WP_REST_Request $req ) {
    return new WP_REST_Response( array_merge( [ 'success' => true ], coffeebrk_get_site_stats() ), 200 );
}
