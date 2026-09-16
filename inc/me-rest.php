<?php
if (!defined('ABSPATH')) exit;

// /wp-json/coffeebrk/v1/me - reports current WP session login state + avatar (Gravatar) for cross-origin clients
add_action('rest_api_init', function(){
    register_rest_route('coffeebrk/v1', '/me', [
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function(){
            if ( ! is_user_logged_in() ) {
                return new WP_REST_Response(['logged_in' => false], 200);
            }
            $user = wp_get_current_user();
            return new WP_REST_Response([
                'logged_in'  => true,
                'name'       => $user->display_name,
                'email'      => $user->user_email,
                'avatar_url' => get_avatar_url($user->ID),
            ], 200);
        }
    ]);
});
