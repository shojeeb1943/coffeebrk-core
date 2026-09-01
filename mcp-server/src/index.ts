#!/usr/bin/env node

/**
 * Coffeebrk Core MCP Server
 *
 * Model Context Protocol (MCP) server that enables AI agents to read, create,
 * update, and manage WordPress content, feeds, stories, and metadata via Coffeebrk Core REST API.
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
  Tool,
} from "@modelcontextprotocol/sdk/types.js";

// Retrieve configuration from environment variables
const WP_URL = (process.env.COFFEEBRK_WP_URL || "http://localhost").replace(/\/+$/, "");
const API_TOKEN = process.env.COFFEEBRK_API_TOKEN || "";

/**
 * Helper to make authenticated requests to Coffeebrk REST API
 */
async function callApi(
  endpoint: string,
  method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE" = "GET",
  body?: any,
  queryParams?: Record<string, any>
) {
  let url = `${WP_URL}/wp-json/coffeebrk/v1${endpoint.startsWith("/") ? endpoint : "/" + endpoint}`;

  if (queryParams) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(queryParams)) {
      if (value !== undefined && value !== null && value !== "") {
        if (Array.isArray(value)) {
          value.forEach((v) => params.append(`${key}[]`, String(v)));
        } else {
          params.append(key, String(value));
        }
      }
    }
    const qs = params.toString();
    if (qs) {
      url += (url.includes("?") ? "&" : "?") + qs;
    }
  }

  const headers: Record<string, string> = {
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
  } catch (error: any) {
    return {
      isError: true,
      error: error.message || "Failed to communicate with WordPress REST API",
    };
  }
}

// Define tools
const TOOLS: Tool[] = [
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
        profile_id: { type: "integer", description: "Filter by profile ID" },
        category: { type: "integer", description: "Filter by category ID" },
        featured: { type: "boolean", description: "Filter by featured status" },
      },
    },
  },
  {
    name: "list_x_profiles",
    description: "List all monitored X (Twitter) collector profiles.",
    inputSchema: {
      type: "object",
      properties: {
        enabled: {
          type: "boolean",
          default: true,
          description: "Filter by enabled status",
        },
      },
    },
  },
  {
    name: "create_x_profile",
    description: "Add or update an X (Twitter) profile to monitor in Coffeebrk X Collector.",
    inputSchema: {
      type: "object",
      properties: {
        username: {
          type: "string",
          description: "X / Twitter username without @",
        },
        display_name: {
          type: "string",
          description: "Display name / label for the profile",
        },
        enabled: {
          type: "boolean",
          default: true,
          description: "Whether the profile is active for sync (default: true)",
        },
        category_id: {
          type: "integer",
          description: "Optional WordPress category ID to tag incoming tweets",
        },
      },
      required: ["username"],
    },
  },
  {
    name: "bulk_create_x_profiles",
    description: "Batch add or activate multiple X (Twitter) profiles at once in Coffeebrk X Collector.",
    inputSchema: {
      type: "object",
      properties: {
        profiles: {
          type: "array",
          description: "Array of X profiles to add/enable",
          items: {
            type: "object",
            properties: {
              username: { type: "string" },
              display_name: { type: "string" },
              enabled: { type: "boolean", default: true },
              category_id: { type: "integer" },
            },
            required: ["username"],
          },
        },
      },
      required: ["profiles"],
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
];

// Create MCP Server instance
const server = new Server(
  {
    name: "coffeebrk-core",
    version: "2.2.3",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

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
        const { id, ...queryParams } = args as any;
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
        const { id, ...body } = args as any;
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
        const { id, force } = args as any;
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

      case "list_x_profiles": {
        const res = await callApi("/x-profiles", "GET", undefined, args);
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

      case "create_x_profile": {
        const res = await callApi("/x-profiles", "POST", args);
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

      case "bulk_create_x_profiles": {
        const res = await callApi("/x-profiles/bulk", "POST", args);
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
  } catch (error: any) {
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
