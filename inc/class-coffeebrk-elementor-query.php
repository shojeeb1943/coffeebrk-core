<?php
/**
 * Adds a "Posts + Stories" Query Source to Elementor Pro's Loop Grid widget,
 * merging WordPress Posts and Story items into a single date-sorted WP_Query.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Inject the extra dropdown option into Loop Grid's own Query section only,
 * so it doesn't leak into Theme Builder conditions, Sitemap, or other widgets
 * that also read Elementor Pro's global public-post-types list.
 */
add_action( 'elementor/element/loop-grid/section_query/after_section_end', function( $element ) {
    $control = $element->get_controls( 'post_query_post_type' );
    if ( ! $control ) {
        return;
    }

    $options = $control['options'];
    $options['coffeebrk_posts_stories'] = __( 'Posts + Stories', 'coffeebrk-core' );

    $element->update_control( 'post_query_post_type', [ 'options' => $options ] );
} );

/**
 * When "Posts + Stories" is selected, expand the single fake post type into
 * the real 'post' + 'cbk_story' types. WP_Query merges and sorts them by the
 * widget's own Order/Order By controls (date DESC by default) natively.
 */
add_filter( 'elementor/query/query_args', function( $query_args, $widget ) {
    if ( 'loop-grid' === $widget->get_name()
        && isset( $query_args['post_type'] )
        && 'coffeebrk_posts_stories' === $query_args['post_type']
    ) {
        $query_args['post_type'] = [ 'post', 'cbk_story' ];
    }

    return $query_args;
}, 10, 2 );
