<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Pure Apify HTTP wrapper — no WordPress storage/admin knowledge, so it
// stays swappable for a different actor/provider later without touching
// the collector, database, or REST layers.
class Coffeebrk_Apify_X_Provider {

    // Runs the actor via Apify's synchronous "run-sync-get-dataset-items"
    // convenience endpoint, avoiding any polling/queue mechanism.
    public static function run_actor( string $actor_id, string $token, array $input, int $timeout = 90 ) {
        if ( $actor_id === '' || $token === '' ) {
            return new WP_Error( 'apify_missing_config', 'Apify actor ID or API token is not configured.' );
        }

        $url = 'https://api.apify.com/v2/acts/' . rawurlencode( $actor_id ) . '/run-sync-get-dataset-items?token=' . rawurlencode( $token );

        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $input ),
            'timeout' => $timeout,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'apify_http_error', 'Apify request failed with status ' . $code . '.', [ 'status' => $code ] );
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'apify_invalid_response', 'Apify returned an unexpected response format.' );
        }

        return $data;
    }

    // Lightweight connectivity check for the admin "Test Connection" button.
    public static function test_connection( string $actor_id, string $token, int $timeout = 15 ) : array {
        if ( $actor_id === '' || $token === '' ) {
            return [ 'ok' => false, 'message' => 'Apify actor ID and API token are required.' ];
        }

        $url = 'https://api.apify.com/v2/acts/' . rawurlencode( $actor_id );

        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            'timeout' => $timeout,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'ok' => false, 'message' => $response->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            return [ 'ok' => true, 'message' => 'Connected successfully.' ];
        }
        if ( $code === 401 || $code === 403 ) {
            return [ 'ok' => false, 'message' => 'Invalid Apify API token.' ];
        }
        if ( $code === 404 ) {
            return [ 'ok' => false, 'message' => 'Actor not found. Check the Actor ID.' ];
        }

        return [ 'ok' => false, 'message' => 'Apify responded with status ' . $code . '.' ];
    }
}
