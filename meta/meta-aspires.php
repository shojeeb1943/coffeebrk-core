<?php
if (!defined('ABSPATH')) exit;

// Meta box: Aspires for this post
add_action('add_meta_boxes', function(){
    $post_types = apply_filters('coffeebrk_meta_post_types', ['post']);
    foreach ($post_types as $pt){
        add_meta_box('coffeebrk_post_aspires','Aspires for this post', function($post){
            $opts = (array) get_option('coffeebrk_aspires', []);
            $saved = (array) get_post_meta($post->ID, '_post_aspires', true);
            wp_nonce_field('coffeebrk_post_aspires_save','coffeebrk_post_aspires_nonce');
            if (empty($opts)) { echo '<p>No aspires configured. Configure them under Coffeebrk Core → Aspire Manager.</p>'; return; }
            echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
            foreach ($opts as $label){
                $checked = in_array($label, $saved, true) ? 'checked' : '';
                echo '<label style="border:1px solid #ddd;padding:6px 10px;border-radius:999px;">';
                echo '<input type="checkbox" name="coffeebrk_post_aspires[]" value="'.esc_attr($label).'" '.$checked.'> '.esc_html($label);
                echo '</label>';
            }
            echo '</div>';
        }, $pt, 'side', 'default');
    }
});

add_action('save_post', function($post_id){
    if (!isset($_POST['coffeebrk_post_aspires_nonce']) || !wp_verify_nonce($_POST['coffeebrk_post_aspires_nonce'],'coffeebrk_post_aspires_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $vals = array_map('sanitize_text_field', (array)($_POST['coffeebrk_post_aspires'] ?? []));
    $vals = array_values(array_unique(array_filter($vals)));
    if (empty($vals)) {
        delete_post_meta($post_id, '_post_aspires');
        delete_post_meta($post_id, '_post_aspires_csv');
    } else {
        update_post_meta($post_id, '_post_aspires', $vals);
        update_post_meta($post_id, '_post_aspires_csv', implode(', ', $vals));
    }
});
