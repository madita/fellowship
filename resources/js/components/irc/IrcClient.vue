<template>
  <v-container fluid class="irc-client pa-0" :style="{ height: containerHeight }">
    <v-row no-gutters class="irc-row">
      <!-- Server/Channel Sidebar -->
      <v-col cols="12" md="3" class="sidebar">
        <v-card flat height="100%">
          <v-card-title class="d-flex justify-space-between align-center">
            IRC Client
            <v-btn icon size="small" @click="showConnectionDialog = true">
              <v-icon>mdi-plus</v-icon>
            </v-btn>
          </v-card-title>

          <v-divider />

          <!-- Connections List -->
          <v-list>
            <div v-for="connection in connections" :key="connection.id">
              <v-list-item>
                <template #prepend>
                  <v-icon :color="getStatusColor(connection.status)">
                    {{ getStatusIcon(connection.status) }}
                  </v-icon>
                </template>

                <v-list-item-title>{{ connection.server.name }}</v-list-item-title>
                <v-list-item-subtitle>{{ connection.nickname }}</v-list-item-subtitle>

                <template #append>
                  <v-menu>
                    <template #activator="{ props }">
                      <v-btn icon size="small" v-bind="props">
                        <v-icon>mdi-dots-vertical</v-icon>
                      </v-btn>
                    </template>
                    <v-list>
                      <v-list-item v-if="connection.status === 'disconnected'" @click="connect(connection)">
                        <v-list-item-title>Connect</v-list-item-title>
                      </v-list-item>
                      <v-list-item v-else @click="disconnect(connection)">
                        <v-list-item-title>Disconnect</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="showJoinDialog(connection)">
                        <v-list-item-title>Join Channel</v-list-item-title>
                      </v-list-item>
                      <v-divider />
                      <v-list-item @click="showCharacterSelector(connection)">
                        <v-list-item-title>Choose Character</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="editConnection(connection)">
                        <v-list-item-title>Edit</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="deleteConnection(connection)">
                        <v-list-item-title class="text-error">Delete</v-list-item-title>
                      </v-list-item>
                    </v-list>
                  </v-menu>
                </template>
              </v-list-item>

              <!-- Channels for this connection -->
              <v-list-group v-if="getChannels(connection).length">
                <template #activator="{ props }">
                  <v-list-item v-bind="props" density="compact" class="pl-8">
                    <v-list-item-title class="text-caption">
                      Channels ({{ getChannels(connection).length }})
                    </v-list-item-title>
                  </v-list-item>
                </template>

                <v-list-item
                  v-for="channel in getChannels(connection)"
                  :key="channel.id"
                  :active="activeChannel?.id === channel.id"
                  @click="selectChannel(channel)"
                  class="pl-12"
                  density="compact"
                >
                  <template #prepend>
                    <v-icon size="small" :color="channel.is_joined ? 'success' : 'grey'">
                      mdi-pound
                    </v-icon>
                  </template>

                  <v-list-item-title class="text-body-2">
                    {{ channel.name.replace('#', '') }}
                    <v-chip v-if="channel.unread_count > 0" size="x-small" color="primary" class="ml-1">
                      {{ channel.unread_count }}
                    </v-chip>
                  </v-list-item-title>

                  <template #append>
                    <v-btn
                      v-if="!channel.is_joined && connection.status === 'connected'"
                      icon
                      size="x-small"
                      variant="text"
                      color="success"
                      @click.stop="rejoinChannel(connection, channel)"
                    >
                      <v-icon size="small">mdi-login</v-icon>
                      <v-tooltip activator="parent" location="top">Rejoin</v-tooltip>
                    </v-btn>
                    <v-btn
                      icon
                      size="x-small"
                      variant="text"
                      @click.stop="toggleFavorite(channel)"
                    >
                      <v-icon size="small" :color="channel.is_favorite ? 'warning' : 'grey'">
                        {{ channel.is_favorite ? 'mdi-star' : 'mdi-star-outline' }}
                      </v-icon>
                    </v-btn>
                  </template>
                </v-list-item>
              </v-list-group>

              <!-- Private Messages for this connection -->
              <v-list-group v-if="getPrivateChats(connection).length">
                <template #activator="{ props }">
                  <v-list-item v-bind="props" density="compact" class="pl-8">
                    <v-list-item-title class="text-caption">
                      Messages ({{ getPrivateChats(connection).length }})
                    </v-list-item-title>
                  </v-list-item>
                </template>

                <v-list-item
                  v-for="channel in getPrivateChats(connection)"
                  :key="channel.id"
                  :active="activeChannel?.id === channel.id"
                  @click="selectChannel(channel)"
                  class="pl-12"
                  density="compact"
                >
                  <template #prepend>
                    <v-icon size="small" color="info">
                      mdi-account
                    </v-icon>
                  </template>

                  <v-list-item-title class="text-body-2">
                    {{ channel.name }}
                    <v-chip v-if="channel.unread_count > 0" size="x-small" color="primary" class="ml-1">
                      {{ channel.unread_count }}
                    </v-chip>
                  </v-list-item-title>
                </v-list-item>
              </v-list-group>
            </div>

            <v-list-item v-if="!connections.length" class="text-center text-grey">
              <v-list-item-title>No connections</v-list-item-title>
              <v-list-item-subtitle>Click + to add one</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>

      <!-- Chat Area -->
      <v-col cols="12" :md="activeChannel && showUserList ? 6 : 9" class="chat-area d-flex flex-column">
        <v-card v-if="activeChannel" flat class="d-flex flex-column flex-grow-1" style="min-height: 0;">
          <!-- Channel Header -->
          <v-card-title class="d-flex justify-space-between align-center flex-shrink-0">
            <div>
              <span class="text-h6">{{ activeChannel.name }}</span>
              <span v-if="activeChannel.topic" class="text-caption text-grey ml-3">
                {{ activeChannel.topic }}
              </span>
            </div>
            <div class="d-flex gap-2 align-center">
              <!-- View Mode Toggle -->
              <v-btn-toggle v-model="viewMode" mandatory density="compact">
                <v-btn value="classic" size="small">
                  <v-icon>mdi-format-align-left</v-icon>
                  <v-tooltip activator="parent" location="bottom">
                    Classic IRC View
                  </v-tooltip>
                </v-btn>
                <v-btn value="comic" size="small">
                  <v-icon>mdi-book-open-variant</v-icon>
                  <v-tooltip activator="parent" location="bottom">
                    Comic Chat View
                  </v-tooltip>
                </v-btn>
              </v-btn-toggle>

              <!-- Users Toggle -->
              <v-btn icon size="small" :color="showUserList ? 'primary' : undefined" @click="showUserList = !showUserList">
                <v-icon>mdi-account-group</v-icon>
                <v-tooltip activator="parent" location="bottom">
                  {{ showUserList ? 'Hide' : 'Show' }} Users
                </v-tooltip>
              </v-btn>

              <v-btn icon size="small" @click="partChannel(activeChannel)">
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </div>
          </v-card-title>

          <v-divider />

          <!-- Classic IRC View -->
          <v-card-text
            v-if="viewMode === 'classic'"
            ref="messagesContainer"
            class="messages-container flex-grow-1"
            style="overflow-y: auto; min-height: 0;"
          >
            <div v-for="message in messages" :key="message.id" :class="getMessageClass(message)">
              <div class="message-line">
                <span class="timestamp text-caption text-grey">
                  {{ formatTime(message.sent_at) }}
                </span>
                <template v-if="message.type === 'action'">
                  <span class="action-message">
                    * <span class="nick-inline" :style="{ color: getNickColor(message.from_nick) }">{{ message.from_nick }}</span>
                    {{ message.message }}
                  </span>
                </template>
                <template v-else-if="message.type === 'system'">
                  <span class="system-message">***</span>
                  <span class="message-text system-text">{{ message.message }}</span>
                </template>
                <template v-else>
                  <span class="nick" :style="{ color: getNickColor(message.from_nick) }">
                    &lt;{{ message.from_nick }}&gt;
                  </span>
                  <span class="message-text">{{ message.message }}</span>
                </template>
              </div>
            </div>

            <div v-if="!messages.length" class="text-center text-grey py-8">
              No messages yet
            </div>
          </v-card-text>

          <!-- Comic Chat View -->
          <comic-chat-view
            v-else
            ref="comicView"
            :messages="messages"
            :character="currentConnection?.comic_character || 'cat'"
            :my-nick="currentConnection?.nickname || ''"
            :background="comicBackground"
            :show-timestamps="true"
            :show-emotion-bar="true"
            @emotion-selected="onEmotionSelected"
            @gesture-selected="onGestureSelected"
            class="flex-grow-1"
            style="min-height: 0; overflow-y: auto;"
          />

          <!-- Message Input -->
          <v-divider />
          <v-card-actions class="pa-2 flex-shrink-0">
            <v-row dense>
              <!-- Emotion/Gesture Bar (Comic Mode Only) -->
              <v-col v-if="viewMode === 'comic'" cols="12">
                <div class="d-flex gap-2 flex-wrap">
                  <v-chip-group v-model="selectedEmotion" mandatory>
                    <v-chip size="small" value="normal">Normal</v-chip>
                    <v-chip size="small" value="happy">Happy</v-chip>
                    <v-chip size="small" value="sad">Sad</v-chip>
                    <v-chip size="small" value="angry">Angry</v-chip>
                    <v-chip size="small" value="surprised">Surprised</v-chip>
                    <v-chip size="small" value="confused">Confused</v-chip>
                  </v-chip-group>
                  <v-divider vertical />
                  <v-chip-group v-model="selectedGesture">
                    <v-chip size="small" value="none">None</v-chip>
                    <v-chip size="small" value="wave">Wave</v-chip>
                    <v-chip size="small" value="laugh">Laugh</v-chip>
                    <v-chip size="small" value="think">Think</v-chip>
                    <v-chip size="small" value="shout">Shout</v-chip>
                    <v-chip size="small" value="whisper">Whisper</v-chip>
                  </v-chip-group>
                </div>
              </v-col>

              <!-- Message Input -->
              <v-col cols="12">
                <v-text-field
                  v-model="newMessage"
                  placeholder="Type a message or /help for commands..."
                  variant="outlined"
                  density="compact"
                  hide-details
                  @keydown.enter="sendMessage"
                  autofocus
                >
                  <template #append-inner>
                    <v-btn
                      icon
                      size="small"
                      color="primary"
                      :disabled="!newMessage.trim()"
                      @click="sendMessage"
                    >
                      <v-icon>mdi-send</v-icon>
                    </v-btn>
                  </template>
                </v-text-field>
              </v-col>
            </v-row>
          </v-card-actions>
        </v-card>

        <!-- No Channel Selected -->
        <v-card v-else flat class="d-flex align-center justify-center flex-grow-1">
          <div class="text-center">
            <v-icon size="64" color="grey-lighten-1">mdi-chat-outline</v-icon>
            <p class="text-h6 mt-4">Select a channel to start chatting</p>
          </div>
        </v-card>
      </v-col>

      <!-- Online Users Sidebar -->
      <v-col v-if="activeChannel && showUserList" cols="12" md="3" class="users-sidebar d-flex flex-column">
        <v-card flat class="d-flex flex-column flex-grow-1" style="min-height: 0;">
          <v-card-title class="d-flex justify-space-between align-center flex-shrink-0 text-body-1">
            <span>
              <v-icon size="small" class="mr-1">mdi-account-group</v-icon>
              Users ({{ channelUsers.length }})
            </span>
            <v-btn icon size="x-small" @click="showUserList = false">
              <v-icon size="small">mdi-close</v-icon>
            </v-btn>
          </v-card-title>
          <v-divider />
          <v-list density="compact" class="flex-grow-1" style="overflow-y: auto; min-height: 0;">
            <v-list-item
              v-for="user in channelUsers"
              :key="user.nickname"
              density="compact"
            >
              <template #prepend>
                <v-icon size="small" :color="user.isOnline ? 'success' : 'grey'">
                  mdi-circle
                </v-icon>
              </template>
              <v-list-item-title class="text-body-2">
                <span v-if="user.prefix" class="font-weight-bold user-prefix">{{ user.prefix }}</span>
                {{ user.nickname }}
              </v-list-item-title>
            </v-list-item>
            <v-list-item v-if="!channelUsers.length">
              <v-list-item-title class="text-caption text-grey text-center">
                No users
              </v-list-item-title>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>

    <!-- Connection Dialog -->
    <irc-connection-dialog
      v-model="showConnectionDialog"
      :connection="editingConnection"
      :servers="servers"
      @saved="onConnectionSaved"
    />

    <!-- Join Channel Dialog -->
    <irc-join-dialog
      v-model="showJoinChannelDialog"
      :connection="joiningConnection"
      @joined="onChannelJoined"
    />

    <!-- Character Selector Dialog -->
    <comic-character-selector
      v-model="showCharacterDialog"
      :current-character="editingConnectionForCharacter?.comic_character"
      :current-background="comicBackground"
      @saved="onCharacterSaved"
    />
  </v-container>
</template>

<script>
import axios from 'axios';
import IrcConnectionDialog from './IrcConnectionDialog.vue';
import IrcJoinDialog from './IrcJoinDialog.vue';
import ComicChatView from './ComicChatView.vue';
import ComicCharacterSelector from './ComicCharacterSelector.vue';

export default {
  name: 'IrcClient',
  components: {
    IrcConnectionDialog,
    IrcJoinDialog,
    ComicChatView,
    ComicCharacterSelector,
  },
  data() {
    return {
      connections: [],
      servers: [],
      activeChannel: null,
      messages: [],
      newMessage: '',
      showConnectionDialog: false,
      showJoinChannelDialog: false,
      showCharacterDialog: false,
      editingConnection: null,
      joiningConnection: null,
      editingConnectionForCharacter: null,
      eventPolling: null,
      viewMode: 'classic', // classic or comic
      selectedEmotion: 'normal',
      selectedGesture: 'none',
      comicBackground: 'room',
      showUserList: false,
      channelUsers: [],
      containerHeight: '100%',
      serverMessages: [],
    };
  },
  computed: {
    currentConnection() {
      if (!this.activeChannel) return null;
      return this.connections.find(c => c.id === this.activeChannel.irc_connection_id);
    },
  },
  watch: {
    // Deep links (e.g. an event's location link) target /irc?channel=<id>.
    '$route.query.channel'() {
      this.openChannelFromRoute();
    },
  },
  mounted() {
    this.calculateHeight();
    window.addEventListener('resize', this.calculateHeight);
    this.fetchServers();
    this.fetchConnections().then(() => this.openChannelFromRoute());
    this.startEventPolling();
  },
  beforeUnmount() {
    if (this.eventPolling) {
      clearInterval(this.eventPolling);
    }
    window.removeEventListener('resize', this.calculateHeight);
  },
  methods: {
    calculateHeight() {
      this.$nextTick(() => {
        const el = this.$el;
        if (el) {
          const rect = el.getBoundingClientRect();
          this.containerHeight = `${window.innerHeight - rect.top}px`;
        }
      });
    },
    startEventPolling() {
      this.eventPolling = setInterval(() => this.pollEvents(), 3000);
    },
    async pollEvents() {
      try {
        const { data } = await axios.get('/api/irc/events');
        if (!data || !data.length) return;

        for (const event of data) {
          this.handleIrcEvent(event);
        }
      } catch {
        // Silently ignore polling errors
      }
    },
    handleIrcEvent(event) {
      const { type, connection_id, data } = event;

      switch (type) {
        case 'status': {
          // Update connection status
          const conn = this.connections.find(c => c.id === connection_id);
          if (conn) {
            conn.status = data.status;
            if (data.error) {
              this.addSystemMessage(`Connection error: ${data.error}`);
            }
          }
          if (data.status === 'connected') {
            this.fetchConnections();
          }
          break;
        }
        case 'message': {
          // New message from IRC
          if (this.activeChannel && this.activeChannel.id === data.channel_id) {
            // Avoid duplicates (from our own sent messages already in the list)
            const exists = this.messages.find(m => m.id === data.message.id);
            if (!exists) {
              this.messages.push(data.message);
              this.$nextTick(() => this.scrollToBottom());
            }
          }
          break;
        }
        case 'channel_update': {
          // Channel joined/parted/topic changed — refresh connections
          this.fetchConnections();
          if (this.activeChannel && this.activeChannel.id === data.channel?.id) {
            Object.assign(this.activeChannel, data.channel);
          }
          break;
        }
        case 'users_updated': {
          if (this.activeChannel && this.activeChannel.id === data.channel_id) {
            this.channelUsers = data.users || [];
          }
          break;
        }
        case 'notice': {
          this.addSystemMessage(`[${data.from}] ${data.message}`);
          break;
        }
        case 'server_message': {
          this.serverMessages.push(data.message);
          // Show as system message if no channel is active
          if (!this.activeChannel) {
            this.addSystemMessage(data.message);
          }
          break;
        }
      }
    },
    getChannels(connection) {
      return (connection.channels || []).filter(c => !c.is_private);
    },
    getPrivateChats(connection) {
      return (connection.channels || []).filter(c => c.is_private);
    },
    async fetchServers() {
      try {
        const { data } = await axios.get('/api/irc/servers');
        this.servers = data;
      } catch (error) {
        console.error('Error fetching servers:', error);
      }
    },
    async fetchConnections() {
      try {
        const { data } = await axios.get('/api/irc/connections');
        this.connections = data;
      } catch (error) {
        console.error('Error fetching connections:', error);
      }
    },
    async connect(connection) {
      try {
        await axios.post(`/api/irc/connections/${connection.id}/connect`);
        this.fetchConnections();
      } catch (error) {
        console.error('Error connecting:', error);
      }
    },
    async disconnect(connection) {
      try {
        await axios.post(`/api/irc/connections/${connection.id}/disconnect`);
        this.fetchConnections();
      } catch (error) {
        console.error('Error disconnecting:', error);
      }
    },
    showJoinDialog(connection) {
      this.joiningConnection = connection;
      this.showJoinChannelDialog = true;
    },
    editConnection(connection) {
      this.editingConnection = connection;
      this.showConnectionDialog = true;
    },
    async deleteConnection(connection) {
      if (!confirm(`Delete connection to ${connection.server.name}?`)) return;

      try {
        await axios.delete(`/api/irc/connections/${connection.id}`);
        this.fetchConnections();
        if (this.activeChannel?.irc_connection_id === connection.id) {
          this.activeChannel = null;
          this.messages = [];
        }
      } catch (error) {
        console.error('Error deleting connection:', error);
      }
    },
    async selectChannel(channel) {
      this.activeChannel = channel;
      await this.fetchMessages(channel);
      this.fetchChannelUsers(channel);
    },
    openChannelFromRoute() {
      const channelId = Number(this.$route?.query?.channel);
      if (!channelId || this.activeChannel?.id === channelId) return;
      for (const connection of this.connections) {
        const channel = (connection.channels || []).find(c => c.id === channelId);
        if (channel) {
          this.selectChannel(channel);
          return;
        }
      }
    },
    async fetchChannelUsers(channel) {
      try {
        const { data } = await axios.get(`/api/irc/channels/${channel.id}/users`);
        this.channelUsers = data;
      } catch {
        // Fallback: show current connection's nick as the only user
        const conn = this.connections.find(c => c.id === channel.irc_connection_id);
        this.channelUsers = conn ? [{ nickname: conn.nickname, isOnline: true, isOp: false }] : [];
      }
    },
    async fetchMessages(channel) {
      try {
        const { data } = await axios.get(`/api/irc/channels/${channel.id}/messages`);
        this.messages = data;
        this.$nextTick(() => this.scrollToBottom());
      } catch (error) {
        console.error('Error fetching messages:', error);
      }
    },
    async sendMessage() {
      if (!this.newMessage.trim()) return;

      // Handle slash commands
      if (this.newMessage.startsWith('/')) {
        await this.handleSlashCommand(this.newMessage);
        this.newMessage = '';
        return;
      }

      if (!this.activeChannel) return;

      try {
        const payload = {
          message: this.newMessage,
        };

        // Add comic chat metadata if in comic mode
        if (this.viewMode === 'comic') {
          payload.emotion = this.selectedEmotion;
          payload.gesture = this.selectedGesture;
          payload.bubble_type = this.getBubbleType();
        }

        await axios.post(`/api/irc/channels/${this.activeChannel.id}/messages`, payload);
        this.newMessage = '';
        await this.fetchMessages(this.activeChannel);
      } catch (error) {
        console.error('Error sending message:', error);
      }
    },
    async handleSlashCommand(input) {
      const parts = input.slice(1).split(/\s+/);
      const command = parts[0].toLowerCase();
      const args = parts.slice(1);

      switch (command) {
        case 'join': {
          if (!args[0]) {
            this.addSystemMessage('Usage: /join #channel');
            return;
          }
          const connection = this.currentConnection || this.connections[0];
          if (!connection) {
            this.addSystemMessage('No active connection. Connect to a server first.');
            return;
          }
          try {
            await axios.post(`/api/irc/connections/${connection.id}/join`, {
              channel: args[0],
            });
            await this.fetchConnections();
          } catch (error) {
            this.addSystemMessage(`Error joining channel: ${error.response?.data?.message || error.message}`);
          }
          break;
        }
        case 'part':
        case 'leave': {
          const channel = this.activeChannel;
          if (!channel) {
            this.addSystemMessage('No active channel to leave.');
            return;
          }
          await this.partChannel(channel);
          break;
        }
        case 'nick': {
          if (!args[0]) {
            this.addSystemMessage('Usage: /nick <new_nickname>');
            return;
          }
          const conn = this.currentConnection || this.connections[0];
          if (!conn) {
            this.addSystemMessage('No active connection.');
            return;
          }
          try {
            await axios.post(`/api/irc/connections/${conn.id}/nick`, {
              nickname: args[0],
            });
            this.addSystemMessage(`Changing nickname to ${args[0]}...`);
          } catch (error) {
            this.addSystemMessage(`Error changing nickname: ${error.response?.data?.message || error.message}`);
          }
          break;
        }
        case 'me': {
          if (!this.activeChannel) {
            this.addSystemMessage('No active channel.');
            return;
          }
          const actionText = args.join(' ');
          if (!actionText) {
            this.addSystemMessage('Usage: /me <action>');
            return;
          }
          try {
            await axios.post(`/api/irc/channels/${this.activeChannel.id}/messages`, {
              message: actionText,
              type: 'action',
            });
            await this.fetchMessages(this.activeChannel);
          } catch (error) {
            this.addSystemMessage(`Error: ${error.response?.data?.message || error.message}`);
          }
          break;
        }
        case 'connect': {
          const conn = this.currentConnection || this.connections[0];
          if (!conn) {
            this.addSystemMessage('No connection configured. Create one first.');
            return;
          }
          await this.connect(conn);
          break;
        }
        case 'disconnect':
        case 'quit': {
          const conn = this.currentConnection || this.connections[0];
          if (!conn) {
            this.addSystemMessage('No active connection.');
            return;
          }
          await this.disconnect(conn);
          break;
        }
        case 'msg':
        case 'query': {
          if (!args[0]) {
            this.addSystemMessage('Usage: /msg <nick> <message>');
            return;
          }
          const targetNick = args[0];
          const privMsg = args.slice(1).join(' ');
          const conn = this.currentConnection || this.connections[0];
          if (!conn) {
            this.addSystemMessage('No active connection.');
            return;
          }
          if (privMsg) {
            // Send a message to the user
            try {
              // Create or find the PM channel first
              await axios.post(`/api/irc/connections/${conn.id}/pm`, {
                nick: targetNick,
                message: privMsg,
              });
              await this.fetchConnections();
              this.addSystemMessage(`-> ${targetNick}: ${privMsg}`);
            } catch (error) {
              this.addSystemMessage(`Error: ${error.response?.data?.message || error.message}`);
            }
          } else {
            // Just open a query window (no message)
            try {
              await axios.post(`/api/irc/connections/${conn.id}/pm`, {
                nick: targetNick,
              });
              await this.fetchConnections();
            } catch (error) {
              this.addSystemMessage(`Error: ${error.response?.data?.message || error.message}`);
            }
          }
          break;
        }
        case 'help': {
          this.addSystemMessage('Available commands: /join #channel, /part, /nick <name>, /me <action>, /msg <nick> <message>, /query <nick>, /connect, /disconnect, /help');
          break;
        }
        default:
          this.addSystemMessage(`Unknown command: /${command}. Type /help for available commands.`);
      }
    },
    addSystemMessage(text) {
      this.messages.push({
        id: Date.now(),
        type: 'system',
        from_nick: '*',
        message: text,
        sent_at: new Date().toISOString(),
      });
      this.$nextTick(() => this.scrollToBottom());
    },
    getBubbleType() {
      if (this.selectedGesture === 'whisper') return 'whisper';
      if (this.selectedGesture === 'shout') return 'shout';
      if (this.selectedGesture === 'think') return 'thought';
      return 'speech';
    },
    onEmotionSelected(emotion) {
      this.selectedEmotion = emotion;
    },
    onGestureSelected(gesture) {
      this.selectedGesture = gesture;
    },
    async rejoinChannel(connection, channel) {
      try {
        await axios.post(`/api/irc/connections/${connection.id}/join`, {
          channel: channel.name,
        });
        this.addSystemMessage(`Rejoining ${channel.name}...`);
      } catch (error) {
        this.addSystemMessage(`Error rejoining: ${error.response?.data?.message || error.message}`);
      }
    },
    async partChannel(channel) {
      try {
        await axios.post(`/api/irc/channels/${channel.id}/part`);
        this.fetchConnections();
        if (this.activeChannel?.id === channel.id) {
          this.activeChannel = null;
          this.messages = [];
        }
      } catch (error) {
        console.error('Error parting channel:', error);
      }
    },
    async toggleFavorite(channel) {
      try {
        const { data } = await axios.post(`/api/irc/channels/${channel.id}/favorite`);
        channel.is_favorite = data.is_favorite;
      } catch (error) {
        console.error('Error toggling favorite:', error);
      }
    },
    onConnectionSaved() {
      this.showConnectionDialog = false;
      this.editingConnection = null;
      this.fetchConnections();
    },
    onChannelJoined() {
      this.showJoinChannelDialog = false;
      this.joiningConnection = null;
      this.fetchConnections();
    },
    scrollToBottom() {
      const container = this.$refs.messagesContainer;
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    },
    getStatusColor(status) {
      return {
        connected: 'success',
        connecting: 'warning',
        disconnected: 'grey',
      }[status] || 'grey';
    },
    getStatusIcon(status) {
      return {
        connected: 'mdi-check-circle',
        connecting: 'mdi-loading mdi-spin',
        disconnected: 'mdi-circle-outline',
      }[status] || 'mdi-circle-outline';
    },
    getMessageClass(message) {
      const classes = ['message'];
      if (message.is_mention) classes.push('mention');
      if (message.type === 'action') classes.push('action');
      else if (message.type === 'system') classes.push('system');
      return classes.join(' ');
    },
    getNickColor(nick) {
      // Simple hash-based color generation
      let hash = 0;
      for (let i = 0; i < nick.length; i++) {
        hash = nick.charCodeAt(i) + ((hash << 5) - hash);
      }
      const hue = hash % 360;
      return `hsl(${hue}, 70%, 50%)`;
    },
    formatTime(timestamp) {
      if (!timestamp) return '';
      const date = new Date(timestamp);
      if (isNaN(date.getTime())) return '';

      const dateStr = date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      });
      const timeStr = date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
      });

      return `${dateStr} ${timeStr}`;
    },
    showCharacterSelector(connection) {
      this.editingConnectionForCharacter = connection;
      this.showCharacterDialog = true;
    },
    async onCharacterSaved(data) {
      if (!this.editingConnectionForCharacter) return;
      try {
        await axios.patch(`/api/irc/connections/${this.editingConnectionForCharacter.id}`, {
          comic_character: data.character,
        });
        this.comicBackground = data.background;
        this.fetchConnections();
        this.editingConnectionForCharacter = null;
      } catch (error) {
        console.error("Error saving character:", error);
      }
    },
  },
};
</script>

<style scoped>
.irc-client {
  overflow: hidden;
}

.irc-row {
  height: 100%;
}

.sidebar {
  border-right: 1px solid rgba(0, 0, 0, 0.12);
  overflow-y: auto;
  height: 100%;
}

.chat-area {
  height: 100%;
  min-height: 0;
}

.users-sidebar {
  border-left: 1px solid rgba(0, 0, 0, 0.12);
  height: 100%;
  min-height: 0;
}

.messages-container {
  font-family: monospace;
  font-size: 14px;
}

.message {
  margin-bottom: 2px;
}

.message-line {
  padding: 2px 4px;
}

.message.mention {
  background-color: rgba(255, 193, 7, 0.1);
}

.message.action .message-line {
  background-color: rgba(156, 39, 176, 0.08);
  border-left: 3px solid #9c27b0;
  padding-left: 8px;
}

.message.action .action-message {
  color: #9c27b0;
  font-style: italic;
  font-weight: 500;
}

.message.action .nick-inline {
  font-weight: bold;
  font-style: normal;
}

.message.system .message-line {
  background-color: rgba(0, 0, 0, 0.03);
}

.message.system .system-text {
  color: #666;
  font-style: italic;
}

.timestamp {
  margin-right: 8px;
}

.nick {
  font-weight: bold;
  margin-right: 8px;
}

.system-message {
  color: #999;
  margin-right: 8px;
}

.user-prefix {
  color: #e53935;
  margin-right: 1px;
}
</style>
