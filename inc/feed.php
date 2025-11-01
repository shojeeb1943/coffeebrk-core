<?php
if (!defined('ABSPATH')) exit;

// /wp-json/coffeebrk/v1/feed - returns posts matching user's aspire CSV
add_action('rest_api_init', function(){
    register_rest_route('coffeebrk/v1', '/feed', [
        'methods'  => 'GET',
        'permission_callback' => function(){ return is_user_logged_in(); },
        'callback' => function( WP_REST_Request $req ){
            $user = wp_get_current_user();
            $aspire_csv = get_user_meta($user->ID, 'aspire_csv', true);
            $needles = array_filter(array_map('trim', explode(',', (string) $aspire_csv)));
            $paged = max(1, (int) $req->get_param('page'));
            $ppp   = max(1, min(50, (int) $req->get_param('per_page') ?: 10));

            $q = new WP_Query([
                'post_type' => apply_filters('coffeebrk_meta_post_types', ['post']),
                'post_status' => 'publish',
                'paged' => $paged,
                'posts_per_page' => $ppp,
                'meta_query' => [
                    [
                        'key' => '_post_aspires_csv',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]);

            $items = [];
            foreach ($q->posts as $p){
                $csv = (string) get_post_meta($p->ID, '_post_aspires_csv', true);
                $hay = array_map('trim', explode(',', $csv));
                $match = array_values(array_intersect($needles, $hay));
                if (!empty($needles) && empty($match)) continue; // only include matching posts if user has aspires
                $items[] = [
                    'id' => $p->ID,
                    'title' => get_the_title($p),
                    'excerpt' => wp_trim_words( wp_strip_all_tags( get_post_field('post_content', $p->ID) ), 30 ),
                    'permalink' => get_permalink($p),
                    'matched_aspires' => $match,
                ];
            }

            return new WP_REST_Response([
                'page' => $paged,
                'per_page' => $ppp,
                'total' => (int) $q->found_posts,
                'items' => $items,
            ], 200);
        }
    ]);
});
