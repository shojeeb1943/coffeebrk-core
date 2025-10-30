<?php
use Elementor\Core\DynamicTags\Tag;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_Source_Name_Tag extends Tag {

    public function get_name() { return 'coffeebrk-source-name'; }
    public function get_title() { return __( 'Source Name', 'coffeebrk-core' ); }
    public function get_group() { return 'coffeebrk-meta'; }
    public function get_categories() {
        return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
    }

    protected function register_controls() {}

    public function render() {
        $value = get_post_meta( get_the_ID(), '_source_name', true );
        echo esc_html( $value ?: '' );
    }
}
