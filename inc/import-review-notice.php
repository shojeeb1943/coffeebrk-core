<?php
/**
 * Admin notice surfacing RSS/JSON-imported articles stuck in draft awaiting
 * editorial review. Imports are intentionally left as drafts (not
 * auto-published); this just makes the backlog visible instead of silent.
 *
 * @package Coffeebrk_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    $count = ( new WP_Query( [
        'post_type'      => 'post',
        'post_status'    => 'draft',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_source_url',
        'no_found_rows'  => false,
    ] ) )->found_posts;

    if ( $count < 1 ) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
        esc_html( sprintf(
            _n(
                '%d imported article is awaiting review.',
                '%d imported articles are awaiting review.',
                $count,
                'coffeebrk-core'
            ),
            $count
        ) ),
        esc_url( admin_url( 'edit.php?post_status=draft&post_type=post' ) ),
        esc_html__( 'Review now', 'coffeebrk-core' )
    );
} );
