# ☕ Coffeebrk Core MCP Server

Model Context Protocol (MCP) server for **Coffeebrk Core**, enabling AI agents (Claude, Antigravity, Cursor, Windsurf, Cline, etc.) to read, create, update, and manage WordPress articles, web stories, RSS feeds, X collector posts, and custom dynamic fields.

---

## 🚀 Features & AI Capabilities

With this MCP server connected, your AI agents can:
* 📝 **Create & Publish Articles**: Write posts with title, HTML content, excerpts, and statuses (`draft`, `publish`, `pending`, `private`).
* 🏷️ **Dynamic Meta & Attribution**: Attach source names (`_source_name`), source URLs (`_source_url`), external image URLs, categories, and tags.
* 📦 **Bulk Post Ingestion**: Ingest batches of news articles in a single prompt.
* 🔍 **Smart Search & Filter**: Search posts by keywords, categories, date ranges, or meta fields.
* 📱 **Web Stories Integration**: Retrieve interactive mobile web stories.
* 🐦 **X (Twitter) Ingestion**: Query ingested tweets from monitored X profiles.
* 🗂️ **Taxonomies & Diagnostics**: Inspect categories, registered dynamic fields, and RSS diagnostics.

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
| `list_x_posts` | Retrieve collected X / Twitter posts | `page`, `per_page`, `profile_id`, `category`, `featured` |
| `list_x_profiles` | List monitored X collector profiles | `enabled` (boolean) |
| `get_site_info` | Get site info, plugin version, and RSS status | _None_ |

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
