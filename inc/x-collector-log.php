<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Rolling ingestion log for the X collector, mirroring the RSS importer's
// log pattern (inc/rss-importer.php) — one option, 24h window, 800-entry cap.
// One row per ingest *call* (not per tweet): n8n calls this far more often
// than RSS cron runs, so per-tweet rows would blow past the cap quickly.

function coffeebrk_x_log_option_key() : string {
    return 'coffeebrk_x_ingest_log';
}

function coffeebrk_x_log_append( array $entry ) : void {
    $key = coffeebrk_x_log_option_key();
    $log = get_option( $key, [] );
    if ( ! is_array( $log ) ) {
        $log = [];
    }

    $entry['time'] = isset( $entry['time'] ) ? (int) $entry['time'] : time();
    $log[] = $entry;

    $since = time() - DAY_IN_SECONDS;
    $log = array_values( array_filter( $log, function( $row ) use ( $since ) {
        if ( ! is_array( $row ) ) return false;
        $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
        return $t >= $since;
    } ) );

    if ( count( $log ) > 800 ) {
        $log = array_slice( $log, -800 );
    }

    update_option( $key, $log, false );
}

function coffeebrk_x_log_get_last_24h() : array {
    $log = get_option( coffeebrk_x_log_option_key(), [] );
    if ( ! is_array( $log ) ) {
        return [];
    }

    $since = time() - DAY_IN_SECONDS;
    return array_values( array_filter( $log, function( $row ) use ( $since ) {
        if ( ! is_array( $row ) ) return false;
        $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
        return $t >= $since;
    } ) );
}

// Returns only id/name for the token that made an ingest request — never
// the hash, prefix, or last4.
function coffeebrk_x_get_request_token_info( WP_REST_Request $req ) : array {
    $token = coffeebrk_core_get_bearer_token_from_rest_request( $req );
    if ( $token === '' ) {
        return [ 'id' => null, 'name' => 'unknown' ];
    }

    $data = coffeebrk_validate_api_token( $token );
    if ( ! $data ) {
        return [ 'id' => null, 'name' => 'unknown' ];
    }

    return [ 'id' => (string) $data['id'], 'name' => (string) $data['name'] ];
}

function coffeebrk_x_get_stats() : array {
    $counts = wp_count_posts( 'cbk_x_post' );

    $imported_today = ( new WP_Query([
        'post_type'      => 'cbk_x_post',
        'post_status'    => 'any',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'date_query'     => [ [ 'column' => 'post_modified_gmt', 'after' => 'today' ] ],
    ]) )->found_posts;

    $imported_last_24h = ( new WP_Query([
        'post_type'      => 'cbk_x_post',
        'post_status'    => 'any',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'date_query'     => [ [ 'column' => 'post_modified_gmt', 'after' => '-1 day' ] ],
    ]) )->found_posts;

    $last_import_at = null;
    $latest = get_posts([
        'post_type'      => 'cbk_x_post',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ]);
    if ( ! empty( $latest ) ) {
        $last_import_at = get_post_modified_time( 'c', true, (int) $latest[0] );
    }

    $log = coffeebrk_x_log_get_last_24h();
    $last_ingest_at = null;
    if ( ! empty( $log ) ) {
        $last = end( $log );
        $last_ingest_at = ! empty( $last['time'] ) ? gmdate( 'c', (int) $last['time'] ) : null;
    }

    $tokens = array_map( function( $t ) {
        return [
            'id'          => $t['id'] ?? '',
            'name'        => $t['name'] ?? '',
            'last_used'   => $t['last_used'] ?? null,
            'permissions' => $t['permissions'] ?? [],
            'status'      => $t['status'] ?? '',
        ];
    }, coffeebrk_get_api_tokens() );

    return [
        'total'             => (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ),
        'published'         => (int) ( $counts->publish ?? 0 ),
        'draft'             => (int) ( $counts->draft ?? 0 ),
        'trash'             => (int) ( $counts->trash ?? 0 ),
        'imported_today'    => (int) $imported_today,
        'imported_last_24h' => (int) $imported_last_24h,
        'last_import_at'    => $last_import_at,
        'last_ingest_at'    => $last_ingest_at,
        'tokens'            => $tokens,
    ];
}
