<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------
// Profiles CRUD
// ---------------------------------------------------------------------

function coffeebrk_x_normalize_username( string $username ) : string {
    $username = trim( $username );
    $username = ltrim( $username, '@' );
    return sanitize_text_field( $username );
}

function coffeebrk_x_get_profile( int $id ) : ?array {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
    return $row ? $row : null;
}

function coffeebrk_x_get_profile_by_username( string $username ) : ?array {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE username = %s", coffeebrk_x_normalize_username( $username ) ),
        ARRAY_A
    );
    return $row ? $row : null;
}

function coffeebrk_x_get_profiles( array $args = [] ) : array {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();

    $orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'username';
    $order   = isset( $args['order'] ) ? strtoupper( (string) $args['order'] ) : 'ASC';

    $allowed_orderby = [ 'id', 'username', 'display_name', 'enabled', 'last_synced_at', 'last_run' ];
    if ( ! in_array( $orderby, $allowed_orderby, true ) ) $orderby = 'username';
    if ( $order !== 'ASC' && $order !== 'DESC' ) $order = 'ASC';

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

function coffeebrk_x_save_profile( array $data, ?int $id = null ) : array {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();

    $username = coffeebrk_x_normalize_username( (string) ( $data['username'] ?? '' ) );
    if ( $username === '' ) {
        return [ 'ok' => false, 'error' => 'missing_username' ];
    }

    $existing = coffeebrk_x_get_profile_by_username( $username );
    if ( $existing && (int) $existing['id'] !== (int) $id ) {
        return [ 'ok' => false, 'error' => 'duplicate_username' ];
    }

    $display_name = sanitize_text_field( $data['display_name'] ?? '' );
    $enabled = isset( $data['enabled'] ) ? (int) (bool) $data['enabled'] : 0;

    $max_items = ( isset( $data['max_items'] ) && $data['max_items'] !== '' ) ? (int) $data['max_items'] : null;
    if ( $max_items !== null && $max_items < 1 ) $max_items = null;

    $include_replies = null;
    if ( isset( $data['include_replies'] ) && $data['include_replies'] !== '' ) {
        $include_replies = (int) (bool) $data['include_replies'];
    }

    $category_id = ( isset( $data['category_id'] ) && $data['category_id'] !== '' ) ? (int) $data['category_id'] : null;
    if ( $category_id !== null && $category_id <= 0 ) $category_id = null;

    $now = current_time( 'mysql' );
    $row = [
        'username'         => $username,
        'display_name'     => $display_name,
        'enabled'          => $enabled,
        'max_items'        => $max_items,
        'include_replies'  => $include_replies,
        'category_id'      => $category_id,
        'updated_at'       => $now,
    ];
    $formats = [ '%s', '%s', '%d', '%d', '%d', '%d', '%s' ];

    if ( $id ) {
        $ok = ( false !== $wpdb->update( $table, $row, [ 'id' => $id ], $formats, [ '%d' ] ) );
        return [ 'ok' => $ok, 'id' => $id ];
    }

    $row['created_at'] = $now;
    $formats[] = '%s';

    $ok = ( false !== $wpdb->insert( $table, $row, $formats ) );
    return [ 'ok' => $ok, 'id' => (int) $wpdb->insert_id ];
}

function coffeebrk_x_delete_profile( int $id ) : bool {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();
    return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
}

function coffeebrk_x_set_profile_enabled( int $id, bool $enabled ) : bool {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();
    $now = current_time( 'mysql' );
    return ( false !== $wpdb->update(
        $table,
        [ 'enabled' => (int) $enabled, 'updated_at' => $now ],
        [ 'id' => $id ],
        [ '%d', '%s' ],
        [ '%d' ]
    ) );
}

function coffeebrk_x_update_profile_run_state( int $id, array $data ) : void {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();

    $row = [];
    $formats = [];

    foreach ( [ 'last_run', 'last_synced_at' ] as $k ) {
        if ( array_key_exists( $k, $data ) ) {
            $row[ $k ] = $data[ $k ];
            $formats[] = '%s';
        }
    }
    if ( array_key_exists( 'last_error', $data ) ) {
        $row['last_error'] = $data['last_error'];
        $formats[] = '%s';
    }

    if ( ! $row ) return;

    $row['updated_at'] = current_time( 'mysql' );
    $formats[] = '%s';

    $wpdb->update( $table, $row, [ 'id' => $id ], $formats, [ '%d' ] );
}

// ---------------------------------------------------------------------
// Posts CRUD
// ---------------------------------------------------------------------

// Inserts a normalized post row. Relies on the tweet_id UNIQUE KEY for
// dedupe rather than pre-querying — cheaper, and correct even if two
// syncs somehow overlap.
function coffeebrk_x_insert_post_if_new( array $row ) : bool {
    global $wpdb;
    $table = coffeebrk_x_posts_table_name();
    $formats = [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ];

    $ok = $wpdb->insert( $table, $row, $formats );

    if ( $ok === false && strpos( (string) $wpdb->last_error, 'Duplicate entry' ) === false && function_exists( 'coffeebrk_log_error' ) ) {
        coffeebrk_log_error( 'x post insert failed', [ 'tweet_id' => $row['tweet_id'] ?? '', 'db_error' => $wpdb->last_error ] );
    }

    return $ok !== false;
}

function coffeebrk_x_get_post( int $id ) : ?array {
    global $wpdb;
    $table = coffeebrk_x_posts_table_name();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
    return $row ? $row : null;
}

function coffeebrk_x_get_posts( array $args = [] ) : array {
    global $wpdb;
    $table = coffeebrk_x_posts_table_name();
    $profiles_table = coffeebrk_x_profiles_table_name();

    $orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'posted_at';
    $order   = isset( $args['order'] ) ? strtoupper( (string) $args['order'] ) : 'DESC';

    $allowed_orderby = [ 'posted_at', 'created_at', 'id' ];
    if ( ! in_array( $orderby, $allowed_orderby, true ) ) $orderby = 'posted_at';
    if ( $order !== 'ASC' && $order !== 'DESC' ) $order = 'DESC';

    $where = '1=1';
    $params = [];
    $joins = '';

    if ( ! empty( $args['profile_id'] ) ) {
        $where .= ' AND p.profile_id = %d';
        $params[] = (int) $args['profile_id'];
    }

    if ( ! empty( $args['category_id'] ) ) {
        $joins = " INNER JOIN {$profiles_table} pr ON pr.id = p.profile_id";
        $where .= ' AND pr.category_id = %d';
        $params[] = (int) $args['category_id'];
    }

    if ( array_key_exists( 'featured', $args ) ) {
        $where .= ' AND p.is_featured = %d';
        $params[] = (int) (bool) $args['featured'];
    }

    if ( array_key_exists( 'status', $args ) && $args['status'] !== '' ) {
        $where .= ' AND p.status = %s';
        $params[] = (string) $args['status'];
    }

    $per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
    $page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
    $offset   = ( $page - 1 ) * $per_page;

    $count_sql = "SELECT COUNT(*) FROM {$table} p{$joins} WHERE {$where}";
    $total = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

    $sql = "SELECT p.* FROM {$table} p{$joins} WHERE {$where} ORDER BY p.{$orderby} {$order} LIMIT %d OFFSET %d";
    $posts = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, [ $per_page, $offset ] ) ), ARRAY_A );

    return [ 'posts' => $posts, 'total' => $total ];
}

function coffeebrk_x_set_post_status( int $id, string $status ) : bool {
    global $wpdb;
    $table = coffeebrk_x_posts_table_name();
    $status = in_array( $status, [ 'published', 'hidden' ], true ) ? $status : 'published';
    return ( false !== $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] ) );
}

function coffeebrk_x_set_post_featured( int $id, bool $featured ) : bool {
    global $wpdb;
    $table = coffeebrk_x_posts_table_name();
    return ( false !== $wpdb->update( $table, [ 'is_featured' => (int) $featured ], [ 'id' => $id ], [ '%d' ], [ '%d' ] ) );
}

function coffeebrk_x_delete_post( int $id ) : bool {
    global $wpdb;
    $table = coffeebrk_x_posts_table_name();
    return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
}

// ---------------------------------------------------------------------
// Activity log — mirrors the RSS module's rolling 24h option log.
// ---------------------------------------------------------------------

function coffeebrk_x_log_option_key() : string {
    return 'coffeebrk_x_import_log';
}

function coffeebrk_x_log_append( array $entry ) : void {
    $key = coffeebrk_x_log_option_key();
    $log = get_option( $key, [] );
    if ( ! is_array( $log ) ) $log = [];

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
    if ( ! is_array( $log ) ) return [];

    $since = time() - DAY_IN_SECONDS;
    return array_values( array_filter( $log, function( $row ) use ( $since ) {
        if ( ! is_array( $row ) ) return false;
        $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
        return $t >= $since;
    } ) );
}
