<?php
if (!defined('ABSPATH')) exit;

const COFFEEBRK_DYNAMIC_FIELDS_OPT = 'coffeebrk_dynamic_fields';

// Register settings and page for Dynamic Fields
add_action('admin_init', function(){
    register_setting('coffeebrk_dynamic_fields', COFFEEBRK_DYNAMIC_FIELDS_OPT, [
        'type' => 'array',
        'sanitize_callback' => function($input){
            $out = [];
            if (!is_array($input)) return $out;
            foreach ($input as $row){
                $label = sanitize_text_field($row['label'] ?? '');
                $key   = ltrim(strtolower(preg_replace('/[^a-z0-9_]/', '_', (string)($row['key'] ?? ''))), '_');
                $type  = in_array(($row['type'] ?? 'text'), ['text','textarea','url','number','select'], true) ? $row['type'] : 'text';
                $choices = sanitize_text_field($row['choices'] ?? ''); // CSV choices for select
                if (!$label || !$key) continue;
                $out[] = [
                    'id'      => sanitize_key($row['id'] ?? wp_generate_password(6,false,false)),
                    'label'   => $label,
                    'key'     => '_' . $key, // store underscored key for postmeta
                    'type'    => $type,
                    'choices' => $choices,
                ];
            }
            return array_values($out);
        }
    ]);

    add_settings_section('coffeebrk_dyn_section', 'Dynamic Fields', function(){
        echo '<p>Manage custom post meta fields. Keys are saved with an underscore prefix in post meta.</p>';
    }, 'coffeebrk_dynamic_fields');

    add_settings_field('coffeebrk_dyn_table', 'Fields', function(){
        $rows = (array) get_option(COFFEEBRK_DYNAMIC_FIELDS_OPT, []);
        echo '<table class="widefat fixed striped" id="cbk-dyn-table"><thead><tr><th style="width:28%">Label</th><th style="width:28%">Meta Key (without leading _)</th><th style="width:14%">Type</th><th>Choices (CSV, for select)</th><th style="width:80px"></th></tr></thead><tbody>';
        if (empty($rows)) {
            $rows = [ ['id'=>wp_generate_password(6,false,false),'label'=>'','key'=>'_','type'=>'text','choices'=>''] ];
        }
        foreach ($rows as $i=>$r){
            $plainKey = ltrim((string)($r['key'] ?? ''), '_');
            printf('<tr>' .
                '<td><input type="text" name="%1$s[%2$d][label]" value="%3$s" class="regular-text"></td>' .
                '<td><input type="text" name="%1$s[%2$d][key]" value="%4$s" class="regular-text" placeholder="e.g. source_name"></td>' .
                '<td><select name="%1$s[%2$d][type]"><option value="text" %5$s>text</option><option value="textarea" %6$s>textarea</option><option value="url" %7$s>url</option><option value="number" %8$s>number</option><option value="select" %9$s>select</option></select></td>' .
                '<td><input type="text" name="%1$s[%2$d][choices]" value="%10$s" placeholder="one, two, three" class="regular-text"></td>' .
                '<td><button type="button" class="button cbk-row-del">Delete</button></td>' .
                '<input type="hidden" name="%1$s[%2$d][id]" value="%11$s">' .
                '</tr>',
                esc_attr(COFFEEBRK_DYNAMIC_FIELDS_OPT),
                (int)$i,
                esc_attr($r['label'] ?? ''),
                esc_attr($plainKey),
                selected(($r['type'] ?? '')==='text', true, false),
                selected(($r['type'] ?? '')==='textarea', true, false),
                selected(($r['type'] ?? '')==='url', true, false),
                selected(($r['type'] ?? '')==='number', true, false),
                selected(($r['type'] ?? '')==='select', true, false),
                esc_attr($r['choices'] ?? ''),
                esc_attr($r['id'] ?? '')
            );
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button button-primary" id="cbk-add-row">Add Field</button></p>';
        echo '<script>(function(){const tbl=document.getElementById("cbk-dyn-table").getElementsByTagName("tbody")[0];document.getElementById("cbk-add-row").addEventListener("click",()=>{const idx=tbl.children.length;const tr=document.createElement("tr");tr.innerHTML=`'
            .'<td><input type=\"text\" name=\"'.esc_js(COFFEEBRK_DYNAMIC_FIELDS_OPT).'[${idx}][label]\" value=\"\" class=\"regular-text\"></td>'
            .'<td><input type=\"text\" name=\"'.esc_js(COFFEEBRK_DYNAMIC_FIELDS_OPT).'[${idx}][key]\" value=\"\" class=\"regular-text\" placeholder=\"e.g. source_name\"></td>'
            .'<td><select name=\"'.esc_js(COFFEEBRK_DYNAMIC_FIELDS_OPT).'[${idx}][type]\"><option value=\"text\">text</option><option value=\"textarea\">textarea</option><option value=\"url\">url</option><option value=\"number\">number</option><option value=\"select\">select</option></select></td>'
            .'<td><input type=\"text\" name=\"'.esc_js(COFFEEBRK_DYNAMIC_FIELDS_OPT).'[${idx}][choices]\" value=\"\" class=\"regular-text\" placeholder=\"one, two, three\"></td>'
            .'<td><button type=\"button\" class=\"button cbk-row-del\">Delete</button></td>'
            .'<input type=\"hidden\" name=\"'.esc_js(COFFEEBRK_DYNAMIC_FIELDS_OPT).'[${idx}][id]\" value=\"new${Date.now()}\">`;
            tbl.appendChild(tr);});tbl.addEventListener("click",(e)=>{if(e.target&&e.target.classList.contains("cbk-row-del")){e.target.closest("tr").remove();}});})();</script>';
    }, 'coffeebrk_dynamic_fields', 'coffeebrk_dyn_section');
});

// Submenu under Coffeebrk Core
add_action('admin_menu', function(){
    add_submenu_page(
        'coffeebrk-core',
        'Dynamic Fields',
        'Dynamic Fields',
        'manage_options',
        'coffeebrk-core-dynfields',
        function(){
            if (!current_user_can('manage_options')) return;
            echo '<div class="wrap"><h1>Dynamic Fields</h1><form method="post" action="options.php">';
            settings_fields('coffeebrk_dynamic_fields');
            do_settings_sections('coffeebrk_dynamic_fields');
            submit_button();
            echo '</form></div>';
        }
    );
});
