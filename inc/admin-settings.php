<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Register settings used by the Auth page
add_action('admin_init', function() {
    register_setting('coffeebrk_auth_options', 'coffeebrk_core_settings');
    add_settings_section('coffeebrk_auth_section','Supabase / Auth Settings',function(){echo'<p>Configure Supabase credentials. Do not expose Service Role on frontend. Supabase OAuth runs on client; server only validates tokens.</p>';},'coffeebrk_auth_options');
    foreach([
        'supabase_url'=>'Supabase URL',
        'supabase_anon_key'=>'Supabase Anon Key',
        'supabase_service_role'=>'Supabase Service Role Key (server-only)',
        'google_client_id' => 'Google Client ID',
        'google_client_secret' => 'Google Client Secret',
        'allowed_origins' => 'Allowed Origins (one per line)'
    ] as $key=>$label){
        add_settings_field($key,$label,function()use($key){$opt=get_option('coffeebrk_core_settings',[]);if($key==='allowed_origins'){printf('<textarea name="coffeebrk_core_settings[%s]" rows="3" style="width:100%%;" placeholder="https://wp.coffeebrk.ai\nhttps://app.coffeebrk.ai">%s</textarea>',$key,esc_textarea($opt[$key]??''));}else{$type=$key==='supabase_service_role'?'password':'text';printf('<input type="%s" name="coffeebrk_core_settings[%s]" value="%s" style="width:100%%;">',$type,$key,esc_attr($opt[$key]??''));}},'coffeebrk_auth_options','coffeebrk_auth_section');
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
    $auth = admin_url('admin.php?page=coffeebrk-core-auth');
    $dyn  = admin_url('admin.php?page=coffeebrk-core-dynfields');
    $asp  = admin_url('admin.php?page=coffeebrk-core-aspires');
    $logs = admin_url('admin.php?page=coffeebrk-core-logs');
    $rss  = admin_url('admin.php?page=coffeebrk-core-rss');
    $json = admin_url('admin.php?page=coffeebrk-core-json-importer');

    $core_settings = (array) get_option( 'coffeebrk_core_settings', [] );
    $supabase_url_set = ! empty( $core_settings['supabase_url'] );

    $rss_total = null;
    $rss_enabled = null;
    if ( function_exists( 'coffeebrk_rss_table_name' ) ) {
        global $wpdb;
        $tbl = coffeebrk_rss_table_name();
        if ( is_string( $tbl ) && $tbl !== '' ) {
            $rss_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl}" );
            $rss_enabled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE enabled = 1" );
        }
    }

    $rss_next = wp_next_scheduled( 'coffeebrk_rss_import_all' );
    $rss_next_str = $rss_next ? wp_date( 'Y-m-d H:i:s', (int) $rss_next ) : 'Not scheduled';

    $rss_log_count = 0;
    $rss_recent = [];
    if ( function_exists( 'coffeebrk_rss_log_option_key' ) ) {
        $tmp = get_option( coffeebrk_rss_log_option_key(), [] );
        if ( is_array( $tmp ) ) {
            $rss_log_count = count( $tmp );
            $rss_recent = array_slice( array_reverse( $tmp ), 0, 8 );
        }
    }

    $json_log_count = 0;
    $json_log_key = 'cbk_json_articles_importer_log_history';
    $json_tmp = get_option( $json_log_key, [] );
    if ( is_array( $json_tmp ) ) {
        $json_log_count = count( $json_tmp );
    }
    echo '<div class="wrap cbk-admin">';
    echo '<h1 style="margin-bottom:10px;">Coffeebrk Core</h1>';
    echo '<p style="max-width:760px;color:#555;">Auth + Onboarding via Supabase, Dynamic Post Meta, Aspire mapping, Elementor dynamic tags, and a simple personalized feed endpoint.</p>';
    echo '<div class="cbk-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-top:20px;">';
    echo '<div class="cbk-card" style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:16px;"><h2 style="margin:0 0 10px;">Quick Links</h2><div style="display:flex;flex-direction:column;gap:8px;">'
        .'<a class="button button-primary" href="'.$auth.'">Auth Settings</a>'
        .'<a class="button" href="'.$dyn.'">Dynamic Fields</a>'
        .'<a class="button" href="'.$asp.'">Aspire Manager</a>'
        .'<a class="button" href="'.$logs.'">View Logs</a>'
        .'<a class="button" href="'.$rss.'">RSS Aggregator</a>'
        .'<a class="button" href="'.$json.'">JSON Articles Importer</a>'
        .'</div></div>';
    echo '<div class="cbk-card" style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:16px;"><h2 style="margin:0 0 10px;">Status</h2>'
        .'<ul style="margin:0;padding-left:18px;">'
        .'<li>Plugin version: '.esc_html( get_file_data( COFFEEBRK_CORE_PATH.'coffeebrk-core.php', ['Version'=>'Version'] )['Version'] ?? '' ).'</li>'
        .'<li>Supabase URL set: '.( $supabase_url_set ? 'Yes' : 'No').'</li>'
        .'<li>Dynamic fields: '.count((array)get_option('coffeebrk_dynamic_fields',[])).'</li>'
        .'<li>Aspire options: '.count((array)get_option('coffeebrk_aspires',[])).'</li>'
        .'</ul></div>';
    echo '<div class="cbk-card" style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:16px;"><h2 style="margin:0 0 10px;">RSS Summary</h2>'
        .'<ul style="margin:0;padding-left:18px;">'
        .'<li>Feeds total: '.esc_html( $rss_total === null ? '—' : (string) $rss_total ).'</li>'
        .'<li>Feeds enabled: '.esc_html( $rss_enabled === null ? '—' : (string) $rss_enabled ).'</li>'
        .'<li>Next RSS auto-run: '.esc_html( $rss_next_str ).'</li>'
        .'<li>RSS log entries (24h retention): '.esc_html( (string) $rss_log_count ).'</li>'
        .'</ul>'
        .'<p style="margin:10px 0 0;"><a class="button" href="'.$rss.'">Open RSS Aggregator</a></p>'
        .'</div>';
    echo '</div></div>';

    echo '<h2 style="margin-top:26px;">Recent Activity (last 24h)</h2>';
    if ( ! empty( $rss_recent ) ) {
        echo '<table class="widefat striped"><thead><tr><th style="width:170px;">Time</th><th style="width:80px;">Context</th><th style="width:120px;">Event</th><th>Feed</th><th style="width:80px;">Status</th><th style="width:90px;">Post</th><th>Details</th></tr></thead><tbody>';
        foreach ( $rss_recent as $row ) {
            if ( ! is_array( $row ) ) continue;
            $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
            $time_str = $t > 0 ? wp_date( 'Y-m-d H:i:s', $t ) : '';
            $context = isset( $row['context'] ) ? (string) $row['context'] : '';
            $event = isset( $row['event'] ) ? (string) $row['event'] : '';
            $feed_name = isset( $row['feed_name'] ) ? (string) $row['feed_name'] : '';
            $status = isset( $row['status'] ) ? (string) $row['status'] : '';
            $post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
            $title = isset( $row['title'] ) ? (string) $row['title'] : '';
            $message = isset( $row['message'] ) ? (string) $row['message'] : '';
            $reason = isset( $row['reason'] ) ? (string) $row['reason'] : '';
            $details = $title !== '' ? $title : $message;
            if ( $reason !== '' ) {
                $details = ( $details !== '' ? $details . ' | ' : '' ) . 'Reason: ' . $reason;
            }

            echo '<tr>';
            echo '<td>' . esc_html( $time_str ) . '</td>';
            echo '<td>' . esc_html( $context ) . '</td>';
            echo '<td>' . esc_html( $event ) . '</td>';
            echo '<td>' . esc_html( $feed_name ) . '</td>';
            echo '<td>' . esc_html( $status ) . '</td>';
            echo '<td>' . esc_html( $post_id > 0 ? (string) $post_id : '—' ) . '</td>';
            echo '<td>' . esc_html( $details ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="color:#555;">No recent RSS activity recorded yet.</p>';
    }

    echo '<h2 style="margin-top:26px;">Plugin Summary</h2>';
    echo '<table class="widefat striped"><tbody>';
    echo '<tr><th style="width:260px;">JSON Importer log entries</th><td>' . esc_html( (string) $json_log_count ) . ' (stored in option <code>' . esc_html( $json_log_key ) . '</code>)</td></tr>';
    echo '<tr><th>RSS log option</th><td><code>' . esc_html( function_exists( 'coffeebrk_rss_log_option_key' ) ? coffeebrk_rss_log_option_key() : 'coffeebrk_rss_import_log' ) . '</code> (24 hour retention)</td></tr>';
    echo '</tbody></table>';

    echo '<h2 style="margin-top:26px;">Endpoints & Access</h2>';
    echo '<p style="max-width:980px;color:#555;">This section lists admin pages, actions, AJAX, REST endpoints, and cron hooks exposed by this plugin, plus required access details.</p>';
    echo '<table class="widefat striped"><thead><tr><th style="width:220px;">Type</th><th>Endpoint</th><th style="width:260px;">Access / Notes</th></tr></thead><tbody>';
    echo '<tr><td>Admin Page</td><td><code>admin.php?page=coffeebrk-core</code></td><td>Capability: <code>manage_options</code></td></tr>';
    echo '<tr><td>Admin Page</td><td><code>admin.php?page=coffeebrk-core-auth</code></td><td>Capability: <code>manage_options</code></td></tr>';
    echo '<tr><td>Admin Page</td><td><code>admin.php?page=coffeebrk-core-dynfields</code></td><td>Capability: <code>manage_options</code></td></tr>';
    echo '<tr><td>Admin Page</td><td><code>admin.php?page=coffeebrk-core-aspires</code></td><td>Capability: <code>manage_options</code></td></tr>';
    echo '<tr><td>Admin Page</td><td><code>admin.php?page=coffeebrk-core-logs</code></td><td>Capability: <code>manage_options</code></td></tr>';
    echo '<tr><td>Admin Page</td><td><code>admin.php?page=coffeebrk-core-rss</code></td><td>Capability: <code>manage_options</code>, actions via <code>admin-post.php</code> + nonces</td></tr>';
    echo '<tr><td>Admin Page</td><td><code>admin.php?page=coffeebrk-core-json-importer</code></td><td>Capability: <code>manage_options</code>, AJAX actions + nonce</td></tr>';
    echo '<tr><td>Admin Action</td><td><code>admin-post.php?action=coffeebrk_rss_save_feed</code></td><td>Capability: <code>manage_options</code>, nonce: <code>coffeebrk_rss_save_feed</code></td></tr>';
    echo '<tr><td>Admin Action</td><td><code>admin-post.php?action=coffeebrk_rss_delete_feed&amp;feed_id=&lt;id&gt;</code></td><td>Capability: <code>manage_options</code>, nonce: <code>coffeebrk_rss_delete_&lt;id&gt;</code></td></tr>';
    echo '<tr><td>Admin Action</td><td><code>admin-post.php?action=coffeebrk_rss_toggle_feed&amp;feed_id=&lt;id&gt;</code></td><td>Capability: <code>manage_options</code>, nonce: <code>coffeebrk_rss_toggle_&lt;id&gt;</code></td></tr>';
    echo '<tr><td>Admin Action</td><td><code>admin-post.php?action=coffeebrk_rss_run_feed&amp;feed_id=&lt;id&gt;</code></td><td>Capability: <code>manage_options</code>, nonce: <code>coffeebrk_rss_run_&lt;id&gt;</code></td></tr>';
    echo '<tr><td>Admin Action</td><td><code>admin-post.php?action=coffeebrk_rss_run_all</code></td><td>Capability: <code>manage_options</code>, nonce: <code>coffeebrk_rss_run_all</code></td></tr>';
    echo '<tr><td>AJAX</td><td><code>wp-admin/admin-ajax.php?action=cbk_json_articles_import_start</code></td><td>Logged-in admin, nonce: <code>cbk_json_articles_import</code></td></tr>';
    echo '<tr><td>AJAX</td><td><code>wp-admin/admin-ajax.php?action=cbk_json_articles_import_process</code></td><td>Logged-in admin, nonce: <code>cbk_json_articles_import</code></td></tr>';
    echo '<tr><td>REST</td><td><code>GET /wp-json/coffeebrk/v1/feed</code></td><td>Requires logged-in user (<code>is_user_logged_in()</code>)</td></tr>';
    echo '<tr><td>REST</td><td><code>POST /wp-json/coffeebrk/v1/supabase/login</code></td><td>Public endpoint; validates Supabase token server-side</td></tr>';
    echo '<tr><td>Cron Hook</td><td><code>coffeebrk_rss_import_all</code></td><td>Schedule: hourly; drafts posts from enabled feeds</td></tr>';
    echo '</tbody></table>';
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
