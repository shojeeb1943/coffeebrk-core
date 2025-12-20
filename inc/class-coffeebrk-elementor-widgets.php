<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Register Coffeebrk widgets only after Elementor is ready
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
    $f = __DIR__ . '/widgets/class-coffeebrk-external-image-widget.php';
    if ( file_exists( $f ) ) {
        require_once $f;
    }

    if ( class_exists( '\\Coffeebrk_External_Image_Widget' ) ) {
        $widgets_manager->register( new \Coffeebrk_External_Image_Widget() );
    }
} );

// Backward compatibility for older Elementor versions
add_action( 'elementor/widgets/widgets_registered', function() {
    if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
        return;
    }

    $f = __DIR__ . '/widgets/class-coffeebrk-external-image-widget.php';
    if ( file_exists( $f ) ) {
        require_once $f;
    }

    if ( ! class_exists( '\\Coffeebrk_External_Image_Widget' ) ) {
        return;
    }

    $plugin = \Elementor\Plugin::instance();
    if ( isset( $plugin->widgets_manager ) && method_exists( $plugin->widgets_manager, 'register_widget_type' ) ) {
        $plugin->widgets_manager->register_widget_type( new \Coffeebrk_External_Image_Widget() );
    }
} );

add_action( 'elementor/elements/categories_registered', function( $elements_manager ) {
    if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
        return;
    }

    $elements_manager->add_category(
        'coffeebrk',
        [
            'title' => __( 'Coffeebrk', 'coffeebrk-core' ),
            'icon' => 'fa fa-plug',
        ]
    );
} );
