<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('rest_api_init',function(){
    register_rest_route('coffeebrk/v1','/auth',['methods'=>'POST','callback'=>'coffeebrk_handle_auth','permission_callback'=>'__return_true']);
});
function coffeebrk_handle_auth(WP_REST_Request $r){
    $data=$r->get_json_params();$token=$data['access_token']??'';
    if(!$token)return['success'=>false,'msg'=>'Missing access token'];
    $opts=get_option('coffeebrk_core_settings',[]);
    $url=rtrim($opts['supabase_url']??'','/').'/auth/v1/user';
    $res=wp_remote_get($url,['headers'=>['Authorization'=>'Bearer '.$token,'apiKey'=>$opts['supabase_service_role']??''],'timeout'=>15]);
    if(is_wp_error($res))return['success'=>false,'msg'=>$res->get_error_message()];
    $body=json_decode(wp_remote_retrieve_body($res),true);
    if(empty($body['email']))return['success'=>false,'msg'=>'Invalid Supabase response'];
    $email=$body['email'];$sid=$body['id']??'';
    $user=get_user_by('email',$email);
    if(!$user){
        $login=sanitize_user( strstr($email,'@',true) ?: $email );
        $u=wp_create_user($login,wp_generate_password(24),$email);
        if(is_wp_error($u))return['success'=>false,'msg'=>$u->get_error_message()];
        update_user_meta($u,'coffeebrk_supabase_id',$sid);
        $user=get_user_by('id',$u);
    } else {
        if($sid) update_user_meta($user->ID,'coffeebrk_supabase_id',$sid);
    }
    wp_set_current_user($user->ID);wp_set_auth_cookie($user->ID);
    return['success'=>true,'user'=>$user->user_email];
}
