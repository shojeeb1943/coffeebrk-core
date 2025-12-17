<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// RSS Aggregator module (admin-only UI + cron-driven importer).

function coffeebrk_rss_table_name() : string {
    global $wpdb;
    return $wpdb->prefix . 'coffeebrk_rss_feeds';
}

function coffeebrk_rss_install() : void {
    global $wpdb;

    // Use dbDelta so schema updates are applied on future versions.
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = coffeebrk_rss_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        feed_name varchar(255) NOT NULL,
        feed_url varchar(1024) NOT NULL,
        enabled tinyint(1) NOT NULL DEFAULT 1,
        import_limit smallint(5) unsigned NOT NULL DEFAULT 5,
        category_id bigint(20) unsigned NULL,
        last_import datetime NULL,
        last_run datetime NULL,
        last_error text NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY feed_url_hash (feed_url(191))
    ) {$charset_collate};";

    dbDelta( $sql );
}

function coffeebrk_rss_schedule_cron() : void {
    // Ensure a single hourly cron hook exists.
    if ( ! wp_next_scheduled( 'coffeebrk_rss_import_all' ) ) {
        wp_schedule_event( time() + 60, 'hourly', 'coffeebrk_rss_import_all' );
    }
}

function coffeebrk_rss_clear_cron() : void {
    // Remove our scheduled cron event on deactivation.
    $ts = wp_next_scheduled( 'coffeebrk_rss_import_all' );
    if ( $ts ) {
        wp_unschedule_event( $ts, 'coffeebrk_rss_import_all' );
    }
}

require_once __DIR__ . '/rss-importer.php';

add_action( 'coffeebrk_rss_import_all', 'coffeebrk_rss_import_all_enabled_feeds' );

// Self-heal: if cron is cleared externally, schedule it again when WP loads.
add_action( 'init', 'coffeebrk_rss_schedule_cron' );

if ( is_admin() ) {
    require_once __DIR__ . '/rss-admin.php';
}
