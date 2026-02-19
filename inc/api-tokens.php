<?php
/**
 * Coffeebrk Core API Token Management
 *
 * Multi-token support with industry-standard features:
 * - Multiple tokens per site
 * - Token labels/names
 * - Last used tracking
 * - Permissions/scopes
 * - Secure hash storage
 *
 * @package Coffeebrk_Core
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get all API tokens
 */
function coffeebrk_get_api_tokens() {
    $tokens = get_option( 'coffeebrk_api_tokens', [] );
    if ( ! is_array( $tokens ) ) {
        $tokens = [];
    }

    // Migration: Convert old single token to new format
    $old_hash = get_option( 'coffeebrk_core_api_token_hash', '' );
    if ( $old_hash !== '' && empty( $tokens ) ) {
        $tokens[] = [
            'id'          => coffeebrk_generate_token_id(),
            'name'        => 'Default Token (Migrated)',
            'hash'        => $old_hash,
            'last4'       => get_option( 'coffeebrk_core_api_token_last4', '****' ),
            'created_at'  => get_option( 'coffeebrk_core_api_token_updated', time() ),
            'created_by'  => get_option( 'coffeebrk_core_api_token_user_id', 0 ),
            'last_used'   => null,
            'permissions' => [ 'read', 'write', 'delete' ],
            'status'      => 'active',
        ];
        update_option( 'coffeebrk_api_tokens', $tokens, false );

        // Clean up old options
        delete_option( 'coffeebrk_core_api_token_hash' );
        delete_option( 'coffeebrk_core_api_token_last4' );
        delete_option( 'coffeebrk_core_api_token_updated' );
        delete_option( 'coffeebrk_core_api_token_user_id' );
    }

    return $tokens;
}

/**
 * Generate unique token ID
 */
function coffeebrk_generate_token_id() {
    return 'tok_' . bin2hex( random_bytes( 8 ) );
}

/**
 * Create a new API token
 */
function coffeebrk_create_api_token( $name = '', $permissions = [ 'read', 'write', 'delete' ] ) {
    $tokens = coffeebrk_get_api_tokens();

    // Generate secure token
    $raw = random_bytes( 32 );
    $plain_token = 'cbk_' . rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    $hash = password_hash( $plain_token, PASSWORD_DEFAULT );

    $token_id = coffeebrk_generate_token_id();
    $user_id = get_current_user_id();

    $new_token = [
        'id'          => $token_id,
        'name'        => sanitize_text_field( $name !== '' ? $name : 'API Token ' . ( count( $tokens ) + 1 ) ),
        'hash'        => $hash,
        'last4'       => substr( $plain_token, -4 ),
        'prefix'      => substr( $plain_token, 0, 8 ),
        'created_at'  => time(),
        'created_by'  => $user_id,
        'last_used'   => null,
        'permissions' => $permissions,
        'status'      => 'active',
    ];

    $tokens[] = $new_token;
    update_option( 'coffeebrk_api_tokens', $tokens, false );

    // Store plain token temporarily for display
    if ( $user_id > 0 ) {
        set_transient( 'coffeebrk_new_token_' . $token_id, $plain_token, 5 * MINUTE_IN_SECONDS );
    }

    return [
        'token_id'    => $token_id,
        'plain_token' => $plain_token,
        'token_data'  => $new_token,
    ];
}

/**
 * Revoke/delete an API token
 */
function coffeebrk_revoke_api_token( $token_id ) {
    $tokens = coffeebrk_get_api_tokens();
    $found = false;

    foreach ( $tokens as $key => $token ) {
        if ( $token['id'] === $token_id ) {
            unset( $tokens[ $key ] );
            $found = true;
            break;
        }
    }

    if ( $found ) {
        $tokens = array_values( $tokens ); // Re-index array
        update_option( 'coffeebrk_api_tokens', $tokens, false );
        delete_transient( 'coffeebrk_new_token_' . $token_id );
    }

    return $found;
}

/**
 * Update token last used timestamp
 */
function coffeebrk_update_token_last_used( $token_id ) {
    $tokens = coffeebrk_get_api_tokens();

    foreach ( $tokens as $key => $token ) {
        if ( $token['id'] === $token_id ) {
            $tokens[ $key ]['last_used'] = time();
            update_option( 'coffeebrk_api_tokens', $tokens, false );
            return true;
        }
    }

    return false;
}

/**
 * Validate API token and return token data if valid
 */
function coffeebrk_validate_api_token( $plain_token ) {
    $plain_token = trim( $plain_token );
    if ( $plain_token === '' ) {
        return false;
    }

    $tokens = coffeebrk_get_api_tokens();

    foreach ( $tokens as $token ) {
        if ( $token['status'] !== 'active' ) {
            continue;
        }

        if ( password_verify( $plain_token, $token['hash'] ) ) {
            // Update last used (async to not slow down request)
            coffeebrk_update_token_last_used( $token['id'] );
            return $token;
        }
    }

    return false;
}

/**
 * Check if token is valid (simple boolean check)
 */
function coffeebrk_is_token_valid( $plain_token ) {
    return coffeebrk_validate_api_token( $plain_token ) !== false;
}

/**
 * Check token permissions
 */
function coffeebrk_token_has_permission( $plain_token, $permission ) {
    $token = coffeebrk_validate_api_token( $plain_token );
    if ( ! $token ) {
        return false;
    }

    return in_array( $permission, $token['permissions'], true );
}

/**
 * Update token name
 */
function coffeebrk_update_token_name( $token_id, $name ) {
    $tokens = coffeebrk_get_api_tokens();

    foreach ( $tokens as $key => $token ) {
        if ( $token['id'] === $token_id ) {
            $tokens[ $key ]['name'] = sanitize_text_field( $name );
            update_option( 'coffeebrk_api_tokens', $tokens, false );
            return true;
        }
    }

    return false;
}

/**
 * Update token permissions
 */
function coffeebrk_update_token_permissions( $token_id, $permissions ) {
    $tokens = coffeebrk_get_api_tokens();
    $valid_permissions = [ 'read', 'write', 'delete' ];
    $permissions = array_intersect( $permissions, $valid_permissions );

    foreach ( $tokens as $key => $token ) {
        if ( $token['id'] === $token_id ) {
            $tokens[ $key ]['permissions'] = array_values( $permissions );
            update_option( 'coffeebrk_api_tokens', $tokens, false );
            return true;
        }
    }

    return false;
}

/**
 * Toggle token status (active/inactive)
 */
function coffeebrk_toggle_token_status( $token_id ) {
    $tokens = coffeebrk_get_api_tokens();

    foreach ( $tokens as $key => $token ) {
        if ( $token['id'] === $token_id ) {
            $tokens[ $key ]['status'] = $token['status'] === 'active' ? 'inactive' : 'active';
            update_option( 'coffeebrk_api_tokens', $tokens, false );
            return $tokens[ $key ]['status'];
        }
    }

    return false;
}

/**
 * Get token by ID
 */
function coffeebrk_get_token_by_id( $token_id ) {
    $tokens = coffeebrk_get_api_tokens();

    foreach ( $tokens as $token ) {
        if ( $token['id'] === $token_id ) {
            return $token;
        }
    }

    return null;
}

/**
 * Format time ago string
 */
function coffeebrk_time_ago( $timestamp ) {
    if ( ! $timestamp ) {
        return 'Never';
    }

    $diff = time() - $timestamp;

    if ( $diff < 60 ) {
        return 'Just now';
    } elseif ( $diff < 3600 ) {
        $mins = floor( $diff / 60 );
        return $mins . ' minute' . ( $mins > 1 ? 's' : '' ) . ' ago';
    } elseif ( $diff < 86400 ) {
        $hours = floor( $diff / 3600 );
        return $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ago';
    } elseif ( $diff < 604800 ) {
        $days = floor( $diff / 86400 );
        return $days . ' day' . ( $days > 1 ? 's' : '' ) . ' ago';
    } else {
        return wp_date( 'M j, Y', $timestamp );
    }
}

// =============================================================================
// ADMIN ACTIONS
// =============================================================================

/**
 * Handle token creation
 */
add_action( 'admin_post_coffeebrk_create_token', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 403 );
    }

    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'coffeebrk_create_token' ) ) {
        wp_die( 'Invalid nonce', 400 );
    }

    $name = isset( $_POST['token_name'] ) ? sanitize_text_field( $_POST['token_name'] ) : '';
    $permissions = [];

    if ( isset( $_POST['perm_read'] ) ) $permissions[] = 'read';
    if ( isset( $_POST['perm_write'] ) ) $permissions[] = 'write';
    if ( isset( $_POST['perm_delete'] ) ) $permissions[] = 'delete';

    if ( empty( $permissions ) ) {
        $permissions = [ 'read', 'write', 'delete' ];
    }

    $result = coffeebrk_create_api_token( $name, $permissions );

    wp_safe_redirect( add_query_arg( [
        'page'      => 'coffeebrk-core-api',
        'msg'       => 'token_created',
        'new_token' => $result['token_id'],
    ], admin_url( 'admin.php' ) ) );
    exit;
});

/**
 * Handle token revocation
 */
add_action( 'admin_post_coffeebrk_revoke_token', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 403 );
    }

    $token_id = isset( $_POST['token_id'] ) ? sanitize_text_field( $_POST['token_id'] ) : '';

    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'coffeebrk_revoke_token_' . $token_id ) ) {
        wp_die( 'Invalid nonce', 400 );
    }

    coffeebrk_revoke_api_token( $token_id );

    wp_safe_redirect( add_query_arg( [
        'page' => 'coffeebrk-core-api',
        'msg'  => 'token_revoked',
    ], admin_url( 'admin.php' ) ) );
    exit;
});

/**
 * Handle token status toggle
 */
add_action( 'admin_post_coffeebrk_toggle_token', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 403 );
    }

    $token_id = isset( $_POST['token_id'] ) ? sanitize_text_field( $_POST['token_id'] ) : '';

    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'coffeebrk_toggle_token_' . $token_id ) ) {
        wp_die( 'Invalid nonce', 400 );
    }

    coffeebrk_toggle_token_status( $token_id );

    wp_safe_redirect( add_query_arg( [
        'page' => 'coffeebrk-core-api',
        'msg'  => 'token_updated',
    ], admin_url( 'admin.php' ) ) );
    exit;
});

/**
 * Handle token name update
 */
add_action( 'admin_post_coffeebrk_update_token', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 403 );
    }

    $token_id = isset( $_POST['token_id'] ) ? sanitize_text_field( $_POST['token_id'] ) : '';

    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'coffeebrk_update_token_' . $token_id ) ) {
        wp_die( 'Invalid nonce', 400 );
    }

    $name = isset( $_POST['token_name'] ) ? sanitize_text_field( $_POST['token_name'] ) : '';
    if ( $name !== '' ) {
        coffeebrk_update_token_name( $token_id, $name );
    }

    $permissions = [];
    if ( isset( $_POST['perm_read'] ) ) $permissions[] = 'read';
    if ( isset( $_POST['perm_write'] ) ) $permissions[] = 'write';
    if ( isset( $_POST['perm_delete'] ) ) $permissions[] = 'delete';

    if ( ! empty( $permissions ) ) {
        coffeebrk_update_token_permissions( $token_id, $permissions );
    }

    wp_safe_redirect( add_query_arg( [
        'page' => 'coffeebrk-core-api',
        'msg'  => 'token_updated',
    ], admin_url( 'admin.php' ) ) );
    exit;
});

// =============================================================================
// BACKWARDS COMPATIBILITY
// =============================================================================

/**
 * Override the old token validation function
 */
if ( ! function_exists( 'coffeebrk_core_api_token_is_valid' ) ) {
    function coffeebrk_core_api_token_is_valid( $token ) {
        return coffeebrk_is_token_valid( $token );
    }
}
