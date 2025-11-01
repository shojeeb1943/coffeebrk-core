<?php
if (!defined('ABSPATH')) exit;

const COFFEEBRK_ASPIRES_OPT = 'coffeebrk_aspires';

add_action('admin_init', function(){
    register_setting('coffeebrk_aspires', COFFEEBRK_ASPIRES_OPT, [
        'type' => 'array',
        'sanitize_callback' => function($input){
            $out = [];
            // Back-compat: if user submitted textarea string
            if (is_string($input)) {
                foreach (explode("\n", $input) as $line){
                    $label = trim(sanitize_text_field($line)); if ($label==='') continue;
                    $out[] = ['label'=>$label,'icon'=>''];
                }
                return $out;
            }
            // New structure: rows with label+icon
            if (is_array($input)){
                foreach ($input as $row){
                    $label = trim(sanitize_text_field($row['label'] ?? ''));
                    $icon  = trim(sanitize_text_field($row['icon'] ?? ''));
                    if ($label==='') continue;
                    $out[] = ['label'=>$label,'icon'=>$icon];
                }
            }
            return array_values($out);
        }
    ]);

    add_settings_section('coffeebrk_aspires_section', 'Aspire Manager', function(){
        echo '<p>Manage onboarding aspire options. Add an emoji or short icon and a label. Icons render at ~18px beside the label.</p>';
    }, 'coffeebrk_aspires');

    add_settings_field('coffeebrk_aspires_field', 'Aspires', function(){
        $rows = (array) get_option(COFFEEBRK_ASPIRES_OPT, []);
        // Back-compat display if old format found
        if (!empty($rows) && is_string(reset($rows))) {
            echo '<p>Legacy format detected. Saving will convert to icon+label rows.</p>';
            echo '<textarea name="'.esc_attr(COFFEEBRK_ASPIRES_OPT).'" rows="8" style="width:100%">'.esc_textarea(implode("\n", $rows)).'</textarea>';
            return;
        }
        if (empty($rows)) { $rows = [ ['icon'=>'🧑‍💻','label'=>'Developer'] ]; }
        echo '<table class="widefat fixed striped" id="cbk-asp-table"><thead><tr><th style="width:120px">Icon (emoji)</th><th>Label</th><th style="width:80px"></th></tr></thead><tbody>';
        foreach ($rows as $i=>$r){
            printf('<tr>'
                .'<td><input type="text" name="%1$s[%2$d][icon]" value="%3$s" class="regular-text" placeholder="🧑‍💻" style="max-width:80px"></td>'
                .'<td><input type="text" name="%1$s[%2$d][label]" value="%4$s" class="regular-text" placeholder="Developer"></td>'
                .'<td><button type="button" class="button cbk-row-del">Delete</button></td>'
                .'</tr>',
                esc_attr(COFFEEBRK_ASPIRES_OPT), (int)$i, esc_attr($r['icon'] ?? ''), esc_attr($r['label'] ?? '')
            );
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="cbk-asp-add">Add Aspire</button></p>';
        echo '<p class="description">Tip: Use emoji or a short unicode icon. It will be displayed at ~18px next to the label.</p>';
        echo '<script>(function(){const tbl=document.getElementById("cbk-asp-table").getElementsByTagName("tbody")[0];document.getElementById("cbk-asp-add").addEventListener("click",()=>{const idx=tbl.children.length;const tr=document.createElement("tr");tr.innerHTML=`'
            .'<td><input type=\"text\" name=\"'.esc_js(COFFEEBRK_ASPIRES_OPT).'[${idx}][icon]\" value=\"\" class=\"regular-text\" placeholder=\"🧑‍💻\" style=\"max-width:80px\"></td>'
            .'<td><input type=\"text\" name=\"'.esc_js(COFFEEBRK_ASPIRES_OPT).'[${idx}][label]\" value=\"\" class=\"regular-text\" placeholder=\"Developer\"></td>'
            .'<td><button type=\"button\" class=\"button cbk-row-del\">Delete</button></td>`;tbl.appendChild(tr);});tbl.addEventListener("click",(e)=>{if(e.target&&e.target.classList.contains("cbk-row-del")){e.target.closest("tr").remove();}});})();</script>';
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
