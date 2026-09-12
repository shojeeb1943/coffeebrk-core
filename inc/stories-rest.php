<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Generic CRUD for the cbk_story CPT. Previously only GET /public/stories
// (public, publish-only) and POST /youtube-videos (YouTube-specific push,
// left untouched since n8n depends on it) existed — no update/delete at
// all. Since YouTube-ingested stories land in this same CPT, this generic
// layer covers both manual and YouTube-sourced stories.

add_action( 'rest_api_init', 'coffeebrk_stories_register_rest_routes' );

function coffeebrk_stories_register_rest_routes() {
    $namespace = 'coffeebrk/v1';

    register_rest_route( $namespace, '/stories', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_stories_api_get_stories',
        'args'                => [
            'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
            'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
            'source'   => [ 'type' => 'string', 'enum' => [ 'youtube', 'manual' ] ],
        ],
    ]);

    register_rest_route( $namespace, '/stories/stats', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_stories_api_get_stats',
    ]);

    register_rest_route( $namespace, '/stories', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_stories_api_create_story',
    ]);

    register_rest_route( $namespace, '/stories/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_stories_api_get_story',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/stories/(?P<id>\d+)', [
        'methods'             => [ 'PUT', 'PATCH' ],
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_stories_api_update_story',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/stories/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => 'coffeebrk_api_permission_delete',
        'callback'            => 'coffeebrk_stories_api_delete_story',
        'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
    ]);
}

function coffeebrk_stories_format( WP_Post $post ) : array {
    $thumb_id = get_post_thumbnail_id( $post->ID );
    return [
        'id'                 => (int) $post->ID,
        'title'              => get_the_title( $post ),
        'status'             => $post->post_status,
        'image'              => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : null,
        'video_url'          => (string) get_post_meta( $post->ID, '_cbk_story_video_url', true ),
        'show_frontend'      => (string) get_post_meta( $post->ID, '_cbk_story_show_frontend', true ) !== 'no',
        'gradient'           => (string) get_post_meta( $post->ID, '_cbk_story_gradient', true ) ?: '#F5F5FF',
        'text_color'         => (string) get_post_meta( $post->ID, '_cbk_story_text_color', true ) ?: '#323232',
        'gradient_intensity' => (int) ( get_post_meta( $post->ID, '_cbk_story_gradient_intensity', true ) ?: 50 ),
        'youtube'            => [
            'video_id' => (string) get_post_meta( $post->ID, '_cbk_story_yt_video_id', true ),
            'channel'  => (string) get_post_meta( $post->ID, '_cbk_story_yt_channel', true ),
            'views'    => (int) get_post_meta( $post->ID, '_cbk_story_yt_views', true ),
            'duration' => (string) get_post_meta( $post->ID, '_cbk_story_yt_duration', true ),
        ],
        'date'               => $post->post_date_gmt,
    ];
}

function coffeebrk_stories_api_get_stories( WP_REST_Request $req ) {
    $page     = max( 1, (int) $req->get_param( 'page' ) );
    $per_page = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
    $source   = $req->get_param( 'source' );

    $args = [
        'post_type'      => 'cbk_story',
        'post_status'    => 'any',
        'paged'          => $page,
        'posts_per_page' => $per_page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ( $source === 'youtube' ) {
        $args['meta_query'] = [ [ 'key' => '_cbk_story_yt_video_id', 'compare' => 'EXISTS' ] ];
    } elseif ( $source === 'manual' ) {
        $args['meta_query'] = [ [ 'key' => '_cbk_story_yt_video_id', 'compare' => 'NOT EXISTS' ] ];
    }

    $query = new WP_Query( $args );
    $items = array_map( 'coffeebrk_stories_format', $query->posts );

    return new WP_REST_Response( [
        'success'     => true,
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'items'       => $items,
    ], 200 );
}

function coffeebrk_stories_api_get_story( WP_REST_Request $req ) {
    $post = get_post( (int) $req->get_param( 'id' ) );
    if ( ! $post || $post->post_type !== 'cbk_story' ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'story_not_found' ], 404 );
    }
    return new WP_REST_Response( [ 'success' => true, 'story' => coffeebrk_stories_format( $post ) ], 200 );
}

function coffeebrk_stories_api_create_story( WP_REST_Request $req ) {
    $params = coffeebrk_get_request_params( $req );
    $title = sanitize_text_field( (string) ( $params['title'] ?? '' ) );

    $post_id = wp_insert_post( [
        'post_type'   => 'cbk_story',
        'post_status' => 'publish',
        'post_title'  => $title !== '' ? $title : 'Untitled Story',
    ], true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => $post_id->get_error_message() ], 500 );
    }

    coffeebrk_stories_apply_meta( $post_id, $params );

    return new WP_REST_Response( [ 'success' => true, 'id' => $post_id, 'story' => coffeebrk_stories_format( get_post( $post_id ) ) ], 201 );
}

function coffeebrk_stories_api_update_story( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'cbk_story' ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'story_not_found' ], 404 );
    }

    $params = coffeebrk_get_request_params( $req );

    if ( array_key_exists( 'title', $params ) ) {
        wp_update_post( [ 'ID' => $id, 'post_title' => sanitize_text_field( (string) $params['title'] ) ] );
    }

    coffeebrk_stories_apply_meta( $id, $params );

    return new WP_REST_Response( [ 'success' => true, 'story' => coffeebrk_stories_format( get_post( $id ) ) ], 200 );
}

function coffeebrk_stories_apply_meta( int $post_id, array $params ) : void {
    if ( array_key_exists( 'video_url', $params ) ) {
        update_post_meta( $post_id, '_cbk_story_video_url', esc_url_raw( (string) $params['video_url'] ) );
    }
    if ( array_key_exists( 'show_frontend', $params ) ) {
        update_post_meta( $post_id, '_cbk_story_show_frontend', ( (bool) $params['show_frontend'] ) ? 'yes' : 'no' );
    }
    if ( array_key_exists( 'gradient', $params ) ) {
        update_post_meta( $post_id, '_cbk_story_gradient', sanitize_hex_color( (string) $params['gradient'] ) ?: '#F5F5FF' );
    }
    if ( array_key_exists( 'text_color', $params ) ) {
        update_post_meta( $post_id, '_cbk_story_text_color', sanitize_hex_color( (string) $params['text_color'] ) ?: '#323232' );
    }
    if ( array_key_exists( 'gradient_intensity', $params ) ) {
        update_post_meta( $post_id, '_cbk_story_gradient_intensity', max( 0, min( 100, (int) $params['gradient_intensity'] ) ) );
    }

    $thumbnail = (string) ( $params['thumbnail_url'] ?? '' );
    if ( $thumbnail !== '' ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( esc_url_raw( $thumbnail ), $post_id, get_the_title( $post_id ), 'id' );
        if ( ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
        } elseif ( function_exists( 'coffeebrk_log_error' ) ) {
            coffeebrk_log_error( 'Story thumbnail sideload failed: ' . $attachment_id->get_error_message(), [ 'source' => 'stories_rest', 'post_id' => $post_id ] );
        }
    }
}

function coffeebrk_stories_api_delete_story( WP_REST_Request $req ) {
    $id = (int) $req->get_param( 'id' );
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'cbk_story' ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'story_not_found' ], 404 );
    }
    $ok = (bool) wp_trash_post( $id );
    return new WP_REST_Response( [ 'success' => $ok, 'id' => $id ], $ok ? 200 : 500 );
}

function coffeebrk_stories_api_get_stats( WP_REST_Request $req ) {
    $counts = wp_count_posts( 'cbk_story' );

    $youtube_count = ( new WP_Query([
        'post_type' => 'cbk_story', 'post_status' => 'any', 'fields' => 'ids',
        'posts_per_page' => 1, 'meta_query' => [ [ 'key' => '_cbk_story_yt_video_id', 'compare' => 'EXISTS' ] ],
    ]) )->found_posts;

    $visible_count = ( new WP_Query([
        'post_type' => 'cbk_story', 'post_status' => 'publish', 'fields' => 'ids',
        'posts_per_page' => 1,
        'meta_query' => [
            'relation' => 'OR',
            [ 'key' => '_cbk_story_show_frontend', 'value' => 'yes' ],
            [ 'key' => '_cbk_story_show_frontend', 'compare' => 'NOT EXISTS' ],
        ],
    ]) )->found_posts;

    return new WP_REST_Response( [
        'success'        => true,
        'total'          => (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ),
        'published'      => (int) ( $counts->publish ?? 0 ),
        'youtube_sourced'=> (int) $youtube_count,
        'visible'        => (int) $visible_count,
    ], 200 );
}
