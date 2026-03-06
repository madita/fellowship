# Collaborative Sandbox System

Real-time collaborative editing system built with Tiptap and Y.js.

## Features

- **Real-time Collaboration**: Multiple users can edit the same document simultaneously
- **Cursor Presence**: See where other collaborators are typing
- **Version History**: Save and restore previous versions
- **Access Control**: Private, members-only, or public sandboxes
- **Collaborator Management**: Invite users with viewer, editor, or admin roles

## Installation

### 1. Install Required npm Packages

```bash
npm install @tiptap/extension-collaboration @tiptap/extension-collaboration-cursor yjs y-websocket
```

### 2. Run Database Migrations

```bash
php artisan migrate
```

### 3. Configure WebSocket Server (Optional)

For production, you'll want your own Y.js WebSocket server. Options:

**Option A: Use Hocuspocus (Recommended)**
```bash
npm install @hocuspocus/server
```

Create `hocuspocus-server.js`:
```javascript
import { Server } from '@hocuspocus/server'

const server = Server.configure({
  port: 1234,
})

server.listen()
```

**Option B: Use y-websocket server**
```bash
npx y-websocket-server
```

**Option C: Use Laravel WebSockets**
Configure Laravel WebSockets for presence channels.

### 4. Set Environment Variable

```env
VITE_COLLAB_WS_URL=wss://your-websocket-server.com
```

If not set, it falls back to the Y.js demo server (for development only).

## API Endpoints

### Sandboxes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/sandbox` | List user's sandboxes |
| POST | `/api/sandbox` | Create new sandbox |
| GET | `/api/sandbox/{slug}` | Get sandbox details |
| PUT | `/api/sandbox/{id}` | Update sandbox |
| DELETE | `/api/sandbox/{id}` | Delete sandbox |

### Collaboration State

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/sandbox/{id}/state` | Get Y.js document state |
| POST | `/api/sandbox/{id}/state` | Save Y.js state |

### Collaborators

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/sandbox/{id}/collaborators` | Add collaborator |
| DELETE | `/api/sandbox/{id}/collaborators/{userId}` | Remove collaborator |
| POST | `/api/sandbox/{id}/accept-invite` | Accept invitation |

### Versions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/sandbox/{id}/versions` | List versions |
| POST | `/api/sandbox/{id}/versions/{versionId}/restore` | Restore version |

## Database Schema

### sandboxes
- `id` - Primary key
- `title` - Document title
- `slug` - URL-friendly identifier
- `description` - Optional description
- `user_id` - Owner
- `visibility` - private/members/public
- `yjs_state` - Binary Y.js document state
- `settings` - JSON configuration
- `last_edited_at` - Timestamp
- `last_edited_by` - User ID

### sandbox_collaborators
- `sandbox_id` - Foreign key
- `user_id` - Foreign key
- `role` - viewer/editor/admin
- `invited_at` - Timestamp
- `accepted_at` - Timestamp (null until accepted)

### sandbox_versions
- `sandbox_id` - Foreign key
- `user_id` - Who created the version
- `title` - Version name
- `yjs_state` - Snapshot of document state

## Frontend Routes

- `/sandbox` - List all accessible sandboxes
- `/sandbox/:slug` - Open sandbox editor

## Components

- `SandboxList.vue` - Grid view of sandboxes with create modal
- `SandboxEditor.vue` - Main collaborative editor
- `SandboxSettings.vue` - Settings modal (title, visibility, etc.)
- `SandboxCollaborators.vue` - Share/invite modal
- `SandboxVersions.vue` - Version history sidebar

## Broadcasting

The system uses Laravel's presence channels for real-time awareness:

```php
// Channel: sandbox.{id}
// Returns user info for presence tracking
```

## Security

- Authorization checks on all endpoints
- Owner and admin can manage collaborators
- Editors can modify content
- Viewers can only read
- Private sandboxes require explicit access
- Member sandboxes visible to all authenticated users
- Public sandboxes visible to everyone

## How Y.js Collaboration Works

1. Y.js creates a CRDT (Conflict-free Replicated Data Type) document
2. WebsocketProvider connects to a Y.js server
3. Changes are synced in real-time across all connected clients
4. Document state is periodically saved to the database
5. On reconnect, state is loaded from database and merged

## Tips

- Set `VITE_COLLAB_WS_URL` for production
- Auto-save runs every 30 seconds when editing
- Create versions manually for important checkpoints
- Use the restore feature to revert to previous versions
