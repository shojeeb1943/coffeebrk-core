<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register REST API endpoint for authentication
add_action( 'rest_api_init', function() {
    register_rest_route( 'coffeebrk/v1', '/auth', [
        'methods'             => 'POST',
        'callback'            => 'coffeebrk_handle_auth',
        'permission_callback' => '__return_true', // Open endpoint - rate limiting handled in callback
    ] );
} );

/**
 * Handle Supabase authentication and WordPress user creation/login
 * 
 * @param WP_REST_Request $request The REST request object
 * @return array Response array with success status and message
 */
function coffeebrk_handle_auth( WP_REST_Request $request ) {
    // Rate limiting check
    if ( ! coffeebrk_check_auth_rate_limit() ) {
        return new WP_REST_Response( [
            'success' => false,
            'msg'     => 'Too many authentication attempts. Please try again later.',
        ], 429 );
    }

    // Get JSON data from request
    $data = $request->get_json_params();
    $token = $data['access_token'] ?? '';

    if ( ! $token ) {
        return [ 'success' => false, 'msg' => 'Missing access token' ];
    }

    // Get Coffeebrk settings
    $opts = get_option( 'coffeebrk_core_settings', [] );
    $supabase_url = rtrim( $opts['supabase_url'] ?? '', '/' );
    $service_role = $opts['supabase_service_role'] ?? '';

    // Verify token with Supabase
    $url = $supabase_url . '/auth/v1/user';
    $response = wp_remote_get( $url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'apiKey'        => $service_role,
        ],
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'msg' => $response->get_error_message() ];
    }

    // Parse Supabase response
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['email'] ) ) {
        return [ 'success' => false, 'msg' => 'Invalid Supabase response' ];
    }

    $email = $body['email'];
    $supabase_id = $body['id'] ?? '';

    // Find or create WordPress user
    $user = get_user_by( 'email', $email );

    if ( ! $user ) {
        $login = sanitize_user( strstr( $email, '@', true ) ?: $email );
        $user_id = wp_create_user( $login, wp_generate_password( 24 ), $email );

        if ( is_wp_error( $user_id ) ) {
            return [ 'success' => false, 'msg' => $user_id->get_error_message() ];
        }

        if ( $supabase_id ) {
            update_user_meta( $user_id, 'coffeebrk_supabase_id', sanitize_text_field( $supabase_id ) );
        }

        $user = get_user_by( 'id', $user_id );
    } else {
        // Update existing user's Supabase ID if provided
        if ( $supabase_id ) {
            update_user_meta( $user->ID, 'coffeebrk_supabase_id', sanitize_text_field( $supabase_id ) );
        }
    }

    // Log user in
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID );

    return [ 'success' => true, 'user' => $user->user_email ];
}

/**
 * Check rate limiting for authentication endpoint
 * Limits to 5 attempts per minute per IP
 * 
 * @return bool True if within rate limit, false if exceeded
 */
function coffeebrk_check_auth_rate_limit() : bool {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
    $transient_key = 'coffeebrk_auth_limit_' . md5( $ip );
    
    $attempts = (int) get_transient( $transient_key );
    
    if ( $attempts >= 5 ) {
        return false;
    }

    set_transient( $transient_key, $attempts + 1, MINUTE_IN_SECONDS );
    
    return true;
}
