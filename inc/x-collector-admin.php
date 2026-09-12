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

// "X Activity" admin page — the module has no existing admin visibility
// beyond the post list, unlike RSS/Logs/Tokens which already have their own
// screens. Structurally mirrors inc/rss-admin.php's log table.
add_action( 'admin_menu', function() {
    add_submenu_page(
        'coffeebrk-core',
        'X Activity',
        'X Activity',
        'manage_options',
        'coffeebrk-core-x-activity',
        'coffeebrk_x_activity_admin_page'
    );
}, 20 );

function coffeebrk_x_activity_admin_page() : void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $stats = coffeebrk_x_get_stats();

    echo '<div class="wrap">';
    echo '<h1>X Activity</h1>';

    echo '<p style="margin:4px 0 14px;color:#555;">'
        . '<strong>Posts:</strong> ' . esc_html( (string) $stats['total'] ) . ' total ('
        . esc_html( (string) $stats['published'] ) . ' published, ' . esc_html( (string) $stats['draft'] ) . ' draft) &nbsp;|&nbsp; '
        . '<strong>Imported today:</strong> ' . esc_html( (string) $stats['imported_today'] ) . ' &nbsp;|&nbsp; '
        . '<strong>Last 24h:</strong> ' . esc_html( (string) $stats['imported_last_24h'] ) . ' &nbsp;|&nbsp; '
        . '<strong>Last import:</strong> ' . esc_html( $stats['last_import_at'] ? wp_date( 'Y-m-d H:i:s', strtotime( $stats['last_import_at'] ) ) : 'Never' )
        . '</p>';

    if ( ! empty( $stats['tokens'] ) ) {
        echo '<h2 style="margin-top:20px;">API Tokens</h2>';
        echo '<table class="widefat striped" style="max-width:700px;"><thead><tr><th>Name</th><th>Last Used</th><th>Permissions</th><th>Status</th></tr></thead><tbody>';
        foreach ( $stats['tokens'] as $t ) {
            echo '<tr>';
            echo '<td>' . esc_html( (string) $t['name'] ) . '</td>';
            echo '<td>' . esc_html( coffeebrk_time_ago( $t['last_used'] ) ) . '</td>';
            echo '<td>' . esc_html( implode( ', ', (array) $t['permissions'] ) ) . '</td>';
            echo '<td>' . esc_html( (string) $t['status'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    $log = array_reverse( coffeebrk_x_log_get_last_24h() );

    echo '<h2 style="margin-top:24px;">Last 24 hours (ingestion history)</h2>';
    echo '<table class="widefat striped">'
        . '<thead><tr>'
        . '<th style="width:170px;">Time</th>'
        . '<th style="width:80px;">Type</th>'
        . '<th>Token</th>'
        . '<th style="width:80px;">Created</th>'
        . '<th style="width:80px;">Skipped</th>'
        . '<th style="width:90px;">Status</th>'
        . '<th>Errors / Tweet IDs</th>'
        . '</tr></thead><tbody>';

    if ( ! empty( $log ) ) {
        foreach ( $log as $row ) {
            if ( ! is_array( $row ) ) continue;
            $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
            $time_str = $t > 0 ? wp_date( 'Y-m-d H:i:s', $t ) : '';
            $event = isset( $row['event'] ) ? (string) $row['event'] : '';
            $token_name = isset( $row['token_name'] ) ? (string) $row['token_name'] : '';
            $created = isset( $row['created'] ) ? (int) $row['created'] : 0;
            $skipped = isset( $row['skipped'] ) ? (int) $row['skipped'] : 0;
            $status = isset( $row['status'] ) ? (string) $row['status'] : '';
            $cls = ( $status === 'ok' ) ? 'cbk-ok' : ( ( $status === 'error' || $status === 'partial' ) ? 'cbk-fail' : ( $status === 'skip' ? 'cbk-skip' : '' ) );

            $tail_parts = [];
            if ( ! empty( $row['reason'] ) ) {
                $tail_parts[] = 'Reason: ' . (string) $row['reason'];
            }
            if ( ! empty( $row['errors'] ) && is_array( $row['errors'] ) ) {
                $tail_parts[] = count( $row['errors'] ) . ' error(s)';
            }
            if ( ! empty( $row['tweet_ids'] ) && is_array( $row['tweet_ids'] ) ) {
                $tail_parts[] = implode( ', ', array_slice( $row['tweet_ids'], 0, 5 ) );
            }

            echo '<tr>';
            echo '<td>' . esc_html( $time_str ) . '</td>';
            echo '<td>' . esc_html( $event ) . '</td>';
            echo '<td>' . esc_html( $token_name ) . '</td>';
            echo '<td>' . esc_html( (string) $created ) . '</td>';
            echo '<td>' . esc_html( (string) $skipped ) . '</td>';
            echo '<td class="' . esc_attr( $cls ) . '">' . esc_html( $status ) . '</td>';
            echo '<td>' . esc_html( implode( ' | ', $tail_parts ) ) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7" style="color:#666;">No ingestion activity in the last 24 hours.</td></tr>';
    }

    echo '</tbody></table>';
    echo '<style>.cbk-ok{color:#0a7b34;font-weight:600;} .cbk-skip{color:#8a6d3b;font-weight:600;} .cbk-fail{color:#b32d2e;font-weight:600;}</style>';
    echo '</div>';
}
