<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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

add_action( 'admin_post_coffeebrk_x_save_profile', 'coffeebrk_x_handle_save_profile' );
add_action( 'admin_post_coffeebrk_x_delete_profile', 'coffeebrk_x_handle_delete_profile' );
add_action( 'admin_post_coffeebrk_x_toggle_profile', 'coffeebrk_x_handle_toggle_profile' );
add_action( 'admin_post_coffeebrk_x_sync_profile', 'coffeebrk_x_handle_sync_profile' );
add_action( 'admin_post_coffeebrk_x_sync_all', 'coffeebrk_x_handle_sync_all' );
add_action( 'admin_post_coffeebrk_x_delete_post', 'coffeebrk_x_handle_delete_post' );
add_action( 'admin_post_coffeebrk_x_toggle_post_status', 'coffeebrk_x_handle_toggle_post_status' );
add_action( 'admin_post_coffeebrk_x_toggle_post_featured', 'coffeebrk_x_handle_toggle_post_featured' );
add_action( 'admin_post_coffeebrk_x_save_settings', 'coffeebrk_x_handle_save_settings' );
add_action( 'wp_ajax_coffeebrk_x_test_connection', 'coffeebrk_x_ajax_test_connection' );

function coffeebrk_x_admin_url( array $args = [] ) : string {
    return add_query_arg( $args, admin_url( 'admin.php?page=coffeebrk-core-x' ) );
}

function coffeebrk_x_admin_redirect( array $args = [] ) : void {
    wp_safe_redirect( coffeebrk_x_admin_url( $args ) );
    exit;
}

// ===========================================================================
// Profiles list table
// ===========================================================================

class Coffeebrk_X_Profiles_Table extends WP_List_Table {
    public function get_columns() {
        return [
            'username'       => 'Username',
            'display_name'   => 'Display Name',
            'enabled'        => 'Enabled',
            'category_id'    => 'Category',
            'last_synced_at' => 'Last Synced',
            'last_run'       => 'Last Run',
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table = coffeebrk_x_profiles_table_name();

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $enabled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE enabled = 1" );
        $disabled = max( 0, $total - $enabled );

        $status = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all';
        if ( $status !== 'active' && $status !== 'inactive' ) $status = 'all';

        $base = remove_query_arg( [ 'status', 'paged' ], coffeebrk_x_admin_url() );

        return [
            'all' => sprintf( '<a href="%s" class="%s">All <span class="count">(%d)</span></a>',
                esc_url( $base ), $status === 'all' ? 'current' : '', $total ),
            'active' => sprintf( '<a href="%s" class="%s">Active <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'active', $base ) ), $status === 'active' ? 'current' : '', $enabled ),
            'inactive' => sprintf( '<a href="%s" class="%s">Inactive <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'inactive', $base ) ), $status === 'inactive' ? 'current' : '', $disabled ),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'username'       => [ 'username', false ],
            'enabled'        => [ 'enabled', false ],
            'last_synced_at' => [ 'last_synced_at', false ],
            'last_run'       => [ 'last_run', false ],
        ];
    }

    public function column_username( $item ) {
        $id = (int) $item['id'];
        $name = '@' . esc_html( (string) $item['username'] );

        $actions = [];
        $actions['edit'] = sprintf( '<a href="%s">Edit</a>', esc_url( coffeebrk_x_admin_url( [ 'action' => 'edit', 'profile_id' => $id ] ) ) );

        $toggle_nonce = wp_create_nonce( 'coffeebrk_x_toggle_' . $id );
        $toggle_label = ( (int) $item['enabled'] === 1 ) ? 'Disable' : 'Enable';
        $actions['toggle'] = sprintf( '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_toggle_profile&profile_id=' . $id . '&_wpnonce=' . $toggle_nonce ) ),
            esc_html( $toggle_label ) );

        $sync_nonce = wp_create_nonce( 'coffeebrk_x_sync_' . $id );
        $actions['sync'] = sprintf( '<a href="%s">Sync Now</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_sync_profile&profile_id=' . $id . '&_wpnonce=' . $sync_nonce ) ) );

        $del_nonce = wp_create_nonce( 'coffeebrk_x_delete_profile_' . $id );
        $actions['delete'] = sprintf( '<a href="%s" onclick="return confirm(%s);">Delete</a>',
            esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_delete_profile&profile_id=' . $id . '&_wpnonce=' . $del_nonce ) ),
            esc_js( 'Are you sure you want to delete this profile?' ) );

        return $name . $this->row_actions( $actions );
    }

    public function column_enabled( $item ) {
        return ( (int) $item['enabled'] === 1 ) ? 'Yes' : 'No';
    }

    public function column_category_id( $item ) {
        $cid = (int) ( $item['category_id'] ?? 0 );
        if ( $cid <= 0 ) return '—';
        $term = get_term( $cid, 'category' );
        return ( ! $term || is_wp_error( $term ) ) ? '—' : esc_html( $term->name );
    }

    public function column_default( $item, $column_name ) {
        $val = $item[ $column_name ] ?? '';
        if ( $column_name === 'last_synced_at' || $column_name === 'last_run' ) {
            return $val ? esc_html( (string) $val ) : '—';
        }
        return is_scalar( $val ) ? esc_html( (string) $val ) : '';
    }

    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;

        $sync_all_nonce = wp_create_nonce( 'coffeebrk_x_sync_all' );
        echo '<div class="alignleft actions">';
        echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin-post.php?action=coffeebrk_x_sync_all&_wpnonce=' . $sync_all_nonce ) ) . '">Sync All Enabled</a>';
        echo '&nbsp;';
        echo '<a class="button button-primary" href="' . esc_url( coffeebrk_x_admin_url( [ 'action' => 'add' ] ) ) . '">Add Profile</a>';
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;
        $table = coffeebrk_x_profiles_table_name();

        $status = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all';
        if ( $status !== 'active' && $status !== 'inactive' ) $status = 'all';

        $per_page = 20;
        $paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        $offset = ( $paged - 1 ) * $per_page;

        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( (string) $_GET['orderby'] ) : 'username';
        $order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( (string) $_GET['order'] ) ) : 'ASC';

        $allowed_orderby = [ 'id', 'username', 'display_name', 'enabled', 'last_synced_at', 'last_run' ];
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) $orderby = 'username';
        if ( $order !== 'ASC' && $order !== 'DESC' ) $order = 'ASC';

        $where_sql = '1=1';
        $where_params = [];
        if ( $status === 'active' ) { $where_sql = 'enabled = %d'; $where_params[] = 1; }
        elseif ( $status === 'inactive' ) { $where_sql = 'enabled = %d'; $where_params[] = 0; }

        $total_items = $where_params
            ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $where_params ) )
            : (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );

        $orderby_escaped = esc_sql( $orderby );
        $order_escaped = esc_sql( $order );

        $list_params = array_merge( $where_params, [ $per_page, $offset ] );
        $items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby_escaped} {$order_escaped} LIMIT %d OFFSET %d", $list_params ),
            ARRAY_A
        );

        $this->items = $items ? $items : [];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => (int) ceil( $total_items / $per_page ),
        ]);

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'username' ];
    }
}

// ===========================================================================
// Posts list table (read-only)
// ===========================================================================

class Coffeebrk_X_Posts_Table extends WP_List_Table {
    public function get_columns() {
        return [
            'author_username' => 'Author',
            'text'             => 'Text',
            'posted_at'        => 'Posted',
            'engagement'       => 'Engagement',
            'status'           => 'Status',
            'permalink'        => 'Link',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'posted_at' => [ 'posted_at', true ],
            'created_at' => [ 'created_at', false ],
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
            'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( (string) $_GET['orderby'] ) : 'posted_at',
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

    $tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'profiles';
    if ( ! in_array( $tab, [ 'profiles', 'posts', 'settings' ], true ) ) $tab = 'profiles';

    echo '<div class="wrap">';
    echo '<h1>X / Social</h1>';

    if ( isset( $_GET['msg'] ) ) {
        coffeebrk_x_render_admin_notice( sanitize_key( (string) $_GET['msg'] ) );
    }

    echo '<h2 class="nav-tab-wrapper">';
    foreach ( [ 'profiles' => 'Profiles', 'posts' => 'Posts', 'settings' => 'Settings' ] as $key => $label ) {
        $class = ( $tab === $key ) ? 'nav-tab nav-tab-active' : 'nav-tab';
        echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( coffeebrk_x_admin_url( [ 'tab' => $key ] ) ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    if ( $tab === 'posts' ) {
        coffeebrk_x_render_posts_tab();
    } elseif ( $tab === 'settings' ) {
        coffeebrk_x_render_settings_tab();
    } else {
        coffeebrk_x_render_profiles_tab();
    }

    echo '</div>';
}

function coffeebrk_x_render_admin_notice( string $msg ) : void {
    $type = ( $msg === 'error' ) ? 'error' : 'updated';
    $text = '';

    if ( $msg === 'saved' ) $text = 'Profile saved.';
    if ( $msg === 'deleted' ) $text = 'Profile deleted.';
    if ( $msg === 'toggled' ) $text = 'Profile updated.';
    if ( $msg === 'settings_saved' ) $text = 'Settings saved.';
    if ( $msg === 'post_deleted' ) $text = 'Post deleted.';
    if ( $msg === 'post_updated' ) $text = 'Post updated.';
    if ( $msg === 'error' ) $text = 'Action failed.';
    if ( $msg === 'synced' ) {
        $imported = isset( $_GET['imported'] ) ? (int) $_GET['imported'] : null;
        $skipped = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : null;
        $text = 'Sync completed.';
        $parts = [];
        if ( $imported !== null ) $parts[] = 'Imported: ' . $imported;
        if ( $skipped !== null ) $parts[] = 'Skipped: ' . $skipped;
        if ( $parts ) $text .= ' ' . implode( ' | ', $parts );
    }

    if ( $text ) {
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
    }
}

function coffeebrk_x_render_profiles_tab() : void {
    $action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';

    if ( $action === 'add' || $action === 'edit' ) {
        $profile_id = isset( $_GET['profile_id'] ) ? (int) $_GET['profile_id'] : 0;
        $profile = $profile_id ? coffeebrk_x_get_profile( $profile_id ) : null;
        coffeebrk_x_render_profile_form( $profile );
        return;
    }

    $next = wp_next_scheduled( 'coffeebrk_x_sync_all' );
    $interval_labels = coffeebrk_x_interval_labels();
    $settings = coffeebrk_x_get_settings();
    $interval_label = $interval_labels[ $settings['sync_interval'] ] ?? $settings['sync_interval'];
    $next_str = $next ? wp_date( 'Y-m-d H:i:s', (int) $next ) : 'Not scheduled (manual only)';

    echo '<p style="margin:8px 0 16px;color:#555;"><strong>Sync interval:</strong> ' . esc_html( $interval_label )
        . ' &nbsp; <strong>Next auto-sync:</strong> ' . esc_html( $next_str ) . '</p>';

    $table = new Coffeebrk_X_Profiles_Table();
    $table->prepare_items();

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="coffeebrk-core-x" />';
    echo '<input type="hidden" name="tab" value="profiles" />';
    $table->display();
    echo '</form>';

    coffeebrk_x_render_activity_log();
}

function coffeebrk_x_render_activity_log() : void {
    $log = coffeebrk_x_log_get_last_24h();
    $log = is_array( $log ) ? array_reverse( $log ) : [];

    echo '<h2 style="margin-top:24px;">Last 24 hours (sync history)</h2>';
    echo '<table class="widefat striped">'
        . '<thead><tr>'
        . '<th style="width:170px;">Time</th>'
        . '<th style="width:80px;">Context</th>'
        . '<th style="width:110px;">Event</th>'
        . '<th>Profile</th>'
        . '<th style="width:90px;">Status</th>'
        . '<th style="width:90px;">Imported</th>'
        . '<th style="width:90px;">Skipped</th>'
        . '<th>Message</th>'
        . '</tr></thead><tbody>';

    if ( ! empty( $log ) ) {
        foreach ( $log as $row ) {
            if ( ! is_array( $row ) ) continue;
            $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
            $cls = ( $row['status'] ?? '' ) === 'ok' ? 'cbk-ok' : ( ( $row['status'] ?? '' ) === 'error' ? 'cbk-fail' : ( ( $row['status'] ?? '' ) === 'skip' ? 'cbk-skip' : '' ) );

            echo '<tr>';
            echo '<td>' . esc_html( $t > 0 ? wp_date( 'Y-m-d H:i:s', $t ) : '' ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['context'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['event'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['username'] ?? '' ) ) . '</td>';
            echo '<td class="' . esc_attr( $cls ) . '">' . esc_html( (string) ( $row['status'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['imported'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['skipped'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['message'] ?? ( $row['reason'] ?? '' ) ) ) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8" style="color:#666;">No log entries in the last 24 hours.</td></tr>';
    }

    echo '</tbody></table>';
    echo '<style>.cbk-ok{color:#0a7b34;font-weight:600;} .cbk-skip{color:#8a6d3b;font-weight:600;} .cbk-fail{color:#b32d2e;font-weight:600;}</style>';
}

function coffeebrk_x_render_profile_form( ?array $profile ) : void {
    $is_edit = $profile && ! empty( $profile['id'] );
    $id = $is_edit ? (int) $profile['id'] : 0;

    $username = $is_edit ? (string) $profile['username'] : '';
    $display_name = $is_edit ? (string) $profile['display_name'] : '';
    $enabled = $is_edit ? ( (int) $profile['enabled'] === 1 ) : true;
    $max_items = $is_edit ? $profile['max_items'] : '';
    $include_replies = $is_edit ? $profile['include_replies'] : '';
    $category_id = $is_edit ? (int) ( $profile['category_id'] ?? 0 ) : 0;

    echo '<h2>' . ( $is_edit ? 'Edit Profile' : 'Add X Profile' ) . '</h2>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="coffeebrk_x_save_profile" />';
    if ( $is_edit ) {
        echo '<input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
    }
    wp_nonce_field( 'coffeebrk_x_save_profile' );

    echo '<table class="form-table" role="presentation">';

    echo '<tr><th scope="row"><label for="username">X Username</label></th><td>';
    echo '<input name="username" id="username" type="text" class="regular-text" value="' . esc_attr( $username ) . '" placeholder="username (without @)" required />';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="display_name">Display Name (optional)</label></th><td>';
    echo '<input name="display_name" id="display_name" type="text" class="regular-text" value="' . esc_attr( $display_name ) . '" />';
    echo '</td></tr>';

    echo '<tr><th scope="row">Enabled</th><td>';
    echo '<label><input name="enabled" type="checkbox" value="1" ' . checked( $enabled, true, false ) . ' /> Enable this profile</label>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Category</th><td>';
    wp_dropdown_categories([
        'taxonomy' => 'category', 'hide_empty' => false, 'name' => 'category_id', 'id' => 'category_id',
        'selected' => $category_id, 'show_option_none' => '— None —', 'option_none_value' => '0',
    ]);
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="max_items">Max items per sync (optional)</label></th><td>';
    echo '<input name="max_items" id="max_items" type="number" min="1" max="100" value="' . esc_attr( (string) $max_items ) . '" placeholder="use global default" />';
    echo '</td></tr>';

    echo '<tr><th scope="row">Include replies (optional)</th><td>';
    echo '<select name="include_replies">';
    echo '<option value=""' . selected( $include_replies, '', false ) . '>Use global default</option>';
    echo '<option value="1"' . selected( (string) $include_replies, '1', false ) . '>Yes</option>';
    echo '<option value="0"' . selected( (string) $include_replies, '0', false ) . '>No</option>';
    echo '</select>';
    echo '</td></tr>';

    echo '</table>';

    submit_button( $is_edit ? 'Save Profile' : 'Add Profile' );
    echo '<a class="button button-secondary" href="' . esc_url( coffeebrk_x_admin_url() ) . '" style="margin-left:8px;">Back to list</a>';
    echo '</form>';
}

function coffeebrk_x_render_posts_tab() : void {
    $table = new Coffeebrk_X_Posts_Table();
    $table->prepare_items();

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="coffeebrk-core-x" />';
    echo '<input type="hidden" name="tab" value="posts" />';
    $table->display();
    echo '</form>';
}

function coffeebrk_x_render_settings_tab() : void {
    $settings = coffeebrk_x_get_settings();
    $interval_labels = coffeebrk_x_interval_labels();

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="coffeebrk_x_save_settings" />';
    wp_nonce_field( 'coffeebrk_x_save_settings' );

    echo '<table class="form-table" role="presentation">';

    echo '<tr><th scope="row"><label for="apify_api_token">Apify API Token</label></th><td>';
    echo '<input type="password" name="apify_api_token" id="apify_api_token" class="regular-text" style="width:400px;" value="' . esc_attr( (string) $settings['apify_api_token'] ) . '" autocomplete="off" />';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="apify_actor_id">Apify Actor ID</label></th><td>';
    echo '<input type="text" name="apify_actor_id" id="apify_actor_id" class="regular-text" style="width:400px;" value="' . esc_attr( (string) $settings['apify_actor_id'] ) . '" />';
    echo '<p class="description">Default: feedminer/x-tweet-scraper</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="sync_interval">Sync Interval</label></th><td>';
    echo '<select name="sync_interval" id="sync_interval">';
    foreach ( $interval_labels as $val => $label ) {
        echo '<option value="' . esc_attr( $val ) . '"' . selected( $settings['sync_interval'], $val, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="default_max_items">Default Max Items per Profile</label></th><td>';
    echo '<input type="number" name="default_max_items" id="default_max_items" min="1" max="100" value="' . esc_attr( (string) $settings['default_max_items'] ) . '" />';
    echo '</td></tr>';

    echo '<tr><th scope="row">Include Replies (default)</th><td>';
    echo '<label><input name="default_include_replies" type="checkbox" value="1" ' . checked( (bool) $settings['default_include_replies'], true, false ) . ' /> Include replies by default</label>';
    echo '</td></tr>';

    echo '</table>';

    submit_button( 'Save Settings' );
    echo '</form>';

    echo '<p><button type="button" class="button" id="cbk-x-test-connection">Test Apify Connection</button> <span id="cbk-x-test-result" style="margin-left:8px;"></span></p>';

    $ajax_nonce = wp_create_nonce( 'coffeebrk_x_test_connection' );
    ?>
    <script>
    (function() {
        var btn = document.getElementById('cbk-x-test-connection');
        var result = document.getElementById('cbk-x-test-result');
        if (!btn) return;
        btn.addEventListener('click', function() {
            result.textContent = 'Testing…';
            var body = new URLSearchParams();
            body.set('action', 'coffeebrk_x_test_connection');
            body.set('nonce', '<?php echo esc_js( $ajax_nonce ); ?>');
            body.set('apify_actor_id', document.getElementById('apify_actor_id').value);
            body.set('apify_api_token', document.getElementById('apify_api_token').value);
            fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                var msg = (json && json.data && json.data.message) ? json.data.message : 'Unknown response.';
                result.textContent = (json && json.success ? '✓ ' : '✗ ') + msg;
            })
            .catch(function() {
                result.textContent = 'Request failed.';
            });
        });
    })();
    </script>
    <?php
}

// ===========================================================================
// admin-post.php handlers
// ===========================================================================

function coffeebrk_x_handle_save_profile() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );
    check_admin_referer( 'coffeebrk_x_save_profile' );

    $profile_id = isset( $_POST['profile_id'] ) ? (int) $_POST['profile_id'] : 0;

    $res = coffeebrk_x_save_profile([
        'username'         => $_POST['username'] ?? '',
        'display_name'     => $_POST['display_name'] ?? '',
        'enabled'          => isset( $_POST['enabled'] ) ? 1 : 0,
        'category_id'      => isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0,
        'max_items'        => $_POST['max_items'] ?? '',
        'include_replies'  => $_POST['include_replies'] ?? '',
    ], $profile_id ? $profile_id : null );

    coffeebrk_x_admin_redirect( [ 'msg' => empty( $res['ok'] ) ? 'error' : 'saved' ] );
}

function coffeebrk_x_handle_delete_profile() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    $profile_id = isset( $_GET['profile_id'] ) ? (int) $_GET['profile_id'] : 0;
    if ( $profile_id <= 0 ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    check_admin_referer( 'coffeebrk_x_delete_profile_' . $profile_id );

    $ok = coffeebrk_x_delete_profile( $profile_id );
    coffeebrk_x_admin_redirect( [ 'msg' => $ok ? 'deleted' : 'error' ] );
}

function coffeebrk_x_handle_toggle_profile() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    $profile_id = isset( $_GET['profile_id'] ) ? (int) $_GET['profile_id'] : 0;
    if ( $profile_id <= 0 ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    check_admin_referer( 'coffeebrk_x_toggle_' . $profile_id );

    $profile = coffeebrk_x_get_profile( $profile_id );
    if ( ! $profile ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    $ok = coffeebrk_x_set_profile_enabled( $profile_id, (int) $profile['enabled'] !== 1 );
    coffeebrk_x_admin_redirect( [ 'msg' => $ok ? 'toggled' : 'error' ] );
}

function coffeebrk_x_handle_sync_profile() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    $profile_id = isset( $_GET['profile_id'] ) ? (int) $_GET['profile_id'] : 0;
    if ( $profile_id <= 0 ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );

    check_admin_referer( 'coffeebrk_x_sync_' . $profile_id );

    $res = coffeebrk_x_sync_profile( $profile_id, 'manual' );
    if ( ! empty( $res['ok'] ) ) {
        coffeebrk_x_admin_redirect( [ 'msg' => 'synced', 'imported' => (int) ( $res['imported'] ?? 0 ), 'skipped' => (int) ( $res['skipped'] ?? 0 ) ] );
    }
    coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );
}

function coffeebrk_x_handle_sync_all() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );
    check_admin_referer( 'coffeebrk_x_sync_all' );

    $res = coffeebrk_x_sync_all_enabled( 'manual' );
    if ( ! empty( $res['ok'] ) ) {
        coffeebrk_x_admin_redirect( [ 'msg' => 'synced', 'imported' => (int) ( $res['imported'] ?? 0 ), 'skipped' => (int) ( $res['skipped'] ?? 0 ) ] );
    }
    coffeebrk_x_admin_redirect( [ 'msg' => 'error' ] );
}

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

function coffeebrk_x_handle_save_settings() : void {
    if ( ! current_user_can( 'manage_options' ) ) coffeebrk_x_admin_redirect( [ 'tab' => 'settings', 'msg' => 'error' ] );
    check_admin_referer( 'coffeebrk_x_save_settings' );

    $interval = isset( $_POST['sync_interval'] ) ? sanitize_key( (string) $_POST['sync_interval'] ) : 'coffeebrk_x_6h';
    if ( ! in_array( $interval, coffeebrk_x_valid_intervals(), true ) ) $interval = 'coffeebrk_x_6h';

    $max_items = isset( $_POST['default_max_items'] ) ? (int) $_POST['default_max_items'] : 20;
    if ( $max_items < 1 ) $max_items = 1;
    if ( $max_items > 100 ) $max_items = 100;

    $settings = [
        'apify_api_token'         => isset( $_POST['apify_api_token'] ) ? trim( (string) $_POST['apify_api_token'] ) : '',
        'apify_actor_id'          => isset( $_POST['apify_actor_id'] ) ? sanitize_text_field( (string) $_POST['apify_actor_id'] ) : 'feedminer/x-tweet-scraper',
        'sync_interval'           => $interval,
        'default_max_items'       => $max_items,
        'default_include_replies' => isset( $_POST['default_include_replies'] ) ? 1 : 0,
    ];

    update_option( 'coffeebrk_x_settings', $settings, false );

    // Re-derive the cron schedule immediately so an interval change takes effect now.
    coffeebrk_x_schedule_cron();

    coffeebrk_x_admin_redirect( [ 'tab' => 'settings', 'msg' => 'settings_saved' ] );
}

function coffeebrk_x_ajax_test_connection() : void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Not allowed.' ], 403 );
    }

    $nonce = $_POST['nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'coffeebrk_x_test_connection' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 400 );
    }

    $actor_id = isset( $_POST['apify_actor_id'] ) ? sanitize_text_field( (string) $_POST['apify_actor_id'] ) : '';
    $token = isset( $_POST['apify_api_token'] ) ? trim( (string) $_POST['apify_api_token'] ) : '';

    // Blank token in the form means "use the already-saved one" (password
    // fields don't round-trip their real value once masked by the browser).
    if ( $token === '' ) {
        $settings = coffeebrk_x_get_settings();
        $token = (string) $settings['apify_api_token'];
        if ( $actor_id === '' ) $actor_id = (string) $settings['apify_actor_id'];
    }

    $res = Coffeebrk_Apify_X_Provider::test_connection( $actor_id, $token );

    if ( ! empty( $res['ok'] ) ) {
        wp_send_json_success( [ 'message' => $res['message'] ] );
    }
    wp_send_json_error( [ 'message' => $res['message'] ?? 'Connection failed.' ] );
}
