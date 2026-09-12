<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// X (Twitter) collector module. Posts are pushed in via REST from an
// external n8n/Apify pipeline (inc/x-collector-rest.php) — there is no
// in-plugin scraping or cron sync; the profiles table is just internal
// bookkeeping (author snapshot) that the ingest endpoint auto-populates.

function coffeebrk_x_profiles_table_name() : string {
    global $wpdb;
    return $wpdb->prefix . 'coffeebrk_x_profiles';
}

function coffeebrk_x_posts_table_name() : string {
    global $wpdb;
    return $wpdb->prefix . 'coffeebrk_x_posts';
}

function coffeebrk_x_install() : void {
    global $wpdb;

    // Use dbDelta so schema updates are applied on future versions.
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $profiles_table = coffeebrk_x_profiles_table_name();
    $sql_profiles = "CREATE TABLE {$profiles_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        username varchar(64) NOT NULL,
        display_name varchar(191) NULL,
        enabled tinyint(1) NOT NULL DEFAULT 1,
        max_items smallint(5) unsigned NULL,
        include_replies tinyint(1) NULL,
        category_id bigint(20) unsigned NULL,
        avatar_url varchar(500) NULL,
        followers_count int(10) unsigned NULL,
        is_verified tinyint(1) NOT NULL DEFAULT 0,
        last_synced_at datetime NULL,
        last_run datetime NULL,
        last_error text NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY username (username)
    ) {$charset_collate};";
    dbDelta( $sql_profiles );

    $posts_table = coffeebrk_x_posts_table_name();
    $sql_posts = "CREATE TABLE {$posts_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        profile_id bigint(20) unsigned NOT NULL,
        tweet_id varchar(64) NOT NULL,
        author_username varchar(64) NOT NULL,
        text longtext NULL,
        permalink varchar(1024) NULL,
        posted_at datetime NULL,
        is_reply tinyint(1) NOT NULL DEFAULT 0,
        like_count int(10) unsigned NULL,
        retweet_count int(10) unsigned NULL,
        reply_count int(10) unsigned NULL,
        view_count int(10) unsigned NULL,
        quote_count int(10) unsigned NULL,
        bookmark_count int(10) unsigned NULL,
        is_retweet tinyint(1) NOT NULL DEFAULT 0,
        is_quote tinyint(1) NOT NULL DEFAULT 0,
        lang varchar(10) NULL,
        media_json text NULL,
        status varchar(20) NOT NULL DEFAULT 'published',
        is_featured tinyint(1) NOT NULL DEFAULT 0,
        raw_synced_at datetime NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY tweet_id (tweet_id),
        KEY profile_id (profile_id),
        KEY posted_at (posted_at),
        KEY status (status)
    ) {$charset_collate};";
    dbDelta( $sql_posts );
}

// One-time cleanup: pre-n8n versions scheduled a recurring Apify cron sync.
// It's no longer used, so clear it if a site still has it scheduled.
function coffeebrk_x_clear_cron() : void {
    $ts = wp_next_scheduled( 'coffeebrk_x_sync_all' );
    if ( $ts ) {
        wp_unschedule_event( $ts, 'coffeebrk_x_sync_all' );
    }
}
add_action( 'init', 'coffeebrk_x_clear_cron' );

require_once __DIR__ . '/x-collector-data.php';
require_once __DIR__ . '/x-collector-normalizer.php';
require_once __DIR__ . '/x-collector-rest.php';

if ( is_admin() ) {
    require_once __DIR__ . '/x-collector-admin.php';
}
