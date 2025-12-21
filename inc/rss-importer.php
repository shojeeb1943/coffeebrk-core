<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function coffeebrk_rss_log_option_key() : string {
    return 'coffeebrk_rss_import_log';
}

function coffeebrk_rss_log_append( array $entry ) : void {
    $key = coffeebrk_rss_log_option_key();
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

function coffeebrk_rss_log_get_last_24h() : array {
    $log = get_option( coffeebrk_rss_log_option_key(), [] );
    if ( ! is_array( $log ) ) {
        return [];
    }

    $since = time() - DAY_IN_SECONDS;
    $log = array_values( array_filter( $log, function( $row ) use ( $since ) {
        if ( ! is_array( $row ) ) return false;
        $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
        return $t >= $since;
    } ) );

    return $log;
}

function coffeebrk_rss_force_refresh_feed_cache( string $url ) : void {
    $url = trim( $url );
    if ( $url === '' ) return;

    $hash = md5( $url );
    delete_transient( 'feed_' . $hash );
    delete_transient( 'feed_mod_' . $hash );

    if ( function_exists( 'wp_cache_delete' ) ) {
        wp_cache_delete( 'feed_' . $hash, 'transient' );
        wp_cache_delete( 'feed_mod_' . $hash, 'transient' );
    }
}

function coffeebrk_rss_normalize_image_url( $url ) : string {
    $url = is_string( $url ) ? trim( $url ) : '';
    if ( $url === '' ) {
        return '';
    }

    if ( strpos( $url, '//' ) === 0 ) {
        $url = 'https:' . $url;
    }

    $url = esc_url_raw( $url );
    return is_string( $url ) ? $url : '';
}

function coffeebrk_rss_extract_first_img_src( $html ) : string {
    $html = is_string( $html ) ? $html : '';
    if ( $html === '' ) {
        return '';
    }

    if ( preg_match( '/<img\s[^>]*src\s*=\s*("([^"]+)"|\'([^\']+)\'|([^\s>]+))/i', $html, $m ) === 1 ) {
        $src = '';
        if ( isset( $m[2] ) && $m[2] !== '' ) $src = $m[2];
        elseif ( isset( $m[3] ) && $m[3] !== '' ) $src = $m[3];
        elseif ( isset( $m[4] ) && $m[4] !== '' ) $src = $m[4];

        return coffeebrk_rss_normalize_image_url( html_entity_decode( (string) $src, ENT_QUOTES ) );
    }

    return '';
}

function coffeebrk_rss_extract_image_url_from_item( $item, string $content_html = '' ) : string {
    if ( ! $item ) {
        return '';
    }

    if ( method_exists( $item, 'get_enclosure' ) ) {
        $enc = $item->get_enclosure();
        if ( $enc && method_exists( $enc, 'get_link' ) ) {
            $enc_url = coffeebrk_rss_normalize_image_url( $enc->get_link() );
            if ( $enc_url !== '' ) {
                return $enc_url;
            }
        }
    }

    if ( method_exists( $item, 'get_item_tags' ) ) {
        $candidates = [];
        $media = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'content' );
        if ( is_array( $media ) ) {
            foreach ( $media as $t ) {
                $attrs = $t['attribs'][''] ?? [];
                if ( ! empty( $attrs['url'] ) ) {
                    $candidates[] = $attrs['url'];
                }
            }
        }
        $thumb = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'thumbnail' );
        if ( is_array( $thumb ) ) {
            foreach ( $thumb as $t ) {
                $attrs = $t['attribs'][''] ?? [];
                if ( ! empty( $attrs['url'] ) ) {
                    $candidates[] = $attrs['url'];
                }
            }
        }

        foreach ( $candidates as $c ) {
            $c = coffeebrk_rss_normalize_image_url( $c );
            if ( $c !== '' ) {
                return $c;
            }
        }
    }

    $from_content = coffeebrk_rss_extract_first_img_src( $content_html );
    if ( $from_content !== '' ) {
        return $from_content;
    }

    if ( method_exists( $item, 'get_description' ) ) {
        $from_desc = coffeebrk_rss_extract_first_img_src( (string) $item->get_description() );
        if ( $from_desc !== '' ) {
            return $from_desc;
        }
    }

    return '';
}

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

function coffeebrk_rss_get_existing_post_id_by_source_url( string $source_url ) : int {
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

    if ( ! empty( $q->posts[0] ) ) {
        return (int) $q->posts[0];
    }

    return 0;
}

function coffeebrk_rss_post_exists_for_source_url( string $source_url ) : bool {
    return coffeebrk_rss_get_existing_post_id_by_source_url( $source_url ) > 0;
}

function coffeebrk_rss_import_feed( int $feed_id, string $context = 'manual' ) : array {
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

    coffeebrk_rss_force_refresh_feed_cache( $url );

    $cbk_rss_cache_lifetime_filter = function() {
        return 0;
    };
    add_filter( 'wp_feed_cache_transient_lifetime', $cbk_rss_cache_lifetime_filter, 999 );
    $rss = fetch_feed( $url );
    remove_filter( 'wp_feed_cache_transient_lifetime', $cbk_rss_cache_lifetime_filter, 999 );

    if ( is_wp_error( $rss ) ) {
        $msg = $rss->get_error_message();
        coffeebrk_rss_update_run_state( $feed_id, [ 'last_error' => $msg ] );
        if ( function_exists( 'coffeebrk_log_error' ) ) {
            coffeebrk_log_error( 'rss import fetch_feed error', [ 'feed_id' => $feed_id, 'url' => $url, 'err' => $msg ] );
        }
        coffeebrk_rss_log_append([
            'event' => 'feed_run',
            'status' => 'error',
            'context' => $context,
            'feed_id' => $feed_id,
            'feed_name' => (string) ( $feed['feed_name'] ?? '' ),
            'feed_url' => $url,
            'imported' => 0,
            'skipped' => 0,
            'message' => $msg,
        ]);
        return [ 'ok' => false, 'error' => 'fetch_failed', 'message' => $msg ];
    }

    $max = $rss->get_item_quantity( $limit );
    $items = $rss->get_items( 0, $max );

    $imported = 0;
    $skipped = 0;
    $newest_import_ts = null;
    $skip_logs_added = 0;
    $skip_logs_limit = 50;

    foreach ( (array) $items as $item ) {
        $key = coffeebrk_rss_item_dedupe_key( $item );
        if ( ! $key ) {
            $skipped++;
            if ( $skip_logs_added < $skip_logs_limit ) {
                $skip_logs_added++;
                $title = sanitize_text_field( (string) $item->get_title() );
                coffeebrk_rss_log_append([
                    'event' => 'skipped_item',
                    'status' => 'skip',
                    'context' => $context,
                    'feed_id' => $feed_id,
                    'feed_name' => (string) ( $feed['feed_name'] ?? '' ),
                    'feed_url' => $url,
                    'title' => $title,
                    'reason' => 'missing_dedupe_key',
                ]);
            }
            continue;
        }

        $existing_post_id = coffeebrk_rss_get_existing_post_id_by_source_url( $key );
        if ( $existing_post_id > 0 ) {
            $skipped++;
            if ( $skip_logs_added < $skip_logs_limit ) {
                $skip_logs_added++;
                $title = sanitize_text_field( (string) $item->get_title() );
                coffeebrk_rss_log_append([
                    'event' => 'skipped_item',
                    'status' => 'skip',
                    'context' => $context,
                    'feed_id' => $feed_id,
                    'feed_name' => (string) ( $feed['feed_name'] ?? '' ),
                    'feed_url' => $url,
                    'post_id' => (int) $existing_post_id,
                    'title' => $title,
                    'source_url' => $key,
                    'reason' => 'duplicate_source_url',
                ]);
            }
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

        $image_url = coffeebrk_rss_extract_image_url_from_item( $item, (string) $content );

        $post_id = wp_insert_post([
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => $content,
        ], true );

        if ( is_wp_error( $post_id ) ) {
            $skipped++;
            if ( $skip_logs_added < $skip_logs_limit ) {
                $skip_logs_added++;
                coffeebrk_rss_log_append([
                    'event' => 'skipped_item',
                    'status' => 'error',
                    'context' => $context,
                    'feed_id' => $feed_id,
                    'feed_name' => (string) ( $feed['feed_name'] ?? '' ),
                    'feed_url' => $url,
                    'title' => $title,
                    'source_url' => $key,
                    'reason' => 'wp_insert_post_error',
                    'message' => $post_id->get_error_message(),
                ]);
            }
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

        if ( $image_url !== '' ) {
            update_post_meta( (int) $post_id, '_image', esc_url_raw( $image_url ) );
        }

        coffeebrk_rss_log_append([
            'event' => 'drafted',
            'status' => 'ok',
            'context' => $context,
            'feed_id' => $feed_id,
            'feed_name' => (string) ( $feed['feed_name'] ?? '' ),
            'feed_url' => $url,
            'post_id' => (int) $post_id,
            'title' => $title,
            'source_url' => $key,
        ]);

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

    coffeebrk_rss_log_append([
        'event' => 'feed_run',
        'status' => 'ok',
        'context' => $context,
        'feed_id' => $feed_id,
        'feed_name' => (string) ( $feed['feed_name'] ?? '' ),
        'feed_url' => $url,
        'imported' => $imported,
        'skipped' => $skipped,
    ]);

    return [ 'ok' => true, 'imported' => $imported, 'skipped' => $skipped ];
}

function coffeebrk_rss_import_all_enabled_feeds( string $context = 'cron' ) : array {
    $feeds = coffeebrk_rss_get_feeds([ 'enabled' => 1, 'orderby' => 'id', 'order' => 'ASC' ]);

    $total_imported = 0;
    $total_skipped = 0;
    $errors = 0;

    foreach ( $feeds as $f ) {
        $res = coffeebrk_rss_import_feed( (int) $f['id'], $context );
        if ( empty( $res['ok'] ) ) {
            $errors++;
            continue;
        }
        $total_imported += (int) ( $res['imported'] ?? 0 );
        $total_skipped += (int) ( $res['skipped'] ?? 0 );
    }

    coffeebrk_rss_log_append([
        'event' => 'import_all',
        'status' => 'ok',
        'context' => $context,
        'feeds' => count( $feeds ),
        'imported' => $total_imported,
        'skipped' => $total_skipped,
        'errors' => $errors,
    ]);

    return [
        'ok' => true,
        'feeds' => count( $feeds ),
        'imported' => $total_imported,
        'skipped' => $total_skipped,
        'errors' => $errors,
    ];
}
