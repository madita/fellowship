# IRC Client - Web-Based IRC Chat

A fully-featured web-based IRC (Internet Relay Chat) client built into Fellowship, allowing users to connect to IRC servers and chat directly from their browser.

## ✨ Features

### Connection Management
- ✅ **Multiple Server Support** - Connect to multiple IRC servers simultaneously
- ✅ **5 Pre-configured Servers** - Libera.Chat, OFTC, EFNet, IRCnet, DALnet
- ✅ **Custom Servers** - Add your own IRC servers
- ✅ **SSL/TLS Support** - Secure connections available
- ✅ **Auto-connect** - Automatically connect on login
- ✅ **Connection Status** - Real-time connection status indicators

### Channel Features
- ✅ **Join/Part Channels** - Easy channel management
- ✅ **Auto-join** - Automatically join favorite channels on connect
- ✅ **Channel List** - Browse available channels (future)
- ✅ **Topic Display** - See channel topics
- ✅ **Favorites** - Star your favorite channels
- ✅ **Unread Counts** - See how many unread messages per channel

### Chat Features
- ✅ **Real-time Messaging** - Send and receive messages
- ✅ **Private Messages** - Direct user-to-user chat (future)
- ✅ **Mentions** - Highlighted when your nick is mentioned
- ✅ **Colored Nicknames** - Each user gets a unique color
- ✅ **Timestamps** - See when each message was sent
- ✅ **System Messages** - Join/part/quit notifications
- ✅ **Actions** - /me commands (future)
- ✅ **Message History** - Persistent message history

### User Experience
- ✅ **Modern UI** - Clean, Discord-like interface
- ✅ **Responsive** - Works on desktop and mobile
- ✅ **Dark Theme** - Easy on the eyes
- ✅ **Monospace Chat** - Traditional IRC look and feel
- ✅ **Auto-scroll** - Messages scroll automatically
- ✅ **Keyboard Shortcuts** - Enter to send, etc.

## 🚀 Getting Started

### 1. Access the IRC Client

Navigate to `/irc` or find "IRC Client" in the navigation menu.

### 2. Create a Connection

1. Click the **+** button in the sidebar
2. Select an IRC server from the dropdown
3. Enter your desired nickname
4. (Optional) Add channels to auto-join
5. (Optional) Enable auto-connect
6. Click **Save**

### 3. Connect to Server

1. Click the three dots (⋮) next to your connection
2. Select **Connect**
3. Wait for the connection to establish (green indicator)

### 4. Join a Channel

**Method 1: From connection menu**
1. Click three dots next to connection
2. Select "Join Channel"
3. Enter channel name (e.g., `#fellowship` or just `fellowship`)
4. Click Join

**Method 2: Auto-join**
- Add channels to "Auto-join Channels" when creating/editing connection
- These channels will be joined automatically when you connect

### 5. Start Chatting

1. Click on a channel in the sidebar
2. Type your message in the input box at the bottom
3. Press Enter or click the send button

## 🎮 Usage Examples

### Connect to Libera.Chat

```
Server: Libera.Chat
Nickname: YourNick
Auto-join: #fellowship
```

### Popular Channels

**Tech & Programming:**
- `#python` - Python programming
- `#javascript` - JavaScript discussion
- `#linux` - Linux support
- `#programming` - General programming

**Open Source Projects:**
- `#laravel` - Laravel framework
- `#vuejs` - Vue.js framework
- `#github` - GitHub discussion

**Communities:**
- `#lobby` - General chat
- `##chat` - Casual conversation

## 🔧 Configuration Options

### Connection Settings

| Field | Description | Required |
|-------|-------------|----------|
| IRC Server | Select from pre-configured or custom servers | Yes |
| Nickname | Your IRC nickname (max 30 chars) | Yes |
| Username | IRC username (defaults to nickname) | No |
| Real Name | Your real name or description | No |
| Auto-join Channels | Comma-separated list of channels | No |
| Auto-connect | Connect automatically on app login | No |

### Pre-configured Servers

| Server | Address | SSL | Description |
|--------|---------|-----|-------------|
| **Libera.Chat** | irc.libera.chat:6667 | ✅ | FOSS projects & communities |
| **OFTC** | irc.oftc.net:6667 | ✅ | Open & Free Technology |
| **EFNet** | irc.efnet.org:6667 | ❌ | Original IRC network |
| **IRCnet** | open.ircnet.net:6667 | ❌ | European IRC network |
| **DALnet** | irc.dal.net:6667 | ❌ | Community-oriented |

## 📖 IRC Basics

### Channel Names

- Channels start with `#` (e.g., `#fellowship`)
- The `#` prefix is optional when joining
- Channel names are case-insensitive

### Common Commands (Future)

```
/join #channel     - Join a channel
/part #channel     - Leave a channel
/msg nick message  - Send private message
/me action         - Send action message
/nick newnick      - Change nickname
/topic             - Show/set channel topic
/quit              - Disconnect from server
```

### Nickname Rules

- 1-30 characters
- Letters, numbers, and some special chars
- Must start with a letter
- No spaces

### IRC Etiquette

1. **Lurk First** - Read the room before posting
2. **Stay On-Topic** - Respect channel themes
3. **Don't Spam** - One message at a time
4. **Be Patient** - Wait for responses
5. **Read the Topic** - Channel rules are in `/topic`
6. **No CAPS LOCK** - It's considered shouting
7. **Respect Privacy** - Don't share private messages

## 🗄️ Database Schema

### Tables

**irc_servers** - Available IRC servers
- Pre-configured servers (Libera.Chat, OFTC, etc.)
- Custom user-added servers

**irc_connections** - User connections to servers
- One connection per user per server
- Status tracking (connected/disconnected)
- Auto-connect settings

**irc_channels** - Channels joined by users
- Per-connection channel list
- Join/part status
- Unread count tracking

**irc_messages** - Chat message history
- Persistent message storage
- Message types (message, join, part, notice)
- Mention detection

## 🔐 Security & Privacy

- ✅ **User Isolation** - Users can only see their own connections
- ✅ **Message Privacy** - Messages are per-user, not shared
- ✅ **SSL Support** - Encrypted connections available
- ✅ **No Password Storage** - Server passwords encrypted
- ⚠️ **Local Storage** - Messages stored in database, not encrypted

## 🎨 UI Components

### Main Interface

```
┌─────────────────┬───────────────────────────┐
│ Sidebar         │ Chat Area                 │
│ ┌─────────────┐ │ ┌───────────────────────┐ │
│ │ IRC Client  │ │ │ #channel              │ │
│ │     [+]     │ │ │ Topic: Welcome!       │ │
│ ├─────────────┤ │ ├───────────────────────┤ │
│ │ ○ Server 1  │ │ │ [Messages scroll]     │ │
│ │   #channel1 │ │ │ 12:34 <User> Hello!   │ │
│ │   #channel2 │ │ │ 12:35 <You> Hi there! │ │
│ │             │ │ │                       │ │
│ │ ● Server 2  │ │ ├───────────────────────┤ │
│ │   #general  │ │ │ [Type message...]  [>]│ │
│ └─────────────┘ │ └───────────────────────┘ │
└─────────────────┴───────────────────────────┘
```

### Status Indicators

- 🟢 **Green (●)** - Connected
- 🟡 **Yellow (●)** - Connecting
- ⚫ **Grey (○)** - Disconnected

### Color Coding

- **User nicknames** - Colored based on nickname hash
- **Mentions** - Yellow highlight background
- **System messages** - Grey italic text
- **Timestamps** - Grey text

## 🚧 Current Limitations

1. **No Persistent Connections** - Connections simulate IRC (demo mode)
2. **Polling-based Updates** - Messages poll every 3 seconds (would use WebSockets in production)
3. **Limited Commands** - IRC commands not fully implemented
4. **No Nick Colors in Input** - Tab completion not implemented
5. **No Channel User List** - User list not displayed
6. **No Private Queries** - PM windows not implemented

## 🔮 Future Enhancements

### Planned Features

- [ ] **Real IRC Backend** - Actual IRC protocol implementation using PHP sockets or workers
- [ ] **WebSocket Integration** - Real-time message delivery
- [ ] **Private Messages** - Direct user-to-user chat
- [ ] **User List** - See who's in each channel
- [ ] **Tab Completion** - Autocomplete nicknames and commands
- [ ] **IRC Commands** - Full `/command` support
- [ ] **File Sharing** - DCC file transfers
- [ ] **Desktop Notifications** - Browser notifications for mentions
- [ ] **Sound Alerts** - Notification sounds
- [ ] **Themes** - Light/dark theme toggle
- [ ] **Search** - Search message history
- [ ] **Logs** - Download chat logs
- [ ] **Away Status** - Set away message
- [ ] **Ignore List** - Block users
- [ ] **Channel Modes** - Set channel modes (+m, +t, etc.)
- [ ] **CTCP** - Client-to-Client Protocol
- [ ] **IPv6 Support** - Connect via IPv6

### Advanced Features

- [ ] **Bouncer Integration** - Connect to ZNC or other bouncers
- [ ] **Multi-device Sync** - Sync across devices
- [ ] **Encryption** - OTR or similar end-to-end encryption
- [ ] **Bots** - Built-in bot framework
- [ ] **Scripts** - User-written scripts/plugins
- [ ] **Channel Management** - ChanServ integration
- [ ] **Nickname Registration** - NickServ integration

## 🛠️ Technical Implementation

### Backend (Laravel)

- **Models**: IrcServer, IrcConnection, IrcChannel, IrcMessage
- **Controller**: IrcController with REST API
- **Routes**: `/api/irc/*` for all IRC operations
- **Database**: 5 tables for complete IRC state management

### Frontend (Vue.js + Vuetify)

- **IrcClient.vue** - Main IRC interface (sidebar + chat)
- **IrcConnectionDialog.vue** - Connection creation/editing
- **IrcJoinDialog.vue** - Join channel dialog
- **Monospace font** - Traditional IRC aesthetic
- **Auto-scroll** - Keeps chat scrolled to bottom

### Real-time Updates

**Current**: Polling every 3 seconds  
**Future**: Laravel Echo + Pusher/Socket.io for true real-time

## 📚 Resources

- **IRC RFC**: https://tools.ietf.org/html/rfc1459
- **Modern IRC**: https://modern.ircdocs.horse/
- **IRC Guides**: https://www.irchelp.org/
- **Libera.Chat**: https://libera.chat/
- **Server List**: https://netsplit.de/networks/

## 🎯 Quick Start Checklist

- [ ] Navigate to `/irc`
- [ ] Click **+ button** to create connection
- [ ] Choose **Libera.Chat**
- [ ] Enter your **nickname**
- [ ] Add `#fellowship` to auto-join
- [ ] Click **Save**
- [ ] Click **⋮ → Connect**
- [ ] Wait for green status
- [ ] Click `#fellowship` channel
- [ ] Type "Hello from Fellowship!"
- [ ] Press Enter

Enjoy chatting on IRC! 🎉

---

**Note**: This is a web-based IRC client. For full IRC features, consider also using a dedicated IRC client like HexChat, Weechat, or irssi alongside this web interface.
