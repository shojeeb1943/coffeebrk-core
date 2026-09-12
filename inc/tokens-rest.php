<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// REST wiring for API token management. All logic already exists in
// inc/api-tokens.php (create/revoke/update/list) — this file only exposes
// it, gated behind the 'manage' scope (coffeebrk_api_permission_manage in
// inc/rest-api.php) since minting/revoking tokens is more sensitive than
// everyday content CRUD and must not be reachable by a plain read/write
// token.

add_action( 'rest_api_init', 'coffeebrk_tokens_register_rest_routes' );

function coffeebrk_tokens_register_rest_routes() {
    $namespace = 'coffeebrk/v1';

    register_rest_route( $namespace, '/tokens', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_manage',
        'callback'            => 'coffeebrk_tokens_api_get_tokens',
    ]);

    register_rest_route( $namespace, '/tokens', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_manage',
        'callback'            => 'coffeebrk_tokens_api_create_token',
    ]);

    register_rest_route( $namespace, '/tokens/(?P<id>[a-zA-Z0-9_]+)', [
        'methods'             => [ 'PUT', 'PATCH' ],
        'permission_callback' => 'coffeebrk_api_permission_manage',
        'callback'            => 'coffeebrk_tokens_api_update_token',
        'args'                => [ 'id' => [ 'type' => 'string', 'required' => true ] ],
    ]);

    register_rest_route( $namespace, '/tokens/(?P<id>[a-zA-Z0-9_]+)', [
        'methods'             => 'DELETE',
        'permission_callback' => 'coffeebrk_api_permission_manage',
        'callback'            => 'coffeebrk_tokens_api_delete_token',
        'args'                => [ 'id' => [ 'type' => 'string', 'required' => true ] ],
    ]);
}

// Whitelisted fields only — never the hash.
function coffeebrk_tokens_format( array $t ) : array {
    return [
        'id'          => $t['id'] ?? '',
        'name'        => $t['name'] ?? '',
        'last4'       => $t['last4'] ?? '',
        'prefix'      => $t['prefix'] ?? '',
        'permissions' => $t['permissions'] ?? [],
        'status'      => $t['status'] ?? '',
        'last_used'   => $t['last_used'] ?? null,
        'created_at'  => $t['created_at'] ?? null,
    ];
}

function coffeebrk_tokens_api_get_tokens( WP_REST_Request $req ) {
    $tokens = array_map( 'coffeebrk_tokens_format', coffeebrk_get_api_tokens() );
    return new WP_REST_Response( [ 'success' => true, 'total' => count( $tokens ), 'items' => $tokens ], 200 );
}

function coffeebrk_tokens_api_create_token( WP_REST_Request $req ) {
    $params = coffeebrk_get_request_params( $req );
    $name = sanitize_text_field( (string) ( $params['name'] ?? '' ) );

    $permissions = isset( $params['permissions'] ) && is_array( $params['permissions'] )
        ? array_values( array_intersect( $params['permissions'], [ 'read', 'write', 'delete', 'manage' ] ) )
        : [ 'read', 'write', 'delete' ];

    $result = coffeebrk_create_api_token( $name, $permissions );

    return new WP_REST_Response( [
        'success' => true,
        'token'   => $result['plain_token'],
        'message' => 'Copy this token now — it will not be shown again.',
        'data'    => coffeebrk_tokens_format( $result['token_data'] ),
    ], 201 );
}

function coffeebrk_tokens_api_update_token( WP_REST_Request $req ) {
    $id = (string) $req->get_param( 'id' );
    if ( ! coffeebrk_get_token_by_id( $id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'token_not_found' ], 404 );
    }

    $params = coffeebrk_get_request_params( $req );

    if ( array_key_exists( 'name', $params ) ) {
        coffeebrk_update_token_name( $id, (string) $params['name'] );
    }
    if ( array_key_exists( 'permissions', $params ) && is_array( $params['permissions'] ) ) {
        coffeebrk_update_token_permissions( $id, $params['permissions'] );
    }
    if ( array_key_exists( 'status', $params ) ) {
        $current = coffeebrk_get_token_by_id( $id );
        if ( $current && $current['status'] !== $params['status'] ) {
            coffeebrk_toggle_token_status( $id );
        }
    }

    return new WP_REST_Response( [ 'success' => true, 'data' => coffeebrk_tokens_format( coffeebrk_get_token_by_id( $id ) ) ], 200 );
}

function coffeebrk_tokens_api_delete_token( WP_REST_Request $req ) {
    $id = (string) $req->get_param( 'id' );
    if ( ! coffeebrk_get_token_by_id( $id ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'token_not_found' ], 404 );
    }
    $ok = coffeebrk_revoke_api_token( $id );
    return new WP_REST_Response( [ 'success' => $ok, 'id' => $id ], $ok ? 200 : 500 );
}
