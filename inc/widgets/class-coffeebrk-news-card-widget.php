<?php

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_News_Card_Widget extends Widget_Base {

    public function get_name() {
        return 'coffeebrk_news_card';
    }

    public function get_title() {
        return __( 'Coffeebrk News Card', 'coffeebrk-core' );
    }

    public function get_icon() {
        return 'eicon-post-content';
    }

    public function get_categories() {
        return [ 'coffeebrk' ];
    }

    public function get_keywords() {
        return [ 'news', 'card', 'post', 'article', 'coffeebrk' ];
    }

    protected function register_controls() {
        
        // Content Section: Background Image
        $this->start_controls_section(
            'section_background_image',
            [
                'label' => __( 'Background Image', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'background_image_source',
            [
                'label' => __( 'Image Source', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'meta' => __( 'Post Meta', 'coffeebrk-core' ),
                    'dynamic' => __( 'Dynamic Tag', 'coffeebrk-core' ),
                    'custom' => __( 'Custom URL', 'coffeebrk-core' ),
                ],
                'default' => 'meta',
            ]
        );

        $this->add_control(
            'background_image_meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => '_image',
                'placeholder' => '_image',
                'condition' => [
                    'background_image_source' => 'meta',
                ],
            ]
        );

        $this->add_control(
            'background_image_custom_url',
            [
                'label' => __( 'Custom Image URL', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'placeholder' => 'https://example.com/image.jpg',
                'condition' => [
                    'background_image_source' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'background_image_fallback',
            [
                'label' => __( 'Fallback Image URL', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => 'https://example.com/fallback.jpg',
            ]
        );

        $this->end_controls_section();

        // Content Section: Title
        $this->start_controls_section(
            'section_title',
            [
                'label' => __( 'Title', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title_source',
            [
                'label' => __( 'Title Source', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'post_title' => __( 'Post Title', 'coffeebrk-core' ),
                    'meta' => __( 'Post Meta', 'coffeebrk-core' ),
                    'custom' => __( 'Custom Text', 'coffeebrk-core' ),
                ],
                'default' => 'post_title',
            ]
        );

        $this->add_control(
            'title_meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '_custom_title',
                'condition' => [
                    'title_source' => 'meta',
                ],
            ]
        );

        $this->add_control(
            'title_custom_text',
            [
                'label' => __( 'Custom Text', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'placeholder' => 'Enter title',
                'condition' => [
                    'title_source' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label' => __( 'HTML Tag', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                    'div' => 'div',
                    'span' => 'span',
                ],
                'default' => 'h3',
            ]
        );

        $this->end_controls_section();

        // Content Section: Description
        $this->start_controls_section(
            'section_description',
            [
                'label' => __( 'Description', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'description_source',
            [
                'label' => __( 'Description Source', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'excerpt' => __( 'Post Excerpt', 'coffeebrk-core' ),
                    'meta' => __( 'Post Meta', 'coffeebrk-core' ),
                    'custom' => __( 'Custom Text', 'coffeebrk-core' ),
                ],
                'default' => 'excerpt',
            ]
        );

        $this->add_control(
            'description_meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '_description',
                'condition' => [
                    'description_source' => 'meta',
                ],
            ]
        );

        $this->add_control(
            'description_custom_text',
            [
                'label' => __( 'Custom Text', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXTAREA,
                'dynamic' => [
                    'active' => true,
                ],
                'placeholder' => 'Enter description',
                'condition' => [
                    'description_source' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'description_length',
            [
                'label' => __( 'Max Words', 'coffeebrk-core' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 20,
                'min' => 0,
                'max' => 200,
            ]
        );

        $this->end_controls_section();

        // Content Section: Source Name
        $this->start_controls_section(
            'section_source_name',
            [
                'label' => __( 'Source Name', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_source_name',
            [
                'label' => __( 'Show Source Name', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'source_name_source',
            [
                'label' => __( 'Source', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'meta' => __( 'Post Meta', 'coffeebrk-core' ),
                    'custom' => __( 'Custom Text', 'coffeebrk-core' ),
                ],
                'default' => 'meta',
                'condition' => [
                    'show_source_name' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'source_name_meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => '_source_name',
                'placeholder' => '_source_name',
                'condition' => [
                    'show_source_name' => 'yes',
                    'source_name_source' => 'meta',
                ],
            ]
        );

        $this->add_control(
            'source_name_custom_text',
            [
                'label' => __( 'Custom Text', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'placeholder' => 'Source Name',
                'condition' => [
                    'show_source_name' => 'yes',
                    'source_name_source' => 'custom',
                ],
            ]
        );

        $this->end_controls_section();

        // Content Section: Source URL
        $this->start_controls_section(
            'section_source_url',
            [
                'label' => __( 'Source URL', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'source_url_source',
            [
                'label' => __( 'URL Source', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'meta' => __( 'Post Meta', 'coffeebrk-core' ),
                    'permalink' => __( 'Post Permalink', 'coffeebrk-core' ),
                    'custom' => __( 'Custom URL', 'coffeebrk-core' ),
                ],
                'default' => 'meta',
            ]
        );

        $this->add_control(
            'source_url_meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => '_source_url',
                'placeholder' => '_source_url',
                'condition' => [
                    'source_url_source' => 'meta',
                ],
            ]
        );

        $this->add_control(
            'source_url_custom',
            [
                'label' => __( 'Custom URL', 'coffeebrk-core' ),
                'type' => Controls_Manager::URL,
                'dynamic' => [
                    'active' => true,
                ],
                'placeholder' => 'https://example.com',
                'condition' => [
                    'source_url_source' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'link_target',
            [
                'label' => __( 'Open in New Tab', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Content Section: Date
        $this->start_controls_section(
            'section_date',
            [
                'label' => __( 'Date', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
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
            'date_source',
            [
                'label' => __( 'Date Source', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'post_date' => __( 'Post Date', 'coffeebrk-core' ),
                    'meta' => __( 'Post Meta', 'coffeebrk-core' ),
                    'custom' => __( 'Custom Text', 'coffeebrk-core' ),
                ],
                'default' => 'post_date',
                'condition' => [
                    'show_date' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'date_meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '_date',
                'condition' => [
                    'show_date' => 'yes',
                    'date_source' => 'meta',
                ],
            ]
        );

        $this->add_control(
            'date_custom_text',
            [
                'label' => __( 'Custom Text', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                ],
                'placeholder' => 'Date',
                'condition' => [
                    'show_date' => 'yes',
                    'date_source' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'date_format',
            [
                'label' => __( 'Date Format', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'F j, Y',
                'placeholder' => 'F j, Y',
                'condition' => [
                    'show_date' => 'yes',
                    'date_source' => 'post_date',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section: Card
        $this->start_controls_section(
            'section_card_style',
            [
                'label' => __( 'Card', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'card_height',
            [
                'label' => __( 'Height', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'vh' ],
                'range' => [
                    'px' => [ 'min' => 200, 'max' => 800, 'step' => 10 ],
                    'vh' => [ 'min' => 20, 'max' => 100, 'step' => 1 ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 400,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label' => __( 'Border Radius', 'coffeebrk-core' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'default' => [
                    'top' => 12,
                    'right' => 12,
                    'bottom' => 12,
                    'left' => 12,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .cbk-news-card',
            ]
        );

        $this->add_control(
            'gradient_overlay',
            [
                'label' => __( 'Gradient Overlay', 'coffeebrk-core' ),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'gradient_color_start',
            [
                'label' => __( 'Start Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => 'rgba(0,0,0,0)',
            ]
        );

        $this->add_control(
            'gradient_color_end',
            [
                'label' => __( 'End Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => 'rgba(0,0,0,0.8)',
            ]
        );

        $this->add_control(
            'hover_scale',
            [
                'label' => __( 'Hover Scale', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [ 'min' => 1, 'max' => 1.2, 'step' => 0.01 ],
                ],
                'default' => [
                    'size' => 1.03,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card:hover' => 'transform: scale({{SIZE}});',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section: Content
        $this->start_controls_section(
            'section_content_style',
            [
                'label' => __( 'Content', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'content_padding',
            [
                'label' => __( 'Padding', 'coffeebrk-core' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'default' => [
                    'top' => 24,
                    'right' => 24,
                    'bottom' => 24,
                    'left' => 24,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'content_text_color',
            [
                'label' => __( 'Text Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__content' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section: Title
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __( 'Title', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .cbk-news-card__title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __( 'Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_spacing',
            [
                'label' => __( 'Spacing', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 50 ],
                ],
                'default' => [
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section: Description
        $this->start_controls_section(
            'section_description_style',
            [
                'label' => __( 'Description', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .cbk-news-card__description',
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __( 'Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => 'rgba(255,255,255,0.9)',
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'description_spacing',
            [
                'label' => __( 'Spacing', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 50 ],
                ],
                'default' => [
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section: Meta (Source & Date)
        $this->start_controls_section(
            'section_meta_style',
            [
                'label' => __( 'Meta (Source & Date)', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'meta_typography',
                'selector' => '{{WRAPPER}} .cbk-news-card__meta',
            ]
        );

        $this->add_control(
            'meta_color',
            [
                'label' => __( 'Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => 'rgba(255,255,255,0.7)',
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__meta' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'meta_spacing',
            [
                'label' => __( 'Gap Between Items', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 30 ],
                ],
                'default' => [
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-news-card__meta' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        // Get background image URL
        $bg_image_url = $this->get_background_image_url( $settings, $post_id );

        // Get link URL
        $link_url = $this->get_link_url( $settings, $post_id );
        $link_target = $settings['link_target'] === 'yes' ? '_blank' : '_self';

        // Get content
        $title = $this->get_title( $settings, $post_id );
        $description = $this->get_description( $settings, $post_id );
        $source_name = $this->get_source_name( $settings, $post_id );
        $date = $this->get_date( $settings, $post_id );

        // Build gradient
        $gradient_start = $settings['gradient_color_start'] ?? 'rgba(0,0,0,0)';
        $gradient_end = $settings['gradient_color_end'] ?? 'rgba(0,0,0,0.8)';
        $gradient = "linear-gradient(to bottom, {$gradient_start}, {$gradient_end})";

        // Inline styles for background image (per loop item)
        $card_style = '';
        if ( $bg_image_url ) {
            $card_style = sprintf(
                'background-image: %s, url(%s); background-size: cover; background-position: center;',
                $gradient,
                esc_url( $bg_image_url )
            );
        } else {
            $card_style = sprintf(
                'background-image: %s; background-color: #333;',
                $gradient
            );
        }

        ?>
        <div class="cbk-news-card" style="<?php echo esc_attr( $card_style ); ?>">
            <?php if ( $link_url ) : ?>
                <a href="<?php echo esc_url( $link_url ); ?>" target="<?php echo esc_attr( $link_target ); ?>" class="cbk-news-card__link" rel="<?php echo $link_target === '_blank' ? 'noopener noreferrer' : ''; ?>">
            <?php endif; ?>
            
            <div class="cbk-news-card__content">
                <?php if ( $settings['show_source_name'] === 'yes' || $settings['show_date'] === 'yes' ) : ?>
                    <div class="cbk-news-card__meta">
                        <?php if ( $settings['show_source_name'] === 'yes' && $source_name ) : ?>
                            <span class="cbk-news-card__source"><?php echo esc_html( $source_name ); ?></span>
                        <?php endif; ?>
                        <?php if ( $settings['show_date'] === 'yes' && $date ) : ?>
                            <span class="cbk-news-card__date"><?php echo esc_html( $date ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $title ) : ?>
                    <<?php echo esc_attr( $settings['title_tag'] ); ?> class="cbk-news-card__title">
                        <?php echo esc_html( $title ); ?>
                    </<?php echo esc_attr( $settings['title_tag'] ); ?>>
                <?php endif; ?>

                <?php if ( $description ) : ?>
                    <div class="cbk-news-card__description">
                        <?php echo esc_html( $description ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( $link_url ) : ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_background_image_url( $settings, $post_id ) {
        $source = $settings['background_image_source'] ?? 'meta';
        $url = '';

        if ( $source === 'meta' ) {
            $meta_key = $settings['background_image_meta_key'] ?? '_image';
            if ( $meta_key && $post_id ) {
                $url = get_post_meta( $post_id, $meta_key, true );
            }
        } elseif ( $source === 'custom' ) {
            $url = $settings['background_image_custom_url'] ?? '';
        }

        // Fallback
        if ( ! $url ) {
            $url = $settings['background_image_fallback'] ?? '';
        }

        return $url ? esc_url( $url ) : '';
    }

    private function get_title( $settings, $post_id ) {
        $source = $settings['title_source'] ?? 'post_title';
        $title = '';

        if ( $source === 'post_title' ) {
            $title = get_the_title( $post_id );
        } elseif ( $source === 'meta' ) {
            $meta_key = $settings['title_meta_key'] ?? '';
            if ( $meta_key && $post_id ) {
                $title = get_post_meta( $post_id, $meta_key, true );
            }
        } elseif ( $source === 'custom' ) {
            $title = $settings['title_custom_text'] ?? '';
        }

        return $title;
    }

    private function get_description( $settings, $post_id ) {
        $source = $settings['description_source'] ?? 'excerpt';
        $description = '';

        if ( $source === 'excerpt' ) {
            $excerpt = get_the_excerpt( $post_id );
            if ( ! $excerpt ) {
                // Fallback to meta if excerpt is empty
                $meta_key = $settings['description_meta_key'] ?? '_description';
                if ( $meta_key && $post_id ) {
                    $excerpt = get_post_meta( $post_id, $meta_key, true );
                }
            }
            $description = $excerpt;
        } elseif ( $source === 'meta' ) {
            $meta_key = $settings['description_meta_key'] ?? '';
            if ( $meta_key && $post_id ) {
                $description = get_post_meta( $post_id, $meta_key, true );
            }
        } elseif ( $source === 'custom' ) {
            $description = $settings['description_custom_text'] ?? '';
        }

        // Trim to max words
        $max_words = (int) ( $settings['description_length'] ?? 20 );
        if ( $max_words > 0 && $description ) {
            $description = wp_trim_words( $description, $max_words, '...' );
        }

        return $description;
    }

    private function get_source_name( $settings, $post_id ) {
        if ( $settings['show_source_name'] !== 'yes' ) {
            return '';
        }

        $source = $settings['source_name_source'] ?? 'meta';
        $name = '';

        if ( $source === 'meta' ) {
            $meta_key = $settings['source_name_meta_key'] ?? '_source_name';
            if ( $meta_key && $post_id ) {
                $name = get_post_meta( $post_id, $meta_key, true );
            }
        } elseif ( $source === 'custom' ) {
            $name = $settings['source_name_custom_text'] ?? '';
        }

        return $name;
    }

    private function get_date( $settings, $post_id ) {
        if ( $settings['show_date'] !== 'yes' ) {
            return '';
        }

        $source = $settings['date_source'] ?? 'post_date';
        $date = '';

        if ( $source === 'post_date' ) {
            $format = $settings['date_format'] ?? 'F j, Y';
            $date = get_the_date( $format, $post_id );
        } elseif ( $source === 'meta' ) {
            $meta_key = $settings['date_meta_key'] ?? '';
            if ( $meta_key && $post_id ) {
                $date = get_post_meta( $post_id, $meta_key, true );
            }
        } elseif ( $source === 'custom' ) {
            $date = $settings['date_custom_text'] ?? '';
        }

        return $date;
    }

    private function get_link_url( $settings, $post_id ) {
        $source = $settings['source_url_source'] ?? 'meta';
        $url = '';

        if ( $source === 'meta' ) {
            $meta_key = $settings['source_url_meta_key'] ?? '_source_url';
            if ( $meta_key && $post_id ) {
                $url = get_post_meta( $post_id, $meta_key, true );
            }
        } elseif ( $source === 'permalink' ) {
            $url = get_permalink( $post_id );
        } elseif ( $source === 'custom' ) {
            $custom_url = $settings['source_url_custom'] ?? [];
            $url = isset( $custom_url['url'] ) ? $custom_url['url'] : '';
        }

        return $url;
    }

    protected function content_template() {
        // JS template for Elementor editor preview (optional but recommended)
        ?>
        <#
        var cardStyle = 'background: linear-gradient(to bottom, rgba(0,0,0,0), rgba(0,0,0,0.8)), #333; background-size: cover; background-position: center;';
        #>
        <div class="cbk-news-card" style="{{{ cardStyle }}}">
            <div class="cbk-news-card__content">
                <# if ( settings.show_source_name === 'yes' || settings.show_date === 'yes' ) { #>
                    <div class="cbk-news-card__meta">
                        <# if ( settings.show_source_name === 'yes' ) { #>
                            <span class="cbk-news-card__source">Source Name</span>
                        <# } #>
                        <# if ( settings.show_date === 'yes' ) { #>
                            <span class="cbk-news-card__date">Date</span>
                        <# } #>
                    </div>
                <# } #>
                
                <{{{ settings.title_tag }}} class="cbk-news-card__title">
                    Card Title
                </{{{ settings.title_tag }}}>
                
                <div class="cbk-news-card__description">
                    This is a preview of the card description text...
                </div>
            </div>
        </div>
        <?php
    }
}
