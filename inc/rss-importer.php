<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function coffeebrk_rss_get_feed( int $id ) : ?array {
    global $wpdb;
    $table = coffeebrk_rss_table_name();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
    return $row ? $row : null;
}

function coffeebrk_rss_get_feeds( array $args = [] ) : array {
    global $wpdb;
    $table = coffeebrk_rss_table_name();

    $orderby = isset($args['orderby']) ? (string) $args['orderby'] : 'id';
    $order   = isset($args['order']) ? strtoupper((string) $args['order']) : 'DESC';

    $allowed_orderby = [ 'id', 'feed_name', 'feed_url', 'enabled', 'last_import', 'last_run' ];
    if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
        $orderby = 'id';
    }
    if ( $order !== 'ASC' && $order !== 'DESC' ) {
        $order = 'DESC';
    }

    $where = '1=1';
    $params = [];

    if ( array_key_exists( 'enabled', $args ) ) {
        $where .= ' AND enabled = %d';
        $params[] = (int) (bool) $args['enabled'];
    }

    $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}";

    if ( $params ) {
        $sql = $wpdb->prepare( $sql, $params );
    }

    return (array) $wpdb->get_results( $sql, ARRAY_A );
}

function coffeebrk_rss_save_feed( array $data, ?int $id = null ) : array {
    global $wpdb;

    $table = coffeebrk_rss_table_name();

    $feed_name = sanitize_text_field( $data['feed_name'] ?? '' );
    $feed_url  = esc_url_raw( $data['feed_url'] ?? '' );
    $enabled   = isset($data['enabled']) ? (int) (bool) $data['enabled'] : 0;

    $import_limit = (int) ( $data['import_limit'] ?? 5 );
    if ( $import_limit < 1 ) $import_limit = 1;
    if ( $import_limit > 50 ) $import_limit = 50;

    $category_id = isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null;
    if ( $category_id !== null && $category_id <= 0 ) {
        $category_id = null;
    }

    if ( $feed_name === '' ) {
        return [ 'ok' => false, 'error' => 'missing_feed_name' ];
    }
    if ( $feed_url === '' ) {
        return [ 'ok' => false, 'error' => 'missing_feed_url' ];
    }

    $now = current_time( 'mysql' );

    $row = [
        'feed_name' => $feed_name,
        'feed_url' => $feed_url,
        'enabled' => $enabled,
        'import_limit' => $import_limit,
        'category_id' => $category_id,
        'updated_at' => $now,
    ];

    $formats = [ '%s', '%s', '%d', '%d', '%d', '%s' ];

    if ( $id ) {
        $where = [ 'id' => $id ];
        $where_format = [ '%d' ];
        $ok = ( false !== $wpdb->update( $table, $row, $where, $formats, $where_format ) );
        return [ 'ok' => $ok, 'id' => $id ];
    }

    $row['created_at'] = $now;
    $formats[] = '%s';

    $ok = ( false !== $wpdb->insert( $table, $row, $formats ) );
    return [ 'ok' => $ok, 'id' => (int) $wpdb->insert_id ];
}

function coffeebrk_rss_delete_feed( int $id ) : bool {
    global $wpdb;
    $table = coffeebrk_rss_table_name();
    return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
}

function coffeebrk_rss_set_feed_enabled( int $id, bool $enabled ) : bool {
    global $wpdb;
    $table = coffeebrk_rss_table_name();
    $now = current_time( 'mysql' );
    return ( false !== $wpdb->update(
        $table,
        [ 'enabled' => (int) $enabled, 'updated_at' => $now ],
        [ 'id' => $id ],
        [ '%d', '%s' ],
        [ '%d' ]
    ) );
}

function coffeebrk_rss_update_run_state( int $feed_id, array $data ) : void {
    global $wpdb;
    $table = coffeebrk_rss_table_name();

    $row = [];
    $formats = [];

    if ( array_key_exists( 'last_run', $data ) ) {
        $row['last_run'] = $data['last_run'];
        $formats[] = '%s';
    }
    if ( array_key_exists( 'last_import', $data ) ) {
        $row['last_import'] = $data['last_import'];
        $formats[] = '%s';
    }
    if ( array_key_exists( 'last_error', $data ) ) {
        $row['last_error'] = $data['last_error'];
        $formats[] = '%s';
    }

    if ( ! $row ) return;

    $row['updated_at'] = current_time( 'mysql' );
    $formats[] = '%s';

    $wpdb->update( $table, $row, [ 'id' => $feed_id ], $formats, [ '%d' ] );
}

function coffeebrk_rss_item_dedupe_key( $item ) : ?string {
    if ( ! $item ) return null;

    $link = $item->get_link();
    if ( $link ) {
        $link = esc_url_raw( $link );
        if ( $link !== '' ) return $link;
    }

    $guid = $item->get_id();
    if ( $guid ) {
        $guid = trim( (string) $guid );
        if ( $guid !== '' ) return $guid;
    }

    return null;
}

function coffeebrk_rss_post_exists_for_source_url( string $source_url ) : bool {
    $q = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => '_source_url',
                'value' => $source_url,
                'compare' => '=',
            ]
        ],
    ]);

    return ! empty( $q->posts );
}

function coffeebrk_rss_import_feed( int $feed_id ) : array {
    $feed = coffeebrk_rss_get_feed( $feed_id );
    if ( ! $feed ) return [ 'ok' => false, 'error' => 'feed_not_found' ];

    $now = current_time( 'mysql' );
    coffeebrk_rss_update_run_state( $feed_id, [ 'last_run' => $now, 'last_error' => '' ] );

    if ( (int) $feed['enabled'] !== 1 ) {
        return [ 'ok' => true, 'imported' => 0, 'skipped' => 0, 'note' => 'disabled' ];
    }

    include_once ABSPATH . WPINC . '/feed.php';

    $url = (string) $feed['feed_url'];
    $limit = (int) $feed['import_limit'];
    if ( $limit < 1 ) $limit = 1;
    if ( $limit > 50 ) $limit = 50;

    $rss = fetch_feed( $url );

    if ( is_wp_error( $rss ) ) {
        $msg = $rss->get_error_message();
        coffeebrk_rss_update_run_state( $feed_id, [ 'last_error' => $msg ] );
        if ( function_exists( 'coffeebrk_log_error' ) ) {
            coffeebrk_log_error( 'rss import fetch_feed error', [ 'feed_id' => $feed_id, 'url' => $url, 'err' => $msg ] );
        }
        return [ 'ok' => false, 'error' => 'fetch_failed', 'message' => $msg ];
    }

    $max = $rss->get_item_quantity( $limit );
    $items = $rss->get_items( 0, $max );

    $imported = 0;
    $skipped = 0;
    $newest_import_ts = null;

    foreach ( (array) $items as $item ) {
        $key = coffeebrk_rss_item_dedupe_key( $item );
        if ( ! $key ) {
            $skipped++;
            continue;
        }

        if ( coffeebrk_rss_post_exists_for_source_url( $key ) ) {
            $skipped++;
            continue;
        }

        $title = sanitize_text_field( (string) $item->get_title() );
        if ( $title === '' ) {
            $title = '(Untitled)';
        }

        $content = $item->get_content();
        if ( ! $content ) {
            $content = $item->get_description();
        }
        $content = $content ? wp_kses_post( $content ) : '';

        $post_id = wp_insert_post([
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => $content,
        ], true );

        if ( is_wp_error( $post_id ) ) {
            $skipped++;
            continue;
        }

        if ( ! empty( $feed['category_id'] ) ) {
            $cat_id = (int) $feed['category_id'];
            if ( $cat_id > 0 ) {
                wp_set_post_categories( (int) $post_id, [ $cat_id ], false );
            }
        }

        update_post_meta( (int) $post_id, '_source_name', (string) $feed['feed_name'] );
        update_post_meta( (int) $post_id, '_source_url', $key );

        $ts = $item->get_date( 'U' );
        if ( $ts ) {
            $ts = (int) $ts;
            if ( $newest_import_ts === null || $ts > $newest_import_ts ) {
                $newest_import_ts = $ts;
            }
        }

        $imported++;
    }

    if ( $imported > 0 && $newest_import_ts !== null ) {
        $gmt = gmdate( 'Y-m-d H:i:s', $newest_import_ts );
        $local = get_date_from_gmt( $gmt, 'Y-m-d H:i:s' );
        coffeebrk_rss_update_run_state( $feed_id, [ 'last_import' => $local ] );
    }

    return [ 'ok' => true, 'imported' => $imported, 'skipped' => $skipped ];
}

function coffeebrk_rss_import_all_enabled_feeds() : array {
    $feeds = coffeebrk_rss_get_feeds([ 'enabled' => 1, 'orderby' => 'id', 'order' => 'ASC' ]);

    $total_imported = 0;
    $total_skipped = 0;
    $errors = 0;

    foreach ( $feeds as $f ) {
        $res = coffeebrk_rss_import_feed( (int) $f['id'] );
        if ( empty( $res['ok'] ) ) {
            $errors++;
            continue;
        }
        $total_imported += (int) ( $res['imported'] ?? 0 );
        $total_skipped += (int) ( $res['skipped'] ?? 0 );
    }

    return [
        'ok' => true,
        'feeds' => count( $feeds ),
        'imported' => $total_imported,
        'skipped' => $total_skipped,
        'errors' => $errors,
    ];
}
