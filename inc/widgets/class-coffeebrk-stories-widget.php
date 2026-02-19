<?php
/**
 * Stories Widget for Elementor
 * 
 * Displays Instagram-style story cards with video playback
 * 
 * @package Coffeebrk_Core
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_Stories_Widget extends Widget_Base {

    public function get_name() {
        return 'coffeebrk_stories';
    }

    public function get_title() {
        return __( 'Coffeebrk Stories', 'coffeebrk-core' );
    }

    public function get_icon() {
        return 'eicon-video-playlist';
    }

    public function get_categories() {
        return [ 'coffeebrk' ];
    }

    public function get_keywords() {
        return [ 'stories', 'video', 'instagram', 'youtube', 'vimeo', 'coffeebrk' ];
    }

    public function get_script_depends() {
        return [ 'coffeebrk-stories' ];
    }

    public function get_style_depends() {
        return [ 'coffeebrk-stories' ];
    }

    protected function register_controls() {
        
        // ============================================
        // CONTENT TAB: Stories
        // ============================================
        // ============================================
        // CONTENT TAB: Source
        // ============================================
        $this->start_controls_section(
            'section_source',
            [
                'label' => __( 'Source', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'source_type',
            [
                'label' => __( 'Source Type', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'manual',
                'options' => [
                    'manual' => __( 'Manual (Repeater)', 'coffeebrk-core' ),
                    'global' => __( 'Global (Admin Panel)', 'coffeebrk-core' ),
                ],
            ]
        );

        $this->add_control(
            'global_limit',
            [
                'label' => __( 'Limit (0 = All)', 'coffeebrk-core' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
                'description' => __( 'Maximum number of stories to display. Set to 0 to show ALL visible stories.', 'coffeebrk-core' ),
                'condition' => [
                    'source_type' => 'global',
                ],
            ]
        );

        $this->end_controls_section();

        // ============================================
        // CONTENT TAB: Stories
        // ============================================
        $this->start_controls_section(
            'section_stories',
            [
                'label' => __( 'Stories Items', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'source_type' => 'manual',
                ],
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'story_title',
            [
                'label' => __( 'Title', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Story Title', 'coffeebrk-core' ),
                'placeholder' => __( 'Enter story title', 'coffeebrk-core' ),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'video_url',
            [
                'label' => __( 'Video URL', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'https://youtube.com/watch?v=... or https://vimeo.com/...', 'coffeebrk-core' ),
                'description' => __( 'YouTube, Vimeo, or direct video URL', 'coffeebrk-core' ),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'thumbnail',
            [
                'label' => __( 'Thumbnail Image', 'coffeebrk-core' ),
                'type' => Controls_Manager::MEDIA,
                'default' => [
                    'url' => '',
                ],
            ]
        );

        $repeater->add_control(
            'gradient_color',
            [
                'label' => __( 'Gradient Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'description' => __( 'Leave empty for auto-detect from thumbnail', 'coffeebrk-core' ),
            ]
        );

        $repeater->add_control(
            'gradient_intensity',
            [
                'label' => __( 'Gradient Intensity', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ '%' ],
                'range' => [
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 5,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 50,
                ],
                'description' => __( 'How much gradient coverage (0-100%)', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'stories',
            [
                'label' => __( 'Stories', 'coffeebrk-core' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'story_title' => __( 'AI mania tanks CoreWeave\'s Core Scientific acquisition — it buys Python notebook Marimo', 'coffeebrk-core' ),
                        'video_url' => 'https://youtu.be/ZT8690E-r6U',
                        'gradient_color' => '#F5F5FF',
                    ],
                    [
                        'story_title' => __( 'OpenAI launches GPT-5 with advanced reasoning capabilities', 'coffeebrk-core' ),
                        'video_url' => 'https://youtu.be/ZT8690E-r6U',
                        'gradient_color' => '#E7EDFF',
                    ],
                    [
                        'story_title' => __( 'Microsoft announces $10B investment in cloud infrastructure', 'coffeebrk-core' ),
                        'video_url' => 'https://youtu.be/ZT8690E-r6U',
                        'gradient_color' => '#F1FDFF',
                    ],
                ],
                'title_field' => '{{{ story_title }}}',
            ]
        );

        $this->end_controls_section();

        // ============================================
        // CONTENT TAB: Container Settings
        // ============================================
        $this->start_controls_section(
            'section_container',
            [
                'label' => __( 'Container Settings', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'cards_gap',
            [
                'label' => __( 'Gap Between Cards', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 50, 'step' => 1 ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-stories' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'enable_scroll',
            [
                'label' => __( 'Enable Horizontal Scroll', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // ============================================
        // CONTENT TAB: Playback Settings
        // ============================================
        $this->start_controls_section(
            'section_playback',
            [
                'label' => __( 'Playback Settings', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label' => __( 'Autoplay Videos', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'loop',
            [
                'label' => __( 'Loop Videos', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'start_muted',
            [
                'label' => __( 'Start Muted', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // ============================================
        // STYLE TAB: Card
        // ============================================
        $this->start_controls_section(
            'section_card_style',
            [
                'label' => __( 'Card', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'card_width',
            [
                'label' => __( 'Width', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 100, 'max' => 300, 'step' => 1 ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 170,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-stories__card' => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'card_height',
            [
                'label' => __( 'Height', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [ 'min' => 150, 'max' => 500, 'step' => 1 ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 300,
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-stories__card' => 'height: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .cbk-stories__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .cbk-stories__card',
                'fields_options' => [
                    'box_shadow_type' => [
                        'default' => 'yes',
                    ],
                    'box_shadow' => [
                        'default' => [
                            'horizontal' => 4,
                            'vertical' => 4,
                            'blur' => 24,
                            'spread' => 0,
                            'color' => 'rgba(0, 0, 0, 0.02)',
                        ],
                    ],
                ],
            ]
        );

        $this->add_control(
            'card_border_color',
            [
                'label' => __( 'Border Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#DEDEDE',
                'selectors' => [
                    '{{WRAPPER}} .cbk-stories__card' => 'border: 1px solid {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // ============================================
        // STYLE TAB: Title
        // ============================================
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
                'selector' => '{{WRAPPER}} .cbk-stories__title',
                'fields_options' => [
                    'typography' => [
                        'default' => 'yes',
                    ],
                    'font_family' => [
                        'default' => 'Poppins',
                    ],
                    'font_size' => [
                        'default' => [
                            'unit' => 'px',
                            'size' => 12,
                        ],
                    ],
                    'font_weight' => [
                        'default' => '500',
                    ],
                    'line_height' => [
                        'default' => [
                            'unit' => 'px',
                            'size' => 16,
                        ],
                    ],
                ],
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __( 'Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#323232',
                'selectors' => [
                    '{{WRAPPER}} .cbk-stories__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'title_text_shadow',
                'selector' => '{{WRAPPER}} .cbk-stories__title',
            ]
        );

        $this->add_responsive_control(
            'content_padding',
            [
                'label' => __( 'Content Padding', 'coffeebrk-core' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em' ],
                'default' => [
                    'top' => 16,
                    'right' => 12,
                    'bottom' => 16,
                    'left' => 12,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-stories__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $source_type = $settings['source_type'] ?? 'manual';
        
        $stories = [];

        if ( $source_type === 'global' ) {
            // Get limit setting: -1, 0, or empty means unlimited
            $limit_setting = isset( $settings['global_limit'] ) ? $settings['global_limit'] : -1;
            $limit = ( $limit_setting === '' || $limit_setting === null || (int) $limit_setting <= 0 ) ? 0 : (int) $limit_setting;

            // Get all published stories without meta_query (filter in PHP for reliability)
            $args = [
                'post_type' => 'cbk_story',
                'posts_per_page' => -1, // Get all, then filter
                'post_status' => 'publish',
                'orderby' => 'menu_order date',
                'order' => 'DESC',
            ];

            $query = new \WP_Query( $args );

            if ( $query->have_posts() ) {
                $count = 0;
                foreach ( $query->posts as $post ) {
                    // Filter: skip stories explicitly marked as hidden
                    $show_frontend = get_post_meta( $post->ID, '_cbk_story_show_frontend', true );
                    if ( $show_frontend === 'no' ) {
                        continue;
                    }

                    // Apply limit only if explicitly set to a positive number
                    if ( $limit > 0 && $count >= $limit ) {
                        break;
                    }

                    $thumb_id = get_post_thumbnail_id( $post->ID );
                    $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : '';

                    $video_url = get_post_meta( $post->ID, '_cbk_story_video_url', true );
                    $gradient = get_post_meta( $post->ID, '_cbk_story_gradient', true );
                    $text_color = get_post_meta( $post->ID, '_cbk_story_text_color', true );
                    $gradient_intensity = get_post_meta( $post->ID, '_cbk_story_gradient_intensity', true );
                    if ( $gradient_intensity === '' ) {
                        $gradient_intensity = 50;
                    }

                    $stories[] = [
                        'story_title' => $post->post_title,
                        'video_url' => $video_url,
                        'thumbnail' => [ 'url' => $thumb_url ],
                        'gradient_color' => $gradient,
                        'text_color' => $text_color,
                        'gradient_intensity' => (int) $gradient_intensity,
                    ];

                    $count++;
                }
            }
        } else {
            $stories = $settings['stories'] ?? [];
        }
        
        if ( empty( $stories ) ) {
            return;
        }

        $autoplay = $settings['autoplay'] === 'yes' ? 'true' : 'false';
        $loop = $settings['loop'] === 'yes' ? 'true' : 'false';
        $scroll_class = $settings['enable_scroll'] === 'yes' ? 'cbk-stories--scrollable' : '';
        
        ?>
        <div class="cbk-stories-wrapper">
            <button class="cbk-stories-nav cbk-stories-nav--prev" aria-label="<?php esc_attr_e( 'Previous', 'coffeebrk-core' ); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div class="cbk-stories <?php echo esc_attr( $scroll_class ); ?>" 
                 data-autoplay="<?php echo esc_attr( $autoplay ); ?>"
                 data-loop="<?php echo esc_attr( $loop ); ?>"
                 data-muted="<?php echo esc_attr( $settings['start_muted'] ?? 'yes' ); ?>"
                 style="flex: 1; min-width: 0; overflow-x: auto; overflow-y: hidden; display: flex; flex-direction: row; scrollbar-width: none; -ms-overflow-style: none;">
                <?php foreach ( $stories as $index => $story ) : 
                    $thumbnail_url = ! empty( $story['thumbnail']['url'] ) ? $story['thumbnail']['url'] : '';
                    $video_url = ! empty( $story['video_url'] ) ? $story['video_url'] : '';
                    $is_video_thumb = false;

                    // Fallback to video thumbnail if no featured image
                    if ( empty( $thumbnail_url ) && ! empty( $video_url ) ) {
                        $thumbnail_url = $this->get_video_thumbnail( $video_url );
                        $is_video_thumb = true;
                    }

                    $gradient_color = ! empty( $story['gradient_color'] ) ? $story['gradient_color'] : '';
                    $title = ! empty( $story['story_title'] ) ? $story['story_title'] : '';
                    $text_color = ! empty( $story['text_color'] ) ? $story['text_color'] : '';
                    $text_color_style = $text_color ? 'color: ' . esc_attr( $text_color ) . ';' : '';
                    
                    // Handle gradient intensity - can be integer (CPT) or array with 'size' key (Elementor slider)
                    $gradient_intensity = 50; // Default
                    if ( isset( $story['gradient_intensity'] ) ) {
                        if ( is_array( $story['gradient_intensity'] ) && isset( $story['gradient_intensity']['size'] ) ) {
                            $gradient_intensity = (int) $story['gradient_intensity']['size'];
                        } else {
                            $gradient_intensity = (int) $story['gradient_intensity'];
                        }
                    }
                    
                    // Calculate opacity based on intensity (0-100 -> 0-1)
                    $intensity_base = $gradient_intensity / 100;
                    $gradient_rgba_mid = $gradient_color ? $this->hex_to_rgba( $gradient_color, 0.5 * $intensity_base ) : '';
                    $gradient_rgba_high = $gradient_color ? $this->hex_to_rgba( $gradient_color, 0.9 * $intensity_base ) : '';
                    
                    // Calculate shadow color (slightly darker version of gradient)
                    $shadow_color = $gradient_color ? $this->darken_color( $gradient_color, 20 ) : 'rgba(0,0,0,0.3)';
                    
                    // Auto-detect class if no gradient color selected or if we are using video thumbnail
                    $auto_gradient_class = empty( $gradient_color ) ? 'cbk-stories__card--auto-gradient' : '';
                ?>
                <div class="cbk-stories__card <?php echo esc_attr( $auto_gradient_class ); ?>" 
                     data-story-index="<?php echo esc_attr( $index ); ?>"
                     data-video-url="<?php echo esc_attr( $video_url ); ?>"
                     data-thumb-url="<?php echo esc_attr( $thumbnail_url ); ?>"
                     data-intensity="<?php echo esc_attr( $gradient_intensity ); ?>"
                     style="--gradient-color: <?php echo esc_attr( $gradient_color ?: '#888888' ); ?>; --shadow-color: <?php echo esc_attr( $shadow_color ); ?>; width: 170px; height: 300px; flex: 0 0 auto; border-radius: 12px; margin-right: 16px;">
                    
                    <?php if ( $thumbnail_url ) : 
                        $thumb_class = 'cbk-stories__thumbnail' . ( $is_video_thumb ? ' cbk-stories__thumbnail--video' : '' );
                    ?>
                    <div class="<?php echo esc_attr( $thumb_class ); ?>" 
                         data-src="<?php echo esc_url( $thumbnail_url ); ?>"
                         style="background-image: url('<?php echo esc_url( $thumbnail_url ); ?>');">
                    </div>
                    <?php else : ?>
                    <div class="cbk-stories__thumbnail cbk-stories__thumbnail--placeholder"></div>
                    <?php endif; ?>
                    
                    <?php if ( $gradient_color ) : ?>
                    <div class="cbk-stories__gradient" 
                         style="background: linear-gradient(180deg, 
                            rgba(245, 245, 255, 0) <?php echo esc_attr( 100 - $gradient_intensity ); ?>%, 
                            <?php echo esc_attr( $gradient_rgba_mid ); ?> <?php echo esc_attr( 100 - ($gradient_intensity * 0.5) ); ?>%, 
                            <?php echo esc_attr( $gradient_rgba_high ); ?> <?php echo esc_attr( 100 - ($gradient_intensity * 0.2) ); ?>%, 
                            <?php echo esc_attr( $gradient_color ); ?> 100%);"></div>
                    <?php else : ?>
                    <div class="cbk-stories__gradient cbk-stories__gradient--auto"></div>
                    <?php endif; ?>
                    
                    <div class="cbk-stories__content">
                        <?php if ( $title ) : ?>
                        <div class="cbk-stories__title" style="<?php echo $text_color_style; ?>"><?php echo esc_html( $title ); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="cbk-stories__play-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="2" fill="rgba(255,255,255,0.9)"/>
                            <path d="M10 8L16 12L10 16V8Z" fill="currentColor"/>
                        </svg>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button class="cbk-stories-nav cbk-stories-nav--next" aria-label="<?php esc_attr_e( 'Next', 'coffeebrk-core' ); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        
        <!-- Story Viewer Modal (rendered once, controlled via JS) -->
        <div class="cbk-stories-viewer" id="cbk-stories-viewer-<?php echo esc_attr( $this->get_id() ); ?>" style="display: none;">
            <div class="cbk-stories-viewer__overlay"></div>
            <button class="cbk-stories-viewer__close" aria-label="<?php esc_attr_e( 'Close', 'coffeebrk-core' ); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <button class="cbk-stories-viewer__nav cbk-stories-viewer__nav--prev" aria-label="<?php esc_attr_e( 'Previous', 'coffeebrk-core' ); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div class="cbk-stories-viewer__content">
                <div class="cbk-stories-viewer__item cbk-stories-viewer__item--prev-2"></div>
                <div class="cbk-stories-viewer__item cbk-stories-viewer__item--prev"></div>
                <div class="cbk-stories-viewer__item cbk-stories-viewer__item--current">
                    <div class="cbk-stories-viewer__video-container"></div>
                </div>
                <div class="cbk-stories-viewer__item cbk-stories-viewer__item--next"></div>
                <div class="cbk-stories-viewer__item cbk-stories-viewer__item--next-2"></div>
            </div>
            <button class="cbk-stories-viewer__nav cbk-stories-viewer__nav--next" aria-label="<?php esc_attr_e( 'Next', 'coffeebrk-core' ); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <?php
    }

    /**
     * Convert hex color to rgba
     */
    private function hex_to_rgba( $hex, $alpha = 1 ) {
        $hex = str_replace( '#', '', $hex );
        
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    /**
     * Darken a hex color by a percentage
     */
    private function darken_color( $hex, $percent ) {
        $hex = str_replace( '#', '', $hex );
        
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - ( 255 * $percent / 100 ) );
        $g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - ( 255 * $percent / 100 ) );
        $b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - ( 255 * $percent / 100 ) );
        
        return "rgba(" . round($r) . ", " . round($g) . ", " . round($b) . ", 0.5)";
    }

    /**
     * Get video thumbnail from URL
     */
    private function get_video_thumbnail( $url ) {
        if ( empty( $url ) ) {
            return '';
        }

        // YouTube (supports youtube.com/watch, youtube.com/shorts, youtube.com/embed, youtu.be)
        if ( preg_match( '/(?:youtube\.com\/(?:shorts\/|watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches ) ) {
            // maxresdefault is 16:9, hqdefault is 4:3 with bars. We use hqdefault as base but CSS covers it.
            // Using hqdefault as it's most reliable for all videos.
            return 'https://img.youtube.com/vi/' . $matches[1] . '/hqdefault.jpg';
        }

        // Vimeo
        if ( preg_match( '/vimeo\.com\/(\d+)/', $url, $matches ) ) {
            // For Vimeo we ideally need an API call, but we can't do that synchronously without caching.
            // For now, we'll try to use a placeholder or skip, as fetching requires a remote request.
            // Optionally, we could use a JS-side solution for Vimeo if needed.
            return ''; 
        }

        return '';
    }

    protected function content_template() {
        ?>
        <#
        var scrollClass = settings.enable_scroll === 'yes' ? 'cbk-stories--scrollable' : '';
        #>
        <div class="cbk-stories {{{ scrollClass }}}">
            <# _.each( settings.stories, function( story, index ) { 
                var thumbnailUrl = story.thumbnail && story.thumbnail.url ? story.thumbnail.url : '';
                var videoUrl = story.video_url || '';
                var gradientColor = story.gradient_color || '';

                // JS Fallback for preview (supports youtube.com/watch, youtube.com/shorts, youtube.com/embed, youtu.be)
                var isVideoThumb = false;
                if ( ! thumbnailUrl && videoUrl ) {
                    var youtubeMatch = videoUrl.match(/(?:youtube\.com\/(?:shorts\/|watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
                    if ( youtubeMatch ) {
                        thumbnailUrl = 'https://img.youtube.com/vi/' + youtubeMatch[1] + '/hqdefault.jpg';
                        isVideoThumb = true;
                    }
                }

                var autoGradientClass = ! gradientColor ? 'cbk-stories__card--auto-gradient' : '';
                var thumbClass = 'cbk-stories__thumbnail' + ( isVideoThumb ? ' cbk-stories__thumbnail--video' : '' );
            #>
            <div class="cbk-stories__card {{{ autoGradientClass }}}" style="--gradient-color: {{{ gradientColor || '#888888' }}};" data-thumb-url="{{{ thumbnailUrl }}}" data-intensity="{{{ story.gradient_intensity && story.gradient_intensity.size ? story.gradient_intensity.size : 50 }}}">
                <# if ( thumbnailUrl ) { #>
                <div class="{{{ thumbClass }}}" style="background-image: url('{{{ thumbnailUrl }}}');"></div>
                <# } else { #>
                <div class="cbk-stories__thumbnail cbk-stories__thumbnail--placeholder"></div>
                <# } #>
                
                <div class="cbk-stories__gradient"></div>
                
                <div class="cbk-stories__content">
                    <# if ( story.story_title ) { #>
                    <div class="cbk-stories__title">{{{ story.story_title }}}</div>
                    <# } #>
                </div>
                
                <div class="cbk-stories__play-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="2" fill="rgba(255,255,255,0.9)"/>
                        <path d="M10 8L16 12L10 16V8Z" fill="currentColor"/>
                    </svg>
                </div>
            </div>
            <# }); #>
        </div>
        <?php
    }
}
