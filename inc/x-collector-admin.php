<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// X (Twitter) collector admin UI — Posts only. All ingestion comes from an
// external n8n/Apify pipeline (see inc/x-collector-rest.php); there is no
// in-plugin profile management or scraper settings to configure here.

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

add_action( 'admin_menu', function() {
    add_submenu_page(
        'coffeebrk-core',
        'X / Social',
        'X / Social',
        'manage_options',
        'coffeebrk-core-x',
        'coffeebrk_x_admin_page'
    );
}, 30 );

add_action( 'admin_post_coffeebrk_x_delete_post', 'coffeebrk_x_handle_delete_post' );
add_action( 'admin_post_coffeebrk_x_toggle_post_status', 'coffeebrk_x_handle_toggle_post_status' );
add_action( 'admin_post_coffeebrk_x_toggle_post_featured', 'coffeebrk_x_handle_toggle_post_featured' );
add_action( 'admin_init', 'coffeebrk_x_maybe_handle_bulk_delete_posts' );

// Bulk actions on a WP_List_Table submit back to admin.php (not
// admin-post.php), so this has to run early enough (admin_init) to redirect
// before the page starts echoing output.
function coffeebrk_x_maybe_handle_bulk_delete_posts() : void {
    if ( ( $_REQUEST['page'] ?? '' ) !== 'coffeebrk-core-x' || ( $_REQUEST['tab'] ?? '' ) !== 'posts' ) return;

    $action = (string) ( $_REQUEST['action'] ?? '-1' );
    $action2 = (string) ( $_REQUEST['action2'] ?? '-1' );
    if ( $action !== 'bulk-delete' && $action2 !== 'bulk-delete' ) return;

    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'tab' => 'posts', 'msg' => 'error' ] );
    check_admin_referer( 'bulk-posts' );

    $ids = isset( $_REQUEST['post'] ) ? array_map( 'intval', (array) $_REQUEST['post'] ) : [];
    $deleted = 0;
    foreach ( $ids as $id ) {
        if ( $id > 0 && coffeebrk_x_delete_post( $id ) ) $deleted++;
    }

    coffeebrk_x_admin_redirect( [ 'tab' => 'posts', 'msg' => $deleted ? 'post_deleted' : 'error' ] );
}

function coffeebrk_x_admin_url( array $args = [] ) : string {
    return add_query_arg( $args, admin_url( 'admin.php?page=coffeebrk-core-x' ) );
}

function coffeebrk_x_admin_redirect( array $args = [] ) : void {
    wp_safe_redirect( coffeebrk_x_admin_url( $args ) );
    exit;
}

// ===========================================================================
// Posts list table
// ===========================================================================

class Coffeebrk_X_Posts_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([ 'singular' => 'post', 'plural' => 'posts', 'ajax' => false ]);
    }

    public function get_columns() {
        return [
            'cb'               => '<input type="checkbox" />',
            'author_username' => 'Author',
            'text'             => 'Text',
            'posted_at'        => 'Posted',
            'created_at'       => 'Imported',
            'engagement'       => 'Engagement',
            'status'           => 'Status',
            'permalink'        => 'Link',
        ];
    }

    protected function get_bulk_actions() {
        return [ 'bulk-delete' => 'Delete' ];
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="post[]" value="%d" />', (int) $item['id'] );
    }

    protected function get_sortable_columns() {
        return [
            'posted_at' => [ 'posted_at', false ],
            'created_at' => [ 'created_at', true ],
        ];
    }

    public function column_author_username( $item ) {
        $id = (int) $item['id'];
        $name = '@' . esc_html( (string) $item['author_username'] );

        $actions = [];

        $status = (string) $item['status'];
        $status_nonce = wp_create_nonce( 'coffeebrk_x_toggle_post_status_' . $id );
        $status_label = ( $status === 'published' ) ? 'Hide' : 'Show';
        $actions['status'] = sprintf( '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_toggle_post_status&post_id=' . $id . '&_wpnonce=' . $status_nonce ) ),
            esc_html( $status_label ) );

        $featured = (int) $item['is_featured'] === 1;
        $feat_nonce = wp_create_nonce( 'coffeebrk_x_toggle_post_featured_' . $id );
        $feat_label = $featured ? 'Unfeature' : 'Feature';
        $actions['featured'] = sprintf( '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_toggle_post_featured&post_id=' . $id . '&_wpnonce=' . $feat_nonce ) ),
            esc_html( $feat_label ) );

        $del_nonce = wp_create_nonce( 'coffeebrk_x_delete_post_' . $id );
        $actions['delete'] = sprintf( '<a href="%s" onclick="return confirm(%s);">Delete</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_delete_post&post_id=' . $id . '&_wpnonce=' . $del_nonce ) ),
            esc_js( 'Delete this post?' ) );

        return $name . $this->row_actions( $actions );
    }

    public function column_text( $item ) {
        $text = (string) $item['text'];
        return esc_html( mb_strlen( $text ) > 140 ? mb_substr( $text, 0, 140 ) . '…' : $text );
    }

    public function column_engagement( $item ) {
        return sprintf( '♥ %d &nbsp; ⇄ %d &nbsp; ↩ %d',
            (int) $item['like_count'], (int) $item['retweet_count'], (int) $item['reply_count'] );
    }

    public function column_permalink( $item ) {
        $url = (string) $item['permalink'];
        return $url ? sprintf( '<a href="%s" target="_blank" rel="noopener">View on X</a>', esc_url( $url ) ) : '—';
    }

    public function column_default( $item, $column_name ) {
        if ( $column_name === 'is_featured' ) return '';
        $val = $item[ $column_name ] ?? '';
        if ( $column_name === 'posted_at' ) return $val ? esc_html( (string) $val ) : '—';
        return is_scalar( $val ) ? esc_html( (string) $val ) : '';
    }

    public function prepare_items() {
        $per_page = 20;
        $paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

        $args = [
            'page'     => $paged,
            'per_page' => $per_page,
            // Default to import order (created_at), not the tweet's original
            // posted_at — with n8n backfilling old tweets, "just imported"
            // is what admins actually want to see first.
            'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( (string) $_GET['orderby'] ) : 'created_at',
            'order'    => isset( $_GET['order'] ) ? strtoupper( sanitize_key( (string) $_GET['order'] ) ) : 'DESC',
        ];

        if ( ! empty( $_GET['profile_id'] ) ) $args['profile_id'] = (int) $_GET['profile_id'];
        if ( ! empty( $_GET['category_id'] ) ) $args['category_id'] = (int) $_GET['category_id'];

        $result = coffeebrk_x_get_posts( $args );

        $this->items = $result['posts'];

        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page' => $per_page,
            'total_pages' => (int) ceil( $result['total'] / $per_page ),
        ]);

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }
}

// ===========================================================================
// Page render
// ===========================================================================

function coffeebrk_x_admin_page() : void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    echo '<div class="wrap">';
    echo '<h1>X / Social</h1>';

    if ( isset( $_GET['msg'] ) ) {
        coffeebrk_x_render_admin_notice( sanitize_key( (string) $_GET['msg'] ) );
    }

    coffeebrk_x_render_posts_tab();

    echo '</div>';
}

function coffeebrk_x_render_admin_notice( string $msg ) : void {
    $type = ( $msg === 'error' ) ? 'error' : 'updated';
    $text = '';

    if ( $msg === 'post_deleted' ) $text = 'Post deleted.';
    if ( $msg === 'post_updated' ) $text = 'Post updated.';
    if ( $msg === 'error' ) $text = 'Action failed.';

    if ( $text ) {
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
    }
}

function coffeebrk_x_render_posts_tab() : void {
    $table = new Coffeebrk_X_Posts_Table();
    $table->prepare_items();

    echo '<form method="post">';
    echo '<input type="hidden" name="page" value="coffeebrk-core-x" />';
    echo '<input type="hidden" name="tab" value="posts" />';
    $table->display();
    echo '</form>';
}

// ===========================================================================
// admin-post.php handlers
// ===========================================================================

function coffeebrk_x_handle_delete_post() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    if ( $post_id <= 0 ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    check_admin_referer( 'coffeebrk_x_delete_post_' . $post_id );

    $ok = coffeebrk_x_delete_post( $post_id );
    coffeebrk_x_admin_redirect( [ 'tab' => 'posts', 'msg' => $ok ? 'post_deleted' : 'error' ] );
}

function coffeebrk_x_handle_toggle_post_status() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    if ( $post_id <= 0 ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    check_admin_referer( 'coffeebrk_x_toggle_post_status_' . $post_id );

    $post = coffeebrk_x_get_post( $post_id );
    if ( ! $post ) coffeebrk_x_admin_redirect( [ 'tab' => 'posts', 'msg' => 'error' ] );

    $new_status = ( (string) $post['status'] === 'published' ) ? 'hidden' : 'published';
    $ok = coffeebrk_x_set_post_status( $post_id, $new_status );
    coffeebrk_x_admin_redirect( [ 'tab' => 'posts', 'msg' => $ok ? 'post_updated' : 'error' ] );
}

function coffeebrk_x_handle_toggle_post_featured() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    if ( $post_id <= 0 ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    check_admin_referer( 'coffeebrk_x_toggle_post_featured_' . $post_id );

    $post = coffeebrk_x_get_post( $post_id );
    if ( ! $post ) coffeebrk_x_admin_redirect( [ 'tab' => 'posts', 'msg' => 'error' ] );

    $ok = coffeebrk_x_set_post_featured( $post_id, (int) $post['is_featured'] !== 1 );
    coffeebrk_x_admin_redirect( [ 'tab' => 'posts', 'msg' => $ok ? 'post_updated' : 'error' ] );
}
