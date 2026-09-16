<?php
/**
 * Bento/masonry grid widget that queries Posts and/or Stories directly and
 * renders them into a CSS-column masonry (native, no JS library) - the same
 * effect as Loop Grid's Masonry toggle, but self-contained so it doesn't
 * depend on Elementor Pro's Loop Grid + Theme Builder conditions being wired
 * up per post type. Video items reuse the Coffeebrk Universal Video widget's
 * exact markup/classes so the existing coffeebrk-stories.js click-to-play
 * binding picks them up for free.
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
        return [ 'coffeebrk-stories' ];
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
                    '{{WRAPPER}} .cbk-bento-grid' => 'column-count: {{VALUE}};',
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
                    '{{WRAPPER}} .cbk-bento-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .cbk-bento-item' => 'margin-bottom: {{SIZE}}{{UNIT}};',
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

        $post_types = $settings['content_types'] === 'both'
            ? [ 'post', 'cbk_story' ]
            : [ $settings['content_types'] ];

        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => (int) ( $settings['posts_per_page'] ?: 12 ),
            'orderby' => $settings['orderby'] ?: 'date',
            'order' => $settings['order'] ?: 'DESC',
            'ignore_sticky_posts' => true,
        ];

        // Respect the same "hidden from frontend" story toggle every other
        // Coffeebrk story query honors (inc/stories-rest.php). Regular posts
        // never have this meta key, so they always pass the NOT EXISTS leg.
        if ( in_array( 'cbk_story', $post_types, true ) ) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [ 'key' => '_cbk_story_show_frontend', 'value' => 'yes' ],
                [ 'key' => '_cbk_story_show_frontend', 'compare' => 'NOT EXISTS' ],
            ];
        }

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return;
        }

        echo '<div class="cbk-bento-grid">';

        while ( $query->have_posts() ) {
            $query->the_post();
            $this->render_item( get_post(), $settings );
        }

        echo '</div>';

        wp_reset_postdata();
    }

    private function render_item( $post, $settings ) {
        $post_id = $post->ID;
        $is_video = $post->post_type === 'cbk_story';
        $template_id = $is_video ? ( $settings['video_template_id'] ?? '' ) : ( $settings['article_template_id'] ?? '' );
        ?>
        <div class="cbk-bento-item <?php echo $is_video ? 'cbk-bento-item--video' : 'cbk-bento-item--article'; ?>">
            <div class="cbk-bento-item__inner">
                <?php if ( $template_id ) : ?>
                    <?php echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) $template_id, true ); ?>
                <?php else : ?>
                    <?php $this->render_default_card( $post, $settings ); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
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
        $thumb_url = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'medium_large' ) : '';
        ?>
        <a class="cbk-bento-item__media-link" href="<?php echo esc_url( $permalink ); ?>">
            <?php if ( $thumb_url ) : ?>
                <img class="cbk-bento-item__img" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
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
