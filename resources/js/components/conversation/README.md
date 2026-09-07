# Conversation System Documentation

## Overview
A real-time conversation/messaging system with Facebook Messenger-like functionality, including chat boxes that open automatically when someone messages you.

## Architecture

### Components

#### Main Components
- **ConversationBox.vue** - Floating chat window (Facebook Messenger style)
- **ConversationBoxManager.vue** - Manages visibility and state of chat boxes
- **Conversation.vue** - Full-page conversation view
- **ConversationMessages.vue** - Message display component
- **Conversations.vue** - List of all conversations
- **ConversationsDashboard.vue** - Two-panel layout (list + active conversation)

#### Form Components
- **ConversationForm.vue** - Create new conversation
- **ConversationAddUserForm.vue** - Add users to existing conversation
- **ConversationReplyForm.vue** - Send replies in a conversation

#### Supporting Components
- **ConversationsNotification.vue** - Notification dropdown for new messages
- **SidebarUsers.vue** - Online users list with click-to-chat

### Composables

Located in `resources/js/composables/conversation/`:

#### useEchoListener.js
Manages WebSocket listeners via Laravel Echo.
```javascript
const { setupListener, cleanup, currentChannel } = useEchoListener(debug)
setupListener(channelName, eventName, callback)
```

#### useUserSearch.js
Debounced user search for recipient selection.
```javascript
const { userList, userSearch, loadingUsers, handleUserSearch } = useUserSearch(debounceMs)
```

#### useScrollToBottom.js
Auto-scrolls message containers to bottom.
```javascript
const { scrollToBottom } = useScrollToBottom(containerRef)
```

### Shared Resources

#### constants.js
Validation rules, debounce delays, message limits.

#### styles.css
Shared CSS for scrollbars, gradients, message bubbles, transitions.

## Real-Time Features

### How It Works

1. **Starting a Chat**
   - User clicks on another user (from SidebarUsers or elsewhere)
   - Event `conversation.new` is emitted via eventBus
   - ConversationBox checks if conversation exists with that user
   - If exists: loads it; if not: prepares to create new one

2. **Sending First Message**
   - ConversationBox creates conversation via API
   - Backend broadcasts `ConversationCreated` event to recipient's private channel
   - Recipient's browser receives event and opens their chat box automatically
   - Message is displayed in both sender's and recipient's chat boxes

3. **Real-Time Message Updates**
   - When a message is sent, backend broadcasts `MessageAdded` to:
     - The conversation's channel (for users actively viewing it)
     - Individual user channels (for users not viewing it)
   - Frontend receives event and either:
     - Adds message to current conversation, OR
     - Opens chat box with the new message

### Event Flow Diagram

```
User A clicks on User B
    ↓
conversation.new event emitted
    ↓
ConversationBox finds/creates conversation
    ↓
User A sends message
    ↓
Backend creates conversation/message
    ↓
Backend broadcasts ConversationCreated to User B's channel (user.{id})
    ↓
User B's browser receives event
    ↓
User B's chat box opens automatically with User A's message
    ↓
Both users can now chat in real-time
```

## Backend Events

### ConversationCreated
**Channel:** `users.{userId}` (for each recipient)

**Payload:**
```php
[
    'conversation' => [
        'uuid' => '...',
        'creator' => [
            'id' => 1,
            'username' => 'john',
            'avatar' => '...'
        ],
        'users' => [...]
    ]
]
```

### MessageAdded
**Channels:**
- `conversations.{conversationUuid}` (for active viewers)
- `user.{userId}` (for each recipient not currently viewing)

**Payload:**
```php
[
    'message' => [
        'id' => 1,
        'body' => '...',
        'user' => [...],
        'conversation' => [
            'uuid' => '...'
        ],
        'created_at_human' => '2 minutes ago',
        'selfOwned' => false
    ]
]
```

## Usage

### Basic Setup

Add ConversationBoxManager to your main layout:

```vue
<template>
    <div>
        <!-- Your app content -->
        <conversation-box-manager />
    </div>
</template>

<script setup>
import ConversationBoxManager from '@/components/conversation/ConversationBoxManager.vue'
</script>
```

### Starting a Chat

From any component, emit the `conversation.new` event:

```javascript
import eventBus from '@/components/common/eventBus.js'

// When user clicks on another user
eventBus.emit('conversation.new', {
    id: userId,
    username: 'john',
    avatar: '/avatars/john.jpg'
})
```

### Programmatically Opening Chat Box

```javascript
eventBus.emit('chat.open', {
    user: {
        id: userId,
        username: 'john',
        avatar: '/avatars/john.jpg'
    },
    conversationUuid: 'optional-uuid' // If you already know the conversation
})
```

### Closing Chat Box

```javascript
eventBus.emit('chat.close')
```

## API Endpoints Required

- `GET /api/conversations` - List all conversations
- `GET /api/conversations/{uuid}` - Get single conversation with messages
- `POST /api/conversations` - Create new conversation
- `POST /api/conversations/{uuid}/messages` - Add message to conversation
- `POST /api/conversations/{uuid}/users` - Add user to conversation
- `POST /api/users/search` - Search users by query

## WebSocket Configuration

Ensure Laravel Echo is configured in your app:

```javascript
window.Echo = new Echo({
    broadcaster: 'ably', // or 'pusher', 'socket.io', etc.
    // ... your config
})
```

Private channels must be authenticated via `routes/channels.php`:

```php
use App\Models\Conversation\Conversation;

// User private channel (singular - used by frontend)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User private channel (plural - legacy support)
Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Conversation private channel
Broadcast::channel('conversations.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::where('uuid', $conversationId)->first();

    if (!$conversation) {
        return false;
    }

    return $user->inConversation($conversation->id);
});
```

**Required User Model Methods:**

```php
public function conversations()
{
    return $this->belongsToMany(Conversation::class)->withPivot('read_at');
}

public function inConversation($id)
{
    return $this->conversations->contains('id', $id);
}
```

## Styling

Components use Vuetify 3 with custom shared styles. To customize:

1. Edit `resources/js/components/conversation/styles.css` for global changes
2. Override in individual component `<style scoped>` sections
3. Use Vuetify theme configuration for colors

## Testing

When testing real-time features:

1. Open app in two different browsers/incognito windows
2. Log in as different users
3. Click on user in one browser to start chat
4. Verify chat box opens automatically in other browser
5. Send messages and verify they appear instantly in both

## Troubleshooting

### Access Denied Error for Private Channels
**Error:** `Access denied, user-id:X channel-name:private:user.X`

**Solution:**
1. Check `routes/channels.php` has proper authorization:
```php
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

2. Make sure user is authenticated before Echo connects
3. Clear browser cache and reload
4. Check that the channel name matches exactly (singular vs plural)

### Messages Not Showing
- Check if conversation is loaded (`console.log(conversation.value)`)
- Verify ConversationMessages component is receiving correct `:id` prop
- Check browser console for API errors
- Verify messages are being stored in database

### Chat Box Not Opening for Recipient
- Verify Laravel Echo is connected:
  - Ably: `Echo.connector.ably.connection.state` should be "connected"
  - Pusher: `Echo.connector.pusher.connection.state` should be "connected"
- Check if ConversationCreated event is being broadcast (check Laravel logs)
- Ensure private channels are properly authenticated
- Verify user is subscribed to correct channel (`user.{userId}`)
- Check browser console for channel subscription errors

### Events Not Received
- Check `routes/channels.php` authorization for all required channels
- Verify queue worker is running: `php artisan queue:work` (if using queued broadcasts)
- Check browser console for WebSocket connection errors
- For Ably: Check your Ably dashboard for connection logs
- For Pusher: Use Pusher debug console
- Test channel authorization manually: `curl -X POST /broadcasting/auth`

### Channel Name Mismatch
If you see errors about wrong channel names:
- Frontend uses: `user.{id}` and `conversations.{uuid}`
- Backend events should broadcast to same channel names
- Both singular and plural versions are supported for `user/users` channels

## Unread Message Tracking

The system automatically tracks unread messages per user:

### How It Works

1. **Tracking Read Status:**
   - Each user has a `read_at` timestamp in the `conversation_user` pivot table
   - When a user opens a conversation, `read_at` is updated to the current time
   - Messages created after `read_at` are considered unread

2. **Unread Count Calculation:**
   - Backend counts messages where `created_at > read_at`
   - Excludes the user's own messages from unread count
   - Returns `unread_count` and `is_unread` in API response

3. **API Response Fields:**
```json
{
  "uuid": "...",
  "body": "Last message text",
  "unread_count": 3,
  "is_unread": true,
  "read_at": "2025-10-26T12:34:56.000000Z",
  "users": [...]
}
```

4. **Auto-Mark as Read:**
   - Conversation is automatically marked as read when:
     - User opens the conversation via `GET /api/conversations/{uuid}`
     - User views messages in ConversationBox
   - Frontend refreshes conversation list to update badge counts

5. **Real-Time Updates:**
   - Badge count updates when new messages arrive via Echo
   - Conversations list refreshes automatically
   - Unread indicator appears on conversation items

## Future Enhancements

- [ ] Multiple simultaneous chat boxes (like Facebook)
- [ ] Typing indicators
- [x] Unread message tracking and badges
- [ ] File attachments
- [ ] Emoji picker
- [ ] Message search
- [ ] Conversation archiving
- [ ] Voice messages
- [ ] Video calls
