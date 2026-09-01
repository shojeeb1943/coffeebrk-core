<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Pure Apify item -> CoffeeBRK x_posts row mapping. No $wpdb, no HTTP.
// The exact field names Apify's feedminer/x-tweet-scraper actor returns
// aren't guaranteed, so every field is read defensively with fallbacks
// and this function never throws — malformed input just yields null
// (skip) or sane defaults, never a fatal.
function coffeebrk_x_normalize_apify_item( array $item, int $profile_id ) : ?array {
    $tweet_id = '';
    foreach ( [ 'id', 'tweetId', 'id_str' ] as $k ) {
        if ( ! empty( $item[ $k ] ) ) { $tweet_id = (string) $item[ $k ]; break; }
    }
    $tweet_id = trim( $tweet_id );
    if ( $tweet_id === '' ) {
        return null; // no usable id, nothing to dedupe/store against
    }

    $text = '';
    foreach ( [ 'text', 'fullText', 'full_text' ] as $k ) {
        if ( isset( $item[ $k ] ) && is_string( $item[ $k ] ) ) { $text = $item[ $k ]; break; }
    }

    $author_username = '';
    if ( isset( $item['author'] ) && is_array( $item['author'] ) ) {
        foreach ( [ 'userName', 'username', 'screen_name' ] as $k ) {
            if ( ! empty( $item['author'][ $k ] ) ) { $author_username = (string) $item['author'][ $k ]; break; }
        }
    }
    if ( $author_username === '' ) {
        foreach ( [ 'author', 'username', 'screen_name' ] as $k ) {
            if ( isset( $item[ $k ] ) && is_string( $item[ $k ] ) ) { $author_username = $item[ $k ]; break; }
        }
    }
    $author_username = ltrim( trim( $author_username ), '@' );

    $permalink = '';
    foreach ( [ 'url', 'twitterUrl', 'permanentUrl' ] as $k ) {
        if ( ! empty( $item[ $k ] ) && is_string( $item[ $k ] ) ) { $permalink = esc_url_raw( $item[ $k ] ); break; }
    }
    if ( $permalink === '' && $author_username !== '' ) {
        $permalink = 'https://x.com/' . rawurlencode( $author_username ) . '/status/' . rawurlencode( $tweet_id );
    }

    $posted_at = null;
    foreach ( [ 'createdAt', 'created_at', 'date' ] as $k ) {
        if ( ! empty( $item[ $k ] ) ) {
            $ts = strtotime( (string) $item[ $k ] );
            if ( $ts !== false ) {
                $posted_at = gmdate( 'Y-m-d H:i:s', $ts );
            }
            break;
        }
    }

    $to_int = function( $v ) : int {
        return is_numeric( $v ) ? (int) $v : 0;
    };

    $like_count    = $to_int( $item['likeCount'] ?? $item['favorite_count'] ?? null );
    $retweet_count = $to_int( $item['retweetCount'] ?? $item['retweet_count'] ?? null );
    $reply_count   = $to_int( $item['replyCount'] ?? $item['reply_count'] ?? null );

    $is_reply = 0;
    if ( ! empty( $item['isReply'] ) || ! empty( $item['in_reply_to_status_id'] ) || ! empty( $item['inReplyToId'] ) ) {
        $is_reply = 1;
    }

    $media = [];
    $raw_media = $item['media'] ?? ( $item['extendedEntities']['media'] ?? [] );
    if ( is_array( $raw_media ) ) {
        foreach ( array_slice( $raw_media, 0, 4 ) as $m ) {
            if ( ! is_array( $m ) ) continue;
            $url = $m['url'] ?? ( $m['media_url_https'] ?? '' );
            if ( ! is_string( $url ) || $url === '' ) continue;
            $media[] = [
                'type' => ( isset( $m['type'] ) && is_string( $m['type'] ) ) ? $m['type'] : 'photo',
                'url'  => esc_url_raw( $url ),
            ];
        }
    }

    return [
        'profile_id'      => $profile_id,
        'tweet_id'        => sanitize_text_field( $tweet_id ),
        'author_username' => sanitize_text_field( $author_username ),
        'text'            => wp_kses( $text, [] ),
        'permalink'       => $permalink,
        'posted_at'       => $posted_at,
        'is_reply'        => $is_reply,
        'like_count'      => $like_count,
        'retweet_count'   => $retweet_count,
        'reply_count'     => $reply_count,
        'media_json'      => wp_json_encode( $media ),
        'status'          => 'published',
        'is_featured'     => 0,
        'raw_synced_at'   => current_time( 'mysql' ),
        'created_at'      => current_time( 'mysql' ),
    ];
}
