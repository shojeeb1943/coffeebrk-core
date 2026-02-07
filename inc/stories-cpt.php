<?php
/**
 * Custom Post Type: Stories (cbk_stories)
 * 
 * Registers the 'Stories' CPT under 'Coffeebrk Core' menu.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function() {
    register_post_type( 'cbk_story', [
        'labels' => [
            'name'               => __( 'Stories', 'coffeebrk-core' ),
            'singular_name'      => __( 'Story', 'coffeebrk-core' ),
            'add_new'            => __( 'Add New Story', 'coffeebrk-core' ),
            'add_new_item'       => __( 'Add New Story', 'coffeebrk-core' ),
            'edit_item'          => __( 'Edit Story', 'coffeebrk-core' ),
            'new_item'           => __( 'New Story', 'coffeebrk-core' ),
            'view_item'          => __( 'View Story', 'coffeebrk-core' ),
            'search_items'       => __( 'Search Stories', 'coffeebrk-core' ),
            'not_found'          => __( 'No stories found', 'coffeebrk-core' ),
            'not_found_in_trash' => __( 'No stories found in Trash', 'coffeebrk-core' ),
            'all_items'          => __( 'All Stories', 'coffeebrk-core' ),
        ],
        'public'              => false, // Internal usage primarily
        'show_ui'             => true,
        'show_in_menu'        => 'coffeebrk-core', // Nest under Coffeebrk Core
        'menu_position'       => 20,
        'supports'            => [ 'title', 'thumbnail' ], // Title, Image
        'hierarchical'        => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
});

/**
 * Register Meta Boxes for Stories
 */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'cbk_story_details',
        __( 'Story Details', 'coffeebrk-core' ),
        'cbk_story_details_callback',
        'cbk_story',
        'normal',
        'high'
    );
});

function cbk_story_details_callback( $post ) {
    // Nonce field
    wp_nonce_field( 'cbk_save_story_details', 'cbk_story_nonce' );

    // Get current values
    $video_url = get_post_meta( $post->ID, '_cbk_story_video_url', true );
    $gradient  = get_post_meta( $post->ID, '_cbk_story_gradient', true );
    $text_color = get_post_meta( $post->ID, '_cbk_story_text_color', true );
    
    // Default gradient if empty
    if ( empty( $gradient ) ) {
        $gradient = '#F5F5FF';
    }
    // Default text color if empty
    if ( empty( $text_color ) ) {
        $text_color = '#323232';
    }
    ?>
    <p>
        <label for="cbk_story_video_url"><strong><?php _e( 'Video URL', 'coffeebrk-core' ); ?></strong></label><br>
        <input type="url" id="cbk_story_video_url" name="cbk_story_video_url" value="<?php echo esc_attr( $video_url ); ?>" class="widefat" placeholder="https://youtube.com/watch?v=...">
        <span class="description"><?php _e( 'Enter YouTube, Vimeo, or direct MP4 URL.', 'coffeebrk-core' ); ?></span>
    </p>

    <p>
        <label for="cbk_story_gradient"><strong><?php _e( 'Gradient Overlay Color', 'coffeebrk-core' ); ?></strong></label><br>
        <input type="color" id="cbk_story_gradient" name="cbk_story_gradient" value="<?php echo esc_attr( $gradient ); ?>">
        <span class="description"><?php _e( 'Select the base color for the gradient overlay.', 'coffeebrk-core' ); ?></span>
    </p>

    <p>
        <label for="cbk_story_text_color"><strong><?php _e( 'Text Color', 'coffeebrk-core' ); ?></strong></label><br>
        <input type="color" id="cbk_story_text_color" name="cbk_story_text_color" value="<?php echo esc_attr( $text_color ); ?>">
        <span class="description"><?php _e( 'Override the text color for this story title.', 'coffeebrk-core' ); ?></span>
    </p>
    <?php
}

/**
 * Save Meta Box Data
 */
add_action( 'save_post', function( $post_id ) {
    // Check nonce
    if ( ! isset( $_POST['cbk_story_nonce'] ) || ! wp_verify_nonce( $_POST['cbk_story_nonce'], 'cbk_save_story_details' ) ) {
        return;
    }

    // Check autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Save Video URL
    if ( isset( $_POST['cbk_story_video_url'] ) ) {
        update_post_meta( $post_id, '_cbk_story_video_url', esc_url_raw( $_POST['cbk_story_video_url'] ) );
    }

    // Save Gradient
    if ( isset( $_POST['cbk_story_gradient'] ) ) {
        update_post_meta( $post_id, '_cbk_story_gradient', sanitize_hex_color( $_POST['cbk_story_gradient'] ) );
    }

    // Save Text Color
    if ( isset( $_POST['cbk_story_text_color'] ) ) {
        update_post_meta( $post_id, '_cbk_story_text_color', sanitize_hex_color( $_POST['cbk_story_text_color'] ) );
    }
});

/**
 * Custom Columns for Admin List
 */
add_filter( 'manage_cbk_story_posts_columns', function( $columns ) {
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['thumbnail'] = __( 'Thumbnail', 'coffeebrk-core' );
    $new_columns['title'] = $columns['title'];
    $new_columns['video'] = __( 'Video', 'coffeebrk-core' );
    $new_columns['date'] = $columns['date'];
    return $new_columns;
});

add_action( 'manage_cbk_story_posts_custom_column', function( $column, $post_id ) {
    switch ( $column ) {
        case 'thumbnail':
            if ( has_post_thumbnail( $post_id ) ) {
                echo get_the_post_thumbnail( $post_id, [ 50, 50 ], [ 'style' => 'width:50px;height:50px;object-fit:cover;border-radius:4px;' ] );
            } else {
                echo '<span style="color:#aaa;">—</span>';
            }
            break;
        case 'video':
            $url = get_post_meta( $post_id, '_cbk_story_video_url', true );
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '" target="_blank" title="' . esc_attr( $url ) . '"><span class="dashicons dashicons-video-alt3"></span></a>';
            } else {
                echo '—';
            }
            break;
    }
}, 10, 2 );
