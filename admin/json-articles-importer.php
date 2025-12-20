<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once COFFEEBRK_CORE_PATH . 'includes/importers/class-coffeebrk-json-articles-importer.php';

add_action( 'admin_menu', function() {
    add_submenu_page(
        'coffeebrk-core',
        'JSON Articles Importer',
        'JSON Articles Importer',
        'manage_options',
        'coffeebrk-core-json-importer',
        'coffeebrk_core_json_importer_page'
    );
}, 25 );

function coffeebrk_core_json_importer_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $cats = get_categories( [ 'hide_empty' => false ] );
    $ajax_url = admin_url( 'admin-ajax.php' );
    $nonce = wp_create_nonce( 'cbk_json_articles_import' );

    echo '<div class="wrap">';
    echo '<h1>JSON Articles Importer</h1>';

    echo '<form id="cbk-json-import-form" method="post" enctype="multipart/form-data">';
    echo '<table class="form-table" role="presentation">';

    echo '<tr><th scope="row"><label for="cbk_json_file">JSON File</label></th><td>';
    echo '<input type="file" id="cbk_json_file" name="cbk_json_file" accept="application/json,.json" required />';
    echo '<p class="description">Upload a .json file containing an array of items.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Categories</th><td>';
    echo '<select id="cbk_categories" name="cbk_categories[]" multiple size="8" style="min-width:320px;">';
    foreach ( $cats as $c ) {
        printf( '<option value="%d">%s</option>', (int) $c->term_id, esc_html( $c->name ) );
    }
    echo '</select>';
    echo '<p class="description">Hold CTRL/CMD to select multiple.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Post Status</th><td>';
    echo '<select id="cbk_post_status" name="cbk_post_status">';
    echo '<option value="draft" selected>Draft</option>';
    echo '<option value="publish">Publish</option>';
    echo '</select>';
    echo '</td></tr>';

    echo '</table>';

    echo '<p><button type="submit" class="button button-primary" id="cbk_import_btn">Import</button></p>';
    echo '</form>';

    echo '<h2 style="margin-top:28px;">Import Log</h2>';

    echo '<div id="cbk-import-summary" style="margin:10px 0;">'
        .'<strong>Total:</strong> <span id="cbk_total">0</span> '
        .'<strong>Imported:</strong> <span id="cbk_imported">0</span> '
        .'<strong>Skipped:</strong> <span id="cbk_skipped">0</span> '
        .'<strong>Failed:</strong> <span id="cbk_failed">0</span>'
        .'</div>';

    echo '<table class="widefat striped" id="cbk-import-log">'
        .'<thead><tr><th style="width:60px;">#</th><th>Title</th><th style="width:120px;">Status</th><th>Reason</th><th style="width:120px;">Post ID</th></tr></thead>'
        .'<tbody></tbody>'
        .'</table>';

    echo '<style>#cbk-import-log td{vertical-align:top;} #cbk-import-log .cbk-ok{color:#0a7b34;font-weight:600;} #cbk-import-log .cbk-skip{color:#8a6d3b;font-weight:600;} #cbk-import-log .cbk-fail{color:#b32d2e;font-weight:600;}</style>';

    echo '<script>
(function(){
  const form = document.getElementById("cbk-json-import-form");
  const tbody = document.querySelector("#cbk-import-log tbody");
  const btn = document.getElementById("cbk_import_btn");
  const totalEl = document.getElementById("cbk_total");
  const importedEl = document.getElementById("cbk_imported");
  const skippedEl = document.getElementById("cbk_skipped");
  const failedEl = document.getElementById("cbk_failed");

  const ajaxUrl = "'.esc_js( $ajax_url ).'";
  const nonce = "'.esc_js( $nonce ).'";

  function setCounts(counts){
    totalEl.textContent = counts.total || 0;
    importedEl.textContent = counts.imported || 0;
    skippedEl.textContent = counts.skipped || 0;
    failedEl.textContent = counts.failed || 0;
  }

  function addRow(row){
    const tr = document.createElement("tr");
    const cls = row.status === "imported" ? "cbk-ok" : (row.status === "skipped" ? "cbk-skip" : "cbk-fail");
    tr.innerHTML = `<td>${row.index}</td><td>${row.title || ""}</td><td class="${cls}">${row.status}</td><td>${row.reason || ""}</td><td>${row.post_id || ""}</td>`;
    tbody.appendChild(tr);
  }

  async function postForm(action, payload){
    const fd = new FormData();
    fd.append("action", action);
    fd.append("nonce", nonce);
    Object.keys(payload || {}).forEach(k => {
      const v = payload[k];
      if (Array.isArray(v)) {
        v.forEach(x => fd.append(k + "[]", x));
      } else {
        fd.append(k, v);
      }
    });

    const res = await fetch(ajaxUrl, { method: "POST", credentials: "same-origin", body: fd });
    return await res.json();
  }

  async function runBatches(session){
    let offset = 0;
    const limit = 10;

    while(true){
      const resp = await postForm("cbk_json_articles_import_process", { session: session, offset: offset, limit: limit });
      if (!resp || !resp.success){
        addRow({ index: "-", title: "", status: "failed", reason: (resp && resp.data && resp.data.message) ? resp.data.message : "Import failed", post_id: "" });
        break;
      }
      const data = resp.data;
      if (data && data.rows){
        data.rows.forEach(addRow);
      }
      if (data && data.counts){
        setCounts(data.counts);
      }
      if (data && data.done){
        break;
      }
      offset = data.next_offset || (offset + limit);
    }
  }

  form.addEventListener("submit", async function(e){
    e.preventDefault();
    tbody.innerHTML = "";
    setCounts({ total: 0, imported: 0, skipped: 0, failed: 0 });

    const fileInput = document.getElementById("cbk_json_file");
    if (!fileInput.files || !fileInput.files[0]) return;

    btn.disabled = true;
    btn.textContent = "Importing...";

    try {
      const categories = Array.from(document.getElementById("cbk_categories").selectedOptions).map(o => o.value);
      const status = document.getElementById("cbk_post_status").value;

      const startFd = new FormData();
      startFd.append("action", "cbk_json_articles_import_start");
      startFd.append("nonce", nonce);
      startFd.append("cbk_json_file", fileInput.files[0]);
      startFd.append("post_status", status);
      categories.forEach(c => startFd.append("category_ids[]", c));

      const startRes = await fetch(ajaxUrl, { method: "POST", credentials: "same-origin", body: startFd });
      const startJson = await startRes.json();

      if (!startJson || !startJson.success){
        addRow({ index: "-", title: "", status: "failed", reason: (startJson && startJson.data && startJson.data.message) ? startJson.data.message : "Start failed", post_id: "" });
        btn.disabled = false;
        btn.textContent = "Import";
        return;
      }

      const session = startJson.data.session;
      setCounts(startJson.data.counts || {});
      await runBatches(session);
    } catch(err){
      addRow({ index: "-", title: "", status: "failed", reason: String(err), post_id: "" });
    } finally {
      btn.disabled = false;
      btn.textContent = "Import";
    }
  });
})();
</script>';

    echo '</div>';
}

add_action( 'wp_ajax_cbk_json_articles_import_start', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Not allowed.' ], 403 );
    }

    $nonce = $_POST['nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'cbk_json_articles_import' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 400 );
    }

    if ( empty( $_FILES['cbk_json_file'] ) ) {
        wp_send_json_error( [ 'message' => 'No file uploaded.' ], 400 );
    }

    $file = $_FILES['cbk_json_file'];
    $name = $file['name'] ?? '';
    if ( ! is_string( $name ) || strtolower( substr( $name, -5 ) ) !== '.json' ) {
        wp_send_json_error( [ 'message' => 'Only .json files are allowed.' ], 400 );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $overrides = [
        'test_form' => false,
        'mimes' => [ 'json' => 'application/json' ],
    ];

    $uploaded = wp_handle_upload( $file, $overrides );
    if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) ) {
        wp_send_json_error( [ 'message' => $uploaded['error'] ?? 'Upload failed.' ], 400 );
    }

    $items = Coffeebrk_Json_Articles_Importer::parse_json_file( $uploaded['file'] );
    if ( is_wp_error( $items ) ) {
        wp_send_json_error( [ 'message' => $items->get_error_message() ], 400 );
    }

    $category_ids = isset( $_POST['category_ids'] ) ? (array) $_POST['category_ids'] : [];
    $category_ids = array_filter( array_map( 'intval', $category_ids ) );

    $post_status = isset( $_POST['post_status'] ) ? (string) $_POST['post_status'] : 'draft';
    $post_status = in_array( $post_status, [ 'draft', 'publish' ], true ) ? $post_status : 'draft';

    $session = 'cbk_json_import_' . get_current_user_id() . '_' . wp_generate_password( 12, false, false );

    $payload = [
        'items' => $items,
        'args' => [
            'category_ids' => $category_ids,
            'post_status' => $post_status,
        ],
        'counts' => [
            'total' => count( $items ),
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
        ],
    ];

    set_transient( $session, $payload, 60 * 60 );

    wp_send_json_success( [
        'session' => $session,
        'counts' => $payload['counts'],
    ] );
} );

add_action( 'wp_ajax_cbk_json_articles_import_process', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Not allowed.' ], 403 );
    }

    $nonce = $_POST['nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'cbk_json_articles_import' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 400 );
    }

    $session = isset( $_POST['session'] ) ? (string) $_POST['session'] : '';
    if ( $session === '' ) {
        wp_send_json_error( [ 'message' => 'Missing session.' ], 400 );
    }

    $payload = get_transient( $session );
    if ( ! is_array( $payload ) || empty( $payload['items'] ) || ! isset( $payload['args'] ) ) {
        wp_send_json_error( [ 'message' => 'Session expired or invalid.' ], 400 );
    }

    $items = (array) $payload['items'];
    $args = (array) $payload['args'];
    $counts = (array) ( $payload['counts'] ?? [ 'total' => count( $items ), 'imported' => 0, 'skipped' => 0, 'failed' => 0 ] );

    $offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
    $limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 10;
    if ( $limit <= 0 || $limit > 50 ) $limit = 10;

    $rows = [];
    $end = min( $offset + $limit, count( $items ) );

    for ( $i = $offset; $i < $end; $i++ ) {
        $item = $items[ $i ];
        if ( ! is_array( $item ) ) {
            $rows[] = [
                'index' => $i + 1,
                'title' => '',
                'status' => 'failed',
                'reason' => 'Invalid item structure.',
                'post_id' => 0,
            ];
            $counts['failed'] = (int) ( $counts['failed'] ?? 0 ) + 1;
            continue;
        }

        $title = isset( $item['title'] ) ? (string) $item['title'] : '';

        $result = Coffeebrk_Json_Articles_Importer::import_item( $item, $args );
        $status = $result['status'] ?? 'failed';

        if ( $status === 'imported' ) {
            $counts['imported'] = (int) ( $counts['imported'] ?? 0 ) + 1;
        } elseif ( $status === 'skipped' ) {
            $counts['skipped'] = (int) ( $counts['skipped'] ?? 0 ) + 1;
        } else {
            $counts['failed'] = (int) ( $counts['failed'] ?? 0 ) + 1;
        }

        $rows[] = [
            'index' => $i + 1,
            'title' => $title,
            'status' => $status,
            'reason' => $result['reason'] ?? '',
            'post_id' => (int) ( $result['post_id'] ?? 0 ),
        ];
    }

    $done = $end >= count( $items );

    $payload['counts'] = $counts;
    set_transient( $session, $payload, 60 * 60 );

    if ( $done ) {
        delete_transient( $session );
    }

    wp_send_json_success( [
        'rows' => $rows,
        'counts' => [
            'total' => (int) ( $counts['total'] ?? count( $items ) ),
            'imported' => (int) ( $counts['imported'] ?? 0 ),
            'skipped' => (int) ( $counts['skipped'] ?? 0 ),
            'failed' => (int) ( $counts['failed'] ?? 0 ),
        ],
        'next_offset' => $end,
        'done' => $done,
    ] );
} );
