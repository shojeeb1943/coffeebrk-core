<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once COFFEEBRK_CORE_PATH . 'includes/importers/class-coffeebrk-apify-x-provider.php';

function coffeebrk_x_acquire_sync_lock() : bool {
    if ( get_transient( 'coffeebrk_x_sync_lock' ) ) {
        return false;
    }
    // ponytail: single global lock (not per-profile); switch to per-profile
    // locks only if concurrent multi-profile syncing is ever actually needed
    set_transient( 'coffeebrk_x_sync_lock', 1, 10 * MINUTE_IN_SECONDS );
    return true;
}

function coffeebrk_x_release_sync_lock() : void {
    delete_transient( 'coffeebrk_x_sync_lock' );
}

function coffeebrk_x_build_apify_input( array $profile, array $settings ) : array {
    $max_items = (int) ( $profile['max_items'] ?? 0 );
    if ( $max_items <= 0 ) {
        $max_items = (int) $settings['default_max_items'];
    }
    if ( $max_items < 1 ) $max_items = 1;
    if ( $max_items > 100 ) $max_items = 100;

    $include_replies = $profile['include_replies'] ?? null;
    $include_replies = ( $include_replies === null )
        ? (bool) $settings['default_include_replies']
        : (bool) $include_replies;

    $query = 'from:' . $profile['username'];
    if ( ! $include_replies ) {
        $query .= ' -filter:replies';
    }
    if ( ! empty( $profile['last_synced_at'] ) ) {
        $since = gmdate( 'Y-m-d', strtotime( (string) $profile['last_synced_at'] ) );
        $query .= ' since:' . $since;
    }

    return [
        'searchTerms' => [ $query ],
        'maxItems'    => $max_items,
    ];
}

// Actual sync body, no lock handling — callers below own the lock.
function coffeebrk_x_do_sync_profile( int $profile_id, string $context ) : array {
    $profile = coffeebrk_x_get_profile( $profile_id );
    if ( ! $profile ) {
        return [ 'ok' => false, 'error' => 'profile_not_found' ];
    }

    $now = current_time( 'mysql' );
    coffeebrk_x_update_profile_run_state( $profile_id, [ 'last_run' => $now, 'last_error' => '' ] );

    if ( (int) $profile['enabled'] !== 1 ) {
        return [ 'ok' => true, 'imported' => 0, 'skipped' => 0, 'note' => 'disabled' ];
    }

    $settings = coffeebrk_x_get_settings();
    if ( (string) $settings['apify_api_token'] === '' ) {
        $msg = 'Apify API token is not configured.';
        coffeebrk_x_update_profile_run_state( $profile_id, [ 'last_error' => $msg ] );
        coffeebrk_x_log_append([
            'event' => 'profile_sync', 'status' => 'error', 'context' => $context,
            'profile_id' => $profile_id, 'username' => $profile['username'], 'message' => $msg,
        ]);
        return [ 'ok' => false, 'error' => 'missing_token' ];
    }

    $input = coffeebrk_x_build_apify_input( $profile, $settings );
    $items = Coffeebrk_Apify_X_Provider::run_actor( (string) $settings['apify_actor_id'], (string) $settings['apify_api_token'], $input );

    if ( is_wp_error( $items ) ) {
        $msg = $items->get_error_message();
        coffeebrk_x_update_profile_run_state( $profile_id, [ 'last_error' => $msg ] );
        if ( function_exists( 'coffeebrk_log_error' ) ) {
            coffeebrk_log_error( 'x sync apify error', [ 'profile_id' => $profile_id, 'username' => $profile['username'], 'err' => $msg ] );
        }
        coffeebrk_x_log_append([
            'event' => 'profile_sync', 'status' => 'error', 'context' => $context,
            'profile_id' => $profile_id, 'username' => $profile['username'], 'message' => $msg,
        ]);
        return [ 'ok' => false, 'error' => 'apify_failed', 'message' => $msg ];
    }

    $imported = 0;
    $skipped = 0;

    foreach ( $items as $raw_item ) {
        if ( ! is_array( $raw_item ) ) { $skipped++; continue; }

        $row = coffeebrk_x_normalize_apify_item( $raw_item, $profile_id );
        if ( $row === null ) { $skipped++; continue; }

        if ( coffeebrk_x_insert_post_if_new( $row ) ) {
            $imported++;
        } else {
            $skipped++;
        }
    }

    // Only advance the watermark on a successful run — a failed run above
    // already returned before reaching here, so last_synced_at is safe to bump.
    coffeebrk_x_update_profile_run_state( $profile_id, [ 'last_synced_at' => $now ] );

    coffeebrk_x_log_append([
        'event' => 'profile_sync', 'status' => 'ok', 'context' => $context,
        'profile_id' => $profile_id, 'username' => $profile['username'],
        'imported' => $imported, 'skipped' => $skipped,
    ]);

    return [ 'ok' => true, 'imported' => $imported, 'skipped' => $skipped ];
}

// Single entry point for one profile — used by both "Sync Now" and by
// coffeebrk_x_sync_all_enabled() looping through enabled profiles.
function coffeebrk_x_sync_profile( int $profile_id, string $context = 'manual' ) : array {
    if ( ! coffeebrk_x_acquire_sync_lock() ) {
        coffeebrk_x_log_append([
            'event' => 'profile_sync', 'status' => 'skip', 'context' => $context,
            'profile_id' => $profile_id, 'reason' => 'lock_active',
        ]);
        return [ 'ok' => false, 'error' => 'sync_in_progress' ];
    }

    try {
        return coffeebrk_x_do_sync_profile( $profile_id, $context );
    } finally {
        coffeebrk_x_release_sync_lock();
    }
}

// Single entry point cron and "Sync All Enabled" both call.
function coffeebrk_x_sync_all_enabled( string $context = 'cron' ) : array {
    if ( ! coffeebrk_x_acquire_sync_lock() ) {
        coffeebrk_x_log_append([ 'event' => 'sync_all', 'status' => 'skip', 'context' => $context, 'reason' => 'lock_active' ]);
        return [ 'ok' => false, 'error' => 'sync_in_progress' ];
    }

    $total_imported = 0;
    $total_skipped = 0;
    $errors = 0;
    $profiles = [];

    try {
        $profiles = coffeebrk_x_get_profiles([ 'enabled' => 1 ]);
        foreach ( $profiles as $profile ) {
            $res = coffeebrk_x_do_sync_profile( (int) $profile['id'], $context );
            if ( empty( $res['ok'] ) ) {
                $errors++;
                continue;
            }
            $total_imported += (int) ( $res['imported'] ?? 0 );
            $total_skipped  += (int) ( $res['skipped'] ?? 0 );
        }
    } finally {
        coffeebrk_x_release_sync_lock();
    }

    coffeebrk_x_log_append([
        'event' => 'sync_all', 'status' => 'ok', 'context' => $context,
        'profiles' => count( $profiles ), 'imported' => $total_imported,
        'skipped' => $total_skipped, 'errors' => $errors,
    ]);

    return [
        'ok' => true, 'profiles' => count( $profiles ),
        'imported' => $total_imported, 'skipped' => $total_skipped, 'errors' => $errors,
    ];
}
