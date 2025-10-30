<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Admin settings page for Supabase credentials / auth
add_action('admin_menu', function() {
    add_options_page('Coffeebrk Auth', 'Coffeebrk Auth', 'manage_options', 'coffeebrk-auth-settings', 'coffeebrk_auth_settings_page');
});
add_action('admin_init', function() {
    register_setting('coffeebrk_auth_options', 'coffeebrk_core_settings');
    add_settings_section('coffeebrk_auth_section','Supabase / Auth Settings',function(){echo'<p>Configure Supabase credentials.</p>';},'coffeebrk_auth_options');
    foreach([
        'supabase_url'=>'Supabase URL',
        'supabase_anon_key'=>'Supabase Anon Key',
        'supabase_service_role'=>'Supabase Service Role Key (server-only)'
    ] as $key=>$label){
        add_settings_field($key,$label,function()use($key){$opt=get_option('coffeebrk_core_settings',[]);$type=$key==='supabase_service_role'?'password':'text';printf('<input type="%s" name="coffeebrk_core_settings[%s]" value="%s" style="width:100%%;">',$type,$key,esc_attr($opt[$key]??''));},'coffeebrk_auth_options','coffeebrk_auth_section');
    }
});
function coffeebrk_auth_settings_page(){if(!current_user_can('manage_options'))return;echo'<div class="wrap"><h1>Coffeebrk Auth Settings</h1><form method="post" action="options.php">';settings_fields('coffeebrk_auth_options');do_settings_sections('coffeebrk_auth_options');submit_button();echo'</form></div>';}
