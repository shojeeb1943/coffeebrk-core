<?php
if (!defined('ABSPATH')) exit;

// /wp-json/coffeebrk/v1/feed - returns posts matching user's aspire CSV
add_action('rest_api_init', function(){
    register_rest_route('coffeebrk/v1', '/feed', [
        'methods'  => 'GET',
        'permission_callback' => function(){ return is_user_logged_in(); },
        'callback' => function( WP_REST_Request $req ){
            $user = wp_get_current_user();
            $aspire_csv = get_user_meta($user->ID, 'aspire_csv', true);
            $needles = array_filter(array_map('trim', explode(',', (string) $aspire_csv)));
            $paged = max(1, (int) $req->get_param('page'));
            $ppp   = max(1, min(50, (int) $req->get_param('per_page') ?: 10));

            $q = new WP_Query([
                'post_type' => apply_filters('coffeebrk_meta_post_types', ['post']),
                'post_status' => 'publish',
                'paged' => $paged,
                'posts_per_page' => $ppp,
                'meta_query' => [
                    [
                        'key' => '_post_aspires_csv',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]);

            $items = [];
            foreach ($q->posts as $p){
                $csv = (string) get_post_meta($p->ID, '_post_aspires_csv', true);
                $hay = array_map('trim', explode(',', $csv));
                $match = array_values(array_intersect($needles, $hay));
                if (!empty($needles) && empty($match)) continue; // only include matching posts if user has aspires
                $items[] = [
                    'id' => $p->ID,
                    'title' => get_the_title($p),
                    'excerpt' => wp_trim_words( wp_strip_all_tags( get_post_field('post_content', $p->ID) ), 30 ),
                    'permalink' => get_permalink($p),
                    'matched_aspires' => $match,
                ];
            }

            return new WP_REST_Response([
                'page' => $paged,
                'per_page' => $ppp,
                'total' => (int) $q->found_posts,
                'items' => $items,
            ], 200);
        }
    ]);

    register_rest_route('coffeebrk/v1', '/site', [
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function( WP_REST_Request $req ){
            $ver = '';
            if ( defined( 'COFFEEBRK_CORE_PATH' ) && function_exists( 'get_file_data' ) ) {
                $data = get_file_data( COFFEEBRK_CORE_PATH . 'coffeebrk-core.php', [ 'Version' => 'Version' ] );
                $ver = (string) ( $data['Version'] ?? '' );
            }

            return new WP_REST_Response([
                'name' => get_bloginfo( 'name' ),
                'description' => get_bloginfo( 'description' ),
                'url' => home_url( '/' ),
                'timezone' => wp_timezone_string(),
                'coffeebrk_core_version' => $ver,
            ], 200);
        },
    ]);

    register_rest_route('coffeebrk/v1', '/submit', [
        'methods'  => 'POST',
        'permission_callback' => function( WP_REST_Request $req ){
            if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
                return true;
            }
            $token = coffeebrk_core_get_bearer_token_from_rest_request( $req );
            if ( $token === '' ) {
                return false;
            }
            // Use new multi-token system if available
            if ( function_exists( 'coffeebrk_token_has_permission' ) ) {
                return coffeebrk_token_has_permission( $token, 'write' );
            }
            // Fallback to old system
            return function_exists( 'coffeebrk_core_api_token_is_valid' ) && coffeebrk_core_api_token_is_valid( $token );
        },
        'callback' => function( WP_REST_Request $req ){
            $params = $req->get_json_params();
            if ( ! is_array( $params ) ) {
                $params = $req->get_params();
            }

            $title = sanitize_text_field( (string) ( $params['title'] ?? '' ) );
            $content = isset( $params['content'] ) ? wp_kses_post( (string) $params['content'] ) : '';
            $status = sanitize_key( (string) ( $params['status'] ?? 'draft' ) );
            if ( ! in_array( $status, [ 'draft', 'publish', 'pending' ], true ) ) {
                $status = 'draft';
            }

            if ( $title === '' && $content === '' ) {
                return new WP_REST_Response([ 'error' => 'missing_title_or_content' ], 400);
            }

            $post_id = wp_insert_post([
                'post_type' => 'post',
                'post_status' => $status,
                'post_title' => $title !== '' ? $title : '(Untitled)',
                'post_content' => $content,
            ], true);

            if ( is_wp_error( $post_id ) ) {
                return new WP_REST_Response([ 'error' => 'insert_failed', 'message' => $post_id->get_error_message() ], 500);
            }

            $source_url = isset( $params['source_url'] ) ? esc_url_raw( (string) $params['source_url'] ) : '';
            $source_name = sanitize_text_field( (string) ( $params['source_name'] ?? '' ) );
            $image_url = isset( $params['image_url'] ) ? esc_url_raw( (string) $params['image_url'] ) : '';

            if ( $source_url !== '' ) {
                update_post_meta( (int) $post_id, '_source_url', $source_url );
            }
            if ( $source_name !== '' ) {
                update_post_meta( (int) $post_id, '_source_name', $source_name );
            }
            if ( $image_url !== '' ) {
                update_post_meta( (int) $post_id, '_image', $image_url );
            }

            $cat_id = isset( $params['category_id'] ) ? (int) $params['category_id'] : 0;
            if ( $cat_id > 0 ) {
                wp_set_post_categories( (int) $post_id, [ $cat_id ], false );
            }

            $dyn = (array) get_option( 'coffeebrk_dynamic_fields', [] );
            $dyn_index = [];
            foreach ( $dyn as $f ) {
                if ( ! is_array( $f ) ) continue;
                $k = (string) ( $f['key'] ?? '' );
                if ( $k === '' ) continue;
                $dyn_index[ $k ] = [
                    'type' => (string) ( $f['type'] ?? 'text' ),
                    'choices' => (string) ( $f['choices'] ?? '' ),
                ];
            }

            $incoming_meta = $params['meta'] ?? [];
            if ( is_array( $incoming_meta ) ) {
                foreach ( $incoming_meta as $meta_key => $raw ) {
                    $meta_key = (string) $meta_key;
                    if ( $meta_key === '' ) continue;
                    if ( $meta_key[0] !== '_' ) {
                        $meta_key = '_' . ltrim( $meta_key, '_' );
                    }
                    if ( ! isset( $dyn_index[ $meta_key ] ) ) {
                        continue;
                    }

                    $type = $dyn_index[ $meta_key ]['type'];
                    switch ( $type ) {
                        case 'textarea':
                            $val = sanitize_textarea_field( (string) $raw );
                            break;
                        case 'url':
                        case 'image_url':
                            $val = esc_url_raw( (string) $raw );
                            break;
                        case 'number':
                            $val = is_numeric( $raw ) ? ( $raw + 0 ) : '';
                            break;
                        case 'select':
                            $val = sanitize_text_field( (string) $raw );
                            break;
                        default:
                            $val = sanitize_text_field( (string) $raw );
                    }

                    if ( $val === '' || $val === null ) {
                        delete_post_meta( (int) $post_id, $meta_key );
                    } else {
                        update_post_meta( (int) $post_id, $meta_key, $val );
                    }
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'id' => (int) $post_id,
                'status' => get_post_status( (int) $post_id ),
                'edit_link' => get_edit_post_link( (int) $post_id, 'raw' ),
                'permalink' => get_permalink( (int) $post_id ),
            ], 201);
        },
    ]);
});

function coffeebrk_core_get_bearer_token_from_rest_request( WP_REST_Request $req ) : string {
    $auth = (string) $req->get_header( 'authorization' );
    if ( $auth === '' ) {
        $auth = (string) $req->get_header( 'Authorization' );
    }
    if ( $auth === '' ) {
        $auth = (string) $req->get_header( 'x-coffeebrk-token' );
    }

    if ( $auth === '' ) {
        return '';
    }

    if ( stripos( $auth, 'Bearer ' ) === 0 ) {
        return trim( substr( $auth, 7 ) );
    }

    return trim( $auth );
}

function coffeebrk_core_api_token_is_valid( string $token ) : bool {
    $token = trim( $token );
    if ( $token === '' ) return false;

    $hash = (string) get_option( 'coffeebrk_core_api_token_hash', '' );
    if ( $hash === '' ) return false;

    return password_verify( $token, $hash );
}

add_action( 'init', function(){
    add_feed( 'coffeebrk', 'coffeebrk_core_do_feed' );
} );

function coffeebrk_core_do_feed(){
    $ppp = 20;
    $q = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => $ppp,
        'no_found_rows' => true,
    ]);

    header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
    echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>';
    echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">';
    echo '<channel>';
    echo '<title>' . esc_html( get_bloginfo( 'name' ) ) . '</title>';
    echo '<link>' . esc_url( home_url( '/' ) ) . '</link>';
    echo '<description>' . esc_html( get_bloginfo( 'description' ) ) . '</description>';
    echo '<language>' . esc_html( get_bloginfo( 'language' ) ) . '</language>';
    echo '<lastBuildDate>' . esc_html( mysql2date( 'r', get_lastpostmodified( 'GMT' ), false ) ) . '</lastBuildDate>';

    foreach ( (array) $q->posts as $p ) {
        $link = get_permalink( $p );
        $title = get_the_title( $p );
        $date = get_post_time( 'r', true, $p );
        $content = get_post_field( 'post_content', $p );
        $excerpt = get_post_field( 'post_excerpt', $p );
        if ( $excerpt === '' ) {
            $excerpt = wp_trim_words( wp_strip_all_tags( (string) $content ), 55 );
        }

        echo '<item>';
        echo '<title>' . esc_html( $title ) . '</title>';
        echo '<link>' . esc_url( $link ) . '</link>';
        echo '<guid isPermaLink="true">' . esc_url( $link ) . '</guid>';
        echo '<pubDate>' . esc_html( $date ) . '</pubDate>';
        echo '<description><![CDATA[' . $excerpt . ']]></description>';
        echo '<content:encoded><![CDATA[' . (string) $content . ']]></content:encoded>';
        echo '</item>';
    }

    echo '</channel>';
    echo '</rss>';
    exit;
}
