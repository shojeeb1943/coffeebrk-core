<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function coffeebrk_logger_dirs() : array {
    $uploads = wp_upload_dir();
    $base = trailingslashit( $uploads['basedir'] ) . 'coffeebrk-core/logs';
    return [
        'base'   => $base,
        'error'  => $base . '/error.log',
        'logins' => $base . '/logins.log',
    ];
}

function coffeebrk_logger_ensure_paths() : void {
    $d = coffeebrk_logger_dirs();
    if ( ! file_exists( $d['base'] ) ) {
        wp_mkdir_p( $d['base'] );
    }
    foreach ( ['error','logins'] as $k ) {
        $f = $d[$k];
        if ( ! file_exists( $f ) ) {
            touch( $f );
        }
    }
}

function coffeebrk_log_write( string $file, array $entry ) : void {
    coffeebrk_logger_ensure_paths();
    $line = wp_json_encode( $entry ) . "\n";
    $result = file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
    
    if ( $result === false ) {
        // Log to WordPress error log if file write fails
        error_log( 'Coffeebrk logger failed to write to ' . $file );
    }
}

function coffeebrk_log_error( string $message, array $context = [] ) : void {
    $d = coffeebrk_logger_dirs();
    coffeebrk_log_write( $d['error'], [
        'ts' => current_time( 'mysql' ),
        'type' => 'error',
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ] );
}

function coffeebrk_log_login( WP_User $user ) : void {
    $d = coffeebrk_logger_dirs();
    coffeebrk_log_write( $d['logins'], [
        'ts' => current_time( 'mysql' ),
        'user_id' => $user->ID,
        'user_login' => $user->user_login,
        'user_email' => $user->user_email,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ] );
}

add_action( 'wp_login', function( $user_login, $user ) {
    if ( $user instanceof WP_User ) {
        coffeebrk_log_login( $user );
    }
}, 10, 2 );
