<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// -------- User meta: aspire (multi-select) --------
add_action('init', function(){
    register_meta('user', 'aspire', [
        'type' => 'array',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() { return current_user_can('list_users'); }
    ]);
});

// -------- Options helpers --------
function coffeebrk_core_get_option($key, $default = ''){
    $o = get_option('coffeebrk_core_settings', []);
    return isset($o[$key]) ? $o[$key] : $default;
}

// -------- Page creation on activation --------
add_action('coffeebrk_core_activate', function(){
    $pages = [
        'coffeebrk-login' => [ 'title' => 'Login', 'shortcode' => '[coffeebrk_login]' ],
        'coffeebrk-signup' => [ 'title' => 'Sign Up', 'shortcode' => '[coffeebrk_signup]' ],
        'coffeebrk-hello' => [ 'title' => 'Hello', 'shortcode' => '[coffeebrk_hello]' ],
        'coffeebrk-onboarding' => [ 'title' => 'Onboarding', 'shortcode' => '[coffeebrk_onboarding]' ],
    ];
    $ids = [];
    foreach ($pages as $slug => $cfg){
        $existing = get_page_by_path($slug);
        if ( $existing ) { $ids[$slug] = $existing->ID; continue; }
        $id = wp_insert_post([
            'post_title' => $cfg['title'],
            'post_name' => $slug,
            'post_content' => $cfg['shortcode'],
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
        if ( $id && ! is_wp_error($id) ) { $ids[$slug] = $id; }
    }
    if ( ! empty($ids) ) {
        update_option('coffeebrk_core_pages', array_merge((array) get_option('coffeebrk_core_pages', []), $ids));
    }
});

function coffeebrk_core_page_url($slug){
    $ids = (array) get_option('coffeebrk_core_pages', []);
    if ( isset($ids[$slug]) ) return get_permalink($ids[$slug]);
    return home_url('/' . $slug . '/');
}

// Get the configured App URL (where users go after onboarding), fallback to home
function coffeebrk_core_app_url(){
    $app_url = coffeebrk_core_get_option('app_url', '');
    if ( ! empty($app_url) ) {
        return esc_url_raw( rtrim($app_url, '/') . '/' );
    }
    return home_url('/');
}

// Allow redirects to the configured App URL domain
add_filter('allowed_redirect_hosts', function($hosts){
    $app_url = coffeebrk_core_get_option('app_url', '');
    if ( ! empty($app_url) ) {
        $parsed = wp_parse_url($app_url);
        if ( ! empty($parsed['host']) ) {
            $hosts[] = $parsed['host'];
        }
    }
    return $hosts;
});

// Safe redirect that falls back to JS if headers already sent (prevents warnings)
function coffeebrk_safe_redirect($url){
    $url = esc_url_raw($url);
    if ( ! headers_sent() ) {
        wp_safe_redirect($url);
        exit;
    }
    add_action('wp_head', function() use ($url){ echo '<meta http-equiv="refresh" content="0;url='.esc_url($url).'">'; });
    add_action('wp_footer', function() use ($url){ echo '<script>window.location.replace('.json_encode($url).');</script>'; });
}

// -------- Content gating (force login) --------
add_action('template_redirect', function(){
    if ( is_admin() || defined('REST_REQUEST') || wp_doing_cron() ) return;
    $ids = (array) get_option('coffeebrk_core_pages', []);
    // If logged-in: push away from hello/login/signup; allow onboarding only if not completed
    if ( is_user_logged_in() ) {
        if ( isset($ids['coffeebrk-onboarding']) && is_page( (int) $ids['coffeebrk-onboarding'] ) ) {
            $u = wp_get_current_user();
            $has_name = (bool) ($u->first_name);
            $has_aspire = ! empty( get_user_meta($u->ID, 'aspire', true) );
            if ( $has_name && $has_aspire ) {
                coffeebrk_safe_redirect( coffeebrk_core_app_url() );
            }
            return; // let incomplete users finish onboarding
        }
        if ( is_page( [ (int)($ids['coffeebrk-hello'] ?? 0), (int)($ids['coffeebrk-login'] ?? 0), (int)($ids['coffeebrk-signup'] ?? 0) ] ) ) {
            coffeebrk_safe_redirect( coffeebrk_core_app_url() );
        }
        return;
    }
    // Anonymous: only allow our auth/onboarding pages
    if ( is_page( array_values($ids) ) ) return;
    coffeebrk_safe_redirect( coffeebrk_core_page_url('coffeebrk-hello') );
});

// -------- Supabase OAuth finalize endpoint --------
add_action('rest_api_init', function(){
    register_rest_route('coffeebrk/v1', '/supabase/login', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function( WP_REST_Request $req ){
            $access = sanitize_text_field( $req->get_param('access_token') );
            if ( ! $access ) return new WP_REST_Response(['error'=>'missing_access_token'], 400);
            $supabase_url = rtrim( coffeebrk_core_get_option('supabase_url'), '/' );
            $anon = coffeebrk_core_get_option('supabase_anon_key');
            $resp = wp_remote_get( $supabase_url . '/auth/v1/user', [ 'headers' => [ 'Authorization' => 'Bearer ' . $access, 'apikey' => $anon ] ] );
            if ( is_wp_error($resp) ) { coffeebrk_log_error('supabase user fetch error', ['err'=>$resp->get_error_message()]); return new WP_REST_Response(['error'=>'upstream_error'], 500); }
            $code = wp_remote_retrieve_response_code($resp);
            if ( $code !== 200 ) { coffeebrk_log_error('supabase user non-200', ['code'=>$code, 'body'=> wp_remote_retrieve_body($resp)]); return new WP_REST_Response(['error'=>'invalid_token'], 401); }
            $info = json_decode( wp_remote_retrieve_body($resp), true );
            $email = sanitize_email( $info['email'] ?? '' );
            $given = sanitize_text_field( $info['user_metadata']['full_name'] ?? ($info['user_metadata']['name'] ?? ($info['user_metadata']['given_name'] ?? '')) );
            if ( ! $email ) return new WP_REST_Response(['error'=>'no_email'], 400);
            $user = get_user_by('email', $email);
            if ( ! $user ) {
                $login = sanitize_user( current( explode('@', $email) ), true );
                if ( username_exists($login) ) { $login = $login . '_' . wp_generate_password(4, false); }
                $uid = wp_create_user( $login, wp_generate_password(20), $email );
                if ( is_wp_error($uid) ) return new WP_REST_Response(['error'=>'create_failed'], 500);
                if ( $given ) wp_update_user([ 'ID'=>$uid, 'first_name'=>$given ]);
                $user = get_user_by('id', $uid);
            }
            if ( $user instanceof WP_User ) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);
                do_action('wp_login', $user->user_login, $user);
                return new WP_REST_Response(['success'=>true, 'redirect'=> coffeebrk_core_page_url('coffeebrk-onboarding') ], 200);
            }
            return new WP_REST_Response(['error'=>'login_failed'], 500);
        }
    ]);
});

// ---- CORS for finalize endpoint (supports multiple domains) ----
function coffeebrk_core_allowed_origins(): array {
    $raw = (string) coffeebrk_core_get_option('allowed_origins', '');
    $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $raw)));
    return $lines;
}

add_filter('rest_pre_serve_request', function($served, $result){
    $req_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos($req_uri, '/coffeebrk/v1/supabase/login') === false ) return $served;
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowed = coffeebrk_core_allowed_origins();
    if ( $origin && in_array($origin, $allowed, true) ) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
    }
    if ( 'OPTIONS' === ($_SERVER['REQUEST_METHOD'] ?? '') ) {
        echo '';
        return true;
    }
    return $served;
}, 10, 2);

// Hide admin bar for non-admins and on auth pages
add_filter('show_admin_bar', function($show){
    if ( is_admin() ) return $show;
    // Hide for non-admins
    if ( ! current_user_can('manage_options') ) return false;
    // Also hide on our auth pages even for admins
    $ids = (array) get_option('coffeebrk_core_pages', []);
    if ( is_page( array_values($ids) ) ) return false;
    return $show;
});

// -------- Shortcodes: markup + handlers --------
function coffeebrk_enqueue_auth_styles(){
    $file = COFFEEBRK_CORE_PATH . 'assets/css/coffeebrk-auth.css';
    $ver = file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0';
    wp_enqueue_style('coffeebrk-auth', COFFEEBRK_CORE_URL . 'assets/css/coffeebrk-auth.css', [], $ver);
}

function coffeebrk_enqueue_supabase_assets(string $context){
    $cfg = [
        'supabaseUrl' => rtrim( coffeebrk_core_get_option('supabase_url'), '/' ),
        'supabaseAnonKey' => coffeebrk_core_get_option('supabase_anon_key'),
        'finalizeUrl' => rest_url('coffeebrk/v1/supabase/login'),
        'redirectAfter' => coffeebrk_core_page_url('coffeebrk-onboarding'),
        'context' => $context,
    ];
    wp_enqueue_script('supabase-js', 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.47.10/dist/umd/supabase.min.js', [], '2.47.10', true);
    wp_enqueue_script('coffeebrk-supabase-auth', COFFEEBRK_CORE_URL . 'assets/js/supabase-auth.js', ['supabase-js'], '1.0.0', true);
    wp_localize_script('coffeebrk-supabase-auth', 'CoffeebrkAuth', $cfg);
}

function coffeebrk_auth_logo_html() : string {
    $src = COFFEEBRK_CORE_URL . 'assets/img/coffeebrk%20logo.svg';
    $href = 'https://app.coffeebrk.ai/';
    return '<div class="cbk-logo"><a href="' . esc_url( $href ) . '" aria-label="Coffeebrk"><img src="' . esc_url( $src ) . '" alt="Coffeebrk" loading="eager" /></a></div>';
}

function coffeebrk_auth_right_panel_html() : string {
    $img = COFFEEBRK_CORE_URL . 'assets/img/person1.png';
    $out = '<div class="cbk-right">';
    $out .= '<div class="cbk-right-hero">';
    $out .= '<img src="' . esc_url( $img ) . '" alt="" loading="eager" />';
    $out .= '<div class="cbk-right-person"><strong>Darlene Robertson</strong>Big Kahuna Burger Ltd.</div>';
    $out .= '</div>';
    $out .= '<h2 class="cbk-right-title">Motivation That Sticks</h2>';
    $out .= '<p class="cbk-right-quote">"I used to quit self-improvement apps after a week. This one? I\'m on day 42. The 5-minute challenges fit perfectly into my routine — no pressure, just progress."</p>';
    $out .= '</div>';
    return $out;
}

function coffeebrk_auth_shell_html( string $left_html ) : string {
    $out = '<div class="cbk-shell">';
    $out .= '<div class="cbk-shell__left"><div class="cbk-left">';
    $out .= coffeebrk_auth_logo_html();
    $out .= '<div class="cbk-left-content"><div class="cbk-content">' . $left_html . '</div></div>';
    $out .= '</div></div>';
    $out .= '<div class="cbk-shell__right">' . coffeebrk_auth_right_panel_html() . '</div>';
    $out .= '</div>';
    return $out;
}

add_shortcode('coffeebrk_login', function(){
    if ( is_user_logged_in() ) return '<div class="cbk-wrap"><div class="cbk-card"><p class="cbk-center">You are already logged in.</p></div></div>';
    coffeebrk_enqueue_auth_styles();
    coffeebrk_enqueue_supabase_assets('login');
    $action = esc_url( get_permalink() );
    $left = '';
    $left .= '<div class="cbk-title">Login to your account</div>';
    $left .= '<form class="cbk-form" method="post" action="'.$action.'">';
    $left .= wp_nonce_field('coffeebrk_login','coffeebrk_login_nonce',true,false);
    $left .= '<div class="cbk-fields">';
    $left .= '<div class="cbk-field"><div class="cbk-label">Email</div><input class="cbk-input" type="email" name="email" required /></div>';
    $left .= '<div class="cbk-field"><div class="cbk-label">Password</div><input class="cbk-input" type="password" name="password" required /></div>';
    $left .= '</div>';
    $left .= '<button class="cbk-btn" type="submit">Login</button>';
    $left .= '</form>';
    $left .= '<div class="cbk-or">Or</div>';
    $left .= '<button type="button" id="coffeebrk-google-btn" class="cbk-google"><span class="cbk-google-icon" aria-hidden="true"></span> Sign in with Google</button>';
    $left .= '<div class="cbk-help">Don\'t have an account? <a class="cbk-link" href="'.esc_url( coffeebrk_core_page_url('coffeebrk-signup') ).'">Sign Up</a></div>';
    return coffeebrk_auth_shell_html( $left );
});

add_shortcode('coffeebrk_signup', function(){
    if ( is_user_logged_in() ) { wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-onboarding') ); exit; }
    coffeebrk_enqueue_auth_styles();
    coffeebrk_enqueue_supabase_assets('signup');
    $action = esc_url( get_permalink() );
    $left = '';
    $left .= '<div class="cbk-title">Create Account</div>';
    $left .= '<form class="cbk-form" method="post" action="'.$action.'">';
    $left .= wp_nonce_field('coffeebrk_signup','coffeebrk_signup_nonce',true,false);
    $left .= '<div class="cbk-fields">';
    $left .= '<div class="cbk-field"><div class="cbk-label">Email</div><input class="cbk-input" type="email" name="email" required /></div>';
    $left .= '<div class="cbk-field"><div class="cbk-label">Password</div><input class="cbk-input" type="password" name="password" required /></div>';
    $left .= '<div class="cbk-field"><div class="cbk-label">Confirm Password</div><input class="cbk-input" type="password" name="password2" required /></div>';
    $left .= '</div>';
    $left .= '<button class="cbk-btn" type="submit">Sign Up</button>';
    $left .= '</form>';
    $left .= '<div class="cbk-or">Or</div>';
    $left .= '<button type="button" id="coffeebrk-google-btn" class="cbk-google"><span class="cbk-google-icon" aria-hidden="true"></span> Sign in with Google</button>';
    $left .= '<div class="cbk-help">Already have an account? <a class="cbk-link" href="'.esc_url( coffeebrk_core_page_url('coffeebrk-login') ).'">Log In</a></div>';
    return coffeebrk_auth_shell_html( $left );
});

add_shortcode('coffeebrk_onboarding', function(){
    coffeebrk_enqueue_auth_styles();
    $user = wp_get_current_user();
    $provided_name = $user->first_name ?: '';
    // Build aspire options from settings, keeping icons via a default mapping
    $defaults = [
        'developer' => '🧑‍💻',
        'designer' => '🎨',
        'marketer' => '📈',
        'writer / content creator' => '🖋️',
        'product manager' => '🧠',
        'data / ai engineer' => '🤖',
        'student / explorer' => '🌱',
    ];
    $configured = (array) get_option('coffeebrk_aspires', []);
    $aspire_options = [];
    if ($configured) {
        // New structure: each row is ['label'=>..., 'icon'=>...]
        if (is_array($configured) && isset($configured[0]) && is_array($configured[0])) {
            foreach ($configured as $row) {
                $label = trim((string)($row['label'] ?? ''));
                if ($label==='') continue;
                $key = strtolower($label);
                $icon = $row['icon'] !== '' ? $row['icon'] : ($defaults[$key] ?? '✨');
                $aspire_options[$label] = $icon;
            }
        } else {
            // Legacy string list
            foreach ($configured as $label) {
                $key = strtolower(trim($label));
                $icon = $defaults[$key] ?? '✨';
                $aspire_options[$label] = $icon;
            }
        }
    } else {
        // fallback to defaults order if option not set
        foreach ($defaults as $label_key => $icon) {
            $aspire_options[ucwords($label_key)] = $icon;
        }
    }
    $selected = (array) get_user_meta($user->ID, 'aspire', true);
    $action = esc_url( get_permalink() );

    $left = '';
    if ( empty($_GET['step']) || $_GET['step']==='name' ){
        $left .= '<div class="cbk-kicker">Welcome onboard!</div>';
        $left .= '<div class="cbk-title">What should we call you?</div>';
        $left .= '<p class="cbk-hello-sub">We\'ll use your name to personalize your daily CoffeeBrk experience.</p>';
        $left .= '<form class="cbk-form" method="post" action="'.$action.'">';
        $left .= wp_nonce_field('coffeebrk_onboard_name','coffeebrk_onboard_name_nonce',true,false);
        $left .= '<div class="cbk-fields">';
        $left .= '<div class="cbk-field"><div class="cbk-label">Your name</div><input class="cbk-input" type="text" name="first_name" value="'.esc_attr($provided_name).'" required /></div>';
        $left .= '</div>';
        $left .= '<button class="cbk-btn" type="submit">Let\'s brew your feed →</button>';
        $left .= '</form>';
    } elseif ( $_GET['step']==='aspire' ){
        $left .= '<div class="cbk-title">What do you do (or aspire to do)?</div>';
        $left .= '<p class="cbk-hello-sub">We\'ll personalize your daily AI feed around your world.</p>';
        $left .= '<form class="cbk-form" method="post" action="'.$action.'?step=aspire">';
        $left .= wp_nonce_field('coffeebrk_onboard_aspire','coffeebrk_onboard_aspire_nonce',true,false);
        $left .= '<div class="cbk-pills">';
        foreach ($aspire_options as $label => $icon){
            $is = in_array($label, $selected, true) ? ' active' : '';
            $checked = in_array($label,$selected,true) ? 'checked' : '';
            $left .= '<label class="cbk-pill'.$is.'" role="checkbox" aria-checked="'.($checked?'true':'false').'" tabindex="0">'
                 . '<input type="checkbox" name="aspire[]" value="'.esc_attr($label).'" '.$checked.' />'
                 . '<span class="cbk-pill-icon">'.esc_html($icon).'</span> '
                 . '<span class="cbk-pill-label">'.esc_html($label).'</span>'
                 . '</label>';
        }
        $left .= '</div>';
        $left .= '<button class="cbk-btn" type="submit">Start Your Coffee Break</button>';
        $left .= '<script>(function(){document.addEventListener("click",function(e){var p=e.target.closest(".cbk-pill");if(!p)return;var cb=p.querySelector("input[type=checkbox]");if(!cb)return;cb.checked=!cb.checked;p.classList.toggle("active", cb.checked);p.setAttribute("aria-checked", cb.checked?"true":"false");});document.addEventListener("keydown",function(e){if((e.key===" "||e.key==="Enter")&&e.target.classList&&e.target.classList.contains("cbk-pill")){e.preventDefault();e.target.click();}});}());</script>';
        $left .= '</form>';
    }
    return coffeebrk_auth_shell_html( $left );
});

// Hello step (welcome screen)
add_shortcode('coffeebrk_hello', function(){
    coffeebrk_enqueue_auth_styles();
    $login = esc_url( coffeebrk_core_page_url('coffeebrk-login') );
    $left = '';
    $left .= '<div class="cbk-title">👋 Hey there, welcome to Coffee Break</div>';
    $left .= '<p class="cbk-hello-sub">Where curious professionals start their day with 5 minutes of AI — stories, ideas, and tools that matter to you.</p>';
    $left .= '<a class="cbk-btn" href="'.$login.'">Continue</a>';
    return coffeebrk_auth_shell_html( $left );
});

// -------- Early POST handlers to avoid header warnings --------
add_action('template_redirect', function(){
    if ( 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '') ) return;
    $ids = (array) get_option('coffeebrk_core_pages', []);
    // Login POST
    if ( isset($ids['coffeebrk-login']) && is_page( (int) $ids['coffeebrk-login'] ) ){
        if ( isset($_POST['coffeebrk_login_nonce']) && wp_verify_nonce($_POST['coffeebrk_login_nonce'],'coffeebrk_login') ){
            $email = sanitize_email($_POST['email'] ?? '');
            $u = $email ? get_user_by('email', $email) : false;
            $username = $u ? $u->user_login : sanitize_text_field($_POST['email'] ?? '');
            $creds = [ 'user_login'=>$username, 'user_password'=>(string)($_POST['password'] ?? ''), 'remember'=>true ];
            $user = wp_signon($creds, false);
            if ( ! is_wp_error($user) ) { coffeebrk_safe_redirect( coffeebrk_core_page_url('coffeebrk-onboarding') ); }
            add_filter('the_content', function($c) use ($user){ return $c.'<p class="cbk-center" style="color:#f66">'.esc_html($user->get_error_message()).'</p>'; });
        }
        return;
    }
    // Signup POST
    if ( isset($ids['coffeebrk-signup']) && is_page( (int) $ids['coffeebrk-signup'] ) ){
        if ( isset($_POST['coffeebrk_signup_nonce']) && wp_verify_nonce($_POST['coffeebrk_signup_nonce'],'coffeebrk_signup') ){
            $email = sanitize_email($_POST['email'] ?? '');
            $pass = (string) ($_POST['password'] ?? '');
            $pass2 = (string) ($_POST['password2'] ?? '');
            if ( ! $email || ! is_email($email) ) { return; }
            if ( $pass !== $pass2 ) { return; }
            if ( email_exists($email) ) { return; }
            $login = sanitize_user( current( explode('@',$email) ), true );
            if ( username_exists($login) ) { $login = $login . '_' . wp_generate_password(4, false); }
            $uid = wp_create_user($login, $pass, $email);
            if ( is_wp_error($uid) ) { return; }
            wp_set_current_user($uid); wp_set_auth_cookie($uid,true);
            coffeebrk_safe_redirect( coffeebrk_core_page_url('coffeebrk-onboarding') );
        }
        return;
    }
    // Onboarding POST
    if ( isset($ids['coffeebrk-onboarding']) && is_page( (int) $ids['coffeebrk-onboarding'] ) ){
        if ( isset($_POST['coffeebrk_onboard_name_nonce']) && wp_verify_nonce($_POST['coffeebrk_onboard_name_nonce'],'coffeebrk_onboard_name') ){
            $user = wp_get_current_user();
            if ( $user && $user->ID ) {
                $first = sanitize_text_field($_POST['first_name'] ?? '');
                wp_update_user([ 'ID'=>$user->ID, 'first_name'=>$first ]);
                coffeebrk_safe_redirect( add_query_arg('step','aspire', get_permalink( (int) $ids['coffeebrk-onboarding'] ) ) );
            }
        }
        if ( isset($_POST['coffeebrk_onboard_aspire_nonce']) && wp_verify_nonce($_POST['coffeebrk_onboard_aspire_nonce'],'coffeebrk_onboard_aspire') ){
            $user = wp_get_current_user();
            if ( $user && $user->ID ) {
                $vals = array_map('sanitize_text_field', (array)($_POST['aspire'] ?? []));
                $vals = array_values(array_unique(array_filter($vals)));
                update_user_meta($user->ID, 'aspire', $vals );
                update_user_meta($user->ID, 'aspire_csv', implode(', ', $vals) );
                coffeebrk_safe_redirect( coffeebrk_core_app_url() );
            }
        }
        return;
    }
});

// Hide page/site titles and chrome for our auth pages
add_filter('body_class', function($classes){
    $ids = (array) get_option('coffeebrk_core_pages', []);
    if ( is_page( array_values($ids) ) ) { $classes[] = 'coffeebrk-auth'; }
    return $classes;
});

add_filter('document_title_parts', function($parts){
    if ( is_admin() ) return $parts;
    $ids = (array) get_option('coffeebrk_core_pages', []);
    if ( is_page( array_values($ids) ) ) {
        $parts['title'] = '';
        $parts['site'] = '';
    }
    return $parts;
});

add_filter('the_title', function($title, $post_id){
    if ( is_admin() ) return $title; // keep meaningful titles in admin
    $ids = (array) get_option('coffeebrk_core_pages', []);
    if ( in_array($post_id, array_map('intval', array_values($ids)), true) ) {
        return '';
    }
    return $title;
}, 10, 2);

// User profile field: Aspire (CSV)
add_action('show_user_profile', function($user){
    $vals = (array) get_user_meta($user->ID, 'aspire', true);
    $csv  = get_user_meta($user->ID, 'aspire_csv', true);
    if ( ! $csv && $vals ) { $csv = implode(', ', array_map('strval', $vals)); }
    echo '<h2>Coffeebrk – Aspire</h2>';
    echo '<table class="form-table" role="presentation"><tr><th><label for="coffeebrk_aspire_csv">Aspire</label></th><td>';
    echo '<input type="text" name="coffeebrk_aspire_csv" id="coffeebrk_aspire_csv" class="regular-text" value="'.esc_attr($csv).'" />';
    echo '<p class="description">Comma-separated list, e.g. Developer, Designer</p>';
    echo '</td></tr></table>';
});

add_action('edit_user_profile', function($user){
    do_action('show_user_profile', $user);
});

add_action('personal_options_update', function($user_id){
    if ( ! current_user_can('edit_user', $user_id) ) return;
    $csv = isset($_POST['coffeebrk_aspire_csv']) ? wp_unslash($_POST['coffeebrk_aspire_csv']) : '';
    $parts = array_filter(array_map(function($s){ return trim( sanitize_text_field($s) ); }, explode(',', (string) $csv)));
    update_user_meta($user_id, 'aspire', array_values(array_unique($parts)) );
    update_user_meta($user_id, 'aspire_csv', implode(', ', array_values(array_unique($parts))) );
});

add_action('edit_user_profile_update', function($user_id){
    do_action('personal_options_update', $user_id);
});
