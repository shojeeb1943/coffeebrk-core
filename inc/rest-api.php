<?php
/**
 * Coffeebrk Core REST API v1
 *
 * Professional REST API for n8n integration and external services.
 * Endpoints: GET/POST/PUT/DELETE for posts, RSS feeds, and site info.
 *
 * @package Coffeebrk_Core
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register all REST API routes
 */
add_action( 'rest_api_init', 'coffeebrk_register_rest_routes' );

function coffeebrk_register_rest_routes() {
    $namespace = 'coffeebrk/v1';

    // =========================================================================
    // GET /posts - List posts with filtering and pagination
    // =========================================================================
    register_rest_route( $namespace, '/posts', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_api_get_posts',
        'args'                => [
            'page'        => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
            'per_page'    => [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100 ],
            'status'      => [ 'type' => 'string', 'default' => 'publish', 'enum' => [ 'publish', 'draft', 'pending', 'any' ] ],
            'category'    => [ 'type' => 'integer', 'default' => 0 ],
            'category_slug' => [ 'type' => 'string', 'default' => '' ],
            'search'      => [ 'type' => 'string', 'default' => '' ],
            'orderby'     => [ 'type' => 'string', 'default' => 'date', 'enum' => [ 'date', 'title', 'modified', 'ID', 'rand' ] ],
            'order'       => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
            'author'      => [ 'type' => 'integer', 'default' => 0 ],
            'meta_key'    => [ 'type' => 'string', 'default' => '' ],
            'meta_value'  => [ 'type' => 'string', 'default' => '' ],
            'after'       => [ 'type' => 'string', 'default' => '' ],
            'before'      => [ 'type' => 'string', 'default' => '' ],
            'include_meta'=> [ 'type' => 'boolean', 'default' => true ],
        ],
    ]);

    // =========================================================================
    // GET /posts/{id} - Get single post
    // =========================================================================
    register_rest_route( $namespace, '/posts/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_api_get_post',
        'args'                => [
            'id' => [ 'type' => 'integer', 'required' => true ],
            'include_meta' => [ 'type' => 'boolean', 'default' => true ],
        ],
    ]);

    // =========================================================================
    // POST /posts - Create a new post
    // =========================================================================
    register_rest_route( $namespace, '/posts', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_api_create_post',
        'args'                => coffeebrk_get_post_schema_args(),
    ]);

    // =========================================================================
    // PUT /posts/{id} - Update an existing post
    // =========================================================================
    register_rest_route( $namespace, '/posts/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_api_update_post',
        'args'                => array_merge(
            [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
            coffeebrk_get_post_schema_args( false )
        ),
    ]);

    // =========================================================================
    // PATCH /posts/{id} - Partial update (same as PUT)
    // =========================================================================
    register_rest_route( $namespace, '/posts/(?P<id>\d+)', [
        'methods'             => 'PATCH',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_api_update_post',
        'args'                => array_merge(
            [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
            coffeebrk_get_post_schema_args( false )
        ),
    ]);

    // =========================================================================
    // DELETE /posts/{id} - Delete a post
    // =========================================================================
    register_rest_route( $namespace, '/posts/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => 'coffeebrk_api_permission_delete',
        'callback'            => 'coffeebrk_api_delete_post',
        'args'                => [
            'id'    => [ 'type' => 'integer', 'required' => true ],
            'force' => [ 'type' => 'boolean', 'default' => false ],
        ],
    ]);

    // =========================================================================
    // GET /categories - List categories
    // =========================================================================
    register_rest_route( $namespace, '/categories', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_api_get_categories',
        'args'                => [
            'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
        ],
    ]);

    // =========================================================================
    // GET /meta-fields - Get available dynamic fields
    // =========================================================================
    register_rest_route( $namespace, '/meta-fields', [
        'methods'             => 'GET',
        'permission_callback' => 'coffeebrk_api_permission_read',
        'callback'            => 'coffeebrk_api_get_meta_fields',
    ]);

    // =========================================================================
    // GET /rss-info - RSS feed information
    // =========================================================================
    register_rest_route( $namespace, '/rss-info', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'coffeebrk_api_get_rss_info',
    ]);

    // =========================================================================
    // POST /bulk-posts - Create multiple posts at once
    // =========================================================================
    register_rest_route( $namespace, '/bulk-posts', [
        'methods'             => 'POST',
        'permission_callback' => 'coffeebrk_api_permission_write',
        'callback'            => 'coffeebrk_api_bulk_create_posts',
        'args'                => [
            'posts' => [
                'type'     => 'array',
                'required' => true,
                'items'    => [ 'type' => 'object' ],
            ],
        ],
    ]);
}

// =============================================================================
// PERMISSION CALLBACKS
// =============================================================================

/**
 * Permission callback for read operations
 * Allows: Bearer token with 'read' permission OR logged-in user
 */
function coffeebrk_api_permission_read( WP_REST_Request $req ) {
    // Check Bearer token first
    $token = coffeebrk_core_get_bearer_token_from_rest_request( $req );
    if ( $token !== '' ) {
        // Use new multi-token system if available
        if ( function_exists( 'coffeebrk_token_has_permission' ) ) {
            return coffeebrk_token_has_permission( $token, 'read' );
        }
        // Fallback to old system
        if ( function_exists( 'coffeebrk_core_api_token_is_valid' ) && coffeebrk_core_api_token_is_valid( $token ) ) {
            return true;
        }
    }

    // Allow logged-in users
    return is_user_logged_in();
}

/**
 * Permission callback for write operations
 * Allows: Bearer token with 'write' permission OR logged-in user with edit_posts
 */
function coffeebrk_api_permission_write( WP_REST_Request $req ) {
    // Check Bearer token first
    $token = coffeebrk_core_get_bearer_token_from_rest_request( $req );
    if ( $token !== '' ) {
        // Use new multi-token system if available
        if ( function_exists( 'coffeebrk_token_has_permission' ) ) {
            return coffeebrk_token_has_permission( $token, 'write' );
        }
        // Fallback to old system
        if ( function_exists( 'coffeebrk_core_api_token_is_valid' ) && coffeebrk_core_api_token_is_valid( $token ) ) {
            return true;
        }
    }

    // Logged-in user with edit_posts capability
    return is_user_logged_in() && current_user_can( 'edit_posts' );
}

/**
 * Permission callback for delete operations
 * Allows: Bearer token with 'delete' permission OR logged-in user with delete_posts
 */
function coffeebrk_api_permission_delete( WP_REST_Request $req ) {
    // Check Bearer token first
    $token = coffeebrk_core_get_bearer_token_from_rest_request( $req );
    if ( $token !== '' ) {
        // Use new multi-token system if available
        if ( function_exists( 'coffeebrk_token_has_permission' ) ) {
            return coffeebrk_token_has_permission( $token, 'delete' );
        }
        // Fallback to old system
        if ( function_exists( 'coffeebrk_core_api_token_is_valid' ) && coffeebrk_core_api_token_is_valid( $token ) ) {
            return true;
        }
    }

    // Logged-in user with delete_posts capability
    return is_user_logged_in() && current_user_can( 'delete_posts' );
}

// =============================================================================
// SCHEMA HELPERS
// =============================================================================

/**
 * Get post schema arguments for create/update operations
 */
function coffeebrk_get_post_schema_args( $require_title = true ) {
    return [
        'title'       => [ 'type' => 'string', 'required' => $require_title, 'sanitize_callback' => 'sanitize_text_field' ],
        'content'     => [ 'type' => 'string', 'default' => '' ],
        'excerpt'     => [ 'type' => 'string', 'default' => '' ],
        'status'      => [ 'type' => 'string', 'default' => 'draft', 'enum' => [ 'draft', 'publish', 'pending', 'private' ] ],
        'category_id' => [ 'type' => 'integer', 'default' => 0 ],
        'categories'  => [ 'type' => 'array', 'default' => [], 'items' => [ 'type' => 'integer' ] ],
        'tags'        => [ 'type' => 'array', 'default' => [], 'items' => [ 'type' => 'string' ] ],
        'source_url'  => [ 'type' => 'string', 'default' => '', 'format' => 'uri' ],
        'source_name' => [ 'type' => 'string', 'default' => '' ],
        'image_url'   => [ 'type' => 'string', 'default' => '', 'format' => 'uri' ],
        'meta'        => [ 'type' => 'object', 'default' => [] ],
        'author_id'   => [ 'type' => 'integer', 'default' => 0 ],
        'date'        => [ 'type' => 'string', 'default' => '' ],
        'slug'        => [ 'type' => 'string', 'default' => '' ],
    ];
}

// =============================================================================
// GET ENDPOINTS
// =============================================================================

/**
 * GET /posts - List posts with filtering
 */
function coffeebrk_api_get_posts( WP_REST_Request $req ) {
    $page        = max( 1, (int) $req->get_param( 'page' ) );
    $per_page    = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
    $status      = sanitize_key( $req->get_param( 'status' ) );
    $category    = (int) $req->get_param( 'category' );
    $cat_slug    = sanitize_title( $req->get_param( 'category_slug' ) );
    $search      = sanitize_text_field( $req->get_param( 'search' ) );
    $orderby     = sanitize_key( $req->get_param( 'orderby' ) );
    $order       = strtoupper( $req->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
    $author      = (int) $req->get_param( 'author' );
    $meta_key    = sanitize_key( $req->get_param( 'meta_key' ) );
    $meta_value  = sanitize_text_field( $req->get_param( 'meta_value' ) );
    $after       = sanitize_text_field( $req->get_param( 'after' ) );
    $before      = sanitize_text_field( $req->get_param( 'before' ) );
    $include_meta = (bool) $req->get_param( 'include_meta' );

    // Build query args
    $args = [
        'post_type'      => apply_filters( 'coffeebrk_api_post_types', [ 'post' ] ),
        'post_status'    => $status === 'any' ? [ 'publish', 'draft', 'pending', 'private' ] : $status,
        'paged'          => $page,
        'posts_per_page' => $per_page,
        'orderby'        => $orderby,
        'order'          => $order,
    ];

    // Category filter
    if ( $category > 0 ) {
        $args['cat'] = $category;
    } elseif ( $cat_slug !== '' ) {
        $args['category_name'] = $cat_slug;
    }

    // Search
    if ( $search !== '' ) {
        $args['s'] = $search;
    }

    // Author filter
    if ( $author > 0 ) {
        $args['author'] = $author;
    }

    // Meta query
    if ( $meta_key !== '' && $meta_value !== '' ) {
        $args['meta_query'] = [
            [
                'key'     => '_' . ltrim( $meta_key, '_' ),
                'value'   => $meta_value,
                'compare' => 'LIKE',
            ],
        ];
    }

    // Date filters
    if ( $after !== '' || $before !== '' ) {
        $args['date_query'] = [];
        if ( $after !== '' ) {
            $args['date_query']['after'] = $after;
        }
        if ( $before !== '' ) {
            $args['date_query']['before'] = $before;
        }
    }

    $query = new WP_Query( $args );
    $items = [];

    foreach ( $query->posts as $post ) {
        $items[] = coffeebrk_format_post_response( $post, $include_meta );
    }

    return new WP_REST_Response( [
        'success'    => true,
        'page'       => $page,
        'per_page'   => $per_page,
        'total'      => (int) $query->found_posts,
        'total_pages'=> (int) $query->max_num_pages,
        'items'      => $items,
    ], 200 );
}

/**
 * GET /posts/{id} - Get single post
 */
function coffeebrk_api_get_post( WP_REST_Request $req ) {
    $post_id     = (int) $req->get_param( 'id' );
    $include_meta = (bool) $req->get_param( 'include_meta' );

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'post_not_found',
            'message' => 'The requested post does not exist.',
        ], 404 );
    }

    return new WP_REST_Response( [
        'success' => true,
        'post'    => coffeebrk_format_post_response( $post, $include_meta, true ),
    ], 200 );
}

/**
 * GET /categories - List categories
 */
function coffeebrk_api_get_categories( WP_REST_Request $req ) {
    $hide_empty = (bool) $req->get_param( 'hide_empty' );

    $categories = get_categories( [
        'hide_empty' => $hide_empty,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );

    $items = [];
    foreach ( $categories as $cat ) {
        $items[] = [
            'id'          => (int) $cat->term_id,
            'name'        => $cat->name,
            'slug'        => $cat->slug,
            'description' => $cat->description,
            'count'       => (int) $cat->count,
            'parent'      => (int) $cat->parent,
        ];
    }

    return new WP_REST_Response( [
        'success' => true,
        'total'   => count( $items ),
        'items'   => $items,
    ], 200 );
}

/**
 * GET /meta-fields - Get available dynamic fields
 */
function coffeebrk_api_get_meta_fields( WP_REST_Request $req ) {
    $dyn = (array) get_option( 'coffeebrk_dynamic_fields', [] );
    $fields = [];

    // Core fields always available
    $fields[] = [
        'key'      => '_source_name',
        'label'    => 'Source Name',
        'type'     => 'text',
        'required' => false,
        'core'     => true,
    ];
    $fields[] = [
        'key'      => '_source_url',
        'label'    => 'Source URL',
        'type'     => 'url',
        'required' => false,
        'core'     => true,
    ];
    $fields[] = [
        'key'      => '_image',
        'label'    => 'Featured Image URL',
        'type'     => 'image_url',
        'required' => false,
        'core'     => true,
    ];

    // Dynamic fields
    foreach ( $dyn as $f ) {
        if ( ! is_array( $f ) ) continue;
        $key = (string) ( $f['key'] ?? '' );
        if ( $key === '' ) continue;

        $fields[] = [
            'key'      => $key,
            'label'    => (string) ( $f['label'] ?? $key ),
            'type'     => (string) ( $f['type'] ?? 'text' ),
            'choices'  => (string) ( $f['choices'] ?? '' ),
            'required' => false,
            'core'     => false,
        ];
    }

    return new WP_REST_Response( [
        'success' => true,
        'total'   => count( $fields ),
        'fields'  => $fields,
    ], 200 );
}

/**
 * GET /rss-info - RSS feed information
 */
function coffeebrk_api_get_rss_info( WP_REST_Request $req ) {
    return new WP_REST_Response( [
        'success' => true,
        'feeds'   => [
            [
                'name'        => 'Coffeebrk Main Feed',
                'url'         => home_url( '/feed/coffeebrk/' ),
                'url_alt'     => home_url( '/?feed=coffeebrk' ),
                'format'      => 'RSS 2.0',
                'description' => 'Latest published posts from the site.',
            ],
            [
                'name'        => 'WordPress Default Feed',
                'url'         => get_bloginfo( 'rss2_url' ),
                'format'      => 'RSS 2.0',
                'description' => 'Standard WordPress RSS feed.',
            ],
        ],
    ], 200 );
}

// =============================================================================
// CREATE / UPDATE ENDPOINTS
// =============================================================================

/**
 * POST /posts - Create a new post
 */
function coffeebrk_api_create_post( WP_REST_Request $req ) {
    $params = coffeebrk_get_request_params( $req );

    $title   = sanitize_text_field( (string) ( $params['title'] ?? '' ) );
    $content = isset( $params['content'] ) ? wp_kses_post( (string) $params['content'] ) : '';
    $excerpt = isset( $params['excerpt'] ) ? sanitize_textarea_field( (string) $params['excerpt'] ) : '';
    $status  = sanitize_key( (string) ( $params['status'] ?? 'draft' ) );

    if ( ! in_array( $status, [ 'draft', 'publish', 'pending', 'private' ], true ) ) {
        $status = 'draft';
    }

    if ( $title === '' && $content === '' ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'missing_title_or_content',
            'message' => 'Either title or content is required.',
        ], 400 );
    }

    // Build post data
    $post_data = [
        'post_type'    => 'post',
        'post_status'  => $status,
        'post_title'   => $title !== '' ? $title : '(Untitled)',
        'post_content' => $content,
        'post_excerpt' => $excerpt,
    ];

    // Author
    $author_id = (int) ( $params['author_id'] ?? 0 );
    if ( $author_id > 0 && get_user_by( 'id', $author_id ) ) {
        $post_data['post_author'] = $author_id;
    }

    // Date
    $date = sanitize_text_field( (string) ( $params['date'] ?? '' ) );
    if ( $date !== '' ) {
        $post_data['post_date'] = $date;
        $post_data['post_date_gmt'] = get_gmt_from_date( $date );
    }

    // Slug
    $slug = sanitize_title( (string) ( $params['slug'] ?? '' ) );
    if ( $slug !== '' ) {
        $post_data['post_name'] = $slug;
    }

    // Insert post
    $post_id = wp_insert_post( $post_data, true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'insert_failed',
            'message' => $post_id->get_error_message(),
        ], 500 );
    }

    // Process meta and taxonomies
    coffeebrk_process_post_meta( $post_id, $params );
    coffeebrk_process_post_taxonomies( $post_id, $params );

    $post = get_post( $post_id );

    return new WP_REST_Response( [
        'success'   => true,
        'message'   => 'Post created successfully.',
        'post'      => coffeebrk_format_post_response( $post, true ),
        'edit_link' => get_edit_post_link( $post_id, 'raw' ),
    ], 201 );
}

/**
 * PUT/PATCH /posts/{id} - Update an existing post
 */
function coffeebrk_api_update_post( WP_REST_Request $req ) {
    $post_id = (int) $req->get_param( 'id' );
    $params  = coffeebrk_get_request_params( $req );

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'post_not_found',
            'message' => 'The requested post does not exist.',
        ], 404 );
    }

    // Build update data
    $post_data = [ 'ID' => $post_id ];

    if ( isset( $params['title'] ) ) {
        $post_data['post_title'] = sanitize_text_field( (string) $params['title'] );
    }
    if ( isset( $params['content'] ) ) {
        $post_data['post_content'] = wp_kses_post( (string) $params['content'] );
    }
    if ( isset( $params['excerpt'] ) ) {
        $post_data['post_excerpt'] = sanitize_textarea_field( (string) $params['excerpt'] );
    }
    if ( isset( $params['status'] ) ) {
        $status = sanitize_key( (string) $params['status'] );
        if ( in_array( $status, [ 'draft', 'publish', 'pending', 'private' ], true ) ) {
            $post_data['post_status'] = $status;
        }
    }
    if ( isset( $params['slug'] ) ) {
        $post_data['post_name'] = sanitize_title( (string) $params['slug'] );
    }
    if ( isset( $params['date'] ) ) {
        $date = sanitize_text_field( (string) $params['date'] );
        if ( $date !== '' ) {
            $post_data['post_date'] = $date;
            $post_data['post_date_gmt'] = get_gmt_from_date( $date );
        }
    }
    if ( isset( $params['author_id'] ) ) {
        $author_id = (int) $params['author_id'];
        if ( $author_id > 0 && get_user_by( 'id', $author_id ) ) {
            $post_data['post_author'] = $author_id;
        }
    }

    // Update post
    $result = wp_update_post( $post_data, true );

    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'update_failed',
            'message' => $result->get_error_message(),
        ], 500 );
    }

    // Process meta and taxonomies
    coffeebrk_process_post_meta( $post_id, $params );
    coffeebrk_process_post_taxonomies( $post_id, $params );

    $post = get_post( $post_id );

    return new WP_REST_Response( [
        'success' => true,
        'message' => 'Post updated successfully.',
        'post'    => coffeebrk_format_post_response( $post, true ),
    ], 200 );
}

/**
 * DELETE /posts/{id} - Delete a post
 */
function coffeebrk_api_delete_post( WP_REST_Request $req ) {
    $post_id = (int) $req->get_param( 'id' );
    $force   = (bool) $req->get_param( 'force' );

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'post_not_found',
            'message' => 'The requested post does not exist.',
        ], 404 );
    }

    if ( $force ) {
        $result = wp_delete_post( $post_id, true );
    } else {
        $result = wp_trash_post( $post_id );
    }

    if ( ! $result ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'delete_failed',
            'message' => 'Failed to delete the post.',
        ], 500 );
    }

    return new WP_REST_Response( [
        'success' => true,
        'message' => $force ? 'Post permanently deleted.' : 'Post moved to trash.',
        'id'      => $post_id,
    ], 200 );
}

/**
 * POST /bulk-posts - Create multiple posts at once
 */
function coffeebrk_api_bulk_create_posts( WP_REST_Request $req ) {
    $params = coffeebrk_get_request_params( $req );
    $posts  = $params['posts'] ?? [];

    if ( ! is_array( $posts ) || empty( $posts ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'missing_posts',
            'message' => 'Posts array is required.',
        ], 400 );
    }

    // Limit bulk operations
    $max_posts = apply_filters( 'coffeebrk_bulk_posts_limit', 50 );
    if ( count( $posts ) > $max_posts ) {
        return new WP_REST_Response( [
            'success' => false,
            'error'   => 'too_many_posts',
            'message' => sprintf( 'Maximum %d posts allowed per request.', $max_posts ),
        ], 400 );
    }

    $results = [];
    $success_count = 0;
    $error_count = 0;

    foreach ( $posts as $index => $post_data ) {
        if ( ! is_array( $post_data ) ) {
            $results[] = [
                'index'   => $index,
                'success' => false,
                'error'   => 'invalid_data',
                'message' => 'Post data must be an object.',
            ];
            $error_count++;
            continue;
        }

        $title   = sanitize_text_field( (string) ( $post_data['title'] ?? '' ) );
        $content = isset( $post_data['content'] ) ? wp_kses_post( (string) $post_data['content'] ) : '';
        $status  = sanitize_key( (string) ( $post_data['status'] ?? 'draft' ) );

        if ( ! in_array( $status, [ 'draft', 'publish', 'pending', 'private' ], true ) ) {
            $status = 'draft';
        }

        if ( $title === '' && $content === '' ) {
            $results[] = [
                'index'   => $index,
                'success' => false,
                'error'   => 'missing_title_or_content',
            ];
            $error_count++;
            continue;
        }

        $post_id = wp_insert_post( [
            'post_type'    => 'post',
            'post_status'  => $status,
            'post_title'   => $title !== '' ? $title : '(Untitled)',
            'post_content' => $content,
            'post_excerpt' => sanitize_textarea_field( (string) ( $post_data['excerpt'] ?? '' ) ),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            $results[] = [
                'index'   => $index,
                'success' => false,
                'error'   => 'insert_failed',
                'message' => $post_id->get_error_message(),
            ];
            $error_count++;
            continue;
        }

        coffeebrk_process_post_meta( $post_id, $post_data );
        coffeebrk_process_post_taxonomies( $post_id, $post_data );

        $results[] = [
            'index'     => $index,
            'success'   => true,
            'id'        => $post_id,
            'permalink' => get_permalink( $post_id ),
        ];
        $success_count++;
    }

    return new WP_REST_Response( [
        'success'       => $error_count === 0,
        'total'         => count( $posts ),
        'success_count' => $success_count,
        'error_count'   => $error_count,
        'results'       => $results,
    ], 200 );
}

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get request parameters (JSON or form data)
 */
function coffeebrk_get_request_params( WP_REST_Request $req ) {
    $params = $req->get_json_params();
    if ( ! is_array( $params ) || empty( $params ) ) {
        $params = $req->get_params();
    }
    return is_array( $params ) ? $params : [];
}

/**
 * Format post data for API response
 */
function coffeebrk_format_post_response( $post, $include_meta = true, $full_content = false ) {
    $data = [
        'id'         => (int) $post->ID,
        'title'      => get_the_title( $post ),
        'slug'       => $post->post_name,
        'status'     => $post->post_status,
        'date'       => $post->post_date,
        'date_gmt'   => $post->post_date_gmt,
        'modified'   => $post->post_modified,
        'excerpt'    => wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ),
        'permalink'  => get_permalink( $post ),
        'author'     => [
            'id'   => (int) $post->post_author,
            'name' => get_the_author_meta( 'display_name', $post->post_author ),
        ],
    ];

    // Full content for single post view
    if ( $full_content ) {
        $data['content'] = $post->post_content;
        $data['content_rendered'] = apply_filters( 'the_content', $post->post_content );
    }

    // Categories
    $categories = wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] );
    $data['categories'] = [];
    if ( is_array( $categories ) ) {
        foreach ( $categories as $cat ) {
            $data['categories'][] = [
                'id'   => (int) $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ];
        }
    }

    // Tags
    $tags = wp_get_post_tags( $post->ID );
    $data['tags'] = [];
    if ( is_array( $tags ) ) {
        foreach ( $tags as $tag ) {
            $data['tags'][] = [
                'id'   => (int) $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ];
        }
    }

    // Featured image
    $thumb_id = get_post_thumbnail_id( $post->ID );
    if ( $thumb_id ) {
        $data['featured_image'] = wp_get_attachment_image_url( $thumb_id, 'full' );
        $data['featured_image_id'] = (int) $thumb_id;
    } else {
        $image_url = get_post_meta( $post->ID, '_image', true );
        $data['featured_image'] = $image_url !== '' ? $image_url : null;
        $data['featured_image_id'] = null;
    }

    // Meta fields
    if ( $include_meta ) {
        $data['meta'] = [];

        // Core meta
        $core_meta = [ '_source_name', '_source_url', '_image', '_organization_logo', '_tagline', '_date' ];
        foreach ( $core_meta as $key ) {
            $val = get_post_meta( $post->ID, $key, true );
            if ( $val !== '' ) {
                $data['meta'][ $key ] = $val;
            }
        }

        // Dynamic fields
        $dyn = (array) get_option( 'coffeebrk_dynamic_fields', [] );
        foreach ( $dyn as $f ) {
            if ( ! is_array( $f ) ) continue;
            $key = (string) ( $f['key'] ?? '' );
            if ( $key === '' ) continue;
            $val = get_post_meta( $post->ID, $key, true );
            if ( $val !== '' ) {
                $data['meta'][ $key ] = $val;
            }
        }
    }

    return $data;
}

/**
 * Process post meta fields
 */
function coffeebrk_process_post_meta( $post_id, $params ) {
    // Core meta fields
    $source_url = isset( $params['source_url'] ) ? esc_url_raw( (string) $params['source_url'] ) : '';
    $source_name = sanitize_text_field( (string) ( $params['source_name'] ?? '' ) );
    $image_url = isset( $params['image_url'] ) ? esc_url_raw( (string) $params['image_url'] ) : '';

    if ( $source_url !== '' ) {
        update_post_meta( $post_id, '_source_url', $source_url );
    }
    if ( $source_name !== '' ) {
        update_post_meta( $post_id, '_source_name', $source_name );
    }
    if ( $image_url !== '' ) {
        update_post_meta( $post_id, '_image', $image_url );
    }

    // Dynamic meta fields
    $incoming_meta = $params['meta'] ?? [];
    if ( ! is_array( $incoming_meta ) ) {
        return;
    }

    $dyn = (array) get_option( 'coffeebrk_dynamic_fields', [] );
    $dyn_index = [];
    foreach ( $dyn as $f ) {
        if ( ! is_array( $f ) ) continue;
        $k = (string) ( $f['key'] ?? '' );
        if ( $k === '' ) continue;
        $dyn_index[ $k ] = [
            'type'    => (string) ( $f['type'] ?? 'text' ),
            'choices' => (string) ( $f['choices'] ?? '' ),
        ];
    }

    // Also allow core meta keys
    $dyn_index['_source_name'] = [ 'type' => 'text', 'choices' => '' ];
    $dyn_index['_source_url'] = [ 'type' => 'url', 'choices' => '' ];
    $dyn_index['_image'] = [ 'type' => 'image_url', 'choices' => '' ];
    $dyn_index['_organization_logo'] = [ 'type' => 'image_url', 'choices' => '' ];
    $dyn_index['_tagline'] = [ 'type' => 'text', 'choices' => '' ];
    $dyn_index['_date'] = [ 'type' => 'text', 'choices' => '' ];

    foreach ( $incoming_meta as $meta_key => $raw ) {
        $meta_key = (string) $meta_key;
        if ( $meta_key === '' ) continue;

        // Normalize key with underscore prefix
        if ( $meta_key[0] !== '_' ) {
            $meta_key = '_' . ltrim( $meta_key, '_' );
        }

        if ( ! isset( $dyn_index[ $meta_key ] ) ) {
            continue;
        }

        $type = $dyn_index[ $meta_key ]['type'];
        switch ( $type ) {
            case 'textarea':
                $val = sanitize_textarea_field( (string) $raw );
                break;
            case 'url':
            case 'image_url':
                $val = esc_url_raw( (string) $raw );
                break;
            case 'number':
                $val = is_numeric( $raw ) ? ( $raw + 0 ) : '';
                break;
            case 'select':
                $val = sanitize_text_field( (string) $raw );
                break;
            default:
                $val = sanitize_text_field( (string) $raw );
        }

        if ( $val === '' || $val === null ) {
            delete_post_meta( $post_id, $meta_key );
        } else {
            update_post_meta( $post_id, $meta_key, $val );
        }
    }
}

/**
 * Process post taxonomies (categories and tags)
 */
function coffeebrk_process_post_taxonomies( $post_id, $params ) {
    // Single category
    $cat_id = isset( $params['category_id'] ) ? (int) $params['category_id'] : 0;

    // Multiple categories
    $categories = $params['categories'] ?? [];
    if ( ! is_array( $categories ) ) {
        $categories = [];
    }
    $categories = array_filter( array_map( 'intval', $categories ) );

    // Combine single and multiple
    if ( $cat_id > 0 && ! in_array( $cat_id, $categories, true ) ) {
        $categories[] = $cat_id;
    }

    if ( ! empty( $categories ) ) {
        wp_set_post_categories( $post_id, $categories, false );
    }

    // Tags
    $tags = $params['tags'] ?? [];
    if ( is_array( $tags ) && ! empty( $tags ) ) {
        $tag_names = array_map( 'sanitize_text_field', $tags );
        wp_set_post_tags( $post_id, $tag_names, false );
    }
}
