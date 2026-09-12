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

function coffeebrk_x_ensure_tables() : void {
    static $ensured = false;
    if ( $ensured ) return;
    $ensured = true;
    if ( function_exists( 'coffeebrk_x_install' ) ) {
        coffeebrk_x_install();
    }
}

// Looks up a profile by username, or creates a minimal one if it doesn't
// exist yet. Every incoming n8n tweet needs a profile_id (FK), and n8n can
// send any author — this is pure bookkeeping, not a "tracked accounts" list.
function coffeebrk_x_get_or_create_profile_by_username( string $username ) : ?array {
    global $wpdb;
    coffeebrk_x_ensure_tables();

    $username = coffeebrk_x_normalize_username( $username );
    if ( $username === '' ) return null;

    $existing = coffeebrk_x_get_profile_by_username( $username );
    if ( $existing ) return $existing;

    $table = coffeebrk_x_profiles_table_name();
    $now = current_time( 'mysql' );
    $ok = $wpdb->insert( $table, [
        'username'     => $username,
        'display_name' => $username,
        'enabled'      => 0,
        'created_at'   => $now,
        'updated_at'   => $now,
    ], [ '%s', '%s', '%d', '%s', '%s' ] );

    if ( ! $ok ) return null;

    return coffeebrk_x_get_profile( (int) $wpdb->insert_id );
}

// Updates a profile's known-good author snapshot (display name, avatar,
// followers, verified badge) from freshly-scraped data. Only touches
// fields that were actually provided.
function coffeebrk_x_update_profile_author_snapshot( int $profile_id, array $author ) : void {
    global $wpdb;
    $table = coffeebrk_x_profiles_table_name();

    $row = [];
    $formats = [];

    if ( ! empty( $author['display_name'] ) ) {
        $row['display_name'] = $author['display_name'];
        $formats[] = '%s';
    }
    if ( ! empty( $author['avatar_url'] ) ) {
        $row['avatar_url'] = $author['avatar_url'];
        $formats[] = '%s';
    }
    if ( ! empty( $author['followers_count'] ) ) {
        $row['followers_count'] = (int) $author['followers_count'];
        $formats[] = '%d';
    }
    if ( isset( $author['is_verified'] ) ) {
        $row['is_verified'] = (int) (bool) $author['is_verified'];
        $formats[] = '%d';
    }

    if ( ! $row ) return;

    $row['updated_at'] = current_time( 'mysql' );
    $formats[] = '%s';

    $wpdb->update( $table, $row, [ 'id' => $profile_id ], $formats, [ '%d' ] );
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
    // Positional, matching coffeebrk_x_normalize_apify_item()'s row key order:
    // profile_id, tweet_id, author_username, text, permalink, posted_at, is_reply,
    // like_count, retweet_count, reply_count, view_count, quote_count, bookmark_count,
    // is_retweet, is_quote, lang, media_json, status, is_featured, raw_synced_at, created_at.
    $formats = [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' ];

    $ok = $wpdb->insert( $table, $row, $formats );

    if ( $ok === false && strpos( (string) $wpdb->last_error, 'Duplicate entry' ) === false && function_exists( 'coffeebrk_log_error' ) ) {
        coffeebrk_log_error( 'x post insert failed', [ 'tweet_id' => $row['tweet_id'] ?? '', 'db_error' => $wpdb->last_error ] );
    }

    return $ok !== false;
}

// Explicit pre-check used by the ingestion REST endpoint so it can report
// a clean { skipped: true } response for retries, rather than relying on
// catching the tweet_id UNIQUE KEY violation like the cron path does.
function coffeebrk_x_post_exists_by_tweet_id( string $tweet_id ) : ?int {
    global $wpdb;
    $table = coffeebrk_x_posts_table_name();
    $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE tweet_id = %s", $tweet_id ) );
    return $id ? (int) $id : null;
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
