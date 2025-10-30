<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_dashboard_setup', function() {
    wp_add_dashboard_widget( 'coffeebrk_login_widget', 'Coffeebrk: Recent User Logins', 'coffeebrk_render_login_widget' );
});

function coffeebrk_render_login_widget() {
    if ( ! function_exists( 'coffeebrk_logger_dirs' ) ) {
        echo '<p>Logging not available.</p>';
        return;
    }
    $d = coffeebrk_logger_dirs();
    $file = $d['logins'];
    if ( ! file_exists( $file ) ) {
        echo '<p>No login logs yet.</p>';
        return;
    }
    $lines = coffeebrk_tail_file_json( $file, 20 );
    if ( empty( $lines ) ) {
        echo '<p>No login logs yet.</p>';
        return;
    }
    echo '<div class="coffeebrk-login-logs"><table class="widefat fixed striped"><thead><tr><th>Time</th><th>User</th><th>Email</th><th>IP</th><th>User Agent</th></tr></thead><tbody>';
    foreach ( $lines as $row ) {
        $ts = esc_html( $row['ts'] ?? '' );
        $user_login = esc_html( $row['user_login'] ?? '' );
        $user_email = esc_html( $row['user_email'] ?? '' );
        $ip = esc_html( $row['ip'] ?? '' );
        $ua = esc_html( $row['ua'] ?? '' );
        echo '<tr>';
        echo '<td>' . $ts . '</td>';
        echo '<td>' . $user_login . '</td>';
        echo '<td>' . $user_email . '</td>';
        echo '<td>' . $ip . '</td>';
        echo '<td style="max-width:300px; overflow:auto;">' . $ua . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function coffeebrk_tail_file_json( string $file, int $limit = 20 ) : array {
    $result = [];
    if ( ! is_readable( $file ) ) return $result;
    $fp = fopen( $file, 'r' );
    if ( ! $fp ) return $result;
    $buffer = '';
    $pos = -1;
    $lines = [];
    fseek( $fp, 0, SEEK_END );
    $filesize = ftell( $fp );
    while ( count( $lines ) <= $limit && -$pos <= $filesize ) {
        fseek( $fp, $pos, SEEK_END );
        $char = fgetc( $fp );
        if ( $char === "\n" ) {
            if ( $buffer !== '' ) {
                $lines[] = strrev( $buffer );
                $buffer = '';
            }
        } else {
            $buffer .= $char;
        }
        $pos--;
    }
    fclose( $fp );
    if ( $buffer !== '' ) {
        $lines[] = strrev( $buffer );
    }
    $lines = array_slice( $lines, 0, $limit );
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        $decoded = json_decode( $line, true );
        if ( is_array( $decoded ) ) {
            $result[] = $decoded;
        }
    }
    return array_reverse( $result );
}
