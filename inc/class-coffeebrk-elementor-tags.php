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
    require_once __DIR__ . '/class-coffeebrk-dynamic-image-url-tag.php';

    // Register legacy tags
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Name_Tag() );
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Url_Tag() );

    // Dynamically register a simple text tag for each field in coffeebrk_dynamic_fields
    $fields = (array) get_option('coffeebrk_dynamic_fields', []);
    foreach ($fields as $f){
        $key = $f['key'] ?? '';
        $label = $f['label'] ?? '';
        $type = $f['type'] ?? 'text';
        if (!$key || !$label) continue;
        // Avoid duplicates with legacy tags
        if ($key === '_source_name' || $key === '_source_url') { continue; }

        if ( $type === 'image_url' && class_exists( '\\Coffeebrk_Dynamic_Image_Url_Tag' ) ) {
            $tag = new \Coffeebrk_Dynamic_Image_Url_Tag();
            $tag->set_tag_name( 'cbk_img_' . ltrim( (string) $key, '_' ) );
            $dynamic_tags_manager->register( $tag );
            continue;
        }

        // Create a unique class name for this dynamic field
        $class_name = 'Coffeebrk_Dynamic_Field_Tag_' . str_replace('-', '_', sanitize_key($key));
        
        // Only define the class if it doesn't exist yet
        if ( ! class_exists( $class_name ) ) {
            eval("
                class {$class_name} extends \\Elementor\\Core\\DynamicTags\\Tag {
                    public function get_name() { 
                        return 'cbk_" . ltrim($key, '_') . "'; 
                    }
                    public function get_title() { 
                        return '" . addslashes($label) . "'; 
                    }
                    public function get_group() { 
                        return 'coffeebrk-meta'; 
                    }
                    public function get_categories() {
                        if ( class_exists('Elementor\\\\Modules\\\\DynamicTags\\\\Module') ) {
                            return [ \\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY ];
                        }
                        return [ 'text' ];
                    }
                    protected function register_controls() {}
                    public function render() { 
                        echo esc_html( get_post_meta( get_the_ID(), '" . addslashes($key) . "', true ) ); 
                    }
                }
            ");
        }
        
        $dynamic_tags_manager->register( new $class_name() );
    }
});
