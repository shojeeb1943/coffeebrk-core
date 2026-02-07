<?php
/*
Plugin Name: Coffeebrk Core
Plugin URI: https://coffeebrk.ai
Description: Core functionality plugin for coffeebrk.ai — adds global fields, Elementor dynamic tags, and future features.
Version: 1.7
Author: Coffeebrk
Author URI: https://coffeebrk.ai
License: GPL2+
Text Domain: coffeebrk-core
*/

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'COFFEEBRK_CORE_PATH' ) ) {
    define( 'COFFEEBRK_CORE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'COFFEEBRK_CORE_URL' ) ) {
    define( 'COFFEEBRK_CORE_URL', plugin_dir_url( __FILE__ ) );
}
 
require_once COFFEEBRK_CORE_PATH . 'inc/logger.php';
require_once COFFEEBRK_CORE_PATH . 'inc/rss.php';
require_once COFFEEBRK_CORE_PATH . 'inc/stories-cpt.php';
 
register_activation_hook( __FILE__, function() {
    if ( function_exists( 'coffeebrk_logger_ensure_paths' ) ) {
        coffeebrk_logger_ensure_paths();
    }
    if ( function_exists( 'coffeebrk_rss_install' ) ) {
        coffeebrk_rss_install();
    }
    if ( function_exists( 'coffeebrk_rss_schedule_cron' ) ) {
        coffeebrk_rss_schedule_cron();
    }
    do_action('coffeebrk_core_activate');
    // Seed default dynamic fields if empty
    $dyn = get_option('coffeebrk_dynamic_fields', []);
    if ( empty($dyn) || !is_array($dyn) ) {
        $seed = [
            [ 'id'=> 'seed_sn', 'label' => 'Source Name', 'key' => '_source_name', 'type' => 'text', 'choices' => '' ],
            [ 'id'=> 'seed_su', 'label' => 'Source URL',  'key' => '_source_url',  'type' => 'url',  'choices' => '' ],
        ];
        update_option('coffeebrk_dynamic_fields', $seed, false);
    }
    // Seed default aspires if empty
    $as = get_option('coffeebrk_aspires', []);
    if ( empty($as) || !is_array($as) ) {
        $defaults = [ 'Developer','Designer','Marketer','Writer / Content Creator','Product Manager','Data / AI Engineer','Student / Explorer' ];
        update_option('coffeebrk_aspires', $defaults, false);
    }
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {
    if ( function_exists( 'coffeebrk_rss_clear_cron' ) ) {
        coffeebrk_rss_clear_cron();
    }
    flush_rewrite_rules();
});

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    if ( ! is_array( $links ) ) {
        $links = [];
    }

    $dashboard = admin_url( 'admin.php?page=coffeebrk-core' );
    $settings = admin_url( 'admin.php?page=coffeebrk-core-auth' );
    $links[] = '<a href="' . esc_url( $dashboard ) . '">Dashboard</a>';
    $links[] = '<a href="' . esc_url( $settings ) . '">Settings</a>';

    return $links;
} );

/**
 * --------------------------------------------------------------
 * SECTION 1: Register Global Meta Fields
 * --------------------------------------------------------------
 */
add_action( 'init', function() {
    $fields = [
        '_source_name' => 'string',
        '_source_url'  => 'string',
    ];

    foreach ( $fields as $key => $type ) {
        register_post_meta( 'post', $key, [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => $type,
            'auth_callback' => fn() => current_user_can( 'edit_posts' ),
        ]);
    }
});

/**
 * --------------------------------------------------------------
 * SECTION 2: Add Meta Box in Post Editor (Source Information)
 * --------------------------------------------------------------
 */
add_action( 'add_meta_boxes', function() {
    // Hide legacy box if Dynamic Fields contains Source Name & URL
    $dyn = (array) get_option('coffeebrk_dynamic_fields', []);
    $has_sn = false; $has_su = false;
    foreach ($dyn as $f){ $k = $f['key'] ?? ''; if ($k === '_source_name') $has_sn = true; if ($k === '_source_url') $has_su = true; }
    if ( $has_sn && $has_su ) return;
    add_meta_box(
        'coffeebrk_source_meta',
        'Source Information',
        'coffeebrk_source_meta_callback',
        'post',
        'normal',
        'high'
    );
});

function coffeebrk_source_meta_callback( $post ) {
    $source_name = get_post_meta( $post->ID, '_source_name', true );
    $source_url  = get_post_meta( $post->ID, '_source_url', true );
    wp_nonce_field( 'coffeebrk_save_source_meta', 'coffeebrk_source_meta_nonce' );
    ?>
    <p>
        <label><strong>Source Name</strong></label><br>
        <input type="text" name="source_name" value="<?php echo esc_attr( $source_name ); ?>" style="width:100%;" placeholder="e.g. OpenAI Blog">
    </p>
    <p>
        <label><strong>Source URL</strong></label><br>
        <input type="url" name="source_url" value="<?php echo esc_attr( $source_url ); ?>" style="width:100%;" placeholder="https://example.com/article">
    </p>
    <?php
}

add_action( 'save_post', function( $post_id ) {
    if ( ! isset( $_POST['coffeebrk_source_meta_nonce'] ) ||
         ! wp_verify_nonce( $_POST['coffeebrk_source_meta_nonce'], 'coffeebrk_save_source_meta' ) )
        return;

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    update_post_meta( $post_id, '_source_name', sanitize_text_field( $_POST['source_name'] ?? '' ) );
    update_post_meta( $post_id, '_source_url', esc_url_raw( $_POST['source_url'] ?? '' ) );
});

/**
 * --------------------------------------------------------------
 * SECTION 3: Load Elementor Integration
 * --------------------------------------------------------------
 */
add_action( 'elementor/init', function() {
    $f = __DIR__ . '/inc/class-coffeebrk-elementor-tags.php';
    if ( file_exists( $f ) ) {
        require_once $f;
    }

    $w = __DIR__ . '/inc/class-coffeebrk-elementor-widgets.php';
    if ( file_exists( $w ) ) {
        require_once $w;
    }
});

/**
 * --------------------------------------------------------------
 * SECTION 4: Optional Frontend Display
 * --------------------------------------------------------------
 */
add_filter( 'the_content', function( $content ) {
    if ( is_single() ) {
        $source_name = get_post_meta( get_the_ID(), '_source_name', true );
        $source_url  = get_post_meta( get_the_ID(), '_source_url', true );

        if ( $source_name && $source_url ) {
            $content .= sprintf(
                '<div class="coffeebrk-source-box" style="margin-top:20px;padding:10px 15px;border-left:3px solid #0073aa;background:#f9f9f9;border-radius:6px;">
                    <strong>📰 Source:</strong> <a href="%s" target="_blank" rel="nofollow noopener">%s</a>
                </div>',
                esc_url( $source_url ),
                esc_html( $source_name )
            );
        }
    }
    return $content;
});

// Register includes
require_once COFFEEBRK_CORE_PATH . 'inc/admin-settings.php';
require_once COFFEEBRK_CORE_PATH . 'inc/admin-login-dashboard.php';
require_once COFFEEBRK_CORE_PATH . 'dashboard/admin-dynamic-fields.php';
require_once COFFEEBRK_CORE_PATH . 'dashboard/admin-aspires.php';
require_once COFFEEBRK_CORE_PATH . 'meta/meta-dynamic-fields.php';
require_once COFFEEBRK_CORE_PATH . 'meta/meta-aspires.php';
require_once COFFEEBRK_CORE_PATH . 'inc/feed.php';
require_once COFFEEBRK_CORE_PATH . 'inc/auth.php';
require_once COFFEEBRK_CORE_PATH . 'admin/json-articles-importer.php';
