<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------
// cbk_x_post CPT storage. All fields (engagement stats + author snapshot)
// are denormalized directly onto the post as meta — there's no separate
// "profile" entity anymore since n8n can send any author, not just a
// curated tracked list.
// ---------------------------------------------------------------------

// Dedupe check by tweet id, mirroring the YouTube importer's
// _cbk_story_yt_video_id meta_query pattern (inc/youtube-importer-rest.php).
function coffeebrk_x_post_exists_by_tweet_id( string $tweet_id ) : ?int {
    if ( $tweet_id === '' ) return null;

    $found = get_posts([
        'post_type'      => 'cbk_x_post',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [ 'key' => '_cbk_x_tweet_id', 'value' => $tweet_id, 'compare' => '=' ],
        ],
    ]);

    return ! empty( $found ) ? (int) $found[0] : null;
}

// Inserts a normalized tweet (see coffeebrk_x_normalize_apify_item()) as a
// cbk_x_post. Returns the new post ID, or a WP_Error on failure.
function coffeebrk_x_insert_post_row( array $data ) {
    $text = (string) ( $data['text'] ?? '' );
    $title = $text !== '' ? mb_substr( $text, 0, 80 ) : 'X Post';

    $post_args = [
        'post_type'    => 'cbk_x_post',
        'post_status'  => ( ( $data['post_status'] ?? 'publish' ) === 'draft' ) ? 'draft' : 'publish',
        'post_title'   => $title,
        'post_content' => $text,
    ];

    // Use the tweet's own date as post_date so native WP sorting/display
    // ("Date" column, orderby=date) reflects when it was posted on X;
    // post_modified (left untouched) then naturally records import time.
    if ( ! empty( $data['posted_at'] ) ) {
        $post_args['post_date_gmt'] = $data['posted_at'];
        $post_args['post_date']     = get_date_from_gmt( $data['posted_at'] );
    }

    $post_id = wp_insert_post( $post_args, true );
    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $meta = [
        '_cbk_x_tweet_id'             => (string) ( $data['tweet_id'] ?? '' ),
        '_cbk_x_author_username'      => (string) ( $data['author_username'] ?? '' ),
        '_cbk_x_permalink'            => (string) ( $data['permalink'] ?? '' ),
        '_cbk_x_is_reply'             => (int) ! empty( $data['is_reply'] ),
        '_cbk_x_is_retweet'           => (int) ! empty( $data['is_retweet'] ),
        '_cbk_x_is_quote'             => (int) ! empty( $data['is_quote'] ),
        '_cbk_x_like_count'           => (int) ( $data['like_count'] ?? 0 ),
        '_cbk_x_retweet_count'        => (int) ( $data['retweet_count'] ?? 0 ),
        '_cbk_x_reply_count'          => (int) ( $data['reply_count'] ?? 0 ),
        '_cbk_x_view_count'           => (int) ( $data['view_count'] ?? 0 ),
        '_cbk_x_quote_count'          => (int) ( $data['quote_count'] ?? 0 ),
        '_cbk_x_bookmark_count'       => (int) ( $data['bookmark_count'] ?? 0 ),
        '_cbk_x_lang'                 => (string) ( $data['lang'] ?? '' ),
        '_cbk_x_media_json'           => (string) ( $data['media_json'] ?? '[]' ),
        '_cbk_x_is_featured'          => (int) ! empty( $data['is_featured'] ),
        '_cbk_x_author_display_name'  => (string) ( $data['author_display_name'] ?? '' ),
        '_cbk_x_author_avatar_url'    => (string) ( $data['author_avatar_url'] ?? '' ),
        '_cbk_x_author_followers'     => (int) ( $data['author_followers'] ?? 0 ),
        '_cbk_x_author_verified'      => (int) ! empty( $data['author_verified'] ),
    ];

    foreach ( $meta as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }

    return $post_id;
}

function coffeebrk_x_set_post_status( int $id, string $status ) : bool {
    $status = ( $status === 'draft' ) ? 'draft' : 'publish';
    $res = wp_update_post( [ 'ID' => $id, 'post_status' => $status ], true );
    return ! is_wp_error( $res );
}

function coffeebrk_x_set_post_featured( int $id, bool $featured ) : bool {
    return (bool) update_post_meta( $id, '_cbk_x_is_featured', $featured ? 1 : 0 );
}

function coffeebrk_x_delete_post( int $id ) : bool {
    return (bool) wp_trash_post( $id );
}

// ---------------------------------------------------------------------
// One-time migration from the old custom-table storage (pre-CPT). Safe to
// run repeatedly: dedupes by tweet_id, and no-ops entirely once the old
// tables are gone or the flag is set. Does NOT drop the old tables — that's
// a manual cleanup step once the migrated data has been verified.
// ---------------------------------------------------------------------

function coffeebrk_x_migrate_legacy_posts_to_cpt() : void {
    if ( get_option( 'coffeebrk_x_migrated_to_cpt' ) ) return;

    global $wpdb;
    $old_posts_table = $wpdb->prefix . 'coffeebrk_x_posts';
    $old_profiles_table = $wpdb->prefix . 'coffeebrk_x_profiles';

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_posts_table ) ) !== $old_posts_table ) {
        update_option( 'coffeebrk_x_migrated_to_cpt', 1, false );
        return;
    }

    $has_profiles_table = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_profiles_table ) ) === $old_profiles_table );

    $rows = $wpdb->get_results( "SELECT * FROM {$old_posts_table}", ARRAY_A );

    foreach ( $rows as $row ) {
        $tweet_id = (string) ( $row['tweet_id'] ?? '' );
        if ( $tweet_id === '' || coffeebrk_x_post_exists_by_tweet_id( $tweet_id ) ) continue;

        $profile = null;
        if ( $has_profiles_table && ! empty( $row['profile_id'] ) ) {
            $profile = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$old_profiles_table} WHERE id = %d", (int) $row['profile_id'] ),
                ARRAY_A
            );
        }

        coffeebrk_x_insert_post_row([
            'tweet_id'            => $tweet_id,
            'author_username'     => $row['author_username'] ?? '',
            'text'                => $row['text'] ?? '',
            'permalink'           => $row['permalink'] ?? '',
            'posted_at'           => $row['posted_at'] ?? null,
            'is_reply'            => (int) ( $row['is_reply'] ?? 0 ),
            'is_retweet'          => (int) ( $row['is_retweet'] ?? 0 ),
            'is_quote'            => (int) ( $row['is_quote'] ?? 0 ),
            'like_count'          => (int) ( $row['like_count'] ?? 0 ),
            'retweet_count'       => (int) ( $row['retweet_count'] ?? 0 ),
            'reply_count'         => (int) ( $row['reply_count'] ?? 0 ),
            'view_count'          => (int) ( $row['view_count'] ?? 0 ),
            'quote_count'         => (int) ( $row['quote_count'] ?? 0 ),
            'bookmark_count'      => (int) ( $row['bookmark_count'] ?? 0 ),
            'lang'                => (string) ( $row['lang'] ?? '' ),
            'media_json'          => (string) ( $row['media_json'] ?? '[]' ),
            'is_featured'         => (int) ( $row['is_featured'] ?? 0 ),
            'post_status'         => ( ( $row['status'] ?? 'published' ) === 'hidden' ) ? 'draft' : 'publish',
            'author_display_name' => $profile['display_name'] ?? '',
            'author_avatar_url'   => $profile['avatar_url'] ?? '',
            'author_followers'    => (int) ( $profile['followers_count'] ?? 0 ),
            'author_verified'     => (int) ( $profile['is_verified'] ?? 0 ),
        ]);
    }

    update_option( 'coffeebrk_x_migrated_to_cpt', 1, false );
}
// init (not admin_init) — the site may run for a while on pure n8n
// webhooks + Elementor front-end without anyone visiting wp-admin, and this
// is a no-op single get_option() read after the first successful run.
add_action( 'init', 'coffeebrk_x_migrate_legacy_posts_to_cpt' );
