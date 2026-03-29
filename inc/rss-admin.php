<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

function coffeebrk_rss_handle_enable_all() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( (string) $_GET['_wpnonce'], 'coffeebrk_rss_enable_all' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    global $wpdb;
    $table = coffeebrk_rss_table_name();
    $wpdb->update( $table, [ 'enabled' => 1 ], [ 1 => 1 ], [ '%d' ], [] );
    coffeebrk_rss_admin_redirect( [ 'msg' => 'enabled_all' ] );
}

function coffeebrk_rss_handle_disable_all() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( (string) $_GET['_wpnonce'], 'coffeebrk_rss_disable_all' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    global $wpdb;
    $table = coffeebrk_rss_table_name();
    $wpdb->update( $table, [ 'enabled' => 0 ], [ 1 => 1 ], [ '%d' ], [] );
    coffeebrk_rss_admin_redirect( [ 'msg' => 'disabled_all' ] );
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
add_action( 'admin_post_coffeebrk_rss_enable_all', 'coffeebrk_rss_handle_enable_all' );
add_action( 'admin_post_coffeebrk_rss_disable_all', 'coffeebrk_rss_handle_disable_all' );

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

    protected function get_views() {
        global $wpdb;
        $table = coffeebrk_rss_table_name();

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $enabled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE enabled = 1" );
        $disabled = max( 0, $total - $enabled );

        $status = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all';
        if ( $status !== 'active' && $status !== 'inactive' ) {
            $status = 'all';
        }

        $base = remove_query_arg( [ 'status', 'paged' ], coffeebrk_rss_admin_url() );

        $views = [];
        $views['all'] = sprintf(
            '<a href="%s" class="%s">All <span class="count">(%d)</span></a>',
            esc_url( $base ),
            $status === 'all' ? 'current' : '',
            (int) $total
        );
        $views['active'] = sprintf(
            '<a href="%s" class="%s">Active <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( 'status', 'active', $base ) ),
            $status === 'active' ? 'current' : '',
            (int) $enabled
        );
        $views['inactive'] = sprintf(
            '<a href="%s" class="%s">Inactive <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( 'status', 'inactive', $base ) ),
            $status === 'inactive' ? 'current' : '',
            (int) $disabled
        );

        return $views;
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
        $id = (int) $item['id'];
        $enabled = ( (int) $item['enabled'] === 1 );
        $toggle_nonce = wp_create_nonce( 'coffeebrk_rss_toggle_' . $id );
        $toggle_label = $enabled ? 'Disable' : 'Enable';
        $url = admin_url( 'admin-post.php?action=coffeebrk_rss_toggle_feed&feed_id=' . $id . '&_wpnonce=' . $toggle_nonce );

        return '<span style="margin-right:8px;">' . ( $enabled ? 'Yes' : 'No' ) . '</span>'
            . '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( $toggle_label ) . '</a>';
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
        $enable_all_nonce = wp_create_nonce( 'coffeebrk_rss_enable_all' );
        $disable_all_nonce = wp_create_nonce( 'coffeebrk_rss_disable_all' );
        echo '<div class="alignleft actions">';
        echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin-post.php?action=coffeebrk_rss_run_all&_wpnonce=' . $run_all_nonce ) ) . '">Run All Feeds Now</a>';
        echo '&nbsp;';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin-post.php?action=coffeebrk_rss_enable_all&_wpnonce=' . $enable_all_nonce ) ) . '">Enable All</a>';
        echo '&nbsp;';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin-post.php?action=coffeebrk_rss_disable_all&_wpnonce=' . $disable_all_nonce ) ) . '">Disable All</a>';
        echo '&nbsp;';
        echo '<a class="button button-primary" href="' . esc_url( coffeebrk_rss_admin_url( [ 'action' => 'add' ] ) ) . '">Add New Feed</a>';
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;

        $table = coffeebrk_rss_table_name();

        $status = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all';
        if ( $status !== 'active' && $status !== 'inactive' ) {
            $status = 'all';
        }

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

        // Build WHERE clause based on status filter
        $where_sql = '1=1';
        $where_params = [];
        if ( $status === 'active' ) {
            $where_sql = 'enabled = %d';
            $where_params[] = 1;
        } elseif ( $status === 'inactive' ) {
            $where_sql = 'enabled = %d';
            $where_params[] = 0;
        }

        // Get total count
        if ( empty( $where_params ) ) {
            $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
        } else {
            $total_items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $where_params ) );
        }

        // Build main query with proper escaping
        $orderby_escaped = esc_sql( $orderby );
        $order_escaped = esc_sql( $order );

        if ( empty( $where_params ) ) {
            $items = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby_escaped} {$order_escaped} LIMIT %d OFFSET %d", $per_page, $offset ),
                ARRAY_A
            );
        } else {
            $items = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby_escaped} {$order_escaped} LIMIT %d OFFSET %d", array_merge( $where_params, [ $per_page, $offset ] ) ),
                ARRAY_A
            );
        }

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

    $feeds_total = 0;
    $feeds_enabled = 0;
    if ( function_exists( 'coffeebrk_rss_table_name' ) ) {
        global $wpdb;
        $table_name = coffeebrk_rss_table_name();
        if ( is_string( $table_name ) && $table_name !== '' ) {
            $feeds_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
            $feeds_enabled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE enabled = 1" );
        }
    }

    echo '<p style="margin:4px 0 14px;color:#555;"><strong>Feeds:</strong> ' . esc_html( (string) $feeds_total ) . ' total, ' . esc_html( (string) $feeds_enabled ) . ' enabled</p>';

    if ( isset($_GET['msg']) ) {
        $msg = sanitize_key( (string) $_GET['msg'] );
        $type = ( $msg === 'error' ) ? 'error' : 'updated';
        $text = '';

        if ( $msg === 'saved' ) $text = 'Feed saved.';
        if ( $msg === 'deleted' ) $text = 'Feed deleted.';
        if ( $msg === 'toggled' ) $text = 'Feed updated.';
        if ( $msg === 'ran' ) {
            $imported = isset( $_GET['imported'] ) ? (int) $_GET['imported'] : null;
            $skipped = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : null;
            $errors = isset( $_GET['errors'] ) ? (int) $_GET['errors'] : null;

            $text = 'Import completed.';
            if ( $imported !== null || $skipped !== null || $errors !== null ) {
                $parts = [];
                if ( $imported !== null ) $parts[] = 'Imported: ' . $imported;
                if ( $skipped !== null ) $parts[] = 'Skipped: ' . $skipped;
                if ( $errors !== null ) $parts[] = 'Errors: ' . $errors;
                if ( $parts ) {
                    $text .= ' ' . implode( ' | ', $parts );
                }
            }
        }
        if ( $msg === 'error' ) $text = 'Action failed.';
        if ( $msg === 'enabled_all' ) $text = 'All feeds enabled.';
        if ( $msg === 'disabled_all' ) $text = 'All feeds disabled.';

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

    $next = wp_next_scheduled( 'coffeebrk_rss_import_all' );
    $next_str = $next ? wp_date( 'Y-m-d H:i:s', (int) $next ) : 'Not scheduled';
    echo '<p style="margin:8px 0 16px;color:#555;"><strong>Next auto-run (hourly):</strong> ' . esc_html( $next_str ) . '</p>';

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="coffeebrk-core-rss" />';
    $table->display();
    echo '</form>';

    if ( function_exists( 'coffeebrk_rss_log_get_last_24h' ) ) {
        $log = coffeebrk_rss_log_get_last_24h();
        if ( is_array( $log ) ) {
            $log = array_reverse( $log );
        } else {
            $log = [];
        }

        echo '<h2 style="margin-top:24px;">Last 24 hours (cron/import history)</h2>';
        echo '<table class="widefat striped">'
            .'<thead><tr>'
            .'<th style="width:170px;">Time</th>'
            .'<th style="width:80px;">Context</th>'
            .'<th style="width:110px;">Event</th>'
            .'<th>Feed</th>'
            .'<th style="width:90px;">Status</th>'
            .'<th style="width:90px;">Imported</th>'
            .'<th style="width:90px;">Skipped</th>'
            .'<th style="width:80px;">Post</th>'
            .'<th>Title / Message</th>'
            .'</tr></thead><tbody>';

        if ( ! empty( $log ) ) {
            foreach ( $log as $row ) {
                if ( ! is_array( $row ) ) continue;
                $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
                $time_str = $t > 0 ? wp_date( 'Y-m-d H:i:s', $t ) : '';
                $context = isset( $row['context'] ) ? (string) $row['context'] : '';
                $event = isset( $row['event'] ) ? (string) $row['event'] : '';
                $feed_name = isset( $row['feed_name'] ) ? (string) $row['feed_name'] : '';
                $status = isset( $row['status'] ) ? (string) $row['status'] : '';
                $imported = isset( $row['imported'] ) ? (int) $row['imported'] : 0;
                $skipped = isset( $row['skipped'] ) ? (int) $row['skipped'] : 0;
                $post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
                $title = isset( $row['title'] ) ? (string) $row['title'] : '';
                $message = isset( $row['message'] ) ? (string) $row['message'] : '';
                $reason = isset( $row['reason'] ) ? (string) $row['reason'] : '';

                $cls = ( $status === 'ok' ) ? 'cbk-ok' : ( $status === 'error' ? 'cbk-fail' : ( $status === 'skip' ? 'cbk-skip' : '' ) );
                $tail = $title !== '' ? $title : $message;
                if ( $reason !== '' ) {
                    $tail = ( $tail !== '' ? $tail . ' | ' : '' ) . 'Reason: ' . $reason;
                }

                echo '<tr>';
                echo '<td>' . esc_html( $time_str ) . '</td>';
                echo '<td>' . esc_html( $context ) . '</td>';
                echo '<td>' . esc_html( $event ) . '</td>';
                echo '<td>' . esc_html( $feed_name ) . '</td>';
                echo '<td class="' . esc_attr( $cls ) . '">' . esc_html( $status ) . '</td>';
                echo '<td>' . esc_html( (string) $imported ) . '</td>';
                echo '<td>' . esc_html( (string) $skipped ) . '</td>';
                echo '<td>' . esc_html( $post_id > 0 ? (string) $post_id : '—' ) . '</td>';
                echo '<td>' . esc_html( $tail ) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="9" style="color:#666;">No log entries in the last 24 hours.</td></tr>';
        }

        echo '</tbody></table>';
        echo '<style>.cbk-ok{color:#0a7b34;font-weight:600;} .cbk-skip{color:#8a6d3b;font-weight:600;} .cbk-fail{color:#b32d2e;font-weight:600;}</style>';
    }

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

    $res = coffeebrk_rss_import_feed( $feed_id, 'manual' );
    if ( ! empty( $res['ok'] ) ) {
        coffeebrk_rss_admin_redirect( [
            'msg' => 'ran',
            'imported' => (int) ( $res['imported'] ?? 0 ),
            'skipped' => (int) ( $res['skipped'] ?? 0 ),
        ] );
    }
    coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
}

function coffeebrk_rss_handle_run_all() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
    }

    check_admin_referer( 'coffeebrk_rss_run_all' );

    $res = coffeebrk_rss_import_all_enabled_feeds( 'manual' );
    if ( ! empty( $res['ok'] ) ) {
        coffeebrk_rss_admin_redirect( [
            'msg' => 'ran',
            'imported' => (int) ( $res['imported'] ?? 0 ),
            'skipped' => (int) ( $res['skipped'] ?? 0 ),
            'errors' => (int) ( $res['errors'] ?? 0 ),
        ] );
    }
    coffeebrk_rss_admin_redirect( [ 'msg' => 'error' ] );
}
