<?php
/**
 * YouTube → Stories importer REST API
 *
 * Single-video POST endpoint for n8n (or any external pipeline) to push
 * YouTube Shorts into the cbk_story CPT. One object per request — this
 * lets n8n's HTTP Request node run once per input item with no extra
 * batching node.
 *
 * @package Coffeebrk_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function() {
    register_rest_route( 'coffeebrk/v1', '/youtube-videos', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_api_create_youtube_story',
        'args'                => [
            'videoId'  => [ 'type' => 'string', 'required' => true ],
            'title'    => [ 'type' => 'string', 'default' => '' ],
            'channel'  => [ 'type' => 'string', 'default' => '' ],
            'views'    => [ 'type' => 'integer', 'default' => 0 ],
            'duration' => [ 'type' => 'string', 'default' => '' ],
            'thumbnail'=> [ 'type' => 'string', 'default' => '' ],
            'url'      => [ 'type' => 'string', 'default' => '' ],
        ],
    ]);
});

/**
 * POST /youtube-videos - Create a cbk_story from a YouTube video
 */
function coffeebrk_api_create_youtube_story( WP_REST_Request $req ) {
    $params   = coffeebrk_get_request_params( $req );
    $video_id = sanitize_text_field( (string) ( $params['videoId'] ?? '' ) );

    if ( $video_id === '' ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'missing_video_id',
            'message' => 'videoId is required.',
        ], 400 );
    }

    // Dedupe: skip if this video was already imported.
    $existing = get_posts( [
        'post_type'      => 'cbk_story',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'      => [
            [ 'key' => '_cbk_story_yt_video_id', 'value' => $video_id, 'compare' => '=' ],
        ],
    ] );

    if ( ! empty( $existing ) ) {
        return new WP_REST_Response( [
            'success' => true,
            'skipped' => true,
            'id'      => (int) $existing[0],
            'message' => 'Video already imported.',
        ], 200 );
    }

    $title = sanitize_text_field( (string) ( $params['title'] ?? '' ) );

    $post_id = wp_insert_post( [
        'post_type'   => 'cbk_story',
        'post_status' => 'publish',
        'post_title'  => $title !== '' ? $title : 'Untitled Short',
    ], true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'insert_failed',
            'message' => $post_id->get_error_message(),
        ], 500 );
    }

    update_post_meta( $post_id, '_cbk_story_video_url', esc_url_raw( (string) ( $params['url'] ?? '' ) ) );
    update_post_meta( $post_id, '_cbk_story_show_frontend', 'yes' );
    update_post_meta( $post_id, '_cbk_story_yt_video_id', $video_id );
    update_post_meta( $post_id, '_cbk_story_yt_channel', sanitize_text_field( (string) ( $params['channel'] ?? '' ) ) );
    update_post_meta( $post_id, '_cbk_story_yt_views', intval( $params['views'] ?? 0 ) );
    update_post_meta( $post_id, '_cbk_story_yt_duration', sanitize_text_field( (string) ( $params['duration'] ?? '' ) ) );

    $thumbnail = esc_url_raw( (string) ( $params['thumbnail'] ?? '' ) );
    if ( $thumbnail !== '' ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( $thumbnail, $post_id, $title, 'id' );

        if ( is_wp_error( $attachment_id ) ) {
            if ( function_exists( 'coffeebrk_log_error' ) ) {
                coffeebrk_log_error( 'Thumbnail sideload failed: ' . $attachment_id->get_error_message(), [ 'source' => 'youtube_importer', 'video_id' => $video_id ] );
            }
        } else {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }

    return new WP_REST_Response( [
        'success'   => true,
        'id'        => $post_id,
        'permalink' => get_permalink( $post_id ),
        'edit_link' => get_edit_post_link( $post_id, 'raw' ),
    ], 201 );
}
