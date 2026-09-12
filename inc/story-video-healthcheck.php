<?php
/**
 * Story video health check
 *
 * Periodically verifies that YouTube/TikTok videos behind cbk_story posts
 * are still available, and auto-hides ones that have been deleted or made
 * private using the existing _cbk_story_show_frontend toggle. Instagram is
 * skipped — its oEmbed API requires a Meta Graph API token this project
 * doesn't have; those rely on the existing manual show/hide toggle.
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
 * Checks every visible cbk_story's YouTube/TikTok video and hides dead ones.
 * Only ever flips show_frontend 'yes' -> 'no' — never un-hides, since a
 * post already marked 'no' might have been hidden manually on purpose.
 */
function cbk_story_healthcheck_run() {
    $stats = [ 'checked' => 0, 'hidden' => 0, 'skipped' => 0, 'errors' => 0 ];

    $post_ids = get_posts( [
        'post_type'      => 'cbk_story',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    foreach ( $post_ids as $post_id ) {
        $show = get_post_meta( $post_id, '_cbk_story_show_frontend', true );
        if ( $show === 'no' ) {
            $stats['skipped']++;
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
            update_post_meta( $post_id, '_cbk_story_show_frontend', 'no' );
            $stats['hidden']++;
        } elseif ( $alive === null ) {
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
