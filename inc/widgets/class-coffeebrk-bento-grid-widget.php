<?php
/**
 * Bento/masonry grid widget that queries Posts and/or Stories, force-
 * interleaves the two types (see merge_and_fetch()), and renders them into
 * a JS-computed shortest-column masonry (assets/js/coffeebrk-bento-grid.js)
 * - self-contained so it doesn't depend on Elementor Pro's Loop Grid +
 * Theme Builder conditions being wired up per post type. Video items reuse
 * the Coffeebrk Universal Video widget's exact markup/classes so the
 * existing coffeebrk-stories.js click-to-play binding picks them up for
 * free.
 *
 * @package Coffeebrk_Core
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_Bento_Grid_Widget extends Widget_Base {

    public function get_name() {
        return 'coffeebrk_bento_grid';
    }

    public function get_title() {
        return __( 'Coffeebrk Bento Grid', 'coffeebrk-core' );
    }

    public function get_icon() {
        return 'eicon-posts-masonry';
    }

    public function get_categories() {
        return [ 'coffeebrk' ];
    }

    public function get_keywords() {
        return [ 'bento', 'grid', 'masonry', 'video', 'article', 'stories', 'posts', 'coffeebrk' ];
    }

    public function get_script_depends() {
        return [ 'coffeebrk-stories', 'coffeebrk-bento-grid' ];
    }

    public function get_style_depends() {
        return [ 'coffeebrk-stories', 'coffeebrk-bento-grid' ];
    }

    protected function register_controls() {

        $this->start_controls_section(
            'section_query',
            [
                'label' => __( 'Query', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'content_types',
            [
                'label' => __( 'Show', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'both' => __( 'Posts + Stories', 'coffeebrk-core' ),
                    'post' => __( 'Posts Only', 'coffeebrk-core' ),
                    'cbk_story' => __( 'Stories Only', 'coffeebrk-core' ),
                ],
                'default' => 'both',
            ]
        );

        $this->add_control(
            'video_ratio_min',
            [
                'label' => __( 'Articles per Video (Min)', 'coffeebrk-core' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 1,
                'min' => 1,
                'max' => 10,
                'condition' => [ 'content_types' => 'both' ],
                'description' => __( 'The gap before the next video varies randomly within this range, seeded fresh each page load - set both to the same number for a fixed rhythm instead.', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'video_ratio_max',
            [
                'label' => __( 'Articles per Video (Max)', 'coffeebrk-core' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 4,
                'min' => 1,
                'max' => 10,
                'condition' => [ 'content_types' => 'both' ],
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => __( 'Items', 'coffeebrk-core' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 12,
                'min' => 1,
                'max' => 50,
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => __( 'Order By', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'date' => __( 'Date', 'coffeebrk-core' ),
                    'title' => __( 'Title', 'coffeebrk-core' ),
                    'rand' => __( 'Random', 'coffeebrk-core' ),
                ],
                'default' => 'date',
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __( 'Order', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'DESC' => __( 'Descending', 'coffeebrk-core' ),
                    'ASC' => __( 'Ascending', 'coffeebrk-core' ),
                ],
                'default' => 'DESC',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_templates',
            [
                'label' => __( 'Item Templates', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $template_options = $this->get_loop_item_template_options();

        $this->add_control(
            'article_template_id',
            [
                'label' => __( 'Article Template', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'options' => $template_options,
                'default' => '',
                'description' => __( 'Elementor Loop Item template used for "post" items. Leave empty for the built-in card.', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'video_template_id',
            [
                'label' => __( 'Story/Video Template', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'options' => $template_options,
                'default' => '',
                'description' => __( 'Elementor Loop Item template used for "cbk_story" items. Leave empty for the built-in card.', 'coffeebrk-core' ),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_layout',
            [
                'label' => __( 'Layout', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => __( 'Columns', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6',
                ],
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'selectors' => [
                    '{{WRAPPER}} .cbk-bento-grid' => '--cbk-bento-columns: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'gap',
            [
                'label' => __( 'Gap', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 60 ],
                ],
                'default' => [ 'size' => 16 ],
                'selectors' => [
                    // Read by the JS masonry engine (assets/js/coffeebrk-bento-grid.js)
                    // via getComputedStyle() - not consumed by CSS directly.
                    '{{WRAPPER}} .cbk-bento-grid' => '--cbk-bento-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'video_aspect',
            [
                'label' => __( 'Video Aspect Ratio', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'default' => '9-16',
                'options' => [
                    '9-16' => __( '9:16 (Shorts/Reels)', 'coffeebrk-core' ),
                    '16-9' => __( '16:9 (Landscape)', 'coffeebrk-core' ),
                    '1-1' => __( '1:1 (Square)', 'coffeebrk-core' ),
                    '4-5' => __( '4:5 (Portrait)', 'coffeebrk-core' ),
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_meta',
            [
                'label' => __( 'Source & Date', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'source_display',
            [
                'label' => __( 'Source Label', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'author' => __( 'Post Author', 'coffeebrk-core' ),
                    'category' => __( 'Category', 'coffeebrk-core' ),
                    'meta' => __( 'Post Meta', 'coffeebrk-core' ),
                    'none' => __( 'Hidden', 'coffeebrk-core' ),
                ],
                'default' => 'author',
            ]
        );

        $this->add_control(
            'source_meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => '_source_name',
                'condition' => [ 'source_display' => 'meta' ],
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label' => __( 'Show Date', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'date_format',
            [
                'label' => __( 'Date Format', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'F j, Y',
                'condition' => [ 'show_date' => 'yes' ],
            ]
        );

        $this->add_control(
            'show_external_icon',
            [
                'label' => __( 'Show External-Link Icon', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_pagination',
            [
                'label' => __( 'Pagination', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'pagination_type',
            [
                'label' => __( 'Pagination', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'none' => __( 'None', 'coffeebrk-core' ),
                    'load_more' => __( 'Load More Button', 'coffeebrk-core' ),
                    'infinite_scroll' => __( 'Infinite Scroll', 'coffeebrk-core' ),
                ],
                'default' => 'none',
            ]
        );

        $this->add_control(
            'load_more_text',
            [
                'label' => __( 'Button Text', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Load More', 'coffeebrk-core' ),
                'condition' => [ 'pagination_type' => 'load_more' ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_card_style',
            [
                'label' => __( 'Card', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label' => __( 'Border Radius', 'coffeebrk-core' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'default' => [ 'top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12, 'unit' => 'px' ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-bento-item__inner' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
                ],
            ]
        );

        $this->add_control(
            'card_background_color',
            [
                'label' => __( 'Background Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#111111',
                'description' => __( 'Shows behind the media while it loads and in any letterboxed corners.', 'coffeebrk-core' ),
                'selectors' => [
                    '{{WRAPPER}} .cbk-bento-item__inner' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .cbk-bento-item',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_typography_style',
            [
                'label' => __( 'Typography', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .cbk-bento-item__title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __( 'Title Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cbk-bento-item__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'meta_typography',
                'selector' => '{{WRAPPER}} .cbk-bento-item__source, {{WRAPPER}} .cbk-bento-item__date',
            ]
        );

        $this->add_control(
            'meta_color',
            [
                'label' => __( 'Source/Date Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cbk-bento-item__source, {{WRAPPER}} .cbk-bento-item__date' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Picked fresh on every real page load, then carried through
        // pagination's data-settings/REST args (render_pagination_control())
        // so "Load More"/infinite scroll continue the SAME randomized
        // video/article rhythm this page load started with, instead of
        // reshuffling mid-scroll.
        $settings['interleave_seed'] = wp_rand( 0, 999999 );

        $result = $this->merge_and_fetch( $settings, 1 );

        if ( empty( $result['posts'] ) ) {
            return;
        }

        echo '<div class="cbk-bento-grid">';

        $this->iterate_posts( $result['posts'], function( $post ) use ( $settings ) {
            $this->render_item( $post, $settings );
        } );

        echo '</div>';

        if ( $settings['pagination_type'] !== 'none' && $result['total_pages'] > 1 ) {
            $this->render_pagination_control( $settings, $result['total_pages'] );
        }
    }

    /**
     * Renders one additional page of items as an HTML string - used by the
     * "Load More" / infinite scroll REST endpoint (inc/bento-grid-rest.php)
     * so pagination reuses the exact same query + card markup as the initial
     * server-side render instead of drifting out of sync with it.
     */
    public function render_items_page( array $settings, $paged ) {
        $settings = array_merge( $this->get_settings_defaults(), $settings );
        $result = $this->merge_and_fetch( $settings, $paged );

        ob_start();
        $this->iterate_posts( $result['posts'], function( $post ) use ( $settings ) {
            $this->render_item( $post, $settings );
        } );

        return [
            'html' => ob_get_clean(),
            'total_pages' => $result['total_pages'],
        ];
    }

    /**
     * Runs $callback( $post ) once per post with correct global post-data
     * context, WITHOUT touching $GLOBALS['wp_query'] (the site's main page
     * query) - required for Loop Item templates' Elementor dynamic tags to
     * resolve the right post per card.
     *
     * $posts comes from a hand-merged array (two separate per-type
     * WP_Query results interleaved in PHP), not one WP_Query's own
     * have_posts()/the_post() loop, so that context has to be rebuilt. The
     * global setup_postdata() function is NOT a safe way to do this: WP
     * core defines it as delegating to $GLOBALS['wp_query']->setup_postdata()
     * - i.e. it mutates the site's MAIN query object, not an isolated one.
     * Calling it once per card corrupts that shared object for the rest of
     * the page render. Instead, seed an empty (never-queried) WP_Query with
     * the merged posts and use ITS OWN the_post()/setup_postdata() instance
     * methods, which scope all postdata mutation to that throwaway object -
     * exactly what a single real WP_Query loop did before this merge existed.
     */
    private function iterate_posts( array $posts, callable $callback ) {
        if ( empty( $posts ) ) {
            return;
        }

        $fake_query = new WP_Query();
        $fake_query->posts = $posts;
        $fake_query->post_count = count( $posts );
        $fake_query->current_post = -1;
        $fake_query->in_the_loop = false;
        $fake_query->is_main_query = false;

        while ( $fake_query->have_posts() ) {
            $fake_query->the_post();
            $callback( get_post() );
        }

        wp_reset_postdata();
    }

    /**
     * Single-type query path (content_types = 'post' or 'cbk_story' only).
     * Ordinary WP_Query pagination - no interleaving needed.
     */
    private function build_query_args( $settings, $paged ) {
        $post_type = $settings['content_types'];

        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => (int) ( $settings['posts_per_page'] ?: 12 ),
            'paged' => max( 1, (int) $paged ),
            'orderby' => $this->resolve_orderby( $settings, $paged ),
            'order' => $settings['order'] ?: 'DESC',
            'ignore_sticky_posts' => true,
        ];

        if ( $post_type === 'cbk_story' ) {
            $args['meta_query'] = $this->story_visibility_meta_query();
        }

        return $args;
    }

    /**
     * Per-type query args used by the interleave path - same filters as
     * build_query_args() but offset/count driven (no 'paged') so two
     * independent per-type queries can be sliced precisely for one merged
     * page (see compute_interleave_page()).
     */
    private function build_type_query_args( $settings, $post_type, $offset, $count, $paged ) {
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => max( 1, (int) $count ),
            'offset' => max( 0, (int) $offset ),
            'orderby' => $this->resolve_orderby( $settings, $paged ),
            'order' => $settings['order'] ?: 'DESC',
            'ignore_sticky_posts' => true,
        ];

        if ( $post_type === 'cbk_story' ) {
            $args['meta_query'] = $this->story_visibility_meta_query();
        }

        return $args;
    }

    // Respect the same "hidden from frontend" story toggle every other
    // Coffeebrk story query honors (inc/stories-rest.php). Regular posts
    // never have this meta key, so they always pass the NOT EXISTS leg.
    private function story_visibility_meta_query() {
        return [
            'relation' => 'OR',
            [ 'key' => '_cbk_story_show_frontend', 'value' => 'yes' ],
            [ 'key' => '_cbk_story_show_frontend', 'compare' => 'NOT EXISTS' ],
        ];
    }

    // orderby=rand + offset/paged-based pagination don't mix - each request
    // reshuffles, so an offset stops meaning anything between page loads.
    // Coerce to date ordering once pagination is actually in play.
    private function resolve_orderby( $settings, $paged ) {
        $orderby = $settings['orderby'] ?: 'date';
        $paginating = $paged > 1 || ( ( $settings['pagination_type'] ?? 'none' ) !== 'none' );

        return ( $orderby === 'rand' && $paginating ) ? 'date' : $orderby;
    }

    /**
     * Returns [$video_total, $article_total] - cheap posts_per_page=1
     * queries reusing build_type_query_args() so the count honors the exact
     * same filters (story visibility, status) as the real fetch below.
     */
    private function get_type_totals( $settings, $paged ) {
        $video_query = new WP_Query( $this->build_type_query_args( $settings, 'cbk_story', 0, 1, $paged ) );
        $article_query = new WP_Query( $this->build_type_query_args( $settings, 'post', 0, 1, $paged ) );

        return [ (int) $video_query->found_posts, (int) $article_query->found_posts ];
    }

    /**
     * Deterministic hash-based PRNG - deliberately NOT mt_rand()/mt_srand(),
     * which mutate PHP's process-wide RNG state and would drift between the
     * initial render() and later REST "load more" requests (separate PHP
     * processes). Same ($seed, $draw) always returns the same value, which
     * is what lets compute_interleave_page() below replay an identical
     * random sequence on every request for one page load's $seed, while a
     * fresh $seed (picked once per real page load) gives the next reload a
     * different pattern.
     */
    private function seeded_rand( $seed, $draw, $min, $max ) {
        if ( $max <= $min ) {
            return $min;
        }

        $hash = crc32( $seed . ':' . $draw );
        return $min + ( $hash % ( $max - $min + 1 ) );
    }

    /**
     * Walks a virtual slot sequence, placing a video then a randomized
     * (video_ratio_min..video_ratio_max) run of articles before the next
     * one, falling back to whichever type still has posts once the other
     * is exhausted. Returns exactly the offset/count each per-type query
     * needs to produce $page's items, plus the ordered video/article
     * sequence to merge the two fetched arrays back together correctly.
     *
     * Always replays the walk from slot 0 (not just from $page's start) so
     * the random gap sizes stay consistent regardless of which page is
     * being requested - required for the seeded PRNG's sequence to line up
     * the same way across separate "Load More" requests.
     */
    private function compute_interleave_page( $page, $per_page, $ratio_min, $ratio_max, $seed, $video_total, $article_total ) {
        $page = max( 1, (int) $page );
        $per_page = max( 1, (int) $per_page );

        $total_items = $video_total + $article_total;
        $total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
        $page_start = ( $page - 1 ) * $per_page;

        if ( $page_start >= $total_items ) {
            return [
                'video_offset' => $video_total,
                'video_count' => 0,
                'article_offset' => $article_total,
                'article_count' => 0,
                'page_sequence' => [],
                'total_pages' => $total_pages,
            ];
        }

        $target = $page * $per_page;
        $video_cursor = 0;
        $article_cursor = 0;
        $video_offset_at_start = 0;
        $article_offset_at_start = 0;
        $page_sequence = [];
        $video_draws = 0;    // how many random gaps have been drawn so far
        $next_video_at = 0;  // slot index the next video is due at

        for ( $i = 0; $i < $target; $i++ ) {
            if ( $i === $page_start ) {
                $video_offset_at_start = $video_cursor;
                $article_offset_at_start = $article_cursor;
            }

            $want_video = ( $i === $next_video_at );

            if ( $want_video && $video_cursor < $video_total ) {
                $type = 'video';
            } elseif ( ! $want_video && $article_cursor < $article_total ) {
                $type = 'article';
            } elseif ( $video_cursor < $video_total ) {
                $type = 'video';
            } elseif ( $article_cursor < $article_total ) {
                $type = 'article';
            } else {
                break; // Both exhausted.
            }

            if ( $type === 'video' ) {
                $video_cursor++;
                $gap = $this->seeded_rand( $seed, $video_draws, $ratio_min, $ratio_max );
                $video_draws++;
                $next_video_at = $i + 1 + $gap;
            } else {
                $article_cursor++;
            }

            if ( $i >= $page_start ) {
                $page_sequence[] = $type;
            }
        }

        return [
            'video_offset' => $video_offset_at_start,
            'video_count' => $video_cursor - $video_offset_at_start,
            'article_offset' => $article_offset_at_start,
            'article_count' => $article_cursor - $article_offset_at_start,
            'page_sequence' => $page_sequence,
            'total_pages' => $total_pages,
        ];
    }

    /**
     * Single entry point for both render() and render_items_page(): returns
     * ['posts' => WP_Post[], 'total_pages' => int]. Single-type mode is an
     * ordinary WP_Query; 'both' mode force-interleaves per video_ratio.
     */
    private function merge_and_fetch( $settings, $paged ) {
        $paged = max( 1, (int) $paged );

        if ( $settings['content_types'] !== 'both' ) {
            $query = new WP_Query( $this->build_query_args( $settings, $paged ) );
            return [
                'posts' => $query->posts,
                'total_pages' => (int) $query->max_num_pages,
            ];
        }

        $per_page = (int) ( $settings['posts_per_page'] ?: 12 );
        $ratio_min = max( 1, (int) ( $settings['video_ratio_min'] ?: 1 ) );
        $ratio_max = max( $ratio_min, (int) ( $settings['video_ratio_max'] ?: 4 ) );
        $seed = (int) ( $settings['interleave_seed'] ?? 0 );

        [ $video_total, $article_total ] = $this->get_type_totals( $settings, $paged );
        $slice = $this->compute_interleave_page( $paged, $per_page, $ratio_min, $ratio_max, $seed, $video_total, $article_total );

        $video_posts = [];
        if ( $slice['video_count'] > 0 ) {
            $video_query = new WP_Query( $this->build_type_query_args( $settings, 'cbk_story', $slice['video_offset'], $slice['video_count'], $paged ) );
            $video_posts = $video_query->posts;
        }

        $article_posts = [];
        if ( $slice['article_count'] > 0 ) {
            $article_query = new WP_Query( $this->build_type_query_args( $settings, 'post', $slice['article_offset'], $slice['article_count'], $paged ) );
            $article_posts = $article_query->posts;
        }

        $merged = [];
        foreach ( $slice['page_sequence'] as $type ) {
            if ( $type === 'video' && $video_posts ) {
                $merged[] = array_shift( $video_posts );
            } elseif ( $type === 'article' && $article_posts ) {
                $merged[] = array_shift( $article_posts );
            }
        }

        return [
            'posts' => $merged,
            'total_pages' => $slice['total_pages'],
        ];
    }

    private function render_pagination_control( $settings, $total_pages ) {
        $query_settings = [
            'content_types' => $settings['content_types'],
            'video_ratio_min' => (int) ( $settings['video_ratio_min'] ?: 1 ),
            'video_ratio_max' => (int) ( $settings['video_ratio_max'] ?: 4 ),
            'interleave_seed' => (int) ( $settings['interleave_seed'] ?? 0 ),
            'posts_per_page' => (int) $settings['posts_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
            'article_template_id' => (int) ( $settings['article_template_id'] ?: 0 ),
            'video_template_id' => (int) ( $settings['video_template_id'] ?: 0 ),
            'source_display' => $settings['source_display'],
            'source_meta_key' => $settings['source_meta_key'],
            'show_date' => $settings['show_date'],
            'date_format' => $settings['date_format'],
            'show_external_icon' => $settings['show_external_icon'],
            'video_aspect' => $settings['video_aspect'],
        ];

        $data = wp_json_encode( [
            'restUrl' => rest_url( 'coffeebrk/v1/bento-grid' ),
            'query' => $query_settings,
            'currentPage' => 1,
            'totalPages' => $total_pages,
        ] );
        ?>
        <div class="cbk-bento-pagination" data-type="<?php echo esc_attr( $settings['pagination_type'] ); ?>" data-settings='<?php echo esc_attr( $data ); ?>'>
            <?php if ( $settings['pagination_type'] === 'load_more' ) : ?>
                <button type="button" class="cbk-bento-load-more"><?php echo esc_html( $settings['load_more_text'] ?: __( 'Load More', 'coffeebrk-core' ) ); ?></button>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_settings_defaults() {
        return [
            'content_types' => 'both',
            'video_ratio_min' => 1,
            'video_ratio_max' => 4,
            'interleave_seed' => 0,
            'posts_per_page' => 12,
            'orderby' => 'date',
            'order' => 'DESC',
            'article_template_id' => '',
            'video_template_id' => '',
            'source_display' => 'author',
            'source_meta_key' => '_source_name',
            'show_date' => 'yes',
            'date_format' => 'F j, Y',
            'show_external_icon' => 'yes',
            'video_aspect' => '9-16',
        ];
    }

    private function render_item( $post, $settings ) {
        $post_id = $post->ID;
        $is_video = $post->post_type === 'cbk_story';
        $template_id = $is_video ? (int) ( $settings['video_template_id'] ?? 0 ) : (int) ( $settings['article_template_id'] ?? 0 );

        if ( $template_id && ! $this->is_valid_loop_item_template( $template_id ) ) {
            $template_id = 0;
        }
        ?>
        <div class="cbk-bento-item <?php echo $is_video ? 'cbk-bento-item--video' : 'cbk-bento-item--article'; ?>">
            <div class="cbk-bento-item__inner">
                <?php if ( $template_id ) : ?>
                    <?php echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, true ); ?>
                <?php else : ?>
                    <?php $this->render_default_card( $post, $settings ); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Template IDs reach here from the REST "load more" endpoint as raw,
     * unauthenticated public input, so this must confirm the ID is really a
     * published Loop Item template before it's handed to
     * get_builder_content_for_display() - otherwise a crafted request could
     * use that call to render the content of an arbitrary post/page.
     */
    private function is_valid_loop_item_template( $id ) {
        if ( $id <= 0 ) {
            return false;
        }

        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'elementor_library' || $post->post_status !== 'publish' ) {
            return false;
        }

        return get_post_meta( $id, '_elementor_template_type', true ) === 'loop-item';
    }

    private function render_default_card( $post, $settings ) {
        $post_id = $post->ID;
        $is_video = $post->post_type === 'cbk_story';
        $permalink = get_permalink( $post_id );
        $title = get_the_title( $post_id );
        $source = $this->get_source_label( $post, $settings );
        $date = $settings['show_date'] === 'yes' ? get_the_date( $settings['date_format'] ?: 'F j, Y', $post_id ) : '';
        ?>
        <?php if ( $is_video ) : ?>
            <?php $this->render_video_media( $post_id, $settings ); ?>
        <?php else : ?>
            <?php $this->render_article_media( $post_id, $title, $permalink ); ?>
        <?php endif; ?>

        <?php if ( $settings['show_external_icon'] === 'yes' && $permalink ) : ?>
            <a class="cbk-bento-item__external" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Open', 'coffeebrk-core' ); ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 5H19V10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19 5L10 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18 14V18C18 18.5523 17.5523 19 17 19H6C5.44772 19 5 18.5523 5 18V7C5 6.44772 5.44772 6 6 6H10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        <?php endif; ?>

        <div class="cbk-bento-item__body">
            <?php if ( $source || $date ) : ?>
                <div class="cbk-bento-item__meta">
                    <?php if ( $source ) : ?><span class="cbk-bento-item__source"><?php echo esc_html( $source ); ?></span><?php endif; ?>
                    <?php if ( $date ) : ?><span class="cbk-bento-item__date"><?php echo esc_html( $date ); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <h3 class="cbk-bento-item__title"><?php echo esc_html( $title ); ?></h3>
        </div>
        <?php
    }

    private function get_loop_item_template_options() {
        $options = [ '' => __( '— Default (Built-in Card) —', 'coffeebrk-core' ) ];

        $templates = get_posts( [
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_elementor_template_type',
            'meta_value' => 'loop-item',
            'orderby' => 'title',
            'order' => 'ASC',
        ] );

        foreach ( $templates as $template ) {
            $options[ $template->ID ] = $template->post_title;
        }

        return $options;
    }

    private function render_video_media( $post_id, $settings ) {
        $video_url = (string) get_post_meta( $post_id, '_cbk_story_video_url', true );
        if ( $video_url === '' ) {
            return;
        }

        $thumb_url = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'full' ) : '';
        if ( ! $thumb_url && function_exists( 'cbk_story_get_video_thumbnail' ) ) {
            $thumb_url = cbk_story_get_video_thumbnail( $video_url );
        }

        $aspect = $settings['video_aspect'] ?: '9-16';
        ?>
        <div class="cbk-universal-video cbk-universal-video--<?php echo esc_attr( $aspect ); ?>" data-video-url="<?php echo esc_attr( $video_url ); ?>">
            <?php if ( $thumb_url ) : ?>
                <div class="cbk-universal-video__thumbnail" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>');"></div>
            <?php else : ?>
                <div class="cbk-universal-video__thumbnail cbk-universal-video__thumbnail--placeholder"></div>
            <?php endif; ?>
            <div class="cbk-universal-video__play-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="2" fill="rgba(255,255,255,0.9)"/>
                    <path d="M10 8L16 12L10 16V8Z" fill="currentColor"/>
                </svg>
            </div>
        </div>
        <?php
    }

    private function render_article_media( $post_id, $title, $permalink ) {
        // width/height (not just the URL) so the browser reserves the
        // correct aspect-ratio box before the byte data arrives - the JS
        // masonry engine measures each item's height on insert and can't
        // wait for lazy-loaded images to finish downloading.
        $thumb_id = get_post_thumbnail_id( $post_id );
        $thumb_src = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'medium_large' ) : false;
        ?>
        <a class="cbk-bento-item__media-link" href="<?php echo esc_url( $permalink ); ?>">
            <?php if ( $thumb_src ) : ?>
                <img class="cbk-bento-item__img" src="<?php echo esc_url( $thumb_src[0] ); ?>" width="<?php echo esc_attr( $thumb_src[1] ); ?>" height="<?php echo esc_attr( $thumb_src[2] ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
            <?php else : ?>
                <div class="cbk-bento-item__img cbk-bento-item__img--placeholder"></div>
            <?php endif; ?>
        </a>
        <?php
    }

    private function get_source_label( $post, $settings ) {
        $mode = $settings['source_display'] ?? 'author';

        if ( $mode === 'none' ) {
            return '';
        }

        if ( $mode === 'author' ) {
            return get_the_author_meta( 'display_name', $post->post_author );
        }

        if ( $mode === 'category' ) {
            $terms = get_the_category( $post->ID );
            return ! empty( $terms ) ? $terms[0]->name : '';
        }

        if ( $mode === 'meta' ) {
            $key = $settings['source_meta_key'] ?: '_source_name';
            return (string) get_post_meta( $post->ID, $key, true );
        }

        return '';
    }
}
