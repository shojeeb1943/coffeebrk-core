<?php

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_Dynamic_Image_Url_Tag extends Data_Tag {

    private $cbk_tag_name = '';

    public function __construct( $data = [] ) {
        parent::__construct( $data );

        if ( is_string( $data ) && $data !== '' ) {
            $this->cbk_tag_name = $data;
            return;
        }

        if ( is_array( $data ) ) {
            foreach ( [ 'name', 'tag_name', 'id' ] as $k ) {
                if ( ! empty( $data[ $k ] ) && is_string( $data[ $k ] ) ) {
                    $this->cbk_tag_name = $data[ $k ];
                    break;
                }
            }
        }
    }

    public function set_tag_name( $name ) {
        $this->cbk_tag_name = is_string( $name ) ? $name : '';
    }

    public function get_name() {
        $this->ensure_tag_name();
        return $this->cbk_tag_name;
    }

    public function get_title() {
        $meta_key = $this->get_meta_key();

        $fields = (array) get_option( 'coffeebrk_dynamic_fields', [] );
        foreach ( $fields as $f ) {
            $key = $f['key'] ?? '';
            if ( $key && $key === $meta_key ) {
                $label = $f['label'] ?? '';
                if ( is_string( $label ) && $label !== '' ) {
                    return $label;
                }
                break;
            }
        }

        return $meta_key ?: __( 'External Image (URL)', 'coffeebrk-core' );
    }

    public function get_group() {
        return 'post';
    }

    public function get_categories() {
        return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
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

    public function get_value( array $options = [] ) {
        $meta_key = $this->get_meta_key();
        if ( empty( $meta_key ) ) {
            return null;
        }

        $candidates = [];
        if ( isset( $options['post_id'] ) && is_numeric( $options['post_id'] ) ) {
            $candidates[] = (int) $options['post_id'];
        }
        $candidates[] = (int) get_the_ID();
        $candidates[] = (int) get_queried_object_id();
        $p = get_post();
        if ( $p && isset( $p->ID ) ) {
            $candidates[] = (int) $p->ID;
        }

        // De-duplicate & remove empties while preserving order.
        $seen = [];
        $post_ids = [];
        foreach ( $candidates as $cid ) {
            $cid = (int) $cid;
            if ( $cid <= 0 ) {
                continue;
            }
            if ( isset( $seen[ $cid ] ) ) {
                continue;
            }
            $seen[ $cid ] = true;
            $post_ids[] = $cid;
        }

        if ( empty( $post_ids ) ) {
            return null;
        }

        $image_url = '';
        foreach ( $post_ids as $post_id ) {
            $candidate_url = get_post_meta( $post_id, $meta_key, true );
            $candidate_url = is_string( $candidate_url ) ? trim( $candidate_url ) : '';

            // Fallback: in case legacy data stored without underscore.
            if ( empty( $candidate_url ) ) {
                $fallback_key = ltrim( $meta_key, '_' );
                if ( $fallback_key !== '' ) {
                    $candidate_url = get_post_meta( $post_id, $fallback_key, true );
                    $candidate_url = is_string( $candidate_url ) ? trim( $candidate_url ) : '';
                }
            }

            if ( ! empty( $candidate_url ) ) {
                $image_url = $candidate_url;
                break;
            }
        }

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

    private function get_meta_key() {
        $this->ensure_tag_name();
        $name = $this->cbk_tag_name;
        if ( ! is_string( $name ) || $name === '' ) {
            return '';
        }

        $prefix = 'cbk_img_';
        if ( strpos( $name, $prefix ) !== 0 ) {
            return '';
        }

        $suffix = substr( $name, strlen( $prefix ) );
        $suffix = is_string( $suffix ) ? $suffix : '';
        $suffix = ltrim( $suffix, '_' );

        if ( $suffix === '' ) {
            return '';
        }

        return '_' . $suffix;
    }

    private function ensure_tag_name() {
        if ( is_string( $this->cbk_tag_name ) && $this->cbk_tag_name !== '' ) {
            return;
        }

        // Some Elementor versions store the tag name as an inherited property.
        if ( property_exists( $this, 'name' ) && is_string( $this->name ) && $this->name !== '' ) {
            $this->cbk_tag_name = $this->name;
            return;
        }
        if ( property_exists( $this, '_name' ) && is_string( $this->_name ) && $this->_name !== '' ) {
            $this->cbk_tag_name = $this->_name;
            return;
        }
    }
}
