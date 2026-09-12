# ☕ Coffeebrk Core MCP Server

Model Context Protocol (MCP) server for **Coffeebrk Core**, enabling AI agents (Claude, Antigravity, Cursor, Windsurf, Cline, etc.) to read, create, update, and manage every module of the plugin — WordPress articles, web stories, RSS feeds, X collector posts, API tokens, and custom dynamic fields — and to check what's currently going on across the live site (recent imports, errors, aggregate stats).

---

## 🚀 Features & AI Capabilities

With this MCP server connected, your AI agents can:
* 📝 **Create & Publish Articles**: Write posts with title, HTML content, excerpts, and statuses (`draft`, `publish`, `pending`, `private`).
* 🏷️ **Dynamic Meta & Attribution**: Attach source names (`_source_name`), source URLs (`_source_url`), external image URLs, categories, and tags.
* 📦 **Bulk Post Ingestion**: Ingest batches of news articles in a single prompt.
* 🔍 **Smart Search & Filter**: Search posts by keywords, categories, date ranges, or meta fields.
* 📱 **Web Stories**: Full CRUD for stories, including ones ingested via the YouTube n8n pipeline.
* 🐦 **X (Twitter) Ingestion**: Query, update, or delete ingested tweets, and check ingestion activity/stats.
* 📡 **RSS Aggregator**: Manage feed sources, trigger manual imports, and check import history.
* 🔑 **API Token Management**: List, create, update, and revoke API tokens (requires the `manage` scope).
* 📊 **Live Monitoring**: Tail error/login logs and get an aggregate "what's going on" snapshot across the whole plugin.
* 🗂️ **Taxonomies & Diagnostics**: Inspect categories, registered dynamic fields, and RSS output-feed diagnostics.

---

## 🛠️ Tools Reference

| Tool Name | Description | Key Parameters |
| :--- | :--- | :--- |
| `create_post` | Create a WordPress post with dynamic fields | `title`, `content`, `excerpt`, `status`, `category_id`, `source_name`, `source_url`, `image_url`, `tags`, `meta` |
| `list_posts` | Search and paginate posts | `page`, `per_page`, `search`, `status`, `category`, `orderby`, `order`, `meta_key`, `meta_value` |
| `get_post` | Retrieve single post by ID with all custom meta | `id` |
| `update_post` | Update an existing post and its metadata | `id`, `title`, `content`, `excerpt`, `status`, `source_name`, `source_url`, `image_url` |
| `delete_post` | Delete or move a post to trash | `id`, `force` (boolean) |
| `bulk_create_posts` | Batch create multiple articles at once | `posts` (array of post objects) |
| `list_categories` | List categories with post counts | `hide_empty` (boolean) |
| `get_meta_fields` | List registered Coffeebrk Dynamic Fields | _None_ |
| `list_stories` | Fetch web stories | `page`, `per_page` |
| `get_story` / `create_story` / `update_story` / `delete_story` | Full CRUD for a single Web Story | `id`, `title`, `video_url`, `thumbnail_url`, `show_frontend`, `gradient`, `text_color`, `gradient_intensity` |
| `get_stories_stats` | Aggregate story counts (total, YouTube-sourced, visible) | _None_ |
| `list_x_posts` | Retrieve collected X / Twitter posts | `page`, `per_page`, `featured`, `orderby`, `order` |
| `create_x_post` / `bulk_create_x_posts` | Ingest one or many scraped X posts (idempotent by tweet id) | raw tweet object(s) |
| `update_x_post` | Update an X post's publish status or featured flag | `id`, `status`, `is_featured` |
| `delete_x_post` | Trash an X post | `id` |
| `get_x_activity_log` | Recent X ingestion activity (last 24h) | `limit` |
| `get_x_stats` | Aggregate X-collector counts + token usage | _None_ |
| `list_rss_feeds` / `get_rss_feed` | List or fetch RSS feed sources | `orderby`, `order`, `enabled` / `id` |
| `create_rss_feed` / `update_rss_feed` / `delete_rss_feed` | Manage RSS feed sources | `feed_name`, `feed_url`, `enabled`, `import_limit`, `category_id` |
| `run_rss_feed` / `run_all_rss_feeds` | Manually trigger an RSS import now | `id` (for single feed) |
| `get_rss_activity_log` / `get_rss_stats` | RSS import history and aggregate counts | `limit` |
| `list_api_tokens` / `create_api_token` / `update_api_token` / `revoke_api_token` | Manage API tokens — never returns the secret except once on creation. **Requires the `manage` scope.** | `name`, `permissions`, `status` |
| `get_error_log` / `get_login_log` | Tail the error/login logs. **Requires the `manage` scope.** | `limit` |
| `get_site_activity` | Aggregate "what's going on" snapshot across every module | _None_ |
| `get_site_info` | Get site info and RSS output-feed diagnostics | _None_ |

> **The `manage` scope**: token/log endpoints are more sensitive than everyday content CRUD, so they require a token explicitly granted the `manage` permission (check it when creating a token on the API page), or a logged-in admin session. A plain read/write/delete token gets a 403 on these.

---

## ⚙️ Configuration

### Environment Variables
| Variable | Required | Description | Example |
| :--- | :--- | :--- | :--- |
| `COFFEEBRK_WP_URL` | **Yes** | Base URL of your WordPress installation | `https://coffeebrk.ai` or `http://localhost:8000` |
| `COFFEEBRK_API_TOKEN` | **Yes** | Bearer API Token generated from **WP Admin → Coffeebrk Core → API Keys** | `cbk_9f8a...` |

---

## 🔌 Setup & Connecting to AI Clients

### 1. Antigravity IDE / Gemini Agent (`mcp_config.json`)
Add to `mcp_config.json`:
```json
{
  "mcpServers": {
    "coffeebrk": {
      "command": "node",
      "args": ["c:/dev/coffeebrk-core/mcp-server/dist/index.js"],
      "env": {
        "COFFEEBRK_WP_URL": "https://your-wordpress-site.com",
        "COFFEEBRK_API_TOKEN": "YOUR_COFFEEBRK_API_TOKEN"
      }
    }
  }
}
```

### 2. Claude Desktop (`claude_desktop_config.json`)
Location: `%APPDATA%\Claude\claude_desktop_config.json` (Windows) or `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS)
```json
{
  "mcpServers": {
    "coffeebrk": {
      "command": "node",
      "args": ["C:\\dev\\coffeebrk-core\\mcp-server\\dist\\index.js"],
      "env": {
        "COFFEEBRK_WP_URL": "https://your-wordpress-site.com",
        "COFFEEBRK_API_TOKEN": "YOUR_COFFEEBRK_API_TOKEN"
      }
    }
  }
}
```

### 3. Cursor / Windsurf / Cline
In your MCP settings UI or `.cursor/mcp.json`:
* **Name:** `coffeebrk`
* **Command:** `node`
* **Args:** `["c:/dev/coffeebrk-core/mcp-server/dist/index.js"]`
* **Env:**
  * `COFFEEBRK_WP_URL`: `https://your-wordpress-site.com`
  * `COFFEEBRK_API_TOKEN`: `YOUR_COFFEEBRK_API_TOKEN`

---

## 🏗️ Development & Building

```bash
cd mcp-server
# Install dependencies
npm install

# Build TypeScript to dist/
npm run build

# Run directly
npm start
```

---

## 🤖 Example Agent Prompts

Once configured, you can prompt your AI agent with instructions like:
* *"Find the latest 5 posts published in the AI category and draft a summary post referencing their sources."*
* *"Create a draft article titled 'Top 10 AI Tools in 2026' with content ..., source name 'TechCrunch', and source URL 'https://techcrunch.com/...'"*
* *"Check which dynamic meta fields are available in Coffeebrk and list the most recent X posts collected."*
