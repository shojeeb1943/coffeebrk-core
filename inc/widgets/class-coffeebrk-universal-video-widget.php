<?php
/**
 * Universal Video Widget for Elementor
 *
 * Drop-in replacement for Elementor's native Video widget that actually
 * supports YouTube, Vimeo, TikTok, and Instagram - the native widget is
 * hardcoded to a single "Source" type (e.g. YouTube) for every item in a
 * Loop Grid template, so any other platform's URL bound via dynamic tag
 * breaks it with "Invalid video id". This widget auto-detects the platform
 * from the URL instead, and reuses the existing Coffeebrk Stories popup
 * viewer (coffeebrk-stories.js) for playback.
 *
 * @package Coffeebrk_Core
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_Universal_Video_Widget extends Widget_Base {

    public function get_name() {
        return 'coffeebrk_universal_video';
    }

    public function get_title() {
        return __( 'Coffeebrk Universal Video', 'coffeebrk-core' );
    }

    public function get_icon() {
        return 'eicon-video-camera';
    }

    public function get_categories() {
        return [ 'coffeebrk' ];
    }

    public function get_keywords() {
        return [ 'video', 'youtube', 'vimeo', 'tiktok', 'instagram', 'coffeebrk' ];
    }

    public function get_script_depends() {
        return [ 'coffeebrk-stories' ];
    }

    public function get_style_depends() {
        return [ 'coffeebrk-stories' ];
    }

    protected function register_controls() {

        $this->start_controls_section(
            'section_video',
            [
                'label' => __( 'Video', 'coffeebrk-core' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'video_url',
            [
                'label'       => __( 'Video Link', 'coffeebrk-core' ),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => [ 'active' => true ],
                'placeholder' => __( 'YouTube, Vimeo, TikTok, or Instagram URL', 'coffeebrk-core' ),
                'description' => __( 'Leave empty to auto-use this post\'s Story video URL (works when placed inside a Story loop item).', 'coffeebrk-core' ),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'thumbnail',
            [
                'label'       => __( 'Thumbnail Override', 'coffeebrk-core' ),
                'type'        => Controls_Manager::MEDIA,
                'default'     => [ 'url' => '' ],
                'description' => __( 'Optional. Leave empty to auto-detect (post featured image, or fetched from the video platform).', 'coffeebrk-core' ),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Style', 'coffeebrk-core' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'aspect_ratio',
            [
                'label'   => __( 'Aspect Ratio', 'coffeebrk-core' ),
                'type'    => Controls_Manager::SELECT,
                'default' => '9-16',
                'options' => [
                    '9-16' => __( '9:16 (Shorts/Reels)', 'coffeebrk-core' ),
                    '16-9' => __( '16:9 (Landscape)', 'coffeebrk-core' ),
                    '1-1'  => __( '1:1 (Square)', 'coffeebrk-core' ),
                    '4-5'  => __( '4:5 (Portrait)', 'coffeebrk-core' ),
                ],
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label'      => __( 'Border Radius', 'coffeebrk-core' ),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors'  => [
                    '{{WRAPPER}} .cbk-universal-video' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings  = $this->get_settings_for_display();
        $video_url = trim( (string) ( $settings['video_url'] ?? '' ) );

        // Zero-config fallback: works out of the box when dropped into a
        // cbk_story loop item without rebinding the dynamic tag.
        if ( $video_url === '' && get_post_type() === 'cbk_story' ) {
            $video_url = (string) get_post_meta( get_the_ID(), '_cbk_story_video_url', true );
        }

        if ( $video_url === '' ) {
            return;
        }

        $thumb_url = ! empty( $settings['thumbnail']['url'] ) ? $settings['thumbnail']['url'] : '';

        if ( ! $thumb_url && get_post_type() === 'cbk_story' && has_post_thumbnail() ) {
            $thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
        }

        if ( ! $thumb_url ) {
            $thumb_url = cbk_story_get_video_thumbnail( $video_url );
        }

        $aspect = ! empty( $settings['aspect_ratio'] ) ? $settings['aspect_ratio'] : '9-16';
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

    protected function content_template() {
        ?>
        <#
        var aspect = settings.aspect_ratio || '9-16';
        var thumbUrl = settings.thumbnail && settings.thumbnail.url ? settings.thumbnail.url : '';
        #>
        <div class="cbk-universal-video cbk-universal-video--{{{ aspect }}}">
            <# if ( thumbUrl ) { #>
            <div class="cbk-universal-video__thumbnail" style="background-image:url('{{{ thumbUrl }}}');"></div>
            <# } else { #>
            <div class="cbk-universal-video__thumbnail cbk-universal-video__thumbnail--placeholder"></div>
            <# } #>
            <div class="cbk-universal-video__play-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="2" fill="rgba(255,255,255,0.9)"/>
                    <path d="M10 8L16 12L10 16V8Z" fill="currentColor"/>
                </svg>
            </div>
        </div>
        <?php
    }
}
