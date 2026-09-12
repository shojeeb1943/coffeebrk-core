#!/usr/bin/env node
/**
 * Coffeebrk Core MCP Server
 *
 * Model Context Protocol (MCP) server that enables AI agents to read, create,
 * update, and manage WordPress content, feeds, stories, and metadata via Coffeebrk Core REST API.
 */
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema, } from "@modelcontextprotocol/sdk/types.js";
// Retrieve configuration from environment variables
const WP_URL = (process.env.COFFEEBRK_WP_URL || "http://localhost").replace(/\/+$/, "");
const API_TOKEN = process.env.COFFEEBRK_API_TOKEN || "";
/**
 * Helper to make authenticated requests to Coffeebrk REST API
 */
async function callApi(endpoint, method = "GET", body, queryParams) {
    let url = `${WP_URL}/wp-json/coffeebrk/v1${endpoint.startsWith("/") ? endpoint : "/" + endpoint}`;
    if (queryParams) {
        const params = new URLSearchParams();
        for (const [key, value] of Object.entries(queryParams)) {
            if (value !== undefined && value !== null && value !== "") {
                if (Array.isArray(value)) {
                    value.forEach((v) => params.append(`${key}[]`, String(v)));
                }
                else {
                    params.append(key, String(value));
                }
            }
        }
        const qs = params.toString();
        if (qs) {
            url += (url.includes("?") ? "&" : "?") + qs;
        }
    }
    const headers = {
        "Accept": "application/json",
    };
    if (API_TOKEN) {
        headers["Authorization"] = `Bearer ${API_TOKEN}`;
    }
    if (body && (method === "POST" || method === "PUT" || method === "PATCH")) {
        headers["Content-Type"] = "application/json";
    }
    try {
        const response = await fetch(url, {
            method,
            headers,
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            return {
                isError: true,
                status: response.status,
                statusText: response.statusText,
                error: data.message || data.error || `HTTP ${response.status}: ${response.statusText}`,
                details: data,
            };
        }
        return {
            isError: false,
            status: response.status,
            data,
        };
    }
    catch (error) {
        return {
            isError: true,
            error: error.message || "Failed to communicate with WordPress REST API",
        };
    }
}
// Define tools
const TOOLS = [
    {
        name: "create_post",
        description: "Create a new article/post in WordPress with Coffeebrk custom dynamic fields (source name, source URL, external image URL, tags, categories).",
        inputSchema: {
            type: "object",
            properties: {
                title: {
                    type: "string",
                    description: "Post title",
                },
                content: {
                    type: "string",
                    description: "Full post content in HTML or plain text",
                },
                excerpt: {
                    type: "string",
                    description: "Short summary/excerpt of the post",
                },
                status: {
                    type: "string",
                    enum: ["draft", "publish", "pending", "private"],
                    default: "draft",
                    description: "Publication status (default: draft)",
                },
                category_id: {
                    type: "integer",
                    description: "Primary WordPress category ID",
                },
                categories: {
                    type: "array",
                    items: { type: "integer" },
                    description: "Array of category IDs",
                },
                tags: {
                    type: "array",
                    items: { type: "string" },
                    description: "Array of tag names",
                },
                source_name: {
                    type: "string",
                    description: "Original source name (e.g. TechCrunch, OpenAI Blog)",
                },
                source_url: {
                    type: "string",
                    description: "Original article URL",
                },
                image_url: {
                    type: "string",
                    description: "External featured image URL",
                },
                meta: {
                    type: "object",
                    description: "Custom key-value meta pairs to store on the post",
                },
                slug: {
                    type: "string",
                    description: "Custom post slug",
                },
            },
            required: ["title"],
        },
    },
    {
        name: "list_posts",
        description: "Search and retrieve a list of WordPress posts with pagination, category filter, search query, and meta fields.",
        inputSchema: {
            type: "object",
            properties: {
                page: {
                    type: "integer",
                    default: 1,
                    description: "Page number (default: 1)",
                },
                per_page: {
                    type: "integer",
                    default: 10,
                    description: "Number of posts to return (1-100, default: 10)",
                },
                search: {
                    type: "string",
                    description: "Search keyword matching title and content",
                },
                status: {
                    type: "string",
                    enum: ["publish", "draft", "pending", "any"],
                    default: "publish",
                    description: "Post status filter",
                },
                category: {
                    type: "integer",
                    description: "Filter by category ID",
                },
                category_slug: {
                    type: "string",
                    description: "Filter by category slug",
                },
                orderby: {
                    type: "string",
                    enum: ["date", "title", "modified", "ID", "rand"],
                    default: "date",
                    description: "Sort field",
                },
                order: {
                    type: "string",
                    enum: ["ASC", "DESC"],
                    default: "DESC",
                    description: "Sort direction",
                },
                meta_key: {
                    type: "string",
                    description: "Filter by meta key (e.g. _source_name)",
                },
                meta_value: {
                    type: "string",
                    description: "Filter by meta value",
                },
            },
        },
    },
    {
        name: "get_post",
        description: "Get detailed information for a single WordPress post by ID, including all Coffeebrk dynamic meta fields.",
        inputSchema: {
            type: "object",
            properties: {
                id: {
                    type: "integer",
                    description: "WordPress post ID",
                },
            },
            required: ["id"],
        },
    },
    {
        name: "update_post",
        description: "Update an existing WordPress post and its custom fields (source, external image, categories, status).",
        inputSchema: {
            type: "object",
            properties: {
                id: {
                    type: "integer",
                    description: "WordPress post ID to update",
                },
                title: {
                    type: "string",
                    description: "Updated title",
                },
                content: {
                    type: "string",
                    description: "Updated content",
                },
                excerpt: {
                    type: "string",
                    description: "Updated excerpt",
                },
                status: {
                    type: "string",
                    enum: ["draft", "publish", "pending", "private"],
                    description: "Updated publication status",
                },
                category_id: {
                    type: "integer",
                    description: "Primary category ID",
                },
                categories: {
                    type: "array",
                    items: { type: "integer" },
                    description: "Array of category IDs",
                },
                tags: {
                    type: "array",
                    items: { type: "string" },
                    description: "Array of tags",
                },
                source_name: {
                    type: "string",
                    description: "Updated source name",
                },
                source_url: {
                    type: "string",
                    description: "Updated source URL",
                },
                image_url: {
                    type: "string",
                    description: "Updated external image URL",
                },
                meta: {
                    type: "object",
                    description: "Key-value pairs to update in post meta",
                },
                slug: {
                    type: "string",
                    description: "Updated post slug",
                },
            },
            required: ["id"],
        },
    },
    {
        name: "delete_post",
        description: "Delete or trash a WordPress post by ID.",
        inputSchema: {
            type: "object",
            properties: {
                id: {
                    type: "integer",
                    description: "WordPress post ID to delete",
                },
                force: {
                    type: "boolean",
                    default: false,
                    description: "Permanently delete (true) or move to trash (false, default)",
                },
            },
            required: ["id"],
        },
    },
    {
        name: "bulk_create_posts",
        description: "Batch import and create multiple posts at once (ideal for AI news aggregation and feed ingestion).",
        inputSchema: {
            type: "object",
            properties: {
                posts: {
                    type: "array",
                    description: "Array of post objects to create",
                    items: {
                        type: "object",
                        properties: {
                            title: { type: "string" },
                            content: { type: "string" },
                            excerpt: { type: "string" },
                            status: { type: "string", default: "draft" },
                            category_id: { type: "integer" },
                            source_name: { type: "string" },
                            source_url: { type: "string" },
                            image_url: { type: "string" },
                            tags: { type: "array", items: { type: "string" } },
                            meta: { type: "object" },
                        },
                        required: ["title"],
                    },
                },
            },
            required: ["posts"],
        },
    },
    {
        name: "list_categories",
        description: "List all WordPress post categories with their IDs, names, slugs, and post counts.",
        inputSchema: {
            type: "object",
            properties: {
                hide_empty: {
                    type: "boolean",
                    default: false,
                    description: "Whether to hide categories with 0 posts",
                },
            },
        },
    },
    {
        name: "get_meta_fields",
        description: "Get all registered Coffeebrk Dynamic Fields (custom meta keys, labels, and types).",
        inputSchema: {
            type: "object",
            properties: {},
        },
    },
    {
        name: "list_stories",
        description: "Fetch web stories (Coffeebrk Stories custom post type) for display or mobile feed consumption.",
        inputSchema: {
            type: "object",
            properties: {
                page: {
                    type: "integer",
                    default: 1,
                    description: "Page number",
                },
                per_page: {
                    type: "integer",
                    default: 10,
                    description: "Number of stories per page",
                },
            },
        },
    },
    {
        name: "list_x_posts",
        description: "List ingested X (Twitter) posts collected by the Coffeebrk X Collector.",
        inputSchema: {
            type: "object",
            properties: {
                page: { type: "integer", default: 1 },
                per_page: { type: "integer", default: 20 },
                featured: { type: "boolean", description: "Filter by featured status" },
                orderby: { type: "string", enum: ["date", "modified"], description: "Sort field" },
                order: { type: "string", enum: ["ASC", "DESC"], description: "Sort direction" },
            },
        },
    },
    {
        name: "create_x_post",
        description: "Ingest a single already-scraped X (Twitter) post (e.g. from an Apify/n8n pipeline) into Coffeebrk X Collector. Pass the raw tweet object (id, text, author, likeCount, retweetCount, viewCount, media, etc.) — all fields are captured. Idempotent by tweet id.",
        inputSchema: {
            type: "object",
            description: "The raw tweet object as scraped (Apify feedminer/x-tweet-scraper shape or compatible).",
            properties: {},
            additionalProperties: true,
        },
    },
    {
        name: "bulk_create_x_posts",
        description: "Batch-ingest multiple already-scraped X (Twitter) posts into Coffeebrk X Collector in one call. Idempotent by tweet id.",
        inputSchema: {
            type: "object",
            properties: {
                posts: {
                    type: "array",
                    description: "Array of raw tweet objects to import",
                    items: { type: "object", additionalProperties: true },
                },
            },
            required: ["posts"],
        },
    },
    {
        name: "get_site_info",
        description: "Get site information, Coffeebrk Core version, and RSS feed diagnostics.",
        inputSchema: {
            type: "object",
            properties: {},
        },
    },
    // --- X-Collector: activity + CRUD gap fill ---
    {
        name: "get_x_activity_log",
        description: "Get recent X-collector ingestion activity (last 24h) — one entry per ingest call from n8n, with created/skipped counts and errors.",
        inputSchema: {
            type: "object",
            properties: {
                limit: { type: "integer", default: 50, description: "Max entries to return (1-200)" },
            },
        },
    },
    {
        name: "get_x_stats",
        description: "Get aggregate X-collector stats: post counts, imports today/last-24h, last import time, and API token usage.",
        inputSchema: { type: "object", properties: {} },
    },
    {
        name: "update_x_post",
        description: "Update an ingested X post's publish status (draft/publish) or featured flag.",
        inputSchema: {
            type: "object",
            properties: {
                id: { type: "integer", description: "X post ID" },
                status: { type: "string", enum: ["draft", "publish"], description: "Publish status" },
                is_featured: { type: "boolean", description: "Featured flag" },
            },
            required: ["id"],
        },
    },
    {
        name: "delete_x_post",
        description: "Trash an ingested X post by ID.",
        inputSchema: {
            type: "object",
            properties: { id: { type: "integer", description: "X post ID" } },
            required: ["id"],
        },
    },
    // --- RSS Importer: feed source management ---
    {
        name: "list_rss_feeds",
        description: "List RSS aggregator feed sources with their enabled state, import limit, category mapping, and last run/import times.",
        inputSchema: {
            type: "object",
            properties: {
                orderby: { type: "string", description: "id, feed_name, feed_url, enabled, last_import, or last_run" },
                order: { type: "string", enum: ["ASC", "DESC"] },
                enabled: { type: "boolean", description: "Filter by enabled state" },
            },
        },
    },
    {
        name: "get_rss_feed",
        description: "Get a single RSS feed source by ID.",
        inputSchema: {
            type: "object",
            properties: { id: { type: "integer", description: "Feed ID" } },
            required: ["id"],
        },
    },
    {
        name: "create_rss_feed",
        description: "Add a new RSS feed source to the aggregator.",
        inputSchema: {
            type: "object",
            properties: {
                feed_name: { type: "string", description: "Display name for the feed" },
                feed_url: { type: "string", description: "RSS/Atom feed URL" },
                enabled: { type: "boolean", default: true },
                import_limit: { type: "integer", default: 5, description: "Max items to import per run (1-50)" },
                category_id: { type: "integer", description: "WordPress category to assign imported posts to" },
            },
            required: ["feed_name", "feed_url"],
        },
    },
    {
        name: "update_rss_feed",
        description: "Update an existing RSS feed source's name, URL, enabled state, import limit, or category.",
        inputSchema: {
            type: "object",
            properties: {
                id: { type: "integer", description: "Feed ID to update" },
                feed_name: { type: "string" },
                feed_url: { type: "string" },
                enabled: { type: "boolean" },
                import_limit: { type: "integer" },
                category_id: { type: "integer" },
            },
            required: ["id"],
        },
    },
    {
        name: "delete_rss_feed",
        description: "Delete an RSS feed source.",
        inputSchema: {
            type: "object",
            properties: { id: { type: "integer", description: "Feed ID" } },
            required: ["id"],
        },
    },
    {
        name: "run_rss_feed",
        description: "Manually trigger an import run for a single RSS feed right now (doesn't wait for the hourly cron).",
        inputSchema: {
            type: "object",
            properties: { id: { type: "integer", description: "Feed ID to run" } },
            required: ["id"],
        },
    },
    {
        name: "run_all_rss_feeds",
        description: "Manually trigger an import run for all enabled RSS feeds right now.",
        inputSchema: { type: "object", properties: {} },
    },
    {
        name: "get_rss_activity_log",
        description: "Get recent RSS import activity (last 24h): feed runs, drafted posts, skipped items, and errors.",
        inputSchema: {
            type: "object",
            properties: { limit: { type: "integer", default: 50, description: "Max entries to return (1-200)" } },
        },
    },
    {
        name: "get_rss_stats",
        description: "Get aggregate RSS aggregator stats: total/enabled feed counts and the next scheduled cron run.",
        inputSchema: { type: "object", properties: {} },
    },
    // --- Stories: full CRUD (covers YouTube-ingested stories too) ---
    {
        name: "get_story",
        description: "Get a single Web Story by ID, including YouTube-ingestion metadata if applicable.",
        inputSchema: {
            type: "object",
            properties: { id: { type: "integer", description: "Story ID" } },
            required: ["id"],
        },
    },
    {
        name: "create_story",
        description: "Create a new Web Story (manual, not YouTube-ingestion — use the n8n YouTube pipeline for that).",
        inputSchema: {
            type: "object",
            properties: {
                title: { type: "string" },
                video_url: { type: "string" },
                thumbnail_url: { type: "string", description: "Image URL to sideload as the featured thumbnail" },
                show_frontend: { type: "boolean", default: true },
                gradient: { type: "string", description: "Hex color, e.g. #F5F5FF" },
                text_color: { type: "string", description: "Hex color, e.g. #323232" },
                gradient_intensity: { type: "integer", description: "0-100" },
            },
            required: ["title"],
        },
    },
    {
        name: "update_story",
        description: "Update an existing Web Story's title, video URL, visibility, or styling.",
        inputSchema: {
            type: "object",
            properties: {
                id: { type: "integer", description: "Story ID to update" },
                title: { type: "string" },
                video_url: { type: "string" },
                thumbnail_url: { type: "string" },
                show_frontend: { type: "boolean" },
                gradient: { type: "string" },
                text_color: { type: "string" },
                gradient_intensity: { type: "integer" },
            },
            required: ["id"],
        },
    },
    {
        name: "delete_story",
        description: "Trash a Web Story by ID.",
        inputSchema: {
            type: "object",
            properties: { id: { type: "integer", description: "Story ID" } },
            required: ["id"],
        },
    },
    {
        name: "get_stories_stats",
        description: "Get aggregate story counts: total, published, YouTube-sourced, and visible-on-frontend.",
        inputSchema: { type: "object", properties: {} },
    },
    // --- API Tokens: management (requires the 'manage' scope) ---
    {
        name: "list_api_tokens",
        description: "List all API tokens (name, permissions, status, last used) — never returns the secret value. Requires a token with the 'manage' scope or a logged-in admin.",
        inputSchema: { type: "object", properties: {} },
    },
    {
        name: "create_api_token",
        description: "Create a new API token. Returns the plaintext token once — it is never shown again. Requires the 'manage' scope.",
        inputSchema: {
            type: "object",
            properties: {
                name: { type: "string", description: "Label for the token, e.g. 'n8n Production'" },
                permissions: {
                    type: "array",
                    items: { type: "string", enum: ["read", "write", "delete", "manage"] },
                    description: "Scopes to grant (default: read, write, delete)",
                },
            },
        },
    },
    {
        name: "update_api_token",
        description: "Update an API token's name, permissions, or active/inactive status. Requires the 'manage' scope.",
        inputSchema: {
            type: "object",
            properties: {
                id: { type: "string", description: "Token ID (e.g. tok_xxxxx)" },
                name: { type: "string" },
                permissions: { type: "array", items: { type: "string", enum: ["read", "write", "delete", "manage"] } },
                status: { type: "string", enum: ["active", "inactive"] },
            },
            required: ["id"],
        },
    },
    {
        name: "revoke_api_token",
        description: "Permanently revoke (delete) an API token. Requires the 'manage' scope.",
        inputSchema: {
            type: "object",
            properties: { id: { type: "string", description: "Token ID (e.g. tok_xxxxx)" } },
            required: ["id"],
        },
    },
    // --- Monitoring: logs + site-wide activity ---
    {
        name: "get_error_log",
        description: "Tail the plugin's error log (ingestion failures, sideload errors, etc.). Requires the 'manage' scope — contains IPs and user-agents.",
        inputSchema: {
            type: "object",
            properties: { limit: { type: "integer", default: 50, description: "Max entries to return (1-200)" } },
        },
    },
    {
        name: "get_login_log",
        description: "Tail the WordPress login log (user, email, IP, user-agent per login). Requires the 'manage' scope.",
        inputSchema: {
            type: "object",
            properties: { limit: { type: "integer", default: 50, description: "Max entries to return (1-200)" } },
        },
    },
    {
        name: "get_site_activity",
        description: "Get an aggregate 'what's going on' snapshot across the whole plugin: post/story/X-post counts, RSS feed counts and next cron run, API token counts, and errors/logins in the last 24h.",
        inputSchema: { type: "object", properties: {} },
    },
];
// Create MCP Server instance
const server = new Server({
    name: "coffeebrk-core",
    version: "2.3.0",
}, {
    capabilities: {
        tools: {},
    },
});
// Register list tools handler
server.setRequestHandler(ListToolsRequestSchema, async () => {
    return {
        tools: TOOLS,
    };
});
// Register call tool handler
server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args = {} } = request.params;
    try {
        switch (name) {
            case "create_post": {
                const res = await callApi("/posts", "POST", args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "list_posts": {
                const res = await callApi("/posts", "GET", undefined, args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "get_post": {
                const { id, ...queryParams } = args;
                const res = await callApi(`/posts/${id}`, "GET", undefined, queryParams);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "update_post": {
                const { id, ...body } = args;
                const res = await callApi(`/posts/${id}`, "PUT", body);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "delete_post": {
                const { id, force } = args;
                const res = await callApi(`/posts/${id}`, "DELETE", undefined, { force });
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "bulk_create_posts": {
                const res = await callApi("/bulk-posts", "POST", args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "list_categories": {
                const res = await callApi("/categories", "GET", undefined, args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "get_meta_fields": {
                const res = await callApi("/meta-fields", "GET");
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "list_stories": {
                const res = await callApi("/public/stories", "GET", undefined, args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "list_x_posts": {
                const res = await callApi("/x-posts", "GET", undefined, args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "create_x_post": {
                const res = await callApi("/x-posts", "POST", args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "bulk_create_x_posts": {
                const res = await callApi("/x-posts/bulk", "POST", args);
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            case "get_site_info": {
                const res = await callApi("/rss-info", "GET");
                return {
                    content: [
                        {
                            type: "text",
                            text: JSON.stringify(res, null, 2),
                        },
                    ],
                    isError: res.isError,
                };
            }
            // --- X-Collector ---
            case "get_x_activity_log": {
                const res = await callApi("/x-posts/activity", "GET", undefined, args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "get_x_stats": {
                const res = await callApi("/x-posts/stats", "GET");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "update_x_post": {
                const { id, ...body } = args;
                const res = await callApi(`/x-posts/${id}`, "PUT", body);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "delete_x_post": {
                const { id } = args;
                const res = await callApi(`/x-posts/${id}`, "DELETE");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            // --- RSS Importer ---
            case "list_rss_feeds": {
                const res = await callApi("/rss-feeds", "GET", undefined, args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "get_rss_feed": {
                const { id } = args;
                const res = await callApi(`/rss-feeds/${id}`, "GET");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "create_rss_feed": {
                const res = await callApi("/rss-feeds", "POST", args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "update_rss_feed": {
                const { id, ...body } = args;
                const res = await callApi(`/rss-feeds/${id}`, "PUT", body);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "delete_rss_feed": {
                const { id } = args;
                const res = await callApi(`/rss-feeds/${id}`, "DELETE");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "run_rss_feed": {
                const { id } = args;
                const res = await callApi(`/rss-feeds/${id}/run`, "POST");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "run_all_rss_feeds": {
                const res = await callApi("/rss-feeds/run-all", "POST");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "get_rss_activity_log": {
                const res = await callApi("/rss-feeds/activity", "GET", undefined, args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "get_rss_stats": {
                const res = await callApi("/rss-feeds/stats", "GET");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            // --- Stories ---
            case "get_story": {
                const { id } = args;
                const res = await callApi(`/stories/${id}`, "GET");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "create_story": {
                const res = await callApi("/stories", "POST", args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "update_story": {
                const { id, ...body } = args;
                const res = await callApi(`/stories/${id}`, "PUT", body);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "delete_story": {
                const { id } = args;
                const res = await callApi(`/stories/${id}`, "DELETE");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "get_stories_stats": {
                const res = await callApi("/stories/stats", "GET");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            // --- API Tokens ---
            case "list_api_tokens": {
                const res = await callApi("/tokens", "GET");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "create_api_token": {
                const res = await callApi("/tokens", "POST", args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "update_api_token": {
                const { id, ...body } = args;
                const res = await callApi(`/tokens/${id}`, "PATCH", body);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "revoke_api_token": {
                const { id } = args;
                const res = await callApi(`/tokens/${id}`, "DELETE");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            // --- Monitoring ---
            case "get_error_log": {
                const res = await callApi("/logs/errors", "GET", undefined, args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "get_login_log": {
                const res = await callApi("/logs/logins", "GET", undefined, args);
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            case "get_site_activity": {
                const res = await callApi("/site-activity", "GET");
                return { content: [{ type: "text", text: JSON.stringify(res, null, 2) }], isError: res.isError };
            }
            default:
                return {
                    content: [
                        {
                            type: "text",
                            text: `Unknown tool name: ${name}`,
                        },
                    ],
                    isError: true,
                };
        }
    }
    catch (error) {
        return {
            content: [
                {
                    type: "text",
                    text: `Error executing ${name}: ${error.message || String(error)}`,
                },
            ],
            isError: true,
        };
    }
});
// Start the server using stdio transport
async function run() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("Coffeebrk Core MCP Server running on stdio");
}
run().catch((error) => {
    console.error("Fatal error running Coffeebrk MCP server:", error);
    process.exit(1);
});
