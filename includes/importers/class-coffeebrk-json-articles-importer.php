<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_Json_Articles_Importer {

    public static function parse_json_file( $file_path ) {
        if ( ! is_string( $file_path ) || $file_path === '' || ! file_exists( $file_path ) ) {
            return new WP_Error( 'cbk_file_missing', 'JSON file not found.' );
        }

        $raw = file_get_contents( $file_path );
        if ( $raw === false ) {
            return new WP_Error( 'cbk_file_read', 'Could not read JSON file.' );
        }

        return self::parse_json_text( $raw );
    }

    public static function parse_json_text( $raw ) {
        if ( ! is_string( $raw ) ) {
            return new WP_Error( 'cbk_json_invalid', 'Invalid JSON input.' );
        }

        $raw = trim( $raw );
        if ( $raw === '' ) {
            return new WP_Error( 'cbk_json_empty', 'JSON input is empty.' );
        }

        // Accept "object list" pasted format (objects separated by commas, no surrounding [ ]).
        // Example:
        //   { ... },
        //   { ... }
        // This is not valid JSON by itself, so we auto-wrap it into an array.
        $first_non_ws = substr( $raw, 0, 1 );
        $last_non_ws = substr( $raw, -1 );
        if ( $first_non_ws === '{' && $last_non_ws !== ']' ) {
            // Remove a trailing comma if present.
            $raw = preg_replace( '/,\s*$/', '', $raw );
            $raw = '[' . $raw . ']';
        }

        // Quick format hints before attempting decode
        $first = substr( $raw, 0, 1 );
        $last = substr( $raw, -1 );
        if ( $first !== '[' ) {
            // We still try decoding to keep compatibility, but provide guidance.
            // Many users paste a single object instead of an array.
        }

        $data = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $len = strlen( $raw );
            $prefix = substr( $raw, 0, 120 );
            $suffix = $len > 120 ? substr( $raw, -120 ) : '';

            $hints = [];
            $hints[] = 'Expected: a JSON array of items (starts with [ and ends with ]).';
            $hints[] = 'Length: ' . $len . ' chars.';
            $hints[] = 'First char: ' . $first . ' | Last char: ' . $last . '.';
            if ( $first === '{' ) {
                $hints[] = 'It looks like you pasted a single object. Wrap it in [ ... ] to make an array.';
            }
            if ( $last !== ']' && $first === '[' ) {
                $hints[] = 'It looks like the JSON array is not closed. Make sure it ends with ].';
            }
            $hints[] = 'Prefix: ' . $prefix;
            if ( $suffix !== '' ) {
                $hints[] = 'Suffix: ' . $suffix;
            }

            return new WP_Error(
                'cbk_json_invalid',
                'Invalid JSON: ' . json_last_error_msg() . ' | ' . implode( ' ', $hints )
            );
        }

        if ( ! is_array( $data ) ) {
            return new WP_Error( 'cbk_json_format', 'JSON must decode to an array of items.' );
        }

        // If the file contains an object with a known key, allow it, but keep the format fixed (array of items).
        // (We won\'t attempt to support other shapes.)
        $items = array_values( $data );
        return $items;
    }

    public static function import_item( array $item, array $args = [] ) {
        $defaults = [
            'post_status' => 'draft',
            'category_ids' => [],
        ];
        $args = array_merge( $defaults, $args );

        $title = isset( $item['title'] ) ? (string) $item['title'] : '';
        $content = isset( $item['description'] ) ? (string) $item['description'] : '';
        $date = isset( $item['date'] ) ? (string) $item['date'] : '';
        $source_url = isset( $item['url'] ) ? (string) $item['url'] : '';
        $source_name = isset( $item['source'] ) ? (string) $item['source'] : '';
        $image_url = isset( $item['image'] ) ? (string) $item['image'] : '';
        $logo_url = isset( $item['logo'] ) ? (string) $item['logo'] : '';
        $tagline = isset( $item['tagline'] ) ? (string) $item['tagline'] : '';

        $title = trim( wp_strip_all_tags( $title ) );
        $source_url = trim( $source_url );

        if ( $title === '' ) {
            return [ 'status' => 'failed', 'reason' => 'Missing required field: title', 'post_id' => 0 ];
        }

        if ( $source_url === '' ) {
            return [ 'status' => 'failed', 'reason' => 'Missing required field: url (source URL)', 'post_id' => 0 ];
        }

        $dup_id = self::find_duplicate_post_id_by_source_url( $source_url );
        if ( $dup_id ) {
            return [
                'status' => 'skipped',
                'reason' => 'Skipped (duplicate _source_url): ' . esc_url_raw( $source_url ),
                'post_id' => (int) $dup_id,
            ];
        }

        $post_date = self::normalize_post_date( $date );

        $postarr = [
            'post_type' => 'post',
            'post_status' => in_array( $args['post_status'], [ 'draft', 'publish' ], true ) ? $args['post_status'] : 'draft',
            'post_title' => $title,
            'post_content' => $content,
        ];

        if ( $post_date ) {
            $postarr['post_date'] = $post_date;
        }

        $post_id = wp_insert_post( wp_slash( $postarr ), true );
        if ( is_wp_error( $post_id ) ) {
            return [ 'status' => 'failed', 'reason' => $post_id->get_error_message(), 'post_id' => 0 ];
        }

        $post_id = (int) $post_id;

        // Categories
        $cats = array_filter( array_map( 'intval', (array) $args['category_ids'] ) );
        if ( ! empty( $cats ) ) {
            wp_set_post_terms( $post_id, $cats, 'category', false );
        }

        // Meta (underscored keys)
        update_post_meta( $post_id, '_source_url', esc_url_raw( $source_url ) );
        update_post_meta( $post_id, '_source_name', sanitize_text_field( $source_name ) );

        if ( $image_url !== '' ) {
            update_post_meta( $post_id, '_image', esc_url_raw( $image_url ) );
        }

        if ( $logo_url !== '' ) {
            update_post_meta( $post_id, '_organization_logo', esc_url_raw( $logo_url ) );
        }

        if ( $tagline !== '' ) {
            update_post_meta( $post_id, '_tagline', sanitize_text_field( $tagline ) );
        }

        return [ 'status' => 'imported', 'reason' => '', 'post_id' => $post_id ];
    }

    private static function find_duplicate_post_id_by_source_url( $source_url ) {
        $source_url = trim( (string) $source_url );
        if ( $source_url === '' ) {
            return 0;
        }

        $q = new WP_Query( [
            'post_type' => 'post',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_source_url',
                    'value' => $source_url,
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => true,
        ] );

        if ( ! empty( $q->posts[0] ) ) {
            return (int) $q->posts[0];
        }

        return 0;
    }

    private static function normalize_post_date( $date ) {
        $date = trim( (string) $date );
        if ( $date === '' ) {
            return '';
        }

        $ts = strtotime( $date );
        if ( ! $ts ) {
            return '';
        }

        return gmdate( 'Y-m-d H:i:s', $ts );
    }
}
