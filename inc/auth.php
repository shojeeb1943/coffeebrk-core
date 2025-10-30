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

// -------- Content gating (force login) --------
add_action('template_redirect', function(){
    if ( is_admin() || defined('REST_REQUEST') || wp_doing_cron() ) return;
    if ( is_user_logged_in() ) return;
    $ids = (array) get_option('coffeebrk_core_pages', []);
    if ( is_page( array_values($ids) ) ) return; // allow our auth/onboarding pages
    wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') );
    exit;
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

// -------- Shortcodes: markup + handlers --------
function coffeebrk_enqueue_auth_styles(){
    $css = 'body{background:#111!important;color:#f6f2e8}.cbk-wrap{min-height:70vh;display:flex;align-items:center;justify-content:center}.cbk-card{max-width:520px;margin:0 auto}.cbk-title{font-size:40px;font-weight:600;text-align:center;margin-bottom:24px;color:#f5e6c8;font-family:Georgia,serif}.cbk-input{width:100%;padding:12px 14px;border-radius:8px;background:#1a1a1a;border:1px solid #333;color:#fff;margin:10px 0}.cbk-btn{display:inline-block;background:#8b5e2e;color:#fff;border:none;border-radius:8px;padding:12px 18px;cursor:pointer;width:100%;margin-top:10px}.cbk-btn:hover{background:#a8743c}.cbk-divider{height:1px;background:#333;margin:24px 0}.cbk-center{text-align:center}.cbk-google{display:block;background:#fff;color:#222;border-radius:8px;padding:10px 12px;text-align:center}.cbk-pills{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}.cbk-pill{padding:10px 14px;border:1px solid #3a3a3a;border-radius:999px;color:#ddd;cursor:pointer;background:#141414}.cbk-pill.active{background:#0f3d2e;border-color:#1d5f49}';
    wp_register_style('coffeebrk-auth-inline', false);
    wp_enqueue_style('coffeebrk-auth-inline');
    wp_add_inline_style('coffeebrk-auth-inline', $css);
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

add_shortcode('coffeebrk_login', function(){
    if ( is_user_logged_in() ) return '<div class="cbk-wrap"><div class="cbk-card"><p class="cbk-center">You are already logged in.</p></div></div>';
    coffeebrk_enqueue_auth_styles();
    coffeebrk_enqueue_supabase_assets('login');
    $action = esc_url( get_permalink() );
    $out = '<div class="cbk-wrap"><div class="cbk-card">';
    $out .= '<div class="cbk-title">Login to your account</div>';
    $out .= '<form method="post" action="'.$action.'">';
    $out .= wp_nonce_field('coffeebrk_login','coffeebrk_login_nonce',true,false);
    $out .= '<input class="cbk-input" type="email" name="email" placeholder="Email" required />';
    $out .= '<input class="cbk-input" type="password" name="password" placeholder="Password" required />';
    $out .= '<button class="cbk-btn" type="submit">Login</button>';
    $out .= '</form>';
    $out .= '<div class="cbk-divider"></div>';
    $out .= '<button type="button" id="coffeebrk-google-btn" class="cbk-google">Sign in with Google</button>';
    $out .= '<p class="cbk-center" style="margin-top:12px;">Don\'t have an account? <a href="'.esc_url( coffeebrk_core_page_url('coffeebrk-signup') ).'">Sign Up</a></p>';
    $out .= '</div></div>';
    // Handle POST
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['coffeebrk_login_nonce']) && wp_verify_nonce($_POST['coffeebrk_login_nonce'],'coffeebrk_login') ){
        $email = sanitize_email($_POST['email'] ?? '');
        $user = $email ? get_user_by('email', $email) : false;
        $username = $user ? $user->user_login : sanitize_text_field($_POST['email'] ?? '');
        $creds = [
            'user_login' => $username,
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember' => true,
        ];
        $user = wp_signon($creds, false);
        if ( ! is_wp_error($user) ) { wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-onboarding') ); exit; }
        $out .= '<p class="cbk-center" style="color:#f66">'.esc_html($user->get_error_message()).'</p>';
    }
    return $out;
});

add_shortcode('coffeebrk_signup', function(){
    if ( is_user_logged_in() ) { wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-onboarding') ); exit; }
    coffeebrk_enqueue_auth_styles();
    coffeebrk_enqueue_supabase_assets('signup');
    $action = esc_url( get_permalink() );
    $out = '<div class="cbk-wrap"><div class="cbk-card">';
    $out .= '<div class="cbk-title">Create Account</div>';
    $out .= '<form method="post" action="'.$action.'">';
    $out .= wp_nonce_field('coffeebrk_signup','coffeebrk_signup_nonce',true,false);
    $out .= '<input class="cbk-input" type="email" name="email" placeholder="Email" required />';
    $out .= '<input class="cbk-input" type="password" name="password" placeholder="Password" required />';
    $out .= '<input class="cbk-input" type="password" name="password2" placeholder="Confirm Password" required />';
    $out .= '<button class="cbk-btn" type="submit">Sign Up</button>';
    $out .= '</form>';
    $out .= '<div class="cbk-divider"></div>';
    $out .= '<button type="button" id="coffeebrk-google-btn" class="cbk-google">Sign in with Google</button>';
    $out .= '<p class="cbk-center" style="margin-top:12px;">Already have an account? <a href="'.esc_url( coffeebrk_core_page_url('coffeebrk-login') ).'">Log In</a></p>';
    $out .= '</div></div>';
    // Handle POST
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['coffeebrk_signup_nonce']) && wp_verify_nonce($_POST['coffeebrk_signup_nonce'],'coffeebrk_signup') ){
        $email = sanitize_email($_POST['email'] ?? '');
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if ( ! $email || ! is_email($email) ) { return $out.'<p class="cbk-center" style="color:#f66">Invalid email.</p>'; }
        if ( $pass !== $pass2 ) { return $out.'<p class="cbk-center" style="color:#f66">Passwords do not match.</p>'; }
        if ( email_exists($email) ) { return $out.'<p class="cbk-center" style="color:#f66">Email already registered.</p>'; }
        $login = sanitize_user( current( explode('@',$email) ), true );
        if ( username_exists($login) ) { $login = $login . '_' . wp_generate_password(4, false); }
        $uid = wp_create_user($login, $pass, $email);
        if ( is_wp_error($uid) ) { return $out.'<p class="cbk-center" style="color:#f66">'.esc_html($uid->get_error_message()).'</p>'; }
        wp_set_current_user($uid); wp_set_auth_cookie($uid,true);
        wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-onboarding') ); exit;
    }
    return $out;
});

add_shortcode('coffeebrk_onboarding', function(){
    if ( ! is_user_logged_in() ) { wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') ); exit; }
    coffeebrk_enqueue_auth_styles();
    $user = wp_get_current_user();
    $provided_name = $user->first_name ?: '';
    $aspire_options = [ 'Developer','Designer','Marketer','Writer / Content Creator','Product Manager','Data / AI Engineer','Student / Explorer' ];
    $selected = (array) get_user_meta($user->ID, 'aspire', true);
    $action = esc_url( get_permalink() );

    $out = '<div class="cbk-wrap"><div class="cbk-card">';
    if ( empty($_GET['step']) || $_GET['step']==='name' ){
        $out .= '<div class="cbk-title">Let\'s get personal – what should we call you?</div>';
        $out .= '<form method="post" action="'.$action.'">';
        $out .= wp_nonce_field('coffeebrk_onboard_name','coffeebrk_onboard_name_nonce',true,false);
        $out .= '<input class="cbk-input" type="text" name="first_name" placeholder="Full Name" value="'.esc_attr($provided_name).'" required />';
        $out .= '<button class="cbk-btn" type="submit">Let\'s brew your feed →</button>';
        $out .= '</form>';
    } elseif ( $_GET['step']==='aspire' ){
        $out .= '<div class="cbk-title">What do you do (or aspire to do)?</div>';
        $out .= '<form method="post" action="'.$action.'?step=aspire">';
        $out .= wp_nonce_field('coffeebrk_onboard_aspire','coffeebrk_onboard_aspire_nonce',true,false);
        $out .= '<div class="cbk-pills">';
        foreach ($aspire_options as $opt){
            $is = in_array($opt, $selected, true) ? ' active' : '';
            $out .= '<label class="cbk-pill'.$is.'"><input style="display:none" type="checkbox" name="aspire[]" value="'.esc_attr($opt).'" '.checked(true, in_array($opt,$selected,true), false).' />'.esc_html($opt).'</label>';
        }
        $out .= '</div>';
        $out .= '<div class="cbk-center" style="margin-top:16px;"><button class="cbk-btn" type="submit">Start Your Coffee Break</button></div>';
        $out .= '<script>document.querySelectorAll(".cbk-pill").forEach(p=>{p.addEventListener("click",()=>{const cb=p.querySelector("input"); cb.checked=!cb.checked; p.classList.toggle("active");});});</script>';
        $out .= '</form>';
    }
    $out .= '</div></div>';

    // Handle steps
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] ){
        if ( isset($_POST['coffeebrk_onboard_name_nonce']) && wp_verify_nonce($_POST['coffeebrk_onboard_name_nonce'],'coffeebrk_onboard_name') ){
            $first = sanitize_text_field($_POST['first_name'] ?? '');
            wp_update_user([ 'ID'=>$user->ID, 'first_name'=>$first ]);
            wp_safe_redirect( add_query_arg('step','aspire', get_permalink() ) ); exit;
        }
        if ( isset($_POST['coffeebrk_onboard_aspire_nonce']) && wp_verify_nonce($_POST['coffeebrk_onboard_aspire_nonce'],'coffeebrk_onboard_aspire') ){
            $vals = array_map('sanitize_text_field', (array)($_POST['aspire'] ?? []));
            update_user_meta($user->ID, 'aspire', array_values(array_unique($vals)) );
            wp_safe_redirect( home_url('/') ); exit;
        }
    }

    return $out;
});
