<?php
use Elementor\Core\DynamicTags\Tag;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_Source_Url_Tag extends Tag {

    public function get_name() { return 'coffeebrk-source-url'; }
    public function get_title() { return __( 'Source URL', 'coffeebrk-core' ); }
    public function get_group() { return 'coffeebrk-meta'; }
    public function get_categories() {
        return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
    }

    protected function register_controls() {}

    public function render() {
        $value = get_post_meta( get_the_ID(), '_source_url', true );
        echo esc_url( $value ?: '#' );
    }
}
