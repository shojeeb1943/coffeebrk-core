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

// -------- Google OAuth routes --------
add_action('init', function(){
    add_rewrite_rule('^coffeebrk-oauth/google/?', 'index.php?coffeebrk_oauth=google', 'top');
    add_rewrite_tag('%coffeebrk_oauth%', '([^&]+)');
});

add_action('template_redirect', function(){
    $q = get_query_var('coffeebrk_oauth');
    if ( $q !== 'google' ) return;
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'start';
    if ( $action === 'start' ) {
        coffeebrk_google_oauth_start();
    } else {
        coffeebrk_google_oauth_callback();
    }
    exit;
});

function coffeebrk_google_oauth_start(){
    $client_id = coffeebrk_core_get_option('google_client_id');
    $redirect_uri = add_query_arg(['action'=>'callback'], home_url('/coffeebrk-oauth/google'));
    $state = wp_create_nonce('coffeebrk_google_state');
    $params = [
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'consent',
        'access_type' => 'offline',
    ];
    wp_safe_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params) );
    exit;
}

function coffeebrk_google_oauth_callback(){
    if ( ! isset($_GET['state']) || ! wp_verify_nonce($_GET['state'], 'coffeebrk_google_state') ) {
        coffeebrk_log_error('Google OAuth invalid state');
        wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') );
        return;
    }
    $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
    if ( ! $code ) { wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') ); return; }
    $client_id = coffeebrk_core_get_option('google_client_id');
    $client_secret = coffeebrk_core_get_option('google_client_secret');
    $redirect_uri = add_query_arg(['action'=>'callback'], home_url('/coffeebrk-oauth/google'));
    $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => [
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        ]
    ]);
    if ( is_wp_error($resp) ) { coffeebrk_log_error('Google token error', ['err'=>$resp->get_error_message()]); wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') ); return; }
    $data = json_decode( wp_remote_retrieve_body($resp), true );
    $access = $data['access_token'] ?? '';
    if ( ! $access ) { coffeebrk_log_error('Google token missing'); wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') ); return; }
    $u = wp_remote_get('https://openidconnect.googleapis.com/v1/userinfo', [ 'headers' => [ 'Authorization' => 'Bearer ' . $access ]]);
    if ( is_wp_error($u) ) { coffeebrk_log_error('Google userinfo error', ['err'=>$u->get_error_message()]); wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') ); return; }
    $info = json_decode( wp_remote_retrieve_body($u), true );
    $email = sanitize_email( $info['email'] ?? '' );
    if ( ! $email ) { wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') ); return; }
    $user = get_user_by('email', $email);
    if ( ! $user ) {
        $login = sanitize_user( current( explode('@', $email) ), true );
        if ( username_exists($login) ) { $login = $login . '_' . wp_generate_password(4, false); }
        $uid = wp_create_user( $login, wp_generate_password(20), $email );
        if ( ! is_wp_error($uid) ) {
            $user = get_user_by('id', $uid);
            wp_update_user([ 'ID'=>$uid, 'first_name' => sanitize_text_field( $info['given_name'] ?? ($info['name'] ?? '') ) ]);
        }
    }
    if ( $user instanceof WP_User ) {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-onboarding') );
        return;
    }
    wp_safe_redirect( coffeebrk_core_page_url('coffeebrk-login') );
}

// -------- Shortcodes: markup + handlers --------
function coffeebrk_enqueue_auth_styles(){
    $css = 'body{background:#111!important;color:#f6f2e8}.cbk-wrap{min-height:70vh;display:flex;align-items:center;justify-content:center}.cbk-card{max-width:520px;margin:0 auto}.cbk-title{font-size:40px;font-weight:600;text-align:center;margin-bottom:24px;color:#f5e6c8;font-family:Georgia,serif}.cbk-input{width:100%;padding:12px 14px;border-radius:8px;background:#1a1a1a;border:1px solid #333;color:#fff;margin:10px 0}.cbk-btn{display:inline-block;background:#8b5e2e;color:#fff;border:none;border-radius:8px;padding:12px 18px;cursor:pointer;width:100%;margin-top:10px}.cbk-btn:hover{background:#a8743c}.cbk-divider{height:1px;background:#333;margin:24px 0}.cbk-center{text-align:center}.cbk-google{display:block;background:#fff;color:#222;border-radius:8px;padding:10px 12px;text-align:center}.cbk-pills{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}.cbk-pill{padding:10px 14px;border:1px solid #3a3a3a;border-radius:999px;color:#ddd;cursor:pointer;background:#141414}.cbk-pill.active{background:#0f3d2e;border-color:#1d5f49}';
    wp_register_style('coffeebrk-auth-inline', false);
    wp_enqueue_style('coffeebrk-auth-inline');
    wp_add_inline_style('coffeebrk-auth-inline', $css);
}

add_shortcode('coffeebrk_login', function(){
    if ( is_user_logged_in() ) return '<div class="cbk-wrap"><div class="cbk-card"><p class="cbk-center">You are already logged in.</p></div></div>';
    coffeebrk_enqueue_auth_styles();
    $action = esc_url( get_permalink() );
    $google_url = esc_url( add_query_arg(['action'=>'start'], home_url('/coffeebrk-oauth/google')) );
    $out = '<div class="cbk-wrap"><div class="cbk-card">';
    $out .= '<div class="cbk-title">Login to your account</div>';
    $out .= '<form method="post" action="'.$action.'">';
    $out .= wp_nonce_field('coffeebrk_login','coffeebrk_login_nonce',true,false);
    $out .= '<input class="cbk-input" type="email" name="email" placeholder="Email" required />';
    $out .= '<input class="cbk-input" type="password" name="password" placeholder="Password" required />';
    $out .= '<button class="cbk-btn" type="submit">Login</button>';
    $out .= '</form>';
    $out .= '<div class="cbk-divider"></div>';
    $out .= '<a class="cbk-google" href="'.$google_url.'">Sign in with Google</a>';
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
    $action = esc_url( get_permalink() );
    $google_url = esc_url( add_query_arg(['action'=>'start'], home_url('/coffeebrk-oauth/google')) );
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
    $out .= '<a class="cbk-google" href="'.$google_url.'">Sign in with Google</a>';
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
