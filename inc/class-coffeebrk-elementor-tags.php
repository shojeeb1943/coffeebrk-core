<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Plugin;

// Load Coffeebrk dynamic tags only after Elementor is ready
add_action( 'elementor/dynamic_tags/register', function( $dynamic_tags_manager ) {

    // Register a custom Coffeebrk group
    $dynamic_tags_manager->register_group( 'coffeebrk-meta', [
        'title' => __( 'Coffeebrk Meta', 'coffeebrk-core' ),
    ]);

    // Include our tag classes
    require_once __DIR__ . '/class-coffeebrk-source-name-tag.php';
    require_once __DIR__ . '/class-coffeebrk-source-url-tag.php';

    // Register legacy tags
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Name_Tag() );
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Url_Tag() );

    // Dynamically register a simple text tag class for each field in coffeebrk_dynamic_fields
    $fields = (array) get_option('coffeebrk_dynamic_fields', []);
    foreach ($fields as $f){
        $key = $f['key'] ?? '';
        $label = $f['label'] ?? '';
        if (!$key || !$label) continue;
        $class = 'Coffeebrk_DynTag_' . preg_replace('/[^A-Za-z0-9_]/', '', strtoupper(ltrim($key,'_')));
        if (!class_exists($class)){
            eval('namespace { class '.$class.' extends \Elementor\\Core\\DynamicTags\\Tag { public function get_name(){ return '.var_export('cbk_'.ltrim($key,'_'), true).'; } public function get_title(){ return '.var_export($label, true).'; } public function get_group(){ return "coffeebrk-meta"; } public function get_categories(){ return [\Elementor\\Core\\DynamicTags\\Tag::TEXT_CATEGORY]; } protected function register_controls(){} public function render(){ echo esc_html( get_post_meta(get_the_ID(), '.var_export($key,true).', true) ); } } }');
        }
        $dynamic_tags_manager->register( new ('\\'.$class) );
    }
});
