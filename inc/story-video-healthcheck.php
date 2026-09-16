<?php
/**
 * Story video health check
 *
 * Periodically verifies that YouTube/TikTok videos behind cbk_story posts
 * are still available, and auto-hides ones that fail two consecutive daily
 * checks (to avoid one-off false positives from geo-blocks, rate limits, or
 * network hiccups) using the existing _cbk_story_show_frontend toggle.
 * Stories it auto-hides are also auto-restored once a later check confirms
 * the video is alive again. Instagram is skipped — its oEmbed API requires
 * a Meta Graph API token this project doesn't have; those rely on the
 * existing manual show/hide toggle.
 *
 * @package Coffeebrk_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CBK_STORY_HEALTHCHECK_HOOK', 'coffeebrk_story_healthcheck_daily' );

function coffeebrk_story_healthcheck_schedule_cron() {
    if ( ! wp_next_scheduled( CBK_STORY_HEALTHCHECK_HOOK ) ) {
        wp_schedule_event( time() + 300, 'daily', CBK_STORY_HEALTHCHECK_HOOK );
    }
}

function coffeebrk_story_healthcheck_clear_cron() {
    $ts = wp_next_scheduled( CBK_STORY_HEALTHCHECK_HOOK );
    if ( $ts ) {
        wp_unschedule_event( $ts, CBK_STORY_HEALTHCHECK_HOOK );
    }
}

add_action( CBK_STORY_HEALTHCHECK_HOOK, 'cbk_story_healthcheck_run' );

/**
 * @return true|false|null true = alive, false = confirmed dead (404), null = ambiguous (network/other error)
 */
function cbk_story_check_oembed( $oembed_url ) {
    $response = wp_remote_get( $oembed_url, [ 'timeout' => 5 ] );
    if ( is_wp_error( $response ) ) {
        return null;
    }
    $code = wp_remote_retrieve_response_code( $response );
    if ( $code === 404 ) {
        return false;
    }
    if ( $code >= 400 ) {
        return null;
    }
    return true;
}

/**
 * Checks every non-manually-hidden cbk_story's YouTube/TikTok video.
 * Hides a story only after 2 consecutive failing checks (avoids one-off
 * false positives), and auto-restores a story it previously auto-hid once
 * a later check confirms the video is alive again. Posts hidden manually
 * (show_frontend = 'no' without the auto-hidden flag) are left alone.
 */
function cbk_story_healthcheck_run() {
    $stats = [ 'checked' => 0, 'hidden' => 0, 'restored' => 0, 'pending' => 0, 'skipped' => 0, 'errors' => 0 ];

    $post_ids = get_posts( [
        'post_type'      => 'cbk_story',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    foreach ( $post_ids as $post_id ) {
        $show        = get_post_meta( $post_id, '_cbk_story_show_frontend', true );
        $auto_hidden = get_post_meta( $post_id, '_cbk_story_auto_hidden', true ) === 'yes';

        if ( $show === 'no' && ! $auto_hidden ) {
            $stats['skipped']++; // hidden manually — leave it alone
            continue;
        }

        $url      = get_post_meta( $post_id, '_cbk_story_video_url', true );
        $platform = cbk_story_detect_platform( $url );

        if ( $platform === 'youtube' ) {
            $yt_id     = get_post_meta( $post_id, '_cbk_story_yt_video_id', true );
            $check_url = $yt_id ? 'https://www.youtube.com/watch?v=' . $yt_id : $url;
            $alive     = cbk_story_check_oembed( 'https://www.youtube.com/oembed?url=' . urlencode( $check_url ) . '&format=json' );
        } elseif ( $platform === 'tiktok' ) {
            $alive = cbk_story_check_oembed( 'https://www.tiktok.com/oembed?url=' . urlencode( $url ) );
        } else {
            continue; // instagram (no token) and everything else: not automated
        }

        $stats['checked']++;

        if ( $alive === false ) {
            $fails = (int) get_post_meta( $post_id, '_cbk_story_healthcheck_fails', true ) + 1;
            if ( $fails >= 2 ) {
                update_post_meta( $post_id, '_cbk_story_show_frontend', 'no' );
                update_post_meta( $post_id, '_cbk_story_auto_hidden', 'yes' );
                update_post_meta( $post_id, '_cbk_story_healthcheck_fails', 0 );
                $stats['hidden']++;
            } else {
                update_post_meta( $post_id, '_cbk_story_healthcheck_fails', $fails );
                $stats['pending']++;
            }
        } elseif ( $alive === true ) {
            update_post_meta( $post_id, '_cbk_story_healthcheck_fails', 0 );
            if ( $show === 'no' && $auto_hidden ) {
                update_post_meta( $post_id, '_cbk_story_show_frontend', 'yes' );
                delete_post_meta( $post_id, '_cbk_story_auto_hidden' );
                $stats['restored']++;
            }
        } else {
            $stats['errors']++;
        }
    }

    return $stats;
}

/**
 * Manual "Recheck Videos Now" trigger from the Import Stories admin page.
 */
add_action( 'admin_post_cbk_recheck_story_videos', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    check_admin_referer( 'cbk_recheck_story_videos' );

    $stats = cbk_story_healthcheck_run();

    wp_safe_redirect( add_query_arg( [
        'page'             => 'cbk-stories-import',
        'cbk_recheck_done' => 1,
        'checked'          => $stats['checked'],
        'hidden'           => $stats['hidden'],
    ], admin_url( 'admin.php' ) ) );
    exit;
});

/**
 * One-time backlog cleanup: re-checks every currently-hidden YouTube/TikTok
 * story (including ones hidden before the 2-strike/auto-restore logic
 * existed) and restores any that are confirmed alive. This can also restore
 * a story an editor hid on purpose if that video happens to still be live —
 * there's no way to tell the two apart retroactively; re-hide manually if so.
 */
function cbk_story_restore_healthcheck_backlog() {
    $stats = [ 'checked' => 0, 'restored' => 0 ];

    $post_ids = get_posts( [
        'post_type'      => 'cbk_story',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_cbk_story_show_frontend',
        'meta_value'     => 'no',
    ] );

    foreach ( $post_ids as $post_id ) {
        $url      = get_post_meta( $post_id, '_cbk_story_video_url', true );
        $platform = cbk_story_detect_platform( $url );

        if ( $platform === 'youtube' ) {
            $yt_id     = get_post_meta( $post_id, '_cbk_story_yt_video_id', true );
            $check_url = $yt_id ? 'https://www.youtube.com/watch?v=' . $yt_id : $url;
            $alive     = cbk_story_check_oembed( 'https://www.youtube.com/oembed?url=' . urlencode( $check_url ) . '&format=json' );
        } elseif ( $platform === 'tiktok' ) {
            $alive = cbk_story_check_oembed( 'https://www.tiktok.com/oembed?url=' . urlencode( $url ) );
        } else {
            continue;
        }

        $stats['checked']++;
        if ( $alive === true ) {
            update_post_meta( $post_id, '_cbk_story_show_frontend', 'yes' );
            delete_post_meta( $post_id, '_cbk_story_auto_hidden' );
            update_post_meta( $post_id, '_cbk_story_healthcheck_fails', 0 );
            $stats['restored']++;
        }
    }

    return $stats;
}

add_action( 'admin_post_cbk_restore_healthcheck_backlog', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    check_admin_referer( 'cbk_restore_healthcheck_backlog' );

    $stats = cbk_story_restore_healthcheck_backlog();

    wp_safe_redirect( add_query_arg( [
        'page'              => 'cbk-stories-import',
        'cbk_restore_done'  => 1,
        'checked'           => $stats['checked'],
        'restored'          => $stats['restored'],
    ], admin_url( 'admin.php' ) ) );
    exit;
});
