<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// X (Twitter) collector module (admin-only UI + cron-driven Apify sync).

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

// ---------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------

function coffeebrk_x_default_settings() : array {
    return [
        'apify_api_token'          => '',
        'apify_actor_id'           => 'feedminer/x-tweet-scraper',
        'sync_interval'            => 'coffeebrk_x_6h',
        'default_max_items'        => 20,
        'default_include_replies'  => 0,
    ];
}

function coffeebrk_x_get_settings() : array {
    $opt = get_option( 'coffeebrk_x_settings', [] );
    if ( ! is_array( $opt ) ) $opt = [];
    return array_merge( coffeebrk_x_default_settings(), $opt );
}

function coffeebrk_x_valid_intervals() : array {
    return [ 'manual', 'coffeebrk_x_2h', 'coffeebrk_x_4h', 'coffeebrk_x_6h', 'coffeebrk_x_8h', 'twicedaily', 'daily' ];
}

function coffeebrk_x_interval_labels() : array {
    return [
        'manual'         => 'Manual only',
        'coffeebrk_x_2h' => 'Every 2 hours',
        'coffeebrk_x_4h' => 'Every 4 hours',
        'coffeebrk_x_6h' => 'Every 6 hours',
        'coffeebrk_x_8h' => 'Every 8 hours',
        'twicedaily'     => 'Every 12 hours',
        'daily'          => 'Once daily',
    ];
}

// Registers the intervals WP-Cron doesn't ship with (2h/4h/6h/8h).
// twicedaily/daily are already built in; manual means "don't schedule".
add_filter( 'cron_schedules', function( $schedules ) {
    foreach ( [ 2, 4, 6, 8 ] as $h ) {
        $schedules[ 'coffeebrk_x_' . $h . 'h' ] = [
            'interval' => $h * HOUR_IN_SECONDS,
            'display'  => sprintf( 'Every %d Hours', $h ),
        ];
    }
    return $schedules;
});

function coffeebrk_x_schedule_cron() : void {
    $settings = coffeebrk_x_get_settings();
    $interval = in_array( $settings['sync_interval'], coffeebrk_x_valid_intervals(), true )
        ? $settings['sync_interval']
        : 'coffeebrk_x_6h';

    if ( $interval === 'manual' ) {
        coffeebrk_x_clear_cron();
        return;
    }

    $current = wp_get_schedule( 'coffeebrk_x_sync_all' );
    if ( $current === $interval ) {
        return; // already scheduled at the right cadence
    }

    if ( $current !== false ) {
        coffeebrk_x_clear_cron();
    }

    wp_schedule_event( time() + 60, $interval, 'coffeebrk_x_sync_all' );
}

function coffeebrk_x_clear_cron() : void {
    $ts = wp_next_scheduled( 'coffeebrk_x_sync_all' );
    if ( $ts ) {
        wp_unschedule_event( $ts, 'coffeebrk_x_sync_all' );
    }
}

require_once __DIR__ . '/x-collector-data.php';
require_once __DIR__ . '/x-collector-normalizer.php';
require_once __DIR__ . '/x-collector-sync.php';
require_once __DIR__ . '/x-collector-rest.php';

add_action( 'coffeebrk_x_sync_all', 'coffeebrk_x_sync_all_enabled' );

// Self-heal: reschedule (or clear, if settings say manual) if cron drifts from settings.
add_action( 'init', 'coffeebrk_x_schedule_cron' );

if ( is_admin() ) {
    require_once __DIR__ . '/x-collector-admin.php';
}
