<?php

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_External_Image_Url_Tag extends Data_Tag {

    public function get_name() {
        return 'external_image_url';
    }

    public function get_title() {
        return __( 'External Image (URL)', 'coffeebrk-core' );
    }

    public function get_group() {
        return 'post';
    }

    public function get_categories() {
        return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
    }

    public function get_value( array $options = [] ) {
        $image_url = get_post_meta( get_the_ID(), 'image', true );
        $image_url = is_string( $image_url ) ? trim( $image_url ) : '';

        if ( empty( $image_url ) ) {
            $placeholder = $this->get_settings( 'placeholder_url' );
            $placeholder = is_string( $placeholder ) ? trim( $placeholder ) : '';
            if ( empty( $placeholder ) ) {
                return null;
            }
            $image_url = $placeholder;
        }

        return [
            'id'  => 0,
            'url' => esc_url( $image_url ),
        ];
    }

    protected function register_controls() {
        $this->add_control(
            'placeholder_url',
            [
                'label' => __( 'Placeholder URL', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => 'https://example.com/placeholder.jpg',
            ]
        );
    }
}
