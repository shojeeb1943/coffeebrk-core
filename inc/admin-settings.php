<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Register settings used by the Auth page
add_action('admin_init', function() {
    register_setting('coffeebrk_auth_options', 'coffeebrk_core_settings');

    // General Auth Settings Section
    add_settings_section('coffeebrk_general_section', 'General Auth Settings', function(){
        echo '<p>Configure general authentication settings.</p>';
    }, 'coffeebrk_auth_options');

    // Auth Provider Selection
    add_settings_field('auth_provider', 'Auth Provider', function(){
        $opt = get_option('coffeebrk_core_settings', []);
        $provider = $opt['auth_provider'] ?? 'supabase';
        ?>
        <select name="coffeebrk_core_settings[auth_provider]" id="coffeebrk_auth_provider">
            <option value="supabase" <?php selected($provider, 'supabase'); ?>>Supabase</option>
            <option value="firebase" <?php selected($provider, 'firebase'); ?>>Firebase</option>
        </select>
        <p class="description">Select the authentication provider to use for Google sign-in.</p>
        <?php
    }, 'coffeebrk_auth_options', 'coffeebrk_general_section');

    // App URL
    add_settings_field('app_url', 'App URL (redirect after onboarding)', function(){
        $opt = get_option('coffeebrk_core_settings', []);
        printf('<input type="url" name="coffeebrk_core_settings[app_url]" value="%s" style="width:100%%;" placeholder="https://app.coffeebrk.ai">', esc_attr($opt['app_url'] ?? ''));
        echo '<p class="description">Users will be redirected here after completing onboarding. Defaults to site home if empty.</p>';
    }, 'coffeebrk_auth_options', 'coffeebrk_general_section');

    // Allowed Origins
    add_settings_field('allowed_origins', 'Allowed Origins (one per line)', function(){
        $opt = get_option('coffeebrk_core_settings', []);
        printf('<textarea name="coffeebrk_core_settings[allowed_origins]" rows="3" style="width:100%%;" placeholder="https://wp.coffeebrk.ai\nhttps://app.coffeebrk.ai">%s</textarea>', esc_textarea($opt['allowed_origins'] ?? ''));
    }, 'coffeebrk_auth_options', 'coffeebrk_general_section');

    // Supabase Settings Section
    add_settings_section('coffeebrk_supabase_section', 'Supabase Settings', function(){
        echo '<p>Configure Supabase credentials. Only needed if using Supabase as auth provider.</p>';
    }, 'coffeebrk_auth_options');

    foreach(['supabase_url' => 'Supabase URL', 'supabase_anon_key' => 'Supabase Anon Key', 'supabase_service_role' => 'Supabase Service Role Key (server-only)'] as $key => $label) {
        add_settings_field($key, $label, function() use ($key) {
            $opt = get_option('coffeebrk_core_settings', []);
            $type = $key === 'supabase_service_role' ? 'password' : 'text';
            printf('<input type="%s" name="coffeebrk_core_settings[%s]" value="%s" style="width:100%%;">', $type, $key, esc_attr($opt[$key] ?? ''));
        }, 'coffeebrk_auth_options', 'coffeebrk_supabase_section');
    }

    // Firebase Settings Section
    add_settings_section('coffeebrk_firebase_section', 'Firebase Settings', function(){
        echo '<p>Configure Firebase credentials. Only needed if using Firebase as auth provider.</p>';
    }, 'coffeebrk_auth_options');

    foreach([
        'firebase_api_key' => 'Firebase API Key',
        'firebase_auth_domain' => 'Firebase Auth Domain',
        'firebase_project_id' => 'Firebase Project ID',
        'firebase_storage_bucket' => 'Firebase Storage Bucket',
        'firebase_messaging_sender_id' => 'Firebase Messaging Sender ID',
        'firebase_app_id' => 'Firebase App ID',
        'firebase_measurement_id' => 'Firebase Measurement ID (optional)'
    ] as $key => $label) {
        add_settings_field($key, $label, function() use ($key) {
            $opt = get_option('coffeebrk_core_settings', []);
            $placeholder = '';
            if ($key === 'firebase_auth_domain') $placeholder = 'your-app.firebaseapp.com';
            if ($key === 'firebase_storage_bucket') $placeholder = 'your-app.firebasestorage.app';
            printf('<input type="text" name="coffeebrk_core_settings[%s]" value="%s" style="width:100%%;" placeholder="%s">', $key, esc_attr($opt[$key] ?? ''), esc_attr($placeholder));
        }, 'coffeebrk_auth_options', 'coffeebrk_firebase_section');
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

    add_submenu_page(
        'coffeebrk-core',
        'API',
        'API',
        'manage_options',
        'coffeebrk-core-api',
        'coffeebrk_core_api_page'
    );
});

// Ensure Dashboard is always the first submenu item
// (CPT submenus like "All Stories" get auto-inserted before manual items)
add_action( 'admin_menu', function() {
    global $submenu;
    if ( empty( $submenu['coffeebrk-core'] ) ) return;
    $dashboard = null;
    $rest = [];
    foreach ( $submenu['coffeebrk-core'] as $item ) {
        if ( isset( $item[2] ) && $item[2] === 'coffeebrk-core' ) {
            $dashboard = $item;
        } else {
            $rest[] = $item;
        }
    }
    if ( $dashboard ) {
        $submenu['coffeebrk-core'] = array_values( array_merge( [ $dashboard ], $rest ) );
    }
}, 999 );

/* ---- Enqueue dashboard CSS ---- */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'toplevel_page_coffeebrk-core' ) return;
    wp_enqueue_style(
        'coffeebrk-admin-dashboard',
        COFFEEBRK_CORE_URL . 'assets/css/coffeebrk-admin-dashboard.css',
        [],
        '2.1.0'
    );
});

function coffeebrk_core_dashboard_page(){
    if ( ! current_user_can( 'manage_options' ) ) return;

    // ---- Data gathering ----
    $version = get_file_data( COFFEEBRK_CORE_PATH . 'coffeebrk-core.php', [ 'Version' => 'Version' ] )['Version'] ?? '';
    $core_settings   = (array) get_option( 'coffeebrk_core_settings', [] );
    $supabase_url_set = ! empty( $core_settings['supabase_url'] );
    $dyn_fields = (array) get_option( 'coffeebrk_dynamic_fields', [] );
    $aspires    = (array) get_option( 'coffeebrk_aspires', [] );

    $post_count = (int) wp_count_posts()->publish;

    $rss_total = null; $rss_enabled = null;
    if ( function_exists( 'coffeebrk_rss_table_name' ) ) {
        global $wpdb;
        $tbl = coffeebrk_rss_table_name();
        if ( is_string( $tbl ) && $tbl !== '' ) {
            $rss_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl}" );
            $rss_enabled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE enabled = 1" );
        }
    }

    $rss_next     = wp_next_scheduled( 'coffeebrk_rss_import_all' );
    $rss_next_str = $rss_next ? wp_date( 'Y-m-d H:i:s', (int) $rss_next ) : 'Not scheduled';

    $rss_log_count = 0; $rss_recent = [];
    if ( function_exists( 'coffeebrk_rss_log_option_key' ) ) {
        $tmp = get_option( coffeebrk_rss_log_option_key(), [] );
        if ( is_array( $tmp ) ) {
            $rss_log_count = count( $tmp );
            $rss_recent    = array_slice( array_reverse( $tmp ), 0, 8 );
        }
    }

    $json_log_key   = 'cbk_json_articles_importer_log_history';
    $json_log_count = 0;
    $json_tmp       = get_option( $json_log_key, [] );
    if ( is_array( $json_tmp ) ) { $json_log_count = count( $json_tmp ); }

    $token_hash = (string) get_option( 'coffeebrk_core_api_token_hash', '' );
    $has_token  = $token_hash !== '';

    // ---- Navigation links ----
    $nav = [
        [ 'url' => admin_url('admin.php?page=coffeebrk-core-auth'),          'icon' => 'dashicons-shield',          'label' => 'Auth Settings' ],
        [ 'url' => admin_url('admin.php?page=coffeebrk-core-dynfields'),     'icon' => 'dashicons-forms',           'label' => 'Dynamic Fields' ],
        [ 'url' => admin_url('admin.php?page=coffeebrk-core-aspires'),       'icon' => 'dashicons-star-filled',     'label' => 'Aspire Manager' ],
        [ 'url' => admin_url('admin.php?page=coffeebrk-core-rss'),           'icon' => 'dashicons-rss',             'label' => 'RSS Aggregator' ],
        [ 'url' => admin_url('admin.php?page=coffeebrk-core-json-importer'), 'icon' => 'dashicons-upload',          'label' => 'JSON Importer' ],
        [ 'url' => admin_url('admin.php?page=coffeebrk-core-api'),           'icon' => 'dashicons-rest-api',        'label' => 'API' ],
        [ 'url' => admin_url('admin.php?page=coffeebrk-core-logs'),          'icon' => 'dashicons-list-view',       'label' => 'Logs' ],
        [ 'url' => admin_url('edit.php?post_type=cbk_story'),                'icon' => 'dashicons-format-video',    'label' => 'Stories' ],
    ];

    // ---- Render ----
    echo '<div class="wrap cbk-dash">';

    // ======== HEADER ========
    echo '<div class="cbk-header">';
    echo '  <div class="cbk-header-left">';
    echo '    <div class="cbk-header-logo"><span class="dashicons dashicons-coffee" style="color:#fff;font-size:28px;width:28px;height:28px;"></span></div>';
    echo '    <div>';
    echo '      <h1 class="cbk-header-title">Coffeebrk Core</h1>';
    echo '      <p class="cbk-header-sub">Auth · Dynamic Meta · RSS · API · Elementor Tags</p>';
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="cbk-header-right">';
    echo '    <span class="cbk-badge cbk-badge--version"><span class="dashicons dashicons-tag" style="font-size:14px;width:14px;height:14px;"></span> v' . esc_html( $version ) . '</span>';
    if ( $supabase_url_set ) {
        echo '  <span class="cbk-badge cbk-badge--ok"><span class="dashicons dashicons-yes-alt" style="font-size:14px;width:14px;height:14px;"></span> Connected</span>';
    }
    echo '  </div>';
    echo '</div>';

    // ======== STAT CARDS ========
    echo '<div class="cbk-stats">';

    echo '<div class="cbk-stat">';
    echo '  <div class="cbk-stat__icon"><span class="dashicons dashicons-admin-post"></span></div>';
    echo '  <div class="cbk-stat__body">';
    echo '    <p class="cbk-stat__label">Published Posts</p>';
    echo '    <p class="cbk-stat__value">' . esc_html( (string) $post_count ) . '</p>';
    echo '  </div>';
    echo '</div>';

    echo '<div class="cbk-stat">';
    echo '  <div class="cbk-stat__icon"><span class="dashicons dashicons-rss"></span></div>';
    echo '  <div class="cbk-stat__body">';
    echo '    <p class="cbk-stat__label">RSS Feeds</p>';
    echo '    <p class="cbk-stat__value">' . esc_html( $rss_total === null ? '—' : (string) $rss_total ) . '</p>';
    echo '    <p class="cbk-stat__sub">' . esc_html( $rss_enabled === null ? '' : $rss_enabled . ' enabled' ) . '</p>';
    echo '  </div>';
    echo '</div>';

    echo '<div class="cbk-stat">';
    echo '  <div class="cbk-stat__icon"><span class="dashicons dashicons-forms"></span></div>';
    echo '  <div class="cbk-stat__body">';
    echo '    <p class="cbk-stat__label">Dynamic Fields</p>';
    echo '    <p class="cbk-stat__value">' . esc_html( (string) count( $dyn_fields ) ) . '</p>';
    echo '  </div>';
    echo '</div>';

    echo '<div class="cbk-stat">';
    echo '  <div class="cbk-stat__icon"><span class="dashicons dashicons-star-filled"></span></div>';
    echo '  <div class="cbk-stat__body">';
    echo '    <p class="cbk-stat__label">Aspire Options</p>';
    echo '    <p class="cbk-stat__value">' . esc_html( (string) count( $aspires ) ) . '</p>';
    echo '  </div>';
    echo '</div>';

    echo '</div>'; // .cbk-stats

    // ======== TWO-COLUMN LAYOUT ========
    echo '<div class="cbk-columns">';

    // ---- Left: Navigation Grid ----
    echo '<div class="cbk-panel">';
    echo '  <div class="cbk-panel__head"><span class="dashicons dashicons-screenoptions" style="color:var(--cbk-brand);"></span><h2 class="cbk-panel__title">Quick Navigation</h2></div>';
    echo '  <div class="cbk-panel__body"><div class="cbk-nav-grid">';
    foreach ( $nav as $n ) {
        echo '<a href="' . esc_url( $n['url'] ) . '" class="cbk-nav-card">';
        echo '  <span class="dashicons ' . esc_attr( $n['icon'] ) . '"></span>';
        echo '  <span class="cbk-nav-card__label">' . esc_html( $n['label'] ) . '</span>';
        echo '</a>';
    }
    echo '  </div></div>';
    echo '</div>';

    // ---- Right: System Health ----
    echo '<div class="cbk-panel">';
    echo '  <div class="cbk-panel__head"><span class="dashicons dashicons-heart" style="color:var(--cbk-danger);"></span><h2 class="cbk-panel__title">System Health</h2></div>';
    echo '  <div class="cbk-panel__body"><ul class="cbk-health-list">';

    // Supabase
    $supa_pill = $supabase_url_set ? 'cbk-pill--green' : 'cbk-pill--red';
    $supa_text = $supabase_url_set ? 'Connected' : 'Not Set';
    echo '<li class="cbk-health-item">';
    echo '  <div class="cbk-health-item__left">';
    echo '    <div class="cbk-health-item__icon cbk-health-item__icon--blue"><span class="dashicons dashicons-cloud"></span></div>';
    echo '    <div><div class="cbk-health-item__label">Supabase</div><div class="cbk-health-item__detail">' . esc_html( $supabase_url_set ? $core_settings['supabase_url'] : 'Configure in Auth Settings' ) . '</div></div>';
    echo '  </div>';
    echo '  <span class="cbk-pill ' . $supa_pill . '">' . $supa_text . '</span>';
    echo '</li>';

    // RSS Cron
    $cron_ok   = (bool) $rss_next;
    $cron_pill = $cron_ok ? 'cbk-pill--green' : 'cbk-pill--amber';
    $cron_text = $cron_ok ? 'Scheduled' : 'Not Scheduled';
    echo '<li class="cbk-health-item">';
    echo '  <div class="cbk-health-item__left">';
    echo '    <div class="cbk-health-item__icon cbk-health-item__icon--green"><span class="dashicons dashicons-clock"></span></div>';
    echo '    <div><div class="cbk-health-item__label">RSS Cron</div><div class="cbk-health-item__detail">Next: ' . esc_html( $rss_next_str ) . '</div></div>';
    echo '  </div>';
    echo '  <span class="cbk-pill ' . $cron_pill . '">' . $cron_text . '</span>';
    echo '</li>';

    // API Token
    $tok_pill = $has_token ? 'cbk-pill--green' : 'cbk-pill--gray';
    $tok_text = $has_token ? 'Active' : 'None';
    echo '<li class="cbk-health-item">';
    echo '  <div class="cbk-health-item__left">';
    echo '    <div class="cbk-health-item__icon cbk-health-item__icon--amber"><span class="dashicons dashicons-admin-network"></span></div>';
    echo '    <div><div class="cbk-health-item__label">API Token</div><div class="cbk-health-item__detail">' . ( $has_token ? 'Token is configured' : 'Generate in API page' ) . '</div></div>';
    echo '  </div>';
    echo '  <span class="cbk-pill ' . $tok_pill . '">' . $tok_text . '</span>';
    echo '</li>';

    // RSS Activity
    echo '<li class="cbk-health-item">';
    echo '  <div class="cbk-health-item__left">';
    echo '    <div class="cbk-health-item__icon cbk-health-item__icon--green"><span class="dashicons dashicons-chart-area"></span></div>';
    echo '    <div><div class="cbk-health-item__label">RSS Activity</div><div class="cbk-health-item__detail">' . esc_html( (string) $rss_log_count ) . ' log entries (24h)</div></div>';
    echo '  </div>';
    echo '  <span class="cbk-pill cbk-pill--green">' . esc_html( (string) $rss_log_count ) . '</span>';
    echo '</li>';

    // JSON Importer
    echo '<li class="cbk-health-item">';
    echo '  <div class="cbk-health-item__left">';
    echo '    <div class="cbk-health-item__icon cbk-health-item__icon--blue"><span class="dashicons dashicons-media-code"></span></div>';
    echo '    <div><div class="cbk-health-item__label">JSON Importer</div><div class="cbk-health-item__detail">' . esc_html( (string) $json_log_count ) . ' import records</div></div>';
    echo '  </div>';
    echo '  <span class="cbk-pill cbk-pill--green">' . esc_html( (string) $json_log_count ) . '</span>';
    echo '</li>';

    echo '  </ul></div>';
    echo '</div>'; // panel

    echo '</div>'; // .cbk-columns

    // ======== RECENT ACTIVITY TABLE ========
    echo '<div class="cbk-panel" style="margin-bottom:24px;">';
    echo '  <div class="cbk-panel__head"><span class="dashicons dashicons-backup" style="color:var(--cbk-brand);"></span><h2 class="cbk-panel__title">Recent Activity (last 24h)</h2></div>';
    echo '  <div class="cbk-panel__body">';

    if ( ! empty( $rss_recent ) ) {
        echo '<div class="cbk-table-wrap"><table class="cbk-table"><thead><tr>';
        echo '<th style="width:170px;">Time</th><th style="width:80px;">Context</th><th style="width:120px;">Event</th><th>Feed</th><th style="width:80px;">Status</th><th style="width:80px;">Post</th><th>Details</th>';
        echo '</tr></thead><tbody>';

        foreach ( $rss_recent as $row ) {
            if ( ! is_array( $row ) ) continue;
            $t        = isset( $row['time'] ) ? (int) $row['time'] : 0;
            $time_str = $t > 0 ? wp_date( 'Y-m-d H:i:s', $t ) : '';
            $context  = (string) ( $row['context']   ?? '' );
            $event    = (string) ( $row['event']     ?? '' );
            $feed_nm  = (string) ( $row['feed_name'] ?? '' );
            $status   = (string) ( $row['status']    ?? '' );
            $post_id  = (int)    ( $row['post_id']   ?? 0 );
            $title    = (string) ( $row['title']     ?? '' );
            $message  = (string) ( $row['message']   ?? '' );
            $reason   = (string) ( $row['reason']    ?? '' );
            $details  = $title !== '' ? $title : $message;
            if ( $reason !== '' ) {
                $details = ( $details !== '' ? $details . ' — ' : '' ) . $reason;
            }

            // Status pill
            $s_class = 'cbk-pill--gray';
            if ( $status === 'ok' || $status === 'imported' ) $s_class = 'cbk-pill--green';
            elseif ( $status === 'skipped' )                  $s_class = 'cbk-pill--amber';
            elseif ( $status === 'error' || $status === 'failed' ) $s_class = 'cbk-pill--red';

            echo '<tr>';
            echo '<td><span class="cbk-time">' . esc_html( $time_str ) . '</span></td>';
            echo '<td><span class="cbk-context">' . esc_html( $context ) . '</span></td>';
            echo '<td><span class="cbk-event">' . esc_html( $event ) . '</span></td>';
            echo '<td>' . esc_html( $feed_nm ) . '</td>';
            echo '<td><span class="cbk-pill ' . $s_class . '">' . esc_html( $status ) . '</span></td>';
            echo '<td>' . ( $post_id > 0 ? '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">#' . $post_id . '</a>' : '—' ) . '</td>';
            echo '<td>' . esc_html( $details ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="cbk-empty"><span class="dashicons dashicons-format-aside"></span><p>No recent RSS activity recorded yet.</p></div>';
    }

    echo '  </div>';
    echo '</div>';

    // ======== INFO FOOTER ========
    echo '<div class="cbk-info-footer">';
    echo '  <span class="cbk-info-tag"><span class="dashicons dashicons-database" style="font-size:14px;width:14px;height:14px;"></span> JSON log: <code>' . esc_html( $json_log_key ) . '</code></span>';
    echo '  <span class="cbk-info-tag"><span class="dashicons dashicons-database" style="font-size:14px;width:14px;height:14px;"></span> RSS log: <code>' . esc_html( function_exists( 'coffeebrk_rss_log_option_key' ) ? coffeebrk_rss_log_option_key() : 'coffeebrk_rss_import_log' ) . '</code> (24h retention)</span>';
    echo '  <span class="cbk-info-tag"><span class="dashicons dashicons-admin-plugins" style="font-size:14px;width:14px;height:14px;"></span> Plugin: v' . esc_html( $version ) . '</span>';
    echo '</div>';

    echo '</div>'; // .cbk-dash
}

add_action( 'admin_post_coffeebrk_core_api_token_generate', function(){
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 403 );
    }
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'coffeebrk_core_api_token_generate' ) ) {
        wp_die( 'Invalid nonce', 400 );
    }

    $raw = random_bytes( 32 );
    $token = rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    $hash = password_hash( $token, PASSWORD_DEFAULT );

    update_option( 'coffeebrk_core_api_token_hash', $hash, false );
    update_option( 'coffeebrk_core_api_token_last4', substr( $token, -4 ), false );
    update_option( 'coffeebrk_core_api_token_updated', time(), false );
    update_option( 'coffeebrk_core_api_token_user_id', (int) get_current_user_id(), false );

    $uid = get_current_user_id();
    if ( $uid > 0 ) {
        set_transient( 'coffeebrk_core_api_token_plain_' . $uid, $token, 10 * MINUTE_IN_SECONDS );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=coffeebrk-core-api&msg=token_generated' ) );
    exit;
} );

add_action( 'admin_post_coffeebrk_core_api_token_revoke', function(){
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 403 );
    }
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'coffeebrk_core_api_token_revoke' ) ) {
        wp_die( 'Invalid nonce', 400 );
    }

    delete_option( 'coffeebrk_core_api_token_hash' );
    delete_option( 'coffeebrk_core_api_token_last4' );
    delete_option( 'coffeebrk_core_api_token_updated' );
    delete_option( 'coffeebrk_core_api_token_user_id' );

    $uid = get_current_user_id();
    if ( $uid > 0 ) {
        delete_transient( 'coffeebrk_core_api_token_plain_' . $uid );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=coffeebrk-core-api&msg=token_revoked' ) );
    exit;
} );

function coffeebrk_core_api_page(){
    if ( ! current_user_can( 'manage_options' ) ) return;

    $site = home_url( '/' );
    $rest_base = rest_url( 'coffeebrk/v1' );
    $rss_pretty = home_url( '/feed/coffeebrk/' );
    $rss_query = home_url( '/?feed=coffeebrk' );
    $nonce = wp_create_nonce( 'wp_rest' );

    $uid = get_current_user_id();
    $plain_token = $uid > 0 ? (string) get_transient( 'coffeebrk_core_api_token_plain_' . $uid ) : '';
    $token_hash = (string) get_option( 'coffeebrk_core_api_token_hash', '' );
    $token_last4 = (string) get_option( 'coffeebrk_core_api_token_last4', '' );
    $token_updated = (int) get_option( 'coffeebrk_core_api_token_updated', 0 );
    $token_user_id = (int) get_option( 'coffeebrk_core_api_token_user_id', 0 );

    $dyn = (array) get_option( 'coffeebrk_dynamic_fields', [] );
    $allowed_meta_keys = [];
    foreach ( $dyn as $f ) {
        if ( ! is_array( $f ) ) continue;
        $k = (string) ( $f['key'] ?? '' );
        if ( $k === '' ) continue;
        $allowed_meta_keys[] = $k;
    }
    $allowed_meta_keys = array_values( array_unique( $allowed_meta_keys ) );

    // Get current tab
    $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
    $tabs = [
        'overview' => 'Overview',
        'post-api' => 'POST API',
        'get-api'  => 'GET API',
        'rss'      => 'RSS Feeds',
    ];

    // Display notices
    if ( isset( $_GET['msg'] ) ) {
        $msg = sanitize_key( (string) $_GET['msg'] );
        $notices = [
            'token_generated' => [ 'success', 'API token generated. Copy it now (it will only be shown once).' ],
            'token_created'   => [ 'success', 'New API token created successfully. Copy the token now - it will not be shown again.' ],
            'token_revoked'   => [ 'success', 'API token has been permanently revoked.' ],
            'token_updated'   => [ 'success', 'Token settings updated successfully.' ],
        ];
        if ( isset( $notices[ $msg ] ) ) {
            echo '<div class="notice notice-' . esc_attr( $notices[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $notices[ $msg ][1] ) . '</p></div>';
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Coffeebrk API Documentation</h1>';
    echo '<p style="max-width:980px;color:#555;">Complete REST API for integrating Coffeebrk with n8n, Zapier, custom applications, and external services.</p>';

    // Tab navigation
    echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
    foreach ( $tabs as $tab_key => $tab_label ) {
        $active = $current_tab === $tab_key ? ' nav-tab-active' : '';
        $url = add_query_arg( [ 'page' => 'coffeebrk-core-api', 'tab' => $tab_key ], admin_url( 'admin.php' ) );
        echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . $active . '">' . esc_html( $tab_label ) . '</a>';
    }
    echo '</nav>';

    // Tab content
    switch ( $current_tab ) {
        case 'post-api':
            coffeebrk_api_tab_post_api( $rest_base, $plain_token, $token_hash, $token_last4, $token_updated, $token_user_id, $allowed_meta_keys, $uid );
            break;
        case 'get-api':
            coffeebrk_api_tab_get_api( $rest_base, $plain_token, $token_hash );
            break;
        case 'rss':
            coffeebrk_api_tab_rss( $rss_pretty, $rss_query, $rest_base );
            break;
        default:
            coffeebrk_api_tab_overview( $rest_base, $plain_token, $token_hash, $token_last4, $token_updated, $token_user_id, $uid );
            break;
    }

    echo '</div>';
}

/**
 * Overview Tab
 */
function coffeebrk_api_tab_overview( $rest_base, $plain_token, $token_hash, $token_last4, $token_updated, $token_user_id, $uid ) {
    // Get all tokens using new multi-token system
    $tokens = function_exists( 'coffeebrk_get_api_tokens' ) ? coffeebrk_get_api_tokens() : [];
    $new_token_id = isset( $_GET['new_token'] ) ? sanitize_text_field( $_GET['new_token'] ) : '';
    ?>

    <style>
        .cbk-token-card { background:#fff; border:1px solid #e5e5e5; border-radius:8px; padding:16px; margin-bottom:12px; transition: box-shadow 0.2s; }
        .cbk-token-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .cbk-token-card.new-token { border-color: #ffc107; background: #fffdf5; }
        .cbk-token-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
        .cbk-token-name { font-weight:600; font-size:15px; color:#1d2327; display:flex; align-items:center; gap:8px; }
        .cbk-token-status { padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; text-transform:uppercase; }
        .cbk-token-status.active { background:#d4edda; color:#155724; }
        .cbk-token-status.inactive { background:#f8d7da; color:#721c24; }
        .cbk-token-meta { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin:12px 0; padding:12px 0; border-top:1px solid #f0f0f0; border-bottom:1px solid #f0f0f0; }
        .cbk-token-meta-item { text-align:center; }
        .cbk-token-meta-label { font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.5px; }
        .cbk-token-meta-value { font-size:13px; color:#1d2327; margin-top:4px; font-family:monospace; }
        .cbk-token-value-row { display:flex; align-items:center; gap:8px; background:#f8f9fa; padding:10px 12px; border-radius:6px; margin:10px 0; }
        .cbk-token-value { flex:1; font-family:monospace; font-size:13px; letter-spacing:0.5px; color:#1d2327; }
        .cbk-token-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .cbk-btn-icon { padding:6px 10px; border:1px solid #ddd; background:#fff; border-radius:4px; cursor:pointer; font-size:13px; display:inline-flex; align-items:center; gap:4px; }
        .cbk-btn-icon:hover { background:#f0f0f0; }
        .cbk-btn-icon.danger { color:#dc3545; border-color:#dc3545; }
        .cbk-btn-icon.danger:hover { background:#dc3545; color:#fff; }
        .cbk-permissions { display:flex; gap:6px; margin-top:8px; }
        .cbk-perm-badge { padding:2px 8px; border-radius:4px; font-size:11px; font-weight:500; }
        .cbk-perm-badge.read { background:#e3f2fd; color:#1565c0; }
        .cbk-perm-badge.write { background:#fff3e0; color:#e65100; }
        .cbk-perm-badge.delete { background:#ffebee; color:#c62828; }
        .cbk-new-token-form { background:#fff; border:1px solid #0073aa; border-radius:8px; padding:20px; margin-bottom:20px; }
        .cbk-form-row { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .cbk-form-group { flex:1; min-width:200px; }
        .cbk-form-group label { display:block; font-weight:500; margin-bottom:6px; color:#1d2327; }
        .cbk-form-group input[type="text"] { width:100%; padding:8px 12px; border:1px solid #ddd; border-radius:4px; }
        .cbk-checkbox-group { display:flex; gap:16px; padding:8px 0; }
        .cbk-checkbox-group label { display:flex; align-items:center; gap:6px; cursor:pointer; }
        .cbk-copy-btn { background:#0073aa; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }
        .cbk-copy-btn:hover { background:#005a87; }
        .cbk-copy-btn.copied { background:#28a745; }
        .cbk-token-alert { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:12px 16px; margin-bottom:16px; }
        .cbk-token-alert strong { color:#856404; }
        .cbk-token-alert p { color:#856404; margin:4px 0 0; font-size:13px; }
    </style>

    <script>
    function toggleTokenVisibility(btn, tokenId) {
        const valueEl = btn.closest('.cbk-token-card').querySelector('.cbk-token-value');
        const isHidden = valueEl.dataset.hidden === 'true';

        if (isHidden) {
            valueEl.textContent = valueEl.dataset.full;
            valueEl.dataset.hidden = 'false';
            btn.innerHTML = '<span class="dashicons dashicons-hidden"></span> Hide';
        } else {
            valueEl.textContent = valueEl.dataset.masked;
            valueEl.dataset.hidden = 'true';
            btn.innerHTML = '<span class="dashicons dashicons-visibility"></span> Show';
        }
    }

    function copyToken(btn, token) {
        navigator.clipboard.writeText(token).then(function() {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
            btn.classList.add('copied');
            setTimeout(function() {
                btn.innerHTML = originalText;
                btn.classList.remove('copied');
            }, 2000);
        });
    }

    function confirmRevoke(tokenName) {
        return confirm('Are you sure you want to permanently revoke the token "' + tokenName + '"?\n\nThis action cannot be undone and any integrations using this token will stop working.');
    }
    </script>

    <div style="max-width:1200px;">
        <!-- Create New Token Form -->
        <div class="cbk-new-token-form">
            <h3 style="margin:0 0 16px; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-plus-alt2" style="color:#0073aa;"></span>
                Create New API Token
            </h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="coffeebrk_create_token" />
                <?php wp_nonce_field( 'coffeebrk_create_token' ); ?>

                <div class="cbk-form-row">
                    <div class="cbk-form-group">
                        <label for="token_name">Token Name</label>
                        <input type="text" id="token_name" name="token_name" placeholder="e.g., n8n Production, Zapier Integration" />
                    </div>

                    <div class="cbk-form-group" style="flex:0 0 auto;">
                        <label>Permissions</label>
                        <div class="cbk-checkbox-group">
                            <label><input type="checkbox" name="perm_read" checked /> Read</label>
                            <label><input type="checkbox" name="perm_write" checked /> Write</label>
                            <label><input type="checkbox" name="perm_delete" checked /> Delete</label>
                        </div>
                    </div>

                    <div style="flex:0 0 auto;">
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-admin-network" style="margin-top:3px;"></span>
                            Generate Token
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Token List -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0;">API Tokens (<?php echo count( $tokens ); ?>)</h2>
            <span style="color:#666; font-size:13px;">Tokens are used for server-to-server authentication</span>
        </div>

        <?php if ( empty( $tokens ) ) : ?>
            <div style="background:#f8f9fa; border:1px dashed #ddd; border-radius:8px; padding:40px; text-align:center;">
                <span class="dashicons dashicons-admin-network" style="font-size:48px; color:#ccc; margin-bottom:16px;"></span>
                <h3 style="margin:0 0 8px; color:#666;">No API Tokens</h3>
                <p style="color:#888; margin:0;">Create your first token to start using the API with n8n, Zapier, or custom integrations.</p>
            </div>
        <?php else : ?>
            <?php foreach ( $tokens as $token ) :
                $is_new = $token['id'] === $new_token_id;
                $new_plain_token = $is_new ? get_transient( 'coffeebrk_new_token_' . $token['id'] ) : '';
                $owner = isset( $token['created_by'] ) && $token['created_by'] > 0 ? get_user_by( 'id', $token['created_by'] ) : false;
                $masked_token = 'cbk_' . str_repeat( '*', 32 ) . ( $token['last4'] ?? '****' );
            ?>
                <div class="cbk-token-card <?php echo $is_new ? 'new-token' : ''; ?>">
                    <?php if ( $is_new && $new_plain_token ) : ?>
                        <div class="cbk-token-alert">
                            <strong>New Token Created - Copy Now!</strong>
                            <p>This is the only time you'll see this token. Store it securely.</p>
                        </div>
                    <?php endif; ?>

                    <div class="cbk-token-header">
                        <div class="cbk-token-name">
                            <span class="dashicons dashicons-admin-network" style="color:#0073aa;"></span>
                            <?php echo esc_html( $token['name'] ?? 'Unnamed Token' ); ?>
                        </div>
                        <span class="cbk-token-status <?php echo esc_attr( $token['status'] ?? 'active' ); ?>">
                            <?php echo esc_html( $token['status'] ?? 'active' ); ?>
                        </span>
                    </div>

                    <div class="cbk-token-value-row">
                        <?php if ( $is_new && $new_plain_token ) : ?>
                            <code class="cbk-token-value" data-hidden="false" data-full="<?php echo esc_attr( $new_plain_token ); ?>" data-masked="<?php echo esc_attr( $masked_token ); ?>">
                                <?php echo esc_html( $new_plain_token ); ?>
                            </code>
                            <button type="button" class="cbk-btn-icon" onclick="toggleTokenVisibility(this, '<?php echo esc_attr( $token['id'] ); ?>')">
                                <span class="dashicons dashicons-hidden"></span> Hide
                            </button>
                            <button type="button" class="cbk-copy-btn" onclick="copyToken(this, '<?php echo esc_attr( $new_plain_token ); ?>')">
                                <span class="dashicons dashicons-admin-page" style="font-size:14px; margin-top:2px;"></span> Copy
                            </button>
                            <?php delete_transient( 'coffeebrk_new_token_' . $token['id'] ); ?>
                        <?php else : ?>
                            <code class="cbk-token-value" data-hidden="true" data-full="<?php echo esc_attr( ( $token['prefix'] ?? 'cbk_****' ) . str_repeat( '*', 28 ) . ( $token['last4'] ?? '****' ) ); ?>" data-masked="<?php echo esc_attr( $masked_token ); ?>">
                                <?php echo esc_html( $masked_token ); ?>
                            </code>
                            <button type="button" class="cbk-btn-icon" onclick="toggleTokenVisibility(this, '<?php echo esc_attr( $token['id'] ); ?>')">
                                <span class="dashicons dashicons-visibility"></span> Show
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="cbk-token-meta">
                        <div class="cbk-token-meta-item">
                            <div class="cbk-token-meta-label">Token ID</div>
                            <div class="cbk-token-meta-value"><?php echo esc_html( $token['id'] ?? 'N/A' ); ?></div>
                        </div>
                        <div class="cbk-token-meta-item">
                            <div class="cbk-token-meta-label">Created</div>
                            <div class="cbk-token-meta-value"><?php echo esc_html( coffeebrk_time_ago( $token['created_at'] ?? 0 ) ); ?></div>
                        </div>
                        <div class="cbk-token-meta-item">
                            <div class="cbk-token-meta-label">Last Used</div>
                            <div class="cbk-token-meta-value"><?php echo esc_html( coffeebrk_time_ago( $token['last_used'] ?? null ) ); ?></div>
                        </div>
                        <div class="cbk-token-meta-item">
                            <div class="cbk-token-meta-label">Created By</div>
                            <div class="cbk-token-meta-value"><?php echo $owner ? esc_html( $owner->user_login ) : 'System'; ?></div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="cbk-permissions">
                            <?php
                            $perms = $token['permissions'] ?? [ 'read', 'write', 'delete' ];
                            foreach ( $perms as $perm ) :
                            ?>
                                <span class="cbk-perm-badge <?php echo esc_attr( $perm ); ?>"><?php echo esc_html( ucfirst( $perm ) ); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="cbk-token-actions">
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                <input type="hidden" name="action" value="coffeebrk_toggle_token" />
                                <input type="hidden" name="token_id" value="<?php echo esc_attr( $token['id'] ); ?>" />
                                <?php wp_nonce_field( 'coffeebrk_toggle_token_' . $token['id'] ); ?>
                                <button type="submit" class="cbk-btn-icon">
                                    <?php if ( ( $token['status'] ?? 'active' ) === 'active' ) : ?>
                                        <span class="dashicons dashicons-controls-pause"></span> Disable
                                    <?php else : ?>
                                        <span class="dashicons dashicons-controls-play"></span> Enable
                                    <?php endif; ?>
                                </button>
                            </form>

                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirmRevoke('<?php echo esc_attr( $token['name'] ?? 'this token' ); ?>');">
                                <input type="hidden" name="action" value="coffeebrk_revoke_token" />
                                <input type="hidden" name="token_id" value="<?php echo esc_attr( $token['id'] ); ?>" />
                                <?php wp_nonce_field( 'coffeebrk_revoke_token_' . $token['id'] ); ?>
                                <button type="submit" class="cbk-btn-icon danger">
                                    <span class="dashicons dashicons-trash"></span> Revoke
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Quick Reference and Authentication sections below tokens -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:1200px;margin-top:30px;">
        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
            <h2 style="margin-top:0;">Quick Reference</h2>
            <p style="color:#555;margin-bottom:15px;">Base URL: <code><?php echo esc_html( $rest_base ); ?></code></p>

            <table class="widefat striped" style="margin:0;">
                <thead><tr><th>Method</th><th>Endpoint</th><th>Auth</th></tr></thead>
                <tbody>
                    <tr><td><span style="background:#28a745;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">GET</span></td><td><code>/posts</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#28a745;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">GET</span></td><td><code>/posts/{id}</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#ffc107;color:#000;padding:2px 6px;border-radius:3px;font-size:11px;">POST</span></td><td><code>/posts</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#17a2b8;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">PUT</span></td><td><code>/posts/{id}</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#dc3545;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">DELETE</span></td><td><code>/posts/{id}</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#ffc107;color:#000;padding:2px 6px;border-radius:3px;font-size:11px;">POST</span></td><td><code>/bulk-posts</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#28a745;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">GET</span></td><td><code>/categories</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#28a745;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">GET</span></td><td><code>/meta-fields</code></td><td>Bearer Token</td></tr>
                    <tr><td><span style="background:#28a745;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">GET</span></td><td><code>/site</code></td><td>Public</td></tr>
                    <tr><td><span style="background:#28a745;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">GET</span></td><td><code>/rss-info</code></td><td>Public</td></tr>
                </tbody>
            </table>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
            <h2 style="margin-top:0;">Authentication</h2>
            <p style="color:#555;">All API requests (except public endpoints) require authentication using a Bearer token.</p>

            <h3 style="font-size:14px;">HTTP Header</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;"><code>Authorization: Bearer cbk_your_token_here</code></pre>

            <h3 style="font-size:14px;">Alternative Header</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;"><code>X-Coffeebrk-Token: cbk_your_token_here</code></pre>

            <h3 style="font-size:14px;margin-top:16px;">Response Codes</h3>
            <table class="widefat striped" style="font-size:13px;">
                <tbody>
                    <tr><td style="width:60px;"><code>200</code></td><td>Success</td></tr>
                    <tr><td><code>201</code></td><td>Created successfully</td></tr>
                    <tr><td><code>400</code></td><td>Bad request</td></tr>
                    <tr><td><code>401</code></td><td>Unauthorized</td></tr>
                    <tr><td><code>404</code></td><td>Not found</td></tr>
                    <tr><td><code>500</code></td><td>Server error</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * POST API Tab
 */
function coffeebrk_api_tab_post_api( $rest_base, $plain_token, $token_hash, $token_last4, $token_updated, $token_user_id, $allowed_meta_keys, $uid ) {
    ?>
    <div style="max-width:1200px;">
        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#0073aa;">POST /posts - Create New Post</h2>
            <p style="color:#555;">Create a new blog post or news article. Perfect for n8n automation workflows.</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
                <div>
                    <h3>Endpoint</h3>
                    <code style="background:#f1f1f1;padding:10px;display:block;border-radius:4px;"><?php echo esc_html( $rest_base ); ?>/posts</code>

                    <h3 style="margin-top:20px;">Method</h3>
                    <span style="background:#ffc107;color:#000;padding:4px 12px;border-radius:4px;font-weight:bold;">POST</span>

                    <h3 style="margin-top:20px;">Authentication</h3>
                    <p><code>Authorization: Bearer YOUR_TOKEN</code></p>
                </div>
                <div>
                    <h3>Required Headers</h3>
                    <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:13px;margin:0;"><code>Content-Type: application/json
Authorization: Bearer YOUR_TOKEN</code></pre>
                </div>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Request Body Parameters</h3>
            <table class="widefat striped">
                <thead><tr><th style="width:150px;">Parameter</th><th style="width:100px;">Type</th><th style="width:80px;">Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>title</code></td><td>string</td><td>Yes*</td><td>Post title. Required unless content is provided.</td></tr>
                    <tr><td><code>content</code></td><td>string</td><td>Yes*</td><td>Post content (HTML allowed). Required unless title is provided.</td></tr>
                    <tr><td><code>excerpt</code></td><td>string</td><td>No</td><td>Post excerpt/summary.</td></tr>
                    <tr><td><code>status</code></td><td>string</td><td>No</td><td>Post status: <code>draft</code> (default), <code>publish</code>, <code>pending</code>, <code>private</code></td></tr>
                    <tr><td><code>category_id</code></td><td>integer</td><td>No</td><td>Single category ID.</td></tr>
                    <tr><td><code>categories</code></td><td>array</td><td>No</td><td>Array of category IDs. Example: <code>[1, 5, 12]</code></td></tr>
                    <tr><td><code>tags</code></td><td>array</td><td>No</td><td>Array of tag names. Example: <code>["AI", "Tech", "News"]</code></td></tr>
                    <tr><td><code>source_url</code></td><td>string</td><td>No</td><td>Original article URL.</td></tr>
                    <tr><td><code>source_name</code></td><td>string</td><td>No</td><td>Source name (e.g., "TechCrunch").</td></tr>
                    <tr><td><code>image_url</code></td><td>string</td><td>No</td><td>Featured image URL.</td></tr>
                    <tr><td><code>author_id</code></td><td>integer</td><td>No</td><td>WordPress user ID for author.</td></tr>
                    <tr><td><code>date</code></td><td>string</td><td>No</td><td>Post date (format: YYYY-MM-DD HH:MM:SS).</td></tr>
                    <tr><td><code>slug</code></td><td>string</td><td>No</td><td>Custom URL slug.</td></tr>
                    <tr><td><code>meta</code></td><td>object</td><td>No</td><td>Custom meta fields object.</td></tr>
                </tbody>
            </table>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Allowed Meta Keys</h3>
            <p style="color:#555;">Only these meta keys are accepted via API (configure in Dynamic Fields):</p>
            <?php if ( ! empty( $allowed_meta_keys ) ) : ?>
                <div style="background:#f8f9fa;padding:12px;border-radius:6px;font-family:monospace;">
                    <?php echo esc_html( implode( ', ', array_merge( [ '_source_name', '_source_url', '_image', '_organization_logo', '_tagline', '_date' ], $allowed_meta_keys ) ) ); ?>
                </div>
            <?php else : ?>
                <div style="background:#f8f9fa;padding:12px;border-radius:6px;font-family:monospace;">
                    _source_name, _source_url, _image, _organization_logo, _tagline, _date
                </div>
                <p style="color:#666;font-size:13px;margin-top:10px;">Add more fields in <strong>Coffeebrk Core > Dynamic Fields</strong>.</p>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Example Request - cURL</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl -X POST "<?php echo esc_html( $rest_base ); ?>/posts" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "title": "Breaking: AI Revolution in 2025",
    "content": "&lt;p&gt;This is the article content with &lt;strong&gt;HTML&lt;/strong&gt; support.&lt;/p&gt;",
    "excerpt": "A brief summary of the article...",
    "status": "publish",
    "categories": [5, 12],
    "tags": ["AI", "Technology", "Breaking News"],
    "source_url": "https://example.com/original-article",
    "source_name": "TechNews Daily",
    "image_url": "https://example.com/featured-image.jpg",
    "meta": {
      "_tagline": "The future is here",
      "_organization_logo": "https://example.com/logo.png"
    }
  }'</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Example Request - n8n HTTP Request Node</h3>
            <p style="color:#555;">Configure the HTTP Request node in n8n with these settings:</p>

            <table class="widefat" style="margin-top:15px;">
                <tbody>
                    <tr><th style="width:180px;background:#f1f1f1;">Method</th><td>POST</td></tr>
                    <tr><th style="background:#f1f1f1;">URL</th><td><code><?php echo esc_html( $rest_base ); ?>/posts</code></td></tr>
                    <tr><th style="background:#f1f1f1;">Authentication</th><td>Header Auth</td></tr>
                    <tr><th style="background:#f1f1f1;">Header Name</th><td>Authorization</td></tr>
                    <tr><th style="background:#f1f1f1;">Header Value</th><td>Bearer YOUR_API_TOKEN</td></tr>
                    <tr><th style="background:#f1f1f1;">Body Content Type</th><td>JSON</td></tr>
                </tbody>
            </table>

            <h4 style="margin-top:20px;">n8n JSON Body Example (using expressions):</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "title": "{{ $json.title }}",
  "content": "{{ $json.content }}",
  "status": "publish",
  "source_url": "{{ $json.url }}",
  "source_name": "{{ $json.source }}",
  "image_url": "{{ $json.image }}",
  "categories": [{{ $json.category_id }}],
  "tags": {{ JSON.stringify($json.tags) }}
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Success Response (201 Created)</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "success": true,
  "message": "Post created successfully.",
  "post": {
    "id": 123,
    "title": "Breaking: AI Revolution in 2025",
    "slug": "breaking-ai-revolution-in-2025",
    "status": "publish",
    "date": "2025-01-15 10:30:00",
    "permalink": "https://yoursite.com/breaking-ai-revolution-in-2025/",
    "author": {
      "id": 1,
      "name": "Admin"
    },
    "categories": [
      {"id": 5, "name": "Technology", "slug": "technology"}
    ],
    "tags": [
      {"id": 10, "name": "AI", "slug": "ai"}
    ],
    "featured_image": "https://example.com/featured-image.jpg",
    "meta": {
      "_source_name": "TechNews Daily",
      "_source_url": "https://example.com/original-article"
    }
  },
  "edit_link": "https://yoursite.com/wp-admin/post.php?post=123&action=edit"
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;color:#0073aa;">POST /bulk-posts - Create Multiple Posts</h3>
            <p style="color:#555;">Create up to 50 posts in a single request. Ideal for batch imports.</p>

            <h4>Request Body</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "posts": [
    {
      "title": "First Article",
      "content": "Content of first article...",
      "status": "draft",
      "source_name": "Source 1"
    },
    {
      "title": "Second Article",
      "content": "Content of second article...",
      "status": "publish",
      "categories": [5]
    }
  ]
}</code></pre>

            <h4 style="margin-top:15px;">Response</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "success": true,
  "total": 2,
  "success_count": 2,
  "error_count": 0,
  "results": [
    {"index": 0, "success": true, "id": 124, "permalink": "..."},
    {"index": 1, "success": true, "id": 125, "permalink": "..."}
  ]
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;color:#17a2b8;">PUT /posts/{id} - Update Post</h3>
            <p style="color:#555;">Update an existing post. Only include fields you want to change.</p>

            <h4>Endpoint</h4>
            <code style="background:#f1f1f1;padding:10px;display:block;border-radius:4px;"><?php echo esc_html( $rest_base ); ?>/posts/{id}</code>

            <h4 style="margin-top:15px;">Example - Update title and status</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl -X PUT "<?php echo esc_html( $rest_base ); ?>/posts/123" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "title": "Updated Title",
    "status": "publish"
  }'</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
            <h3 style="margin-top:0;color:#dc3545;">DELETE /posts/{id} - Delete Post</h3>
            <p style="color:#555;">Move a post to trash or permanently delete it.</p>

            <h4>Parameters</h4>
            <table class="widefat striped" style="max-width:500px;">
                <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>force</code></td><td>boolean</td><td>If <code>true</code>, permanently delete. Default: <code>false</code> (trash).</td></tr>
                </tbody>
            </table>

            <h4 style="margin-top:15px;">Example - Move to trash</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl -X DELETE "<?php echo esc_html( $rest_base ); ?>/posts/123" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Example - Permanent delete</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl -X DELETE "<?php echo esc_html( $rest_base ); ?>/posts/123?force=true" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>
        </div>
    </div>

    <div style="background:#e7f3ff;border:1px solid #b6d4fe;border-radius:8px;padding:20px;margin-top:20px;max-width:1200px;">
        <h3 style="margin-top:0;color:#084298;">n8n Workflow Tips</h3>
        <ul style="color:#084298;margin:0;padding-left:20px;">
            <li>Use the <strong>HTTP Request</strong> node with POST method</li>
            <li>Store your API token in n8n credentials for security</li>
            <li>Use expressions like <code>{{ $json.field }}</code> to map RSS or webhook data</li>
            <li>Add an <strong>IF</strong> node to check response <code>success === true</code></li>
            <li>Use <strong>bulk-posts</strong> endpoint when processing multiple items to reduce API calls</li>
        </ul>
    </div>
    <?php
}

/**
 * GET API Tab
 */
function coffeebrk_api_tab_get_api( $rest_base, $plain_token, $token_hash ) {
    ?>
    <div style="max-width:1200px;">
        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#28a745;">GET /posts - List Posts</h2>
            <p style="color:#555;">Retrieve posts with powerful filtering, pagination, and sorting options.</p>

            <h3>Endpoint</h3>
            <code style="background:#f1f1f1;padding:10px;display:block;border-radius:4px;"><?php echo esc_html( $rest_base ); ?>/posts</code>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Query Parameters</h3>
            <table class="widefat striped">
                <thead><tr><th style="width:150px;">Parameter</th><th style="width:100px;">Type</th><th style="width:150px;">Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>page</code></td><td>integer</td><td>1</td><td>Page number for pagination.</td></tr>
                    <tr><td><code>per_page</code></td><td>integer</td><td>10</td><td>Posts per page (max: 100).</td></tr>
                    <tr><td><code>status</code></td><td>string</td><td>publish</td><td>Filter by status: <code>publish</code>, <code>draft</code>, <code>pending</code>, <code>any</code></td></tr>
                    <tr><td><code>category</code></td><td>integer</td><td>-</td><td>Filter by category ID.</td></tr>
                    <tr><td><code>category_slug</code></td><td>string</td><td>-</td><td>Filter by category slug.</td></tr>
                    <tr><td><code>search</code></td><td>string</td><td>-</td><td>Search posts by keyword.</td></tr>
                    <tr><td><code>orderby</code></td><td>string</td><td>date</td><td>Sort by: <code>date</code>, <code>title</code>, <code>modified</code>, <code>ID</code>, <code>rand</code></td></tr>
                    <tr><td><code>order</code></td><td>string</td><td>DESC</td><td>Sort order: <code>ASC</code> or <code>DESC</code></td></tr>
                    <tr><td><code>author</code></td><td>integer</td><td>-</td><td>Filter by author ID.</td></tr>
                    <tr><td><code>meta_key</code></td><td>string</td><td>-</td><td>Filter by meta key (use with meta_value).</td></tr>
                    <tr><td><code>meta_value</code></td><td>string</td><td>-</td><td>Filter by meta value.</td></tr>
                    <tr><td><code>after</code></td><td>string</td><td>-</td><td>Posts after date (YYYY-MM-DD).</td></tr>
                    <tr><td><code>before</code></td><td>string</td><td>-</td><td>Posts before date (YYYY-MM-DD).</td></tr>
                    <tr><td><code>include_meta</code></td><td>boolean</td><td>true</td><td>Include meta fields in response.</td></tr>
                </tbody>
            </table>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Example Requests</h3>

            <h4>Get latest 10 published posts</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/posts" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Get posts from specific category with pagination</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/posts?category=5&page=2&per_page=20" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Search posts and sort by title</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/posts?search=AI&orderby=title&order=ASC" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Get draft posts from last week</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/posts?status=draft&after=2025-01-08" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Filter by meta field</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/posts?meta_key=source_name&meta_value=TechCrunch" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">Response Format</h3>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "success": true,
  "page": 1,
  "per_page": 10,
  "total": 156,
  "total_pages": 16,
  "items": [
    {
      "id": 123,
      "title": "Post Title",
      "slug": "post-title",
      "status": "publish",
      "date": "2025-01-15 10:30:00",
      "date_gmt": "2025-01-15 15:30:00",
      "modified": "2025-01-15 11:00:00",
      "excerpt": "First 30 words of content...",
      "permalink": "https://yoursite.com/post-title/",
      "author": {
        "id": 1,
        "name": "Admin"
      },
      "categories": [
        {"id": 5, "name": "Technology", "slug": "technology"}
      ],
      "tags": [
        {"id": 10, "name": "AI", "slug": "ai"}
      ],
      "featured_image": "https://yoursite.com/image.jpg",
      "featured_image_id": 456,
      "meta": {
        "_source_name": "TechNews",
        "_source_url": "https://example.com/article"
      }
    }
  ]
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#28a745;">GET /posts/{id} - Single Post</h2>
            <p style="color:#555;">Retrieve a single post with full content.</p>

            <h4>Example</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/posts/123" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Response (includes full content)</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "success": true,
  "post": {
    "id": 123,
    "title": "Post Title",
    "content": "&lt;p&gt;Full HTML content...&lt;/p&gt;",
    "content_rendered": "&lt;p&gt;Processed content with shortcodes...&lt;/p&gt;",
    ...
  }
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#28a745;">GET /categories - List Categories</h2>
            <p style="color:#555;">Retrieve all categories for mapping in your automation.</p>

            <h4>Example</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/categories" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Response</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "success": true,
  "total": 5,
  "items": [
    {"id": 1, "name": "Uncategorized", "slug": "uncategorized", "description": "", "count": 10, "parent": 0},
    {"id": 5, "name": "Technology", "slug": "technology", "description": "Tech news", "count": 45, "parent": 0}
  ]
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#28a745;">GET /meta-fields - Available Meta Fields</h2>
            <p style="color:#555;">Get list of all available meta fields for posts.</p>

            <h4>Example</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/meta-fields" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>

            <h4 style="margin-top:15px;">Response</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "success": true,
  "total": 6,
  "fields": [
    {"key": "_source_name", "label": "Source Name", "type": "text", "required": false, "core": true},
    {"key": "_source_url", "label": "Source URL", "type": "url", "required": false, "core": true},
    {"key": "_image", "label": "Featured Image URL", "type": "image_url", "required": false, "core": true},
    {"key": "_custom_field", "label": "Custom Field", "type": "text", "choices": "", "required": false, "core": false}
  ]
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
            <h2 style="margin-top:0;color:#28a745;">GET /site - Site Information (Public)</h2>
            <p style="color:#555;">Get basic site information. No authentication required.</p>

            <h4>Example</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/site"</code></pre>

            <h4 style="margin-top:15px;">Response</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "name": "My News Site",
  "description": "Latest news and updates",
  "url": "https://yoursite.com/",
  "timezone": "America/New_York",
  "coffeebrk_core_version": "2.1.0"
}</code></pre>
        </div>
    </div>
    <?php
}

/**
 * RSS Tab
 */
function coffeebrk_api_tab_rss( $rss_pretty, $rss_query, $rest_base ) {
    ?>
    <div style="max-width:1200px;">
        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#fd7e14;">RSS Feeds</h2>
            <p style="color:#555;">Subscribe to your site's RSS feed or use it in automation workflows.</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
                <h3 style="margin-top:0;">Coffeebrk RSS Feed</h3>
                <p style="color:#555;">Custom RSS feed with latest 20 published posts.</p>

                <h4>Pretty URL</h4>
                <div style="display:flex;gap:10px;align-items:center;">
                    <code style="background:#f1f1f1;padding:10px;flex:1;border-radius:4px;word-break:break-all;"><?php echo esc_html( $rss_pretty ); ?></code>
                    <a href="<?php echo esc_url( $rss_pretty ); ?>" target="_blank" class="button">Open</a>
                </div>

                <h4 style="margin-top:15px;">Query String URL</h4>
                <div style="display:flex;gap:10px;align-items:center;">
                    <code style="background:#f1f1f1;padding:10px;flex:1;border-radius:4px;word-break:break-all;"><?php echo esc_html( $rss_query ); ?></code>
                    <a href="<?php echo esc_url( $rss_query ); ?>" target="_blank" class="button">Open</a>
                </div>

                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px;margin-top:15px;">
                    <strong style="color:#856404;">404 Error?</strong>
                    <p style="color:#856404;margin:5px 0 0;">Go to <strong>Settings > Permalinks</strong> and click <strong>Save Changes</strong> to flush rewrite rules.</p>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;">
                <h3 style="margin-top:0;">WordPress Default Feed</h3>
                <p style="color:#555;">Standard WordPress RSS 2.0 feed.</p>

                <h4>URL</h4>
                <div style="display:flex;gap:10px;align-items:center;">
                    <code style="background:#f1f1f1;padding:10px;flex:1;border-radius:4px;word-break:break-all;"><?php echo esc_html( get_bloginfo( 'rss2_url' ) ); ?></code>
                    <a href="<?php echo esc_url( get_bloginfo( 'rss2_url' ) ); ?>" target="_blank" class="button">Open</a>
                </div>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-top:20px;">
            <h3 style="margin-top:0;">RSS Feed Structure</h3>
            <p style="color:#555;">The Coffeebrk RSS feed follows RSS 2.0 specification with content:encoded extension.</p>

            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/"&gt;
  &lt;channel&gt;
    &lt;title&gt;Site Name&lt;/title&gt;
    &lt;link&gt;https://yoursite.com/&lt;/link&gt;
    &lt;description&gt;Site description&lt;/description&gt;
    &lt;language&gt;en-US&lt;/language&gt;
    &lt;lastBuildDate&gt;Wed, 15 Jan 2025 10:30:00 +0000&lt;/lastBuildDate&gt;

    &lt;item&gt;
      &lt;title&gt;Post Title&lt;/title&gt;
      &lt;link&gt;https://yoursite.com/post-title/&lt;/link&gt;
      &lt;guid isPermaLink="true"&gt;https://yoursite.com/post-title/&lt;/guid&gt;
      &lt;pubDate&gt;Wed, 15 Jan 2025 10:30:00 +0000&lt;/pubDate&gt;
      &lt;description&gt;&lt;![CDATA[Post excerpt...]]&gt;&lt;/description&gt;
      &lt;content:encoded&gt;&lt;![CDATA[Full HTML content...]]&gt;&lt;/content:encoded&gt;
    &lt;/item&gt;
  &lt;/channel&gt;
&lt;/rss&gt;</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-top:20px;">
            <h3 style="margin-top:0;">GET /rss-info - RSS Information API (Public)</h3>
            <p style="color:#555;">Programmatically get RSS feed URLs and information.</p>

            <h4>Example</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">curl "<?php echo esc_html( $rest_base ); ?>/rss-info"</code></pre>

            <h4 style="margin-top:15px;">Response</h4>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:6px;overflow-x:auto;"><code style="color:#d4d4d4;">{
  "success": true,
  "feeds": [
    {
      "name": "Coffeebrk Main Feed",
      "url": "<?php echo esc_html( $rss_pretty ); ?>",
      "url_alt": "<?php echo esc_html( $rss_query ); ?>",
      "format": "RSS 2.0",
      "description": "Latest published posts from the site."
    },
    {
      "name": "WordPress Default Feed",
      "url": "<?php echo esc_html( get_bloginfo( 'rss2_url' ) ); ?>",
      "format": "RSS 2.0",
      "description": "Standard WordPress RSS feed."
    }
  ]
}</code></pre>
        </div>

        <div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:20px;margin-top:20px;">
            <h3 style="margin-top:0;">Using RSS with n8n</h3>
            <p style="color:#555;">Use the RSS Feed Read node in n8n to fetch posts from your site.</p>

            <h4>n8n RSS Feed Read Node Configuration</h4>
            <table class="widefat" style="margin-top:15px;max-width:600px;">
                <tbody>
                    <tr><th style="width:150px;background:#f1f1f1;">URL</th><td><code><?php echo esc_html( $rss_pretty ); ?></code></td></tr>
                    <tr><th style="background:#f1f1f1;">Trigger</th><td>On Schedule (e.g., every 15 minutes)</td></tr>
                </tbody>
            </table>

            <h4 style="margin-top:20px;">Example n8n Workflow</h4>
            <ol style="color:#555;padding-left:20px;">
                <li>Add <strong>RSS Feed Read</strong> node with your feed URL</li>
                <li>Add <strong>IF</strong> node to filter new items</li>
                <li>Add <strong>HTTP Request</strong> node to POST to another service</li>
                <li>Connect nodes and activate workflow</li>
            </ol>
        </div>
    </div>

    <div style="background:#e7f3ff;border:1px solid #b6d4fe;border-radius:8px;padding:20px;margin-top:20px;max-width:1200px;">
        <h3 style="margin-top:0;color:#084298;">Pro Tips</h3>
        <ul style="color:#084298;margin:0;padding-left:20px;">
            <li>Use the Coffeebrk feed (<code>/feed/coffeebrk/</code>) for consistent formatting</li>
            <li>RSS feeds are cached by WordPress - changes may take a few minutes to appear</li>
            <li>For real-time notifications, consider using webhooks instead of RSS polling</li>
            <li>The feed includes full HTML content in <code>content:encoded</code> for rich formatting</li>
        </ul>
    </div>
    <?php
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
