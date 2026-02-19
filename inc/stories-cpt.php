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
 * Register Sub-menu for JSON Import
 */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'coffeebrk-core',
        __( 'Import Stories', 'coffeebrk-core' ),
        __( 'Import Stories', 'coffeebrk-core' ),
        'manage_options',
        'cbk-stories-import',
        'cbk_stories_import_page'
    );
}, 20 );

/**
 * Handle JSON Import Logic
 */
add_action( 'admin_init', function() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'cbk-stories-import' ) {
        return;
    }

    if ( ! isset( $_POST['cbk_import_stories_nonce'] ) || ! wp_verify_nonce( $_POST['cbk_import_stories_nonce'], 'cbk_import_stories' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $json_data = '';

    // Handle File Upload
    if ( ! empty( $_FILES['cbk_stories_json_file']['tmp_name'] ) ) {
        $json_data = file_get_contents( $_FILES['cbk_stories_json_file']['tmp_name'] );
    } elseif ( ! empty( $_POST['cbk_stories_json_text'] ) ) {
        $json_data = stripslashes( $_POST['cbk_stories_json_text'] );
    }

    if ( empty( $json_data ) ) {
        add_settings_error( 'cbk_stories_import', 'empty_data', __( 'Please upload a file or paste JSON data.', 'coffeebrk-core' ), 'error' );
        return;
    }

    $stories = json_decode( $json_data, true );

    if ( ! is_array( $stories ) ) {
        add_settings_error( 'cbk_stories_import', 'invalid_json', __( 'Invalid JSON format.', 'coffeebrk-core' ), 'error' );
        return;
    }

    $imported = 0;
    foreach ( $stories as $story ) {
        $title = ! empty( $story['title'] ) ? sanitize_text_field( $story['title'] ) : '';
        $video_url = ! empty( $story['video_url'] ) ? esc_url_raw( $story['video_url'] ) : '';

        if ( empty( $title ) && empty( $video_url ) ) {
            continue;
        }

        $post_id = wp_insert_post([
            'post_title'   => $title ?: __( 'Imported Story', 'coffeebrk-core' ),
            'post_type'    => 'cbk_story',
            'post_status'  => 'publish',
        ]);

        if ( $post_id && ! is_wp_error( $post_id ) ) {
            if ( $video_url ) {
                update_post_meta( $post_id, '_cbk_story_video_url', $video_url );
            }
            // Set show on frontend to 'yes' by default for imported stories
            update_post_meta( $post_id, '_cbk_story_show_frontend', 'yes' );
            $imported++;
        }
    }

    add_settings_error( 'cbk_stories_import', 'success', sprintf( __( 'Successfully imported %d stories.', 'coffeebrk-core' ), $imported ), 'updated' );
});

/**
 * Handle Sample JSON Download
 */
add_action( 'admin_init', function() {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'cbk_download_sample_stories' ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $sample = [
            [
                'title' => 'Sample Story 1',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
            ],
            [
                'title' => 'Sample Story 2',
                'video_url' => 'https://youtu.be/ZT8690E-r6U'
            ]
        ];

        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="sample-stories.json"' );
        echo json_encode( $sample, JSON_PRETTY_PRINT );
        exit;
    }
});

/**
 * Render Import Page
 */
function cbk_stories_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php _e( 'Import Stories from JSON', 'coffeebrk-core' ); ?></h1>
        <?php settings_errors( 'cbk_stories_import' ); ?>

        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'cbk_import_stories', 'cbk_import_stories_nonce' ); ?>
                
                <p>
                    <label for="cbk_stories_json_file"><strong><?php _e( 'Upload JSON File', 'coffeebrk-core' ); ?></strong></label><br>
                    <input type="file" id="cbk_stories_json_file" name="cbk_stories_json_file" accept=".json">
                </p>

                <p>
                    <label for="cbk_stories_json_text"><strong><?php _e( 'Or Paste JSON Data', 'coffeebrk-core' ); ?></strong></label><br>
                    <textarea id="cbk_stories_json_text" name="cbk_stories_json_text" rows="10" class="widefat" placeholder='[{"title": "...", "video_url": "..."}]'></textarea>
                </p>

                <p>
                    <input type="submit" class="button button-primary" value="<?php _e( 'Start Import', 'coffeebrk-core' ); ?>">
                    <a href="<?php echo esc_url( add_query_arg( 'action', 'cbk_download_sample_stories', admin_url() ) ); ?>" class="button button-secondary"><?php _e( 'Download Sample JSON', 'coffeebrk-core' ); ?></a>
                </p>
            </form>
        </div>

        <h2 style="margin-top: 40px;"><?php _e( 'Sample JSON Structure', 'coffeebrk-core' ); ?></h2>
        <pre style="background: #f0f0f1; padding: 15px; border-radius: 4px; overflow: auto; max-width: 800px;">
[
  {
    "title": "Story Title Here",
    "video_url": "https://www.youtube.com/watch?v=..."
  },
  {
    "title": "Another Story",
    "video_url": "https://youtu.be/..."
  }
]
        </pre>
    </div>
    <?php
}

/**
 * Add Import Button to Stories List
 */
add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'edit-cbk_story' ) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('<a href="<?php echo esc_url( admin_url( 'admin.php?page=cbk-stories-import' ) ); ?>" class="page-title-action"><?php _e( 'Import JSON', 'coffeebrk-core' ); ?></a>').insertAfter('.page-title-action:first');
            });
        </script>
        <?php
    }
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

    $gradient_intensity = get_post_meta( $post->ID, '_cbk_story_gradient_intensity', true );
    if ( $gradient_intensity === '' ) {
        $gradient_intensity = 50; // Default 50%
    }

    $show_frontend = get_post_meta( $post->ID, '_cbk_story_show_frontend', true );
    if ( $show_frontend === '' ) {
        $show_frontend = 'yes'; // Default to yes
    }
    ?>
    <p>
        <label for="cbk_story_show_frontend">
            <input type="checkbox" id="cbk_story_show_frontend" name="cbk_story_show_frontend" value="yes" <?php checked( $show_frontend, 'yes' ); ?>>
            <strong><?php _e( 'Show on Frontend', 'coffeebrk-core' ); ?></strong>
        </label><br>
        <span class="description"><?php _e( 'Uncheck this to hide this story from the widget.', 'coffeebrk-core' ); ?></span>
    </p>

    <p>
        <label for="cbk_story_video_url"><strong><?php _e( 'Video URL', 'coffeebrk-core' ); ?></strong></label><br>
        <input type="url" id="cbk_story_video_url" name="cbk_story_video_url" value="<?php echo esc_attr( $video_url ); ?>" class="widefat" placeholder="https://www.youtube.com/watch?v=...">
        <span class="description"><?php _e( 'Enter YouTube, Vimeo, or direct video URL.', 'coffeebrk-core' ); ?></span>
    </p>

    <p>
        <label for="cbk_story_gradient"><strong><?php _e( 'Gradient Overlay Color', 'coffeebrk-core' ); ?></strong></label><br>
        <input type="color" id="cbk_story_gradient" name="cbk_story_gradient" value="<?php echo esc_attr( $gradient ); ?>">
        <span class="description"><?php _e( 'Leave default for auto-detect from thumbnail.', 'coffeebrk-core' ); ?></span>
    </p>

    <p>
        <label for="cbk_story_gradient_intensity"><strong><?php _e( 'Gradient Intensity', 'coffeebrk-core' ); ?></strong></label><br>
        <input type="range" id="cbk_story_gradient_intensity" name="cbk_story_gradient_intensity" min="0" max="100" value="<?php echo esc_attr( $gradient_intensity ); ?>" style="width: 200px;">
        <span id="cbk_gradient_intensity_value"><?php echo esc_html( $gradient_intensity ); ?>%</span>
        <script>
            document.getElementById('cbk_story_gradient_intensity').addEventListener('input', function() {
                document.getElementById('cbk_gradient_intensity_value').textContent = this.value + '%';
            });
        </script>
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

    // Save Gradient Intensity
    if ( isset( $_POST['cbk_story_gradient_intensity'] ) ) {
        $intensity = intval( $_POST['cbk_story_gradient_intensity'] );
        $intensity = max( 0, min( 100, $intensity ) ); // Clamp 0-100
        update_post_meta( $post_id, '_cbk_story_gradient_intensity', $intensity );
    }

    // Save Show on Frontend
    $show_frontend = isset( $_POST['cbk_story_show_frontend'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_cbk_story_show_frontend', $show_frontend );
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
    $new_columns['show_frontend'] = __( 'Show', 'coffeebrk-core' );
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
        case 'show_frontend':
            $show = get_post_meta( $post_id, '_cbk_story_show_frontend', true );
            $is_visible = ( $show !== 'no' );
            $icon_class = $is_visible ? 'dashicons-visibility' : 'dashicons-hidden';
            $icon_color = $is_visible ? '#46b450' : '#aaa';
            $title = $is_visible ? __( 'Visible - Click to hide', 'coffeebrk-core' ) : __( 'Hidden - Click to show', 'coffeebrk-core' );
            printf(
                '<a href="#" class="cbk-toggle-visibility" data-post-id="%d" data-visible="%s" title="%s" style="text-decoration:none;">
                    <span class="dashicons %s" style="color:%s;cursor:pointer;"></span>
                </a>',
                esc_attr( $post_id ),
                esc_attr( $is_visible ? 'yes' : 'no' ),
                esc_attr( $title ),
                esc_attr( $icon_class ),
                esc_attr( $icon_color )
            );
            break;
    }
}, 10, 2 );

/**
 * AJAX Handler for Toggle Visibility
 */
add_action( 'wp_ajax_cbk_toggle_story_visibility', function() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cbk_toggle_visibility' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
    }

    // Check permissions
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Invalid post ID' ] );
    }

    // Get current value and toggle
    $current = get_post_meta( $post_id, '_cbk_story_show_frontend', true );
    $new_value = ( $current === 'no' ) ? 'yes' : 'no';

    update_post_meta( $post_id, '_cbk_story_show_frontend', $new_value );

    wp_send_json_success( [
        'new_value' => $new_value,
        'is_visible' => ( $new_value !== 'no' ),
    ] );
});

/**
 * Enqueue Admin Scripts for Stories List
 */
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'edit-cbk_story' ) {
        return;
    }

    $nonce = wp_create_nonce( 'cbk_toggle_visibility' );
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.cbk-toggle-visibility').on('click', function(e) {
            e.preventDefault();

            var $link = $(this);
            var postId = $link.data('post-id');
            var $icon = $link.find('.dashicons');

            // Disable during request
            $link.css('pointer-events', 'none');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cbk_toggle_story_visibility',
                    post_id: postId,
                    nonce: '<?php echo esc_js( $nonce ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var isVisible = response.data.is_visible;
                        $icon.removeClass('dashicons-visibility dashicons-hidden')
                             .addClass(isVisible ? 'dashicons-visibility' : 'dashicons-hidden')
                             .css('color', isVisible ? '#46b450' : '#aaa');
                        $link.attr('title', isVisible ? '<?php echo esc_js( __( 'Visible - Click to hide', 'coffeebrk-core' ) ); ?>' : '<?php echo esc_js( __( 'Hidden - Click to show', 'coffeebrk-core' ) ); ?>');
                        $link.data('visible', isVisible ? 'yes' : 'no');
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Request failed');
                },
                complete: function() {
                    $link.css('pointer-events', 'auto');
                }
            });
        });
    });
    </script>
    <?php
});
