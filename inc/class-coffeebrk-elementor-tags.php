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

    // Register tags
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Name_Tag() );
    $dynamic_tags_manager->register( new \Coffeebrk_Source_Url_Tag() );
});
