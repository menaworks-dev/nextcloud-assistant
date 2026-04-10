<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Nextcloud Assistant (MenaWorks Fork)

Fork of [nextcloud/assistant](https://github.com/nextcloud/assistant) with Claude Code integration, real-time SSE streaming, and rich tool rendering via [AIquila MCP](https://github.com/elgorro/aiquila-mcp).

## What's Different

This fork connects the Nextcloud AI Assistant to a Claude Code proxy server, giving it access to 150+ Nextcloud MCP tools (files, calendar, contacts, deck, talk, mail, etc.) with:

- **Real-time SSE streaming** — Responses stream token-by-token directly from the proxy through nginx, bypassing PHP-FPM buffering
- **Rich tool cards** — Structured rendering for tool results (file lists, calendar events, deck boards, notifications, contacts) instead of plain text
- **Clickable actions** — Tool cards link to relevant Nextcloud apps (click a deck card to open the board, a notification to open the app)
- **Conversation history** — The proxy maintains session context across messages
- **Automatic fallback** — If streaming fails, falls back to the standard polling-based generation

## Architecture

```
Browser
  |
  |-- GET /apps/assistant/stream/{sessionId}  -->  PHP (auth + prepare messages + API key)
  |-- POST /v1/chat/completions               -->  nginx proxy_pass --> Claude Proxy --> Claude CLI
  |-- POST /ocs/.../chat/save_streamed         -->  PHP (save response to DB)
  |
  +-- SSE chunks render token-by-token in the chat UI
```

### Components

| Component | Repo | Role |
|-----------|------|------|
| **Assistant App** | this repo | Frontend UI, message DB, streaming endpoints |
| **Claude Proxy** | [menaworks-dev/claude-proxy](https://github.com/menaworks-dev/claude-proxy) | OpenAI-compatible API wrapping Claude CLI with `--include-partial-messages` |
| **AIquila MCP** | [elgorro/aiquila-mcp](https://github.com/elgorro/aiquila-mcp) | MCP server exposing Nextcloud APIs as tools |

## Setup

### Prerequisites

- Nextcloud 32+
- Claude CLI installed on the host
- AIquila MCP server connected to Claude CLI
- Node.js for the proxy server

### 1. Deploy the proxy

```bash
git clone https://github.com/menaworks-dev/claude-proxy.git
cd claude-proxy
node server.js  # runs on port 8900
```

### 2. Configure nginx

Add to your Nextcloud nginx config:

```nginx
location ^~ /v1/ {
    proxy_buffering off;
    proxy_pass http://localhost:8900;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_read_timeout 180s;
}
```

### 3. Install the assistant app

```bash
cd /path/to/nextcloud/custom_apps/
git clone -b menaworks/rich-tool-cards https://github.com/menaworks-dev/nextcloud-assistant.git assistant
cd assistant && npm ci && npm run build
occ app:enable assistant
```

### 4. Set the API key

The proxy API key must match what's configured in `integration_openai`:

```bash
occ config:app:set integration_openai api_key --value="your-proxy-key"
```

### 5. OPcache (development)

For development, set `opcache.revalidate_freq=0` so PHP picks up file changes immediately:

```ini
; Mount as /usr/local/etc/php/conf.d/zzz-opcache-dev.ini
opcache.revalidate_freq=0
```

## Key Files

| File | Purpose |
|------|---------|
| `lib/Controller/AssistantController.php` | `streamGenerate()` — prepares messages + API key for frontend streaming |
| `lib/Controller/ChattyLLMController.php` | `saveStreamedMessage()` — persists streamed response to DB |
| `src/components/ChattyLLM/ChattyLLMInputForm.vue` | `runStreamingTask()` — SSE client with polling fallback |
| `src/components/ChattyLLM/ToolCards.vue` | Rich rendering for all tool categories with text parsers |
| `appinfo/routes.php` | Stream + save routes |

## Original README

This app brings a user interface to use the Nextcloud text processing feature.
It allows users to launch AI tasks, be notified when they finish and see the results.

More details in the [upstream repo](https://github.com/nextcloud/assistant).