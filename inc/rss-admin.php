<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

add_action( 'admin_menu', function() {
    add_submenu_page(
        'coffeebrk-core',
        'RSS Aggregator',
        'RSS Aggregator',
        'manage_options',
        'coffeebrk-core-rss',
        'coffeebrk_rss_admin_page'
    );
}, 20 );

add_action( 'admin_post_coffeebrk_rss_save_feed', 'coffeebrk_rss_handle_save_feed' );
add_action( 'admin_post_coffeebrk_rss_delete_feed', 'coffeebrk_rss_handle_delete_feed' );
add_action( 'admin_post_coffeebrk_rss_toggle_feed', 'coffeebrk_rss_handle_toggle_feed' );
add_action( 'admin_post_coffeebrk_rss_run_feed', 'coffeebrk_rss_handle_run_feed' );
add_action( 'admin_post_coffeebrk_rss_run_all', 'coffeebrk_rss_handle_run_all' );

function coffeebrk_rss_admin_url( array $args = [] ) : string {
    return add_query_arg( $args, admin_url( 'admin.php?page=coffeebrk-core-rss' ) );
}

function coffeebrk_rss_admin_redirect( array $args = [] ) : void {
    wp_safe_redirect( coffeebrk_rss_admin_url( $args ) );
    exit;
}

class Coffeebrk_RSS_Feeds_Table extends WP_List_Table {
    public function get_columns() {
        return [
            'feed_name' => 'Feed Name',
            'feed_url' => 'Feed URL',
            'enabled' => 'Enabled',
            'import_limit' => 'Limit',
            'category_id' => 'Category',
            'last_import' => 'Last Import',
            'last_run' => 'Last Run',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'feed_name' => [ 'feed_name', false ],
            'feed_url' => [ 'feed_url', false ],
            'enabled' => [ 'enabled', false ],
            'last_import' => [ 'last_import', false ],
            'last_run' => [ 'last_run', false ],
            'id' => [ 'id', true ],
        ];
    }

    public function column_feed_name( $item ) {
        $id = (int) $item['id'];
        $name = esc_html( (string) $item['feed_name'] );

        $actions = [];

        $actions['edit'] = sprintf(
            '<a href="%s">Edit</a>',
            esc_url( coffeebrk_rss_admin_url( [ 'action' => 'edit', 'feed_id' => $id ] ) )
        );

        $toggle_nonce = wp_create_nonce( 'coffeebrk_rss_toggle_' . $id );
        $toggle_label = ( (int) $item['enabled'] === 1 ) ? 'Disable' : 'Enable';
        $actions['toggle'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_rss_toggle_feed&feed_id=' . $id . '&_wpnonce=' . $toggle_nonce ) ),
            esc_html( $toggle_label )
        );

        $run_nonce = wp_create_nonce( 'coffeebrk_rss_run_' . $id );
        $actions['run'] = sprintf(
            '<a href="%s">Run Import Now</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_rss_run_feed&feed_id=' . $id . '&_wpnonce=' . $run_nonce ) )
        );

        $del_nonce = wp_create_nonce( 'coffeebrk_rss_delete_' . $id );
        $actions['delete'] = sprintf(
            '<a href="%s" onclick="return confirm(%s);">Delete</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_rss_delete_feed&feed_id=' . $id . '&_wpnonce=' . $del_nonce ) ),
            esc_js( 'Are you sure you want to delete this feed?' )
        );

        return $name . $this->row_actions( $actions );
    }

    public function column_feed_url( $item ) {
        $url = (string) $item['feed_url'];
        return $url ? sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $url ) ) : '';
    }

    public function column_enabled( $item ) {
        return ( (int) $item['enabled'] === 1 ) ? 'Yes' : 'No';
    }

    public function column_import_limit( $item ) {
        return (int) $item['import_limit'];
    }

    public function column_category_id( $item ) {
        $cid = (int) ( $item['category_id'] ?? 0 );
        if ( $cid <= 0 ) return '—';
        $term = get_term( $cid, 'category' );
        if ( ! $term || is_wp_error( $term ) ) return '—';
        return esc_html( $term->name );
    }

    public function column_default( $item, $column_name ) {
        $val = $item[ $column_name ] ?? '';
        if ( $column_name === 'last_import' || $column_name === 'last_run' ) {
            return $val ? esc_html( $val ) : '—';
        }
        return is_scalar( $val ) ? esc_html( (string) $val ) : '';
    }

    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;

        $run_all_nonce = wp_create_nonce( 'coffeebrk_rss_run_all' );
        echo '<div class="alignleft actions">';
        echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin-post.php?action=coffeebrk_rss_run_all&_wpnonce=' . $run_all_nonce ) ) . '">Run All Feeds Now</a>';
        echo '&nbsp;';
        echo '<a class="button button-primary" href="' . esc_url( coffeebrk_rss_admin_url( [ 'action' => 'add' ] ) ) . '">Add New Feed</a>';
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;

        $table = coffeebrk_rss_table_name();

        $per_page = 20;
        $paged = isset($_GET['paged']) ? max( 1, (int) $_GET['paged'] ) : 1;
        $offset = ( $paged - 1 ) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key( (string) $_GET['orderby'] ) : 'id';
        $order = isset($_GET['order']) ? strtoupper( sanitize_key( (string) $_GET['order'] ) ) : 'DESC';

        $allowed_orderby = [ 'id', 'feed_name', 'feed_url', 'enabled', 'last_import', 'last_run' ];
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'id';
        }
        if ( $order !== 'ASC' && $order !== 'DESC' ) {
            $order = 'DESC';
        }

        $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        $items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $per_page, $offset ),
            ARRAY_A
        );

        $this->items = $items ? $items : [];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => (int) ceil( $total_items / $per_page ),
        ]);

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'feed_name' ];
    }
}

function coffeebrk_rss_admin_page() : void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $action = isset($_GET['action']) ? sanitize_key( (string) $_GET['action'] ) : '';

    echo '<div class="wrap">';
    echo '<h1>RSS Aggregator</h1>';

    if ( isset($_GET['msg']) ) {
        $msg = sanitize_key( (string) $_GET['msg'] );
        $type = ( $msg === 'error' ) ? 'error' : 'updated';
        $text = '';

        if ( $msg === 'saved' ) $text = 'Feed saved.';
        if ( $msg === 'deleted' ) $text = 'Feed deleted.';
        if ( $msg === 'toggled' ) $text = 'Feed updated.';
        if ( $msg === 'ran' ) $text = 'Import completed.';
        if ( $msg === 'error' ) $text = 'Action failed.';

        if ( $text ) {
            echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
        }
    }

    if ( $action === 'add' || $action === 'edit' ) {
        $feed_id = isset($_GET['feed_id']) ? (int) $_GET['feed_id'] : 0;
        $feed = $feed_id ? coffeebrk_rss_get_feed( $feed_id ) : null;

        coffeebrk_rss_render_feed_form( $feed );
        echo '</div>';
        return;
    }

    $table = new Coffeebrk_RSS_Feeds_Table();
    $table->prepare_items();

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="coffeebrk-core-rss" />';
    $table->display();
    echo '</form>';

    echo '</div>';
}

function coffeebrk_rss_render_feed_form( ?array $feed ) : void {
    $is_edit = $feed && ! empty( $feed['id'] );
    $id = $is_edit ? (int) $feed['id'] : 0;

    $feed_name = $is_edit ? (string) $feed['feed_name'] : '';
    $feed_url = $is_edit ? (string) $feed['feed_url'] : '';
    $enabled = $is_edit ? ( (int) $feed['enabled'] === 1 ) : true;
    $import_limit = $is_edit ? (int) $feed['import_limit'] : 5;
    $category_id = $is_edit ? (int) ( $feed['category_id'] ?? 0 ) : 0;

    echo '<h2>' . ( $is_edit ? 'Edit Feed' : 'Add New Feed' ) . '</h2>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="coffeebrk_rss_save_feed" />';
    if ( $is_edit ) {
        echo '<input type="hidden" name="feed_id" value="' . esc_attr( (string) $id ) . '" />';
    }

    wp_nonce_field( 'coffeebrk_rss_save_feed' );

    echo '<table class="form-table" role="presentation">';

    echo '<tr><th scope="row"><label for="feed_name">Feed Name</label></th><td>';
    echo '<input name="feed_name" id="feed_name" type="text" class="regular-text" value="' . esc_attr( $feed_name ) . '" required />';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="feed_url">Feed URL</label></th><td>';
    echo '<input name="feed_url" id="feed_url" type="url" class="regular-text" style="width:480px;" value="' . esc_attr( $feed_url ) . '" required />';
    echo '</td></tr>';

    echo '<tr><th scope="row">Enabled</th><td>';
    echo '<label><input name="enabled" type="checkbox" value="1" ' . checked( $enabled, true, false ) . ' /> Enable this feed</label>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="import_limit">Import limit per run</label></th><td>';
    echo '<input name="import_limit" id="import_limit" type="number" min="1" max="50" value="' . esc_attr( (string) $import_limit ) . '" />';
    echo '</td></tr>';

    echo '<tr><th scope="row">Category mapping (optional)</th><td>';

    wp_dropdown_categories([
        'taxonomy' => 'category',
        'hide_empty' => false,
        'name' => 'category_id',
        'id' => 'category_id',
        'selected' => $category_id,
        'show_option_none' => '— None —',
        'option_none_value' => '0',
    ]);

    echo '</td></tr>';

    echo '</table>';

    submit_button( $is_edit ? 'Save Feed' : 'Add Feed' );

    echo '<a class="button button-secondary" href="' . esc_url( coffeebrk_rss_admin_url() ) . '" style="margin-left:8px;">Back to list</a>';

    echo '</form>';
}

function coffeebrk_rss_handle_save_feed() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    check_admin_referer( 'coffeebrk_rss_save_feed' );

    $feed_id = isset($_POST['feed_id']) ? (int) $_POST['feed_id'] : 0;

    $res = coffeebrk_rss_save_feed([
        'feed_name' => $_POST['feed_name'] ?? '',
        'feed_url' => $_POST['feed_url'] ?? '',
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
        'import_limit' => $_POST['import_limit'] ?? 5,
        'category_id' => isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0,
    ], $feed_id ? $feed_id : null );

    if ( empty( $res['ok'] ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    coffeebrk_rss_admin_redirect( [ 'msg' => 'saved' ] );
}

function coffeebrk_rss_handle_delete_feed() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    $feed_id = isset($_GET['feed_id']) ? (int) $_GET['feed_id'] : 0;
    if ( $feed_id <= 0 ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    check_admin_referer( 'coffeebrk_rss_delete_' . $feed_id );

    $ok = coffeebrk_rss_delete_feed( $feed_id );
    coffeebrk_rss_admin_redirect( [ 'msg' => $ok ? 'deleted' : 'error' ] );
}

function coffeebrk_rss_handle_toggle_feed() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    $feed_id = isset($_GET['feed_id']) ? (int) $_GET['feed_id'] : 0;
    if ( $feed_id <= 0 ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    check_admin_referer( 'coffeebrk_rss_toggle_' . $feed_id );

    $feed = coffeebrk_rss_get_feed( $feed_id );
    if ( ! $feed ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    $enabled = ( (int) $feed['enabled'] !== 1 );
    $ok = coffeebrk_rss_set_feed_enabled( $feed_id, $enabled );

    coffeebrk_rss_admin_redirect( [ 'msg' => $ok ? 'toggled' : 'error' ] );
}

function coffeebrk_rss_handle_run_feed() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    $feed_id = isset($_GET['feed_id']) ? (int) $_GET['feed_id'] : 0;
    if ( $feed_id <= 0 ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    check_admin_referer( 'coffeebrk_rss_run_' . $feed_id );

    $res = coffeebrk_rss_import_feed( $feed_id );
    coffeebrk_rss_admin_redirect( [ 'msg' => ! empty( $res['ok'] ) ? 'ran' : 'error' ] );
}

function coffeebrk_rss_handle_run_all() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    check_admin_referer( 'coffeebrk_rss_run_all' );

    $res = coffeebrk_rss_import_all_enabled_feeds();
    coffeebrk_rss_admin_redirect( [ 'msg' => ! empty( $res['ok'] ) ? 'ran' : 'error' ] );
}
