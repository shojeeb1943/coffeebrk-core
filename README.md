# ☕ Coffeebrk Core

<div align="center">

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Elementor](https://img.shields.io/badge/Elementor-Compatible-92003B.svg?logo=elementor&logoColor=white)](https://elementor.com/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-2.2.1-orange.svg)](https://coffeebrk.ai)

**The core engine powering [coffeebrk.ai](https://coffeebrk.ai) — bringing automated content ingestion, dynamic Elementor widgets, multi-provider authentication, and developer-friendly REST APIs to WordPress.**

[Features](#-key-features) • [Installation](#-installation) • [Architecture](#-architecture) • [Elementor Integration](#-elementor-integration) • [REST API Reference](#-rest-api-reference) • [Configuration](#-configuration)

</div>

---

## 📖 Overview

**Coffeebrk Core** is an enterprise-ready WordPress plugin developed for the Coffeebrk platform. It orchestrates automated news aggregation, rich social story experiences, dynamic custom fields, secure client authentication (Firebase & Supabase), and seamless Elementor page builder integrations.

---

## ✨ Key Features

### 📰 Automated Content Ingestion & Aggregators
* **RSS Feed Engine**: Scheduled WP-Cron background syncing with customizable fetch intervals, category routing, HTML sanitization, and feed health monitoring.
* **X (Twitter) Collector**: Automated & on-demand tweet/post ingestion via the X API, content normalization pipeline, and sync scheduler.
* **Bulk JSON Importer**: Admin tool to parse and bulk import structured article datasets with duplicate detection by source URL.

### 🎨 Elementor Pro Widgets & Dynamic Tags
* **Interactive Stories Widget**: Immersive, mobile-first Instagram/Facebook-style story viewer carousel with touch gestures, auto-gradients, and progress timers.
* **Dynamic News Card Widget**: High-performance article cards supporting external featured images, category badges, and source attribution.
* **User Greeting Widget**: Dynamic user greeting card with avatar, live auth state detection, and role-based personalization.
* **External Image Widget**: Renders hotlinked/external image URLs with automatic fallback handling.
* **Dynamic Tags**: Exposes post sources (`_source_name`, `_source_url`) and external image URLs directly into Elementor controls.

### 🔐 Multi-Provider Authentication
* **Hybrid Auth Frontend**: Preconfigured client integration scripts for **Firebase Auth** and **Supabase Auth** (Google One-Tap / OAuth popup support).
* **Branded Auth Layouts**: Built-in responsive login, register, and onboarding shell views.
* **REST Security**: Rate-limited REST authentication handlers, cryptographic nonces, and bearer token protection.

### 🔌 Extensible Headless & Public REST APIs
* **Public Endpoints (`/coffeebrk/v1/public/*`)**: High-speed, cached endpoints serving posts, categories, web stories, and rich video embeds to frontend clients and mobile apps.
* **Secured Ingestion Endpoints (`/coffeebrk/v1/*`)**: Token-authenticated endpoints for programmatic post creation, bulk updates, and external webhook integrations.

### 🛠️ Developer, AI Agent & Admin Tools
* **Model Context Protocol (MCP) Server**: Full [MCP Server](mcp-server/README.md) support enabling AI agents (Claude, Antigravity, Cursor, Windsurf) to draft, publish, search, and manage WordPress posts and metadata directly via natural language.
* **Central Dashboard Hub**: Multi-tab management interface for API keys, RSS feeds, X Collector, Aspires taxonomies, and Dynamic Fields.
* **Centralized File Logger**: High-performance, rolling JSON file logger with real-time log tailing in the admin area.

---

## 📂 Architecture & Directory Structure

```
coffeebrk-core/
├── admin/                     # Admin screen controllers and UI templates
│   └── json-articles-importer.php
├── assets/                    # Static frontend assets
│   ├── css/                   # Stylesheets for admin and widgets
│   ├── img/                   # Logos, brand assets, and avatars
│   └── js/                    # Client libraries (stories viewer, Firebase, Supabase)
├── dashboard/                 # Admin dashboard tabs & settings views
│   ├── admin-aspires.php
│   └── admin-dynamic-fields.php
├── inc/                       # Core service modules and API layers
│   ├── admin-settings.php     # Central admin hub and settings pages
│   ├── api-tokens.php         # REST Bearer token authentication manager
│   ├── auth.php               # Frontend auth templates & helpers
│   ├── auth-rest.php          # REST auth endpoint handlers
│   ├── feed.php               # Feed generation and token verification
│   ├── logger.php             # Rolling file logger engine
│   ├── public-api.php         # Public headless REST API endpoints
│   ├── rest-api.php           # Protected REST API CRUD routes
│   ├── rss-admin.php          # RSS Feed table management & actions
│   ├── rss-importer.php       # RSS parser and sync worker
│   ├── stories-cpt.php        # Custom Post Type for Web Stories
│   ├── widgets/               # Elementor custom widget implementations
│   └── x-collector*.php       # X / Twitter ingestion subsystem
├── includes/                  # Utility classes & importers
├── mcp-server/                # Model Context Protocol (MCP) server for AI agents
├── meta/                      # Meta boxes and post attribute handlers
└── coffeebrk-core.php         # Main plugin bootstrap & activation hooks
```

---

## 🚀 Installation

### Requirements
* **WordPress**: 6.0 or higher
* **PHP**: 8.0 or higher
* **Elementor** (Optional, for custom widgets & dynamic tags): 3.5+

### Setup
1. Clone or download this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/shojeeb1943/coffeebrk-core.git coffeebrk-core
   ```
2. Navigate to **WordPress Admin → Plugins → Installed Plugins**.
3. Locate **Coffeebrk Core** and click **Activate**.
4. Upon activation, default dynamic fields, aspirations, and scheduled cron jobs will automatically be initialized.

---

## 🧩 Elementor Integration

Coffeebrk Core automatically registers custom tags and widgets when Elementor is active:

| Component | Type | Identifier | Description |
| :--- | :--- | :--- | :--- |
| **News Card** | Widget | `coffeebrk_news_card` | Responsive article card with author, source, and image overlays. |
| **Stories Viewer** | Widget | `coffeebrk_stories` | Fullscreen interactive story carousel for `coffeebrk_story` posts. |
| **External Image** | Widget | `coffeebrk_external_image` | Image element accepting direct remote URLs without uploading to media library. |
| **User Greeting** | Widget | `coffeebrk_user_greeting` | Branded welcome block displaying authenticated user details. |
| **Source Name** | Dynamic Tag | `coffeebrk_source_name` | Extracts `_source_name` post meta for dynamic text headings/links. |
| **Source URL** | Dynamic Tag | `coffeebrk_source_url` | Extracts `_source_url` post meta for dynamic hyperlinks. |
| **Dynamic Image URL**| Dynamic Tag | `coffeebrk_dynamic_image_url` | Extracts external image URLs for dynamic background/image controls. |

---

## 📡 REST API Reference

The plugin exposes custom endpoints under the namespace `coffeebrk/v1`:

### Public Endpoints (No Auth Required)
* `GET /wp-json/coffeebrk/v1/public/posts` — Fetch paginated articles with normalized meta and category filters.
* `GET /wp-json/coffeebrk/v1/public/categories` — List active categories and article counts.
* `GET /wp-json/coffeebrk/v1/public/stories` — Retrieve latest web stories for mobile and widget clients.
* `GET /wp-json/coffeebrk/v1/public/video-embed` — Resolve video embed HTML from external media URLs.

### Secured Ingestion Endpoints (Bearer Token Required)
* `POST /wp-json/coffeebrk/v1/posts` — Create or update articles programmatically.
* `POST /wp-json/coffeebrk/v1/posts/bulk` — Ingest multiple articles in a single payload.
* `DELETE /wp-json/coffeebrk/v1/posts/<id>` — Delete a post by ID.
* `POST /wp-json/coffeebrk/v1/auth/verify` — Validate client auth session tokens.

> **Authentication Header:**
> ```http
> Authorization: Bearer <YOUR_API_TOKEN>
> ```

---

## ⚙️ Configuration

Navigate to **WordPress Admin → Coffeebrk Core** to configure settings:

1. **API Keys & Tokens**: Generate secure Bearer tokens for external publishing pipelines.
2. **RSS Feeds**: Add, enable, disable, or manually trigger RSS feed synchronization.
3. **X Collector**: Configure X (Twitter) API credentials, target handles/keywords, and sync intervals.
4. **Dynamic Fields**: Define custom meta keys that dynamically appear on post edit screens.
5. **User Aspires**: Manage the list of selectable user professions/interests for onboarding personalization.
6. **System Logs**: View rolling diagnostic logs for RSS imports, API calls, and authentication events.

---

## 🔒 Security & Best Practices

* **CSRF & Nonce Protection**: All admin actions and form submissions are validated using WordPress nonces.
* **Input Sanitization & Output Escaping**: Uses strict WordPress sanitization (`sanitize_text_field`, `esc_url_raw`) and contextual escaping (`esc_html`, `esc_attr`, `wp_kses_post`).
* **Rate Limiting**: Built-in rate limiting on authentication routes prevents brute-force attempts.
* **Direct Access Prevention**: Every script includes `defined( 'ABSPATH' ) || exit;` guards.

---

## 📄 License

This project is licensed under the **GPL-2.0+ License** - see the [LICENSE](LICENSE) file for details.

---

<div align="center">
  <sub>Built with ☕ for <a href="https://coffeebrk.ai">Coffeebrk.ai</a></sub>
</div>
