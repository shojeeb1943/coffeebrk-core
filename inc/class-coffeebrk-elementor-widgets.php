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

    $news_card = __DIR__ . '/widgets/class-coffeebrk-news-card-widget.php';
    if ( file_exists( $news_card ) ) {
        require_once $news_card;
    }

    if ( class_exists( '\\Coffeebrk_News_Card_Widget' ) ) {
        $widgets_manager->register( new \Coffeebrk_News_Card_Widget() );
    }

    $user_greeting = __DIR__ . '/widgets/class-coffeebrk-user-greeting-widget.php';
    if ( file_exists( $user_greeting ) ) {
        require_once $user_greeting;
    }

    if ( class_exists( '\\Coffeebrk_User_Greeting_Widget' ) ) {
        $widgets_manager->register( new \Coffeebrk_User_Greeting_Widget() );
    }

    $stories = __DIR__ . '/widgets/class-coffeebrk-stories-widget.php';
    if ( file_exists( $stories ) ) {
        require_once $stories;
    }

    if ( class_exists( '\\Coffeebrk_Stories_Widget' ) ) {
        $widgets_manager->register( new \Coffeebrk_Stories_Widget() );
    }

    $universal_video = __DIR__ . '/widgets/class-coffeebrk-universal-video-widget.php';
    if ( file_exists( $universal_video ) ) {
        require_once $universal_video;
    }

    if ( class_exists( '\\Coffeebrk_Universal_Video_Widget' ) ) {
        $widgets_manager->register( new \Coffeebrk_Universal_Video_Widget() );
    }

    $bento_grid = __DIR__ . '/widgets/class-coffeebrk-bento-grid-widget.php';
    if ( file_exists( $bento_grid ) ) {
        require_once $bento_grid;
    }

    if ( class_exists( '\\Coffeebrk_Bento_Grid_Widget' ) ) {
        $widgets_manager->register( new \Coffeebrk_Bento_Grid_Widget() );
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

    $news_card = __DIR__ . '/widgets/class-coffeebrk-news-card-widget.php';
    if ( file_exists( $news_card ) ) {
        require_once $news_card;
    }

    $user_greeting = __DIR__ . '/widgets/class-coffeebrk-user-greeting-widget.php';
    if ( file_exists( $user_greeting ) ) {
        require_once $user_greeting;
    }

    $stories = __DIR__ . '/widgets/class-coffeebrk-stories-widget.php';
    if ( file_exists( $stories ) ) {
        require_once $stories;
    }

    $universal_video = __DIR__ . '/widgets/class-coffeebrk-universal-video-widget.php';
    if ( file_exists( $universal_video ) ) {
        require_once $universal_video;
    }

    $bento_grid = __DIR__ . '/widgets/class-coffeebrk-bento-grid-widget.php';
    if ( file_exists( $bento_grid ) ) {
        require_once $bento_grid;
    }

    $plugin = \Elementor\Plugin::instance();
    if ( isset( $plugin->widgets_manager ) && method_exists( $plugin->widgets_manager, 'register_widget_type' ) ) {
        if ( class_exists( '\\Coffeebrk_External_Image_Widget' ) ) {
            $plugin->widgets_manager->register_widget_type( new \Coffeebrk_External_Image_Widget() );
        }
        if ( class_exists( '\\Coffeebrk_News_Card_Widget' ) ) {
            $plugin->widgets_manager->register_widget_type( new \Coffeebrk_News_Card_Widget() );
        }
        if ( class_exists( '\\Coffeebrk_User_Greeting_Widget' ) ) {
            $plugin->widgets_manager->register_widget_type( new \Coffeebrk_User_Greeting_Widget() );
        }
        if ( class_exists( '\\Coffeebrk_Stories_Widget' ) ) {
            $plugin->widgets_manager->register_widget_type( new \Coffeebrk_Stories_Widget() );
        }
        if ( class_exists( '\\Coffeebrk_Universal_Video_Widget' ) ) {
            $plugin->widgets_manager->register_widget_type( new \Coffeebrk_Universal_Video_Widget() );
        }
        if ( class_exists( '\\Coffeebrk_Bento_Grid_Widget' ) ) {
            $plugin->widgets_manager->register_widget_type( new \Coffeebrk_Bento_Grid_Widget() );
        }
    }
} );

// Enqueue widget styles
add_action( 'elementor/frontend/after_enqueue_styles', function() {
    wp_enqueue_style(
        'coffeebrk-news-card',
        COFFEEBRK_CORE_URL . 'assets/css/coffeebrk-news-card.css',
        [],
        '1.0.0'
    );
    
    wp_enqueue_style(
        'coffeebrk-stories',
        COFFEEBRK_CORE_URL . 'assets/css/coffeebrk-stories.css',
        [],
        '1.9.8'
    );

    wp_enqueue_style(
        'coffeebrk-bento-grid',
        COFFEEBRK_CORE_URL . 'assets/css/coffeebrk-bento-grid.css',
        [],
        '1.0.0'
    );
} );

// Enqueue widget scripts
add_action( 'elementor/frontend/after_enqueue_scripts', function() {
    wp_enqueue_script(
        'coffeebrk-stories',
        COFFEEBRK_CORE_URL . 'assets/js/coffeebrk-stories.js',
        [],
        '1.9.11',
        true
    );

    wp_enqueue_script(
        'coffeebrk-bento-grid',
        COFFEEBRK_CORE_URL . 'assets/js/coffeebrk-bento-grid.js',
        [ 'coffeebrk-stories' ],
        '1.0.0',
        true
    );
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
