<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Plugin;
use Elementor\Modules\DynamicTags\Module as DynModule;

// Load Coffeebrk dynamic tags only after Elementor is ready
add_action( 'elementor/dynamic_tags/register', function( $dynamic_tags_manager ) {

    // Register a custom Coffeebrk group
    $dynamic_tags_manager->register_group( 'coffeebrk-meta', [
        'title' => __( 'Coffeebrk Meta', 'coffeebrk-core' ),
    ]);

    // Include our legacy tag classes
    require_once __DIR__ . '/class-coffeebrk-source-name-tag.php';
    require_once __DIR__ . '/class-coffeebrk-source-url-tag.php';

    // Register legacy tags
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Name_Tag() );
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Url_Tag() );

    // Dynamically register a simple text tag for each field in coffeebrk_dynamic_fields
    $fields = (array) get_option('coffeebrk_dynamic_fields', []);
    foreach ($fields as $f){
        $key = $f['key'] ?? '';
        $label = $f['label'] ?? '';
        if (!$key || !$label) continue;
        // Use anonymous class to avoid eval and support older Elementor versions
        $tag = new class($key, $label) extends \Elementor\Core\DynamicTags\Tag {
            private $cbk_key; private $cbk_label;
            public function __construct($k, $l){ $this->cbk_key = $k; $this->cbk_label = $l; }
            public function get_name(){ return 'cbk_'. ltrim($this->cbk_key, '_'); }
            public function get_title(){ return $this->cbk_label; }
            public function get_group(){ return 'coffeebrk-meta'; }
            public function get_categories(){
                if ( class_exists('Elementor\\Modules\\DynamicTags\\Module') ) {
                    return [ DynModule::TEXT_CATEGORY ];
                }
                // Fallback: return plain text category string if constant not available
                return [ 'text' ];
            }
            protected function register_controls(){}
            public function render(){ echo esc_html( get_post_meta( get_the_ID(), $this->cbk_key, true ) ); }
        };
        $dynamic_tags_manager->register( $tag );
    }
});
