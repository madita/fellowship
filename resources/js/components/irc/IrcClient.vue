<template>
  <v-container fluid class="irc-client pa-0">
    <v-row no-gutters style="height: 100vh;">
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
              <v-list-group v-if="connection.channels.length">
                <template #activator="{ props }">
                  <v-list-item v-bind="props" density="compact" class="pl-8">
                    <v-list-item-title class="text-caption">
                      Channels ({{ connection.channels.length }})
                    </v-list-item-title>
                  </v-list-item>
                </template>

                <v-list-item
                  v-for="channel in connection.channels"
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
            </div>

            <v-list-item v-if="!connections.length" class="text-center text-grey">
              <v-list-item-title>No connections</v-list-item-title>
              <v-list-item-subtitle>Click + to add one</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>

      <!-- Chat Area -->
      <v-col cols="12" md="9" class="chat-area">
        <v-card v-if="activeChannel" flat height="100%" class="d-flex flex-column">
          <!-- Channel Header -->
          <v-card-title class="d-flex justify-space-between align-center">
            <div>
              <span class="text-h6">{{ activeChannel.name }}</span>
              <span v-if="activeChannel.topic" class="text-caption text-grey ml-3">
                {{ activeChannel.topic }}
              </span>
            </div>
            <div class="d-flex gap-2">
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

              <v-btn icon @click="partChannel(activeChannel)">
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
            style="overflow-y: auto;"
          >
            <div v-for="message in messages" :key="message.id" :class="getMessageClass(message)">
              <div class="message-line">
                <span class="timestamp text-caption text-grey">
                  {{ formatTime(message.sent_at) }}
                </span>
                <span v-if="message.type === 'message'" class="nick" :style="{ color: getNickColor(message.from_nick) }">
                  &lt;{{ message.from_nick }}&gt;
                </span>
                <span v-else class="system-message">
                  ***
                </span>
                <span class="message-text">{{ message.message }}</span>
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
            :background="comicBackground"
            :show-timestamps="true"
            :show-emotion-bar="true"
            @emotion-selected="onEmotionSelected"
            @gesture-selected="onGestureSelected"
            class="flex-grow-1"
          />

          <!-- Message Input -->
          <v-divider />
          <v-card-actions class="pa-2">
            <v-row dense>
              <!-- Emotion/Gesture Bar (Comic Mode Only) -->
              <v-col v-if="viewMode === 'comic'" cols="12">
                <div class="d-flex gap-2 flex-wrap">
                  <v-chip-group v-model="selectedEmotion" mandatory>
                    <v-chip size="small" value="normal">😐 Normal</v-chip>
                    <v-chip size="small" value="happy">😊 Happy</v-chip>
                    <v-chip size="small" value="sad">😢 Sad</v-chip>
                    <v-chip size="small" value="angry">😠 Angry</v-chip>
                    <v-chip size="small" value="surprised">😲 Surprised</v-chip>
                    <v-chip size="small" value="confused">😕 Confused</v-chip>
                  </v-chip-group>
                  <v-divider vertical />
                  <v-chip-group v-model="selectedGesture">
                    <v-chip size="small" value="none">None</v-chip>
                    <v-chip size="small" value="wave">👋 Wave</v-chip>
                    <v-chip size="small" value="laugh">😂 Laugh</v-chip>
                    <v-chip size="small" value="think">💭 Think</v-chip>
                    <v-chip size="small" value="shout">📢 Shout</v-chip>
                    <v-chip size="small" value="whisper">🤫 Whisper</v-chip>
                  </v-chip-group>
                </div>
              </v-col>

              <!-- Message Input -->
              <v-col cols="12">
                <v-text-field
                  v-model="newMessage"
                  placeholder="Type a message..."
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
        <v-card v-else flat height="100%" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="64" color="grey-lighten-1">mdi-chat-outline</v-icon>
            <p class="text-h6 mt-4">Select a channel to start chatting</p>
          </div>
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
      messagePolling: null,
      viewMode: 'classic', // classic or comic
      selectedEmotion: 'normal',
      selectedGesture: 'none',
      comicBackground: 'room',
    };
  },
  computed: {
    currentConnection() {
      if (!this.activeChannel) return null;
      return this.connections.find(c => c.id === this.activeChannel.irc_connection_id);
    },
  },
  mounted() {
    this.fetchServers();
    this.fetchConnections();
  },
  beforeUnmount() {
    if (this.messagePolling) {
      clearInterval(this.messagePolling);
    }
  },
  methods: {
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
      this.startMessagePolling();
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
      if (!this.newMessage.trim() || !this.activeChannel) return;

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
    startMessagePolling() {
      if (this.messagePolling) {
        clearInterval(this.messagePolling);
      }
      this.messagePolling = setInterval(() => {
        if (this.activeChannel) {
          this.fetchMessages(this.activeChannel);
        }
      }, 3000); // Poll every 3 seconds
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
      if (message.type !== 'message') classes.push('system');
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
      return new Date(timestamp).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
      });
    },
  },
};
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
</script>

<style scoped>
.irc-client {
  height: 100vh;
  max-height: 100vh;
}

.sidebar {
  border-right: 1px solid rgba(0, 0, 0, 0.12);
  overflow-y: auto;
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

.message.system .message-text {
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
</style>
