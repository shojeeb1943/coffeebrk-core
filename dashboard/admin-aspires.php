<?php
if (!defined('ABSPATH')) exit;

const COFFEEBRK_ASPIRES_OPT = 'coffeebrk_aspires';

add_action('admin_init', function(){
    register_setting('coffeebrk_aspires', COFFEEBRK_ASPIRES_OPT, [
        'type' => 'array',
        'sanitize_callback' => function($input){
            $out = [];
            if (is_array($input)){
                foreach ($input as $item){
                    $label = trim(sanitize_text_field($item));
                    if ($label !== '') $out[] = $label;
                }
            } elseif (is_string($input)) {
                foreach (explode("\n", $input) as $line){
                    $label = trim(sanitize_text_field($line));
                    if ($label !== '') $out[] = $label;
                }
            }
            $out = array_values(array_unique($out));
            return $out;
        }
    ]);

    add_settings_section('coffeebrk_aspires_section', 'Aspire Manager', function(){
        echo '<p>Manage the list of available aspire options for onboarding and post mapping.</p>';
    }, 'coffeebrk_aspires');

    add_settings_field('coffeebrk_aspires_field', 'Aspires (one per line)', function(){
        $vals = (array) get_option(COFFEEBRK_ASPIRES_OPT, []);
        echo '<textarea name="'.esc_attr(COFFEEBRK_ASPIRES_OPT).'" rows="8" style="width:100%">'.esc_textarea(implode("\n", $vals)).'</textarea>';
    }, 'coffeebrk_aspires', 'coffeebrk_aspires_section');
});

add_action('admin_menu', function(){
    add_submenu_page(
        'coffeebrk-core',
        'Aspire Manager',
        'Aspire Manager',
        'manage_options',
        'coffeebrk-core-aspires',
        function(){
            if (!current_user_can('manage_options')) return;
            echo '<div class="wrap"><h1>Aspire Manager</h1><form method="post" action="options.php">';
            settings_fields('coffeebrk_aspires');
            do_settings_sections('coffeebrk_aspires');
            submit_button();
            echo '</form></div>';
        }
    );
});
