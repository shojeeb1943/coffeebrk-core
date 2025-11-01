<?php
if (!defined('ABSPATH')) exit;

// Render and save a single meta box for all dynamic fields
add_action('add_meta_boxes', function(){
    $fields = (array) get_option('coffeebrk_dynamic_fields', []);
    if (empty($fields)) return;
    $post_types = apply_filters('coffeebrk_meta_post_types', ['post']);
    foreach ($post_types as $pt){
        add_meta_box(
            'coffeebrk_meta_dynamic',
            'Coffeebrk Meta',
            function($post) use ($fields){
                wp_nonce_field('coffeebrk_meta_dynamic_save','coffeebrk_meta_dynamic_nonce');
                echo '<table class="form-table" role="presentation">';
                foreach ($fields as $f){
                    $label = esc_html($f['label'] ?? '');
                    $key   = (string)($f['key'] ?? ''); // already underscored
                    $type  = (string)($f['type'] ?? 'text');
                    $choices = array_filter(array_map('trim', explode(',', (string)($f['choices'] ?? ''))));
                    if (!$key || !$label) continue;
                    $val = get_post_meta($post->ID, $key, true);
                    echo '<tr><th><label for="'.esc_attr($key).'">'.$label.'</label></th><td>';
                    switch ($type){
                        case 'textarea':
                            printf('<textarea name="coffeebrk_meta[%s]" id="%s" rows="4" style="width:100%%;">%s</textarea>', esc_attr($key), esc_attr($key), esc_textarea($val));
                            break;
                        case 'url':
                            printf('<input type="url" name="coffeebrk_meta[%s]" id="%s" value="%s" class="regular-text" style="width:100%%;"/>', esc_attr($key), esc_attr($key), esc_attr($val));
                            break;
                        case 'number':
                            printf('<input type="number" step="any" name="coffeebrk_meta[%s]" id="%s" value="%s" class="regular-text" style="width:100%%;"/>', esc_attr($key), esc_attr($key), esc_attr($val));
                            break;
                        case 'select':
                            echo '<select name="coffeebrk_meta['.esc_attr($key).']" id="'.esc_attr($key).'" style="min-width:240px;">';
                            echo '<option value="">— Select —</option>';
                            foreach ($choices as $c){
                                printf('<option value="%s" %s>%s</option>', esc_attr($c), selected($val, $c, false), esc_html($c));
                            }
                            echo '</select>';
                            break;
                        default: // text
                            printf('<input type="text" name="coffeebrk_meta[%s]" id="%s" value="%s" class="regular-text" style="width:100%%;"/>', esc_attr($key), esc_attr($key), esc_attr($val));
                    }
                    echo '</td></tr>';
                }
                echo '</table>';
            },
            $pt,
            'normal',
            'high'
        );
    }
});

add_action('save_post', function($post_id){
    if (!isset($_POST['coffeebrk_meta_dynamic_nonce']) || !wp_verify_nonce($_POST['coffeebrk_meta_dynamic_nonce'],'coffeebrk_meta_dynamic_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $fields = (array) get_option('coffeebrk_dynamic_fields', []);
    $incoming = (array) ($_POST['coffeebrk_meta'] ?? []);
    foreach ($fields as $f){
        $key = (string)($f['key'] ?? '');
        $type = (string)($f['type'] ?? 'text');
        if (!$key) continue;
        $raw = $incoming[$key] ?? '';
        switch ($type){
            case 'textarea': $val = sanitize_textarea_field($raw); break;
            case 'url': $val = esc_url_raw($raw); break;
            case 'number': $val = is_numeric($raw) ? $raw + 0 : ''; break;
            case 'select': $val = sanitize_text_field($raw); break;
            default: $val = sanitize_text_field($raw);
        }
        if ($val === '' || $val === null) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $val);
        }
    }
});
