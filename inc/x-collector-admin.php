<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// X (Twitter) collector admin UI. cbk_x_post is a real CPT (registered in
// x-collector.php) so the native WordPress post list already gives us
// search, pagination, Trash/Restore, and bulk actions for free — this file
// only adds the columns and the one non-native action (Feature/Unfeature).

add_filter( 'manage_edit-cbk_x_post_columns', function( $columns ) {
    return [
        'cb'         => $columns['cb'] ?? '<input type="checkbox" />',
        'title'      => __( 'Text', 'coffeebrk-core' ),
        'author_col' => __( 'Author', 'coffeebrk-core' ),
        'engagement' => __( 'Engagement', 'coffeebrk-core' ),
        'posted'     => __( 'Posted', 'coffeebrk-core' ),
        'imported'   => __( 'Imported', 'coffeebrk-core' ),
        'featured'   => __( 'Featured', 'coffeebrk-core' ),
        'permalink'  => __( 'Link', 'coffeebrk-core' ),
    ];
});

add_action( 'manage_cbk_x_post_posts_custom_column', function( $column, $post_id ) {
    switch ( $column ) {
        case 'author_col':
            echo esc_html( '@' . (string) get_post_meta( $post_id, '_cbk_x_author_username', true ) );
            break;

        case 'engagement':
            printf(
                '♥ %d &nbsp; ⇄ %d &nbsp; ↩ %d',
                (int) get_post_meta( $post_id, '_cbk_x_like_count', true ),
                (int) get_post_meta( $post_id, '_cbk_x_retweet_count', true ),
                (int) get_post_meta( $post_id, '_cbk_x_reply_count', true )
            );
            break;

        case 'posted':
            echo esc_html( get_the_date( 'Y-m-d H:i', $post_id ) ?: '—' );
            break;

        case 'imported':
            echo esc_html( get_the_modified_date( 'Y-m-d H:i', $post_id ) ?: '—' );
            break;

        case 'featured':
            $is_featured = (int) get_post_meta( $post_id, '_cbk_x_is_featured', true ) === 1;
            $nonce = wp_create_nonce( 'coffeebrk_x_toggle_post_featured_' . $post_id );
            printf(
                '<a href="%s">%s</a>',
                esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_toggle_post_featured&post_id=' . $post_id . '&_wpnonce=' . $nonce ) ),
                $is_featured ? esc_html__( 'Unfeature', 'coffeebrk-core' ) : esc_html__( 'Feature', 'coffeebrk-core' )
            );
            break;

        case 'permalink':
            $url = (string) get_post_meta( $post_id, '_cbk_x_permalink', true );
            echo $url
                ? sprintf( '<a href="%s" target="_blank" rel="noopener">View on X</a>', esc_url( $url ) )
                : '—';
            break;
    }
}, 10, 2 );

add_filter( 'manage_edit-cbk_x_post_sortable_columns', function( $columns ) {
    $columns['posted']   = 'date';
    $columns['imported'] = 'modified';
    return $columns;
});

// Default to most-recently-imported first — with n8n backfilling old
// tweets, "just imported" is what admins actually want to see first.
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'cbk_x_post' ) return;
    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'modified' );
        $query->set( 'order', 'DESC' );
    }
});

add_action( 'admin_post_coffeebrk_x_toggle_post_featured', 'coffeebrk_x_handle_toggle_post_featured' );

function coffeebrk_x_handle_toggle_post_featured() : void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

    $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    if ( $post_id <= 0 ) wp_die( 'Invalid post', 400 );

    check_admin_referer( 'coffeebrk_x_toggle_post_featured_' . $post_id );

    $is_featured = (int) get_post_meta( $post_id, '_cbk_x_is_featured', true ) === 1;
    coffeebrk_x_set_post_featured( $post_id, ! $is_featured );

    wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php?post_type=cbk_x_post' ) );
    exit;
}
