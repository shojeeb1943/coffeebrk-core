<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('admin_menu',function(){
    add_menu_page('Coffeebrk Dashboard','Coffeebrk','manage_options','coffeebrk-dashboard','coffeebrk_dashboard_page','dashicons-coffee',6);
});
function coffeebrk_dashboard_page(){
    if(!current_user_can('manage_options'))return;
    $opts=get_option('coffeebrk_core_settings',[]);
    echo'<div class="wrap"><h1>Coffeebrk Dashboard</h1>';
    echo'<h2>Auth Status</h2><table class="widefat striped"><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
    echo'<tr><td>Supabase URL</td><td>'.esc_html($opts['supabase_url']??'–').'</td></tr>';
    echo'<tr><td>Anon Key Set</td><td>'.(!empty($opts['supabase_anon_key'])?'Yes':'No').'</td></tr>';
    echo'<tr><td>Service Role Set</td><td>'.(!empty($opts['supabase_service_role'])?'Yes':'No').'</td></tr>';
    echo'</tbody></table><h2>Linked Users</h2>';
    $users=get_users(['meta_key'=>'coffeebrk_supabase_id']);
    if($users){echo'<table class="widefat striped"><thead><tr><th>ID</th><th>Email</th><th>Supabase ID</th></tr></thead><tbody>';
    foreach($users as $u){printf('<tr><td>%d</td><td>%s</td><td>%s</td></tr>',$u->ID,esc_html($u->user_email),esc_html(get_user_meta($u->ID,'coffeebrk_supabase_id',true)));}
    echo'</tbody></table>';}else{echo'<p>No linked users yet.</p>';}
    echo'</div>';
}
