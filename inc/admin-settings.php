<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Register settings used by the Auth page
add_action('admin_init', function() {
    register_setting('coffeebrk_auth_options', 'coffeebrk_core_settings');
    add_settings_section('coffeebrk_auth_section','Supabase / Auth Settings',function(){echo'<p>Configure Supabase credentials.</p>';},'coffeebrk_auth_options');
    foreach([
        'supabase_url'=>'Supabase URL',
        'supabase_anon_key'=>'Supabase Anon Key',
        'supabase_service_role'=>'Supabase Service Role Key (server-only)',
        'google_client_id' => 'Google Client ID',
        'google_client_secret' => 'Google Client Secret'
    ] as $key=>$label){
        add_settings_field($key,$label,function()use($key){$opt=get_option('coffeebrk_core_settings',[]);$type=$key==='supabase_service_role'?'password':'text';printf('<input type="%s" name="coffeebrk_core_settings[%s]" value="%s" style="width:100%%;">',$type,$key,esc_attr($opt[$key]??''));},'coffeebrk_auth_options','coffeebrk_auth_section');
    }
});

// Top-level admin menu: Coffeebrk Core
add_action('admin_menu', function() {
    add_menu_page(
        'Coffeebrk Core',
        'Coffeebrk Core',
        'manage_options',
        'coffeebrk-core',
        'coffeebrk_core_dashboard_page',
        'dashicons-coffee',
        58
    );

    // Dashboard (default)
    add_submenu_page(
        'coffeebrk-core',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'coffeebrk-core',
        'coffeebrk_core_dashboard_page'
    );

    // Auth settings
    add_submenu_page(
        'coffeebrk-core',
        'Auth',
        'Auth',
        'manage_options',
        'coffeebrk-core-auth',
        'coffeebrk_auth_settings_page'
    );

    // Logs page
    add_submenu_page(
        'coffeebrk-core',
        'Logs',
        'Logs',
        'manage_options',
        'coffeebrk-core-logs',
        'coffeebrk_core_logs_page'
    );
});

function coffeebrk_core_dashboard_page(){
    if(!current_user_can('manage_options'))return;
    echo '<div class="wrap">';
    echo '<h1>Coffeebrk Core</h1>';
    echo '<p>Welcome to Coffeebrk Core. Use the submenus for Auth settings and Logs.</p>';
    echo '<ul style="list-style:disc;margin-left:20px;">';
    echo '<li><a href="' . admin_url('admin.php?page=coffeebrk-core-auth') . '">Auth Settings</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=coffeebrk-core-logs') . '">View Logs</a></li>';
    echo '</ul>';
    echo '</div>';
}

function coffeebrk_auth_settings_page(){
    if(!current_user_can('manage_options'))return;
    echo '<div class="wrap"><h1>Coffeebrk Auth Settings</h1><form method="post" action="options.php">';
    settings_fields('coffeebrk_auth_options');
    do_settings_sections('coffeebrk_auth_options');
    submit_button();
    echo '</form></div>';
}

function coffeebrk_core_logs_page(){
    if(!current_user_can('manage_options'))return;
    if ( ! function_exists('coffeebrk_logger_dirs') ) {
        echo '<div class="wrap"><h1>Logs</h1><p>Logger not available.</p></div>';
        return;
    }
    $d = coffeebrk_logger_dirs();
    $login_file = $d['logins'];
    $error_file = $d['error'];
    $login_rows = function_exists('coffeebrk_tail_file_json') ? coffeebrk_tail_file_json($login_file, 50) : coffeebrk_core_tail_file_json_local($login_file, 50);
    $error_rows = function_exists('coffeebrk_tail_file_json') ? coffeebrk_tail_file_json($error_file, 100) : coffeebrk_core_tail_file_json_local($error_file, 100);
    echo '<div class="wrap"><h1>Logs</h1>';
    echo '<h2>User Logins</h2>';
    coffeebrk_core_render_table($login_rows, ['ts'=>'Time','user_login'=>'User','user_email'=>'Email','ip'=>'IP','ua'=>'User Agent']);
    echo '<h2 style="margin-top:30px;">Errors</h2>';
    coffeebrk_core_render_table($error_rows, ['ts'=>'Time','type'=>'Type','message'=>'Message','ip'=>'IP','ua'=>'User Agent']);
    echo '</div>';
}

function coffeebrk_core_render_table(array $rows, array $columns){
    if (empty($rows)) { echo '<p>No entries.</p>'; return; }
    echo '<table class="widefat fixed striped"><thead><tr>';
    foreach($columns as $k=>$label){ echo '<th>'.esc_html($label).'</th>'; }
    echo '</tr></thead><tbody>';
    foreach($rows as $r){
        echo '<tr>';
        foreach($columns as $k=>$label){ $val = isset($r[$k]) ? (is_scalar($r[$k])?$r[$k]:wp_json_encode($r[$k])) : ''; echo '<td>'.esc_html($val).'</td>'; }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

// Local fallback tail function (if dashboard widget helper not loaded yet)
function coffeebrk_core_tail_file_json_local( string $file, int $limit = 50 ) : array {
    $result = [];
    if ( ! $file || ! file_exists($file) || ! is_readable($file) ) return $result;
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
            if ( $buffer !== '' ) { $lines[] = strrev( $buffer ); $buffer = ''; }
        } else { $buffer .= $char; }
        $pos--;
    }
    fclose( $fp );
    if ( $buffer !== '' ) { $lines[] = strrev( $buffer ); }
    $lines = array_slice( $lines, 0, $limit );
    foreach ( $lines as $line ) { $line = trim( $line ); if ( $line === '' ) continue; $decoded = json_decode( $line, true ); if ( is_array( $decoded ) ) { $result[] = $decoded; } }
    return array_reverse( $result );
}
