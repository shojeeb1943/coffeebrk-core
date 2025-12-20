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
    $demo_url = COFFEEBRK_CORE_URL . 'assets/demo-articles.json';

    $demo_path = COFFEEBRK_CORE_PATH . 'assets/demo-articles.json';
    $demo_json = '';
    if ( file_exists( $demo_path ) ) {
        $raw_demo = file_get_contents( $demo_path );
        if ( is_string( $raw_demo ) && $raw_demo !== '' ) {
            $decoded = json_decode( $raw_demo, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $pretty = json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                if ( is_string( $pretty ) ) {
                    $demo_json = $pretty;
                }
            }
        }
    }

    if ( $demo_json === '' ) {
        $demo_json = "[\n  {\n    \"title\": \"Article title\",\n    \"description\": \"Article description\",\n    \"image\": \"https://external-image-url.jpg\",\n    \"url\": \"https://source-article-url\",\n    \"source\": \"Source Name\",\n    \"date\": \"2025-12-09\",\n    \"tagline\": null,\n    \"type\": null,\n    \"logo\": null\n  }\n]";
    }

    // For the demo textarea, show the common "object list" paste format (no surrounding [ ]).
    $demo_json_display = $demo_json;
    $trimmed = trim( $demo_json_display );
    if ( substr( $trimmed, 0, 1 ) === '[' && substr( $trimmed, -1 ) === ']' ) {
        $inner = trim( substr( $trimmed, 1, -1 ) );
        // Remove leading/trailing newlines that come from pretty JSON.
        $inner = preg_replace( '/^\s*\n/', '', $inner );
        $inner = preg_replace( '/\n\s*$/', '', $inner );
        $demo_json_display = $inner;
    }

    echo '<div class="wrap">';
    echo '<h1>JSON Articles Importer</h1>';

    echo '<div class="cbk-json-importer-grid" style="display:grid;grid-template-columns:1fr 420px;gap:16px;align-items:start;">';

    echo '<div class="cbk-json-importer-main">';

    echo '<form id="cbk-json-import-form" method="post" enctype="multipart/form-data">';
    echo '<table class="form-table" role="presentation">';

    echo '<tr><th scope="row"><label for="cbk_json_file">JSON File</label></th><td>';
    echo '<input type="file" id="cbk_json_file" name="cbk_json_file" accept="application/json,.json" />';
    echo '<p class="description">Upload a .json file containing an array of items.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="cbk_json_text">Or Paste JSON</label></th><td>';
    echo '<textarea id="cbk_json_text" name="cbk_json_text" rows="8" style="width:100%;max-width:860px;" placeholder="Paste JSON array here..."></textarea>';
    echo '<p class="description">If you paste JSON here, the file upload is optional.</p>';
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

  const fileInput = document.getElementById("cbk_json_file");
  const jsonTextEl = document.getElementById("cbk_json_text");

  function syncInputs(){
    const hasText = !!((jsonTextEl && jsonTextEl.value) ? jsonTextEl.value.trim() : "");
    const hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (hasText) {
      if (fileInput) {
        fileInput.value = "";
        fileInput.disabled = true;
      }
      if (jsonTextEl) {
        jsonTextEl.disabled = false;
      }
      return;
    }

    if (hasFile) {
      if (jsonTextEl) {
        jsonTextEl.value = "";
        jsonTextEl.disabled = true;
      }
      if (fileInput) {
        fileInput.disabled = false;
      }
      return;
    }

    if (fileInput) fileInput.disabled = false;
    if (jsonTextEl) jsonTextEl.disabled = false;
  }

  if (jsonTextEl) {
    jsonTextEl.addEventListener("input", syncInputs);
  }
  if (fileInput) {
    fileInput.addEventListener("change", syncInputs);
  }
  syncInputs();

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

    const jsonText = (jsonTextEl ? jsonTextEl.value : "").trim();
    const hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);
    if (!hasFile && !jsonText) return;

    btn.disabled = true;
    btn.textContent = "Importing...";

    try {
      const categories = Array.from(document.getElementById("cbk_categories").selectedOptions).map(o => o.value);
      const status = document.getElementById("cbk_post_status").value;

      const startFd = new FormData();
      startFd.append("action", "cbk_json_articles_import_start");
      startFd.append("nonce", nonce);
      if (hasFile) {
        startFd.append("cbk_json_file", fileInput.files[0]);
      }
      if (jsonText) {
        startFd.append("cbk_json_text", jsonText);
      }
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

    echo '<script>(function(){
      function setStatus(st,t){if(!st)return;st.textContent=t;setTimeout(function(){st.textContent="";},1800);}
      function legacyCopy(text){
        var tmp=document.createElement("textarea");
        tmp.value=text;
        tmp.setAttribute("readonly","");
        tmp.style.position="fixed";
        tmp.style.left="-9999px";
        tmp.style.top="-9999px";
        document.body.appendChild(tmp);
        tmp.select();
        tmp.setSelectionRange(0,tmp.value.length);
        var ok=false;
        try{ok=document.execCommand("copy");}catch(e){ok=false;}
        document.body.removeChild(tmp);
        return ok;
      }

      function bind(){
        var btn=document.getElementById("cbk-copy-demo-json");
        var ta=document.getElementById("cbk-demo-json");
        var st=document.getElementById("cbk-copy-demo-json-status");
        var paste=document.getElementById("cbk_json_text");
        if(!btn||!ta){
          // Elements may not exist yet (script rendered before demo panel). Retry a few times.
          return false;
        }

        if(btn.dataset.cbkBound==="1"){
          return true;
        }
        btn.dataset.cbkBound="1";

        btn.addEventListener("click",function(){
          var text=ta.value||"";
          if(!text){setStatus(st,"Nothing to copy");return;}

          // Always fill the left-side paste field for guaranteed UX.
          if(paste){
            paste.value=text;
            paste.focus();
            paste.select();
          }

          // Try modern clipboard API first, then fallback.
          try{
            if(navigator.clipboard&&navigator.clipboard.writeText){
              navigator.clipboard.writeText(text).then(function(){
                setStatus(st, paste ? "Copied + pasted" : "Copied");
              }).catch(function(){
                var ok=legacyCopy(text);
                setStatus(st, ok ? (paste ? "Copied + pasted" : "Copied") : (paste ? "Pasted" : "Copy failed"));
              });
              return;
            }
          }catch(e){}

          var ok2=legacyCopy(text);
          setStatus(st, ok2 ? (paste ? "Copied + pasted" : "Copied") : (paste ? "Pasted" : "Copy failed"));
        });

        return true;
      }

      var tries=0;
      (function retry(){
        if(bind()) return;
        tries++;
        if(tries>20) return;
        setTimeout(retry, 150);
      })();
    })();</script>';

    echo '</div>'; // main

    echo '<div class="cbk-json-importer-demo" style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:14px;">';
    echo '<h2 style="margin-top:0;">Demo</h2>';
    echo '<p style="margin:0 0 12px;">Use the demo JSON to test the importer quickly.</p>';
    echo '<p style="margin:0 0 12px;"><a class="button" href="'.esc_url( $demo_url ).'" download>Download demo .json</a></p>';
    echo '<h3 style="margin:14px 0 6px;">Demo JSON (copy/paste)</h3>';
    echo '<p style="margin:0 0 8px;"><button type="button" class="button" id="cbk-copy-demo-json">Copy</button> <span id="cbk-copy-demo-json-status" style="margin-left:8px;color:#555;"></span></p>';
    echo '<textarea readonly rows="12" id="cbk-demo-json" style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,\"Liberation Mono\",\"Courier New\",monospace;">'.esc_textarea( $demo_json_display ).'</textarea>';
    echo '<p class="description" style="margin:8px 0 0;">Paste the JSON into the left-side <em>Or Paste JSON</em> field, then click Import.</p>';
    echo '</div>'; // demo

    echo '</div>'; // grid

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

    $items = null;
    $json_text = isset( $_POST['cbk_json_text'] ) ? (string) $_POST['cbk_json_text'] : '';
    if ( function_exists( 'wp_unslash' ) ) {
        $json_text = wp_unslash( $json_text );
    }
    $json_text = is_string( $json_text ) ? trim( $json_text ) : '';

    if ( $json_text !== '' ) {
        $items = Coffeebrk_Json_Articles_Importer::parse_json_text( $json_text );
    } elseif ( ! empty( $_FILES['cbk_json_file'] ) ) {
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
    } else {
        wp_send_json_error( [ 'message' => 'Upload a JSON file or paste JSON.' ], 400 );
    }

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
