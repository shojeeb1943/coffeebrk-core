<?php

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_External_Image_Widget extends Widget_Base {

    public function get_name() {
        return 'coffeebrk_external_image';
    }

    public function get_title() {
        return __( 'Coffeebrk External Image', 'coffeebrk-core' );
    }

    public function get_icon() {
        return 'eicon-image';
    }

    public function get_categories() {
        return [ 'coffeebrk' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'cbk_section_content',
            [
                'label' => __( 'Content', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $meta_key_options = $this->get_image_url_meta_key_options();

        $this->add_control(
            'meta_key',
            [
                'label' => __( 'Meta Key', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => $meta_key_options,
                'default' => array_key_exists( '_image', $meta_key_options ) ? '_image' : ( array_key_first( $meta_key_options ) ?: '_image' ),
            ]
        );

        $this->add_control(
            'render_mode',
            [
                'label' => __( 'Render Mode', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'img' => __( 'Image (<img>)', 'coffeebrk-core' ),
                    'background' => __( 'Background (div)', 'coffeebrk-core' ),
                ],
                'default' => 'img',
            ]
        );

        $this->add_control(
            'fallback_url',
            [
                'label' => __( 'Fallback URL', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => 'https://example.com/fallback.jpg',
            ]
        );

        $this->add_control(
            'img_alt_meta_key',
            [
                'label' => __( 'Alt Meta Key (optional)', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => '_image_alt',
                'condition' => [
                    'render_mode' => 'img',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'cbk_section_style',
            [
                'label' => __( 'Style', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'height',
            [
                'label' => __( 'Height', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'vh', '%' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 1200 ],
                    'vh' => [ 'min' => 0, 'max' => 100 ],
                    '%' => [ 'min' => 0, 'max' => 100 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-external-image__bg' => 'height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .cbk-external-image__img' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'width',
            [
                'label' => __( 'Width', 'coffeebrk-core' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'vw' ],
                'range' => [
                    'px' => [ 'min' => 0, 'max' => 2000 ],
                    '%' => [ 'min' => 0, 'max' => 100 ],
                    'vw' => [ 'min' => 0, 'max' => 100 ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-external-image__bg' => 'width: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .cbk-external-image__img' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'object_fit',
            [
                'label' => __( 'Object Fit', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'cover' => 'cover',
                    'contain' => 'contain',
                    'fill' => 'fill',
                    'none' => 'none',
                    'scale-down' => 'scale-down',
                ],
                'default' => 'cover',
                'selectors' => [
                    '{{WRAPPER}} .cbk-external-image__img' => 'object-fit: {{VALUE}};',
                ],
                'condition' => [
                    'render_mode' => 'img',
                ],
            ]
        );

        $this->add_control(
            'background_size',
            [
                'label' => __( 'Background Size', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'cover' => 'cover',
                    'contain' => 'contain',
                    'auto' => 'auto',
                ],
                'default' => 'cover',
                'selectors' => [
                    '{{WRAPPER}} .cbk-external-image__bg' => 'background-size: {{VALUE}};',
                ],
                'condition' => [
                    'render_mode' => 'background',
                ],
            ]
        );

        $this->add_control(
            'background_position',
            [
                'label' => __( 'Background Position', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'center center' => 'center center',
                    'center top' => 'center top',
                    'center bottom' => 'center bottom',
                    'left top' => 'left top',
                    'left center' => 'left center',
                    'left bottom' => 'left bottom',
                    'right top' => 'right top',
                    'right center' => 'right center',
                    'right bottom' => 'right bottom',
                ],
                'default' => 'center center',
                'selectors' => [
                    '{{WRAPPER}} .cbk-external-image__bg' => 'background-position: {{VALUE}};',
                ],
                'condition' => [
                    'render_mode' => 'background',
                ],
            ]
        );

        $this->add_control(
            'background_repeat',
            [
                'label' => __( 'Background Repeat', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'no-repeat' => 'no-repeat',
                    'repeat' => 'repeat',
                    'repeat-x' => 'repeat-x',
                    'repeat-y' => 'repeat-y',
                ],
                'default' => 'no-repeat',
                'selectors' => [
                    '{{WRAPPER}} .cbk-external-image__bg' => 'background-repeat: {{VALUE}};',
                ],
                'condition' => [
                    'render_mode' => 'background',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $meta_key = $settings['meta_key'] ?? '_image';
        $meta_key = is_string( $meta_key ) ? trim( $meta_key ) : '';

        $fallback_url = $settings['fallback_url'] ?? '';
        $fallback_url = is_string( $fallback_url ) ? trim( $fallback_url ) : '';

        $render_mode = $settings['render_mode'] ?? 'img';
        $render_mode = is_string( $render_mode ) ? $render_mode : 'img';

        $post_id = (int) get_the_ID();
        if ( ! $post_id ) {
            $p = get_post();
            if ( $p && isset( $p->ID ) ) {
                $post_id = (int) $p->ID;
            }
        }

        $image_url = '';
        if ( $post_id && $meta_key !== '' ) {
            $image_url = get_post_meta( $post_id, $meta_key, true );
            $image_url = is_string( $image_url ) ? trim( $image_url ) : '';
        }

        if ( $image_url === '' && $fallback_url !== '' ) {
            $image_url = $fallback_url;
        }

        $image_url = esc_url( $image_url );
        if ( $image_url === '' ) {
            return;
        }

        if ( $render_mode === 'background' ) {
            echo '<div class="cbk-external-image__bg" style="background-image:url(' . esc_url( $image_url ) . ');"></div>';
            return;
        }

        $alt = '';
        $alt_key = $settings['img_alt_meta_key'] ?? '';
        $alt_key = is_string( $alt_key ) ? trim( $alt_key ) : '';
        if ( $alt_key !== '' && $post_id ) {
            $alt_val = get_post_meta( $post_id, $alt_key, true );
            $alt = is_string( $alt_val ) ? $alt_val : '';
        }

        echo '<img class="cbk-external-image__img" src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" />';
    }

    private function get_image_url_meta_key_options() {
        $options = [
            '_image' => '_image',
        ];

        $fields = (array) get_option( 'coffeebrk_dynamic_fields', [] );
        foreach ( $fields as $f ) {
            $type = $f['type'] ?? '';
            $key = $f['key'] ?? '';
            $label = $f['label'] ?? '';

            if ( $type !== 'image_url' ) {
                continue;
            }
            if ( ! is_string( $key ) || $key === '' ) {
                continue;
            }

            $title = '';
            if ( is_string( $label ) && $label !== '' ) {
                $title = $label . ' (' . $key . ')';
            } else {
                $title = $key;
            }

            $options[ $key ] = $title;
        }

        return $options;
    }
}
