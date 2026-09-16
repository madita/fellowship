<template>
  <v-container fluid class="irc-client pa-0 d-flex flex-column" :style="{ height: containerHeight }">
    <page-header
      :title="$t('irc.client.title')"
      :subtitle="$t('irc.client.subtitle')"
      icon="mdi-forum-outline"
      fluid
      class="irc-header mb-0 flex-shrink-0"
    >
      <template #actions>
        <v-btn color="primary" variant="elevated" prepend-icon="mdi-plus" @click="showConnectionDialog = true">
          {{ $t('irc.client.addConnection') }}
        </v-btn>
      </template>
    </page-header>

    <v-row no-gutters class="irc-row">
      <!-- Server/Channel Sidebar -->
      <v-col cols="12" md="3" class="sidebar">
        <v-card flat height="100%">
          <v-card-title class="text-subtitle-1 font-weight-medium d-flex justify-space-between align-center">
            {{ $t('irc.client.connections') }}
            <v-btn
              icon="mdi-plus"
              variant="text"
              size="small"
              :title="$t('irc.client.addConnection')"
              @click="showConnectionDialog = true"
            />
          </v-card-title>

          <v-divider />

          <!-- Connections List -->
          <v-list>
            <div v-for="connection in connections" :key="connection.id">
              <v-list-item
                :active="activeServerLog?.id === connection.id"
                @click="selectServerLog(connection)"
              >
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
                      <v-btn icon variant="text" size="small" v-bind="props" @click.stop>
                        <v-icon>mdi-dots-vertical</v-icon>
                      </v-btn>
                    </template>
                    <v-list>
                      <v-list-item
                        v-if="connection.status === 'disconnected'"
                        :disabled="busyConnectionIds.includes(connection.id)"
                        @click="connect(connection)"
                      >
                        <v-list-item-title>{{ $t('irc.client.connect') }}</v-list-item-title>
                      </v-list-item>
                      <v-list-item
                        v-else
                        :disabled="busyConnectionIds.includes(connection.id)"
                        @click="disconnect(connection)"
                      >
                        <v-list-item-title>{{ $t('irc.client.disconnect') }}</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="showJoinDialog(connection)">
                        <v-list-item-title>{{ $t('irc.client.joinChannel') }}</v-list-item-title>
                      </v-list-item>
                      <v-divider v-if="comicChatEnabled" />
                      <v-list-item v-if="comicChatEnabled" @click="showCharacterSelector(connection)">
                        <v-list-item-title>{{ $t('irc.client.chooseCharacter') }}</v-list-item-title>
                      </v-list-item>
                      <v-list-item @click="editConnection(connection)">
                        <v-list-item-title>{{ $t('common.edit') }}</v-list-item-title>
                      </v-list-item>
                      <v-list-item
                        :disabled="busyConnectionIds.includes(connection.id)"
                        @click="deleteConnection(connection)"
                      >
                        <v-list-item-title class="text-error">{{ $t('common.delete') }}</v-list-item-title>
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
                      {{ $t('irc.client.channels', { count: getChannels(connection).length }) }}
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
                    <v-icon size="small" :color="channel.is_joined ? 'success' : 'medium-emphasis'">
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
                      :loading="busyChannelIds.includes(channel.id)"
                      @click.stop="rejoinChannel(connection, channel)"
                    >
                      <v-icon size="small">mdi-login</v-icon>
                      <v-tooltip activator="parent" location="top">{{ $t('irc.client.rejoin') }}</v-tooltip>
                    </v-btn>
                    <v-btn
                      icon
                      size="x-small"
                      variant="text"
                      :loading="busyChannelIds.includes(channel.id)"
                      @click.stop="toggleFavorite(channel)"
                    >
                      <v-icon size="small" :color="channel.is_favorite ? 'warning' : 'medium-emphasis'">
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
                      {{ $t('irc.client.privateMessages', { count: getPrivateChats(connection).length }) }}
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

            <empty-state
              v-if="!connections.length"
              compact
              icon="mdi-server-network-off"
              :title="$t('irc.client.noConnections')"
              :text="$t('irc.client.noConnectionsText')"
            />
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
              <span v-if="activeChannel.topic" class="text-caption text-medium-emphasis ml-3">
                {{ activeChannel.topic }}
              </span>
            </div>
            <div class="d-flex ga-2 align-center">
              <!-- View Mode Toggle -->
              <v-btn-toggle v-if="comicChatEnabled" v-model="viewMode" mandatory density="compact">
                <v-btn value="classic" size="small">
                  <v-icon>mdi-format-align-left</v-icon>
                  <v-tooltip activator="parent" location="bottom">
                    {{ $t('irc.client.classicView') }}
                  </v-tooltip>
                </v-btn>
                <v-btn value="comic" size="small">
                  <v-icon>mdi-book-open-variant</v-icon>
                  <v-tooltip activator="parent" location="bottom">
                    {{ $t('irc.client.comicView') }}
                  </v-tooltip>
                </v-btn>
              </v-btn-toggle>

              <!-- Users Toggle -->
              <v-btn icon variant="text" size="small" :color="showUserList ? 'primary' : undefined" @click="toggleUserList()">
                <v-icon>mdi-account-group</v-icon>
                <v-tooltip activator="parent" location="bottom">
                  {{ showUserList ? $t('irc.client.hideUsers') : $t('irc.client.showUsers') }}
                </v-tooltip>
              </v-btn>

              <v-btn
                icon
                variant="text"
                size="small"
                :loading="busyChannelIds.includes(activeChannel.id)"
                @click="partChannel(activeChannel)"
              >
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </div>
          </v-card-title>

          <v-divider />

          <!-- Classic IRC View -->
          <v-card-text
            v-if="!isComicMode"
            ref="messagesContainer"
            class="messages-container flex-grow-1"
            style="overflow-y: auto; min-height: 0;"
          >
            <div v-for="message in messages" :key="message.id" :class="getMessageClass(message)">
              <div class="message-line">
                <span class="timestamp text-caption text-medium-emphasis">
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
                  <!-- Comic-mode gestures/emotions also show up in the text view -->
                  <span
                    v-if="comicChatEnabled && message.gesture && message.gesture !== 'none'"
                    class="gesture-note"
                  >* {{ message.from_nick }} {{ getGestureVerb(message.gesture) }} {{ getGestureEmoji(message.gesture) }}</span>
                  <span
                    v-if="comicChatEnabled && message.emotion && message.emotion !== 'normal'"
                    class="emotion-note"
                    :title="message.emotion"
                  >{{ getEmotionEmoji(message.emotion) }}</span>
                </template>
              </div>
            </div>

            <empty-state
              v-if="!messages.length"
              compact
              icon="mdi-message-text-outline"
              :title="$t('irc.client.noMessages')"
            />
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
              <v-col v-if="isComicMode" cols="12">
                <div class="d-flex ga-2 flex-wrap">
                  <v-chip-group v-model="selectedEmotion" mandatory>
                    <v-chip size="small" variant="tonal" value="normal">{{ $t('irc.client.emotions.normal') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="happy">{{ $t('irc.client.emotions.happy') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="sad">{{ $t('irc.client.emotions.sad') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="angry">{{ $t('irc.client.emotions.angry') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="surprised">{{ $t('irc.client.emotions.surprised') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="confused">{{ $t('irc.client.emotions.confused') }}</v-chip>
                  </v-chip-group>
                  <v-divider vertical />
                  <v-chip-group v-model="selectedGesture">
                    <v-chip size="small" variant="tonal" value="none">{{ $t('irc.client.gestures.none') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="wave">{{ $t('irc.client.gestures.wave') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="laugh">{{ $t('irc.client.gestures.laugh') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="think">{{ $t('irc.client.gestures.think') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="shout">{{ $t('irc.client.gestures.shout') }}</v-chip>
                    <v-chip size="small" variant="tonal" value="whisper">{{ $t('irc.client.gestures.whisper') }}</v-chip>
                  </v-chip-group>
                </div>
              </v-col>

              <!-- Message Input -->
              <v-col cols="12">
                <v-text-field
                  v-model="newMessage"
                  :placeholder="$t('irc.client.messagePlaceholder')"
                  variant="outlined"
                  density="compact"
                  hide-details
                  @keydown.enter="sendMessage"
                  autofocus
                >
                  <template #append-inner>
                    <v-btn
                      icon
                      variant="text"
                      size="small"
                      color="primary"
                      :disabled="!newMessage.trim()"
                      :loading="sending"
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

        <!-- Server Console (connection log) -->
        <v-card v-else-if="activeServerLog" flat class="d-flex flex-column flex-grow-1" style="min-height: 0;">
          <v-card-title class="d-flex justify-space-between align-center flex-shrink-0">
            <div class="d-flex align-center">
              <v-icon :color="getStatusColor(activeServerLog.status)" class="mr-2">
                {{ getStatusIcon(activeServerLog.status) }}
              </v-icon>
              <span class="text-h6">{{ activeServerLog.server.name }}</span>
              <span class="text-caption text-medium-emphasis ml-3">
                {{ activeServerLog.server.host }}:{{ activeServerLog.server.port }} · {{ activeServerLog.status }}
              </span>
            </div>
            <v-btn
              v-if="activeServerLog.status === 'disconnected'"
              size="small"
              color="primary"
              variant="tonal"
              prepend-icon="mdi-power"
              :loading="busyConnectionIds.includes(activeServerLog.id)"
              @click="connect(activeServerLog)"
            >
              {{ $t('irc.client.connect') }}
            </v-btn>
            <v-btn
              v-else
              size="small"
              variant="tonal"
              prepend-icon="mdi-power-off"
              :loading="busyConnectionIds.includes(activeServerLog.id)"
              @click="disconnect(activeServerLog)"
            >
              {{ $t('irc.client.disconnect') }}
            </v-btn>
          </v-card-title>

          <v-divider />

          <v-card-text
            ref="serverLogContainer"
            class="messages-container server-log flex-grow-1"
            style="overflow-y: auto; min-height: 0;"
          >
            <div v-for="entry in currentServerLog" :key="entry.id" class="message">
              <div class="message-line">
                <span class="timestamp text-caption text-medium-emphasis">{{ formatTime(entry.sent_at) }}</span>
                <span class="system-message">***</span>
                <span class="message-text system-text">{{ entry.message }}</span>
              </div>
            </div>
            <empty-state
              v-if="!currentServerLog.length"
              compact
              icon="mdi-console"
              :title="$t('irc.client.noServerMessages')"
            />
          </v-card-text>

          <v-divider />
          <v-card-actions class="pa-2 flex-shrink-0">
            <v-text-field
              v-model="newMessage"
              :placeholder="$t('irc.client.consolePlaceholder')"
              variant="outlined"
              density="compact"
              hide-details
              @keydown.enter="sendMessage"
            >
              <template #append-inner>
                <v-btn
                  icon
                  variant="text"
                  size="small"
                  color="primary"
                  :disabled="!newMessage.trim()"
                  :loading="sending"
                  @click="sendMessage"
                >
                  <v-icon>mdi-send</v-icon>
                </v-btn>
              </template>
            </v-text-field>
          </v-card-actions>
        </v-card>

        <!-- Nothing Selected -->
        <v-card v-else flat class="d-flex align-center justify-center flex-grow-1">
          <empty-state
            icon="mdi-chat-outline"
            :title="$t('irc.client.selectChannel')"
            :text="$t('irc.client.selectChannelText')"
          />
        </v-card>
      </v-col>

      <!-- Online Users Sidebar -->
      <v-col v-if="activeChannel && showUserList" cols="12" md="3" class="users-sidebar d-flex flex-column">
        <v-card flat class="d-flex flex-column flex-grow-1" style="min-height: 0;">
          <v-card-title class="d-flex justify-space-between align-center flex-shrink-0 text-subtitle-1 font-weight-medium">
            <span>
              <v-icon size="small" class="mr-1">mdi-account-group</v-icon>
              {{ $t('irc.client.users', { count: channelUsers.length }) }}
            </span>
            <v-btn icon variant="text" size="x-small" @click="toggleUserList()">
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
                <v-icon size="small" :color="user.isOnline ? 'success' : 'medium-emphasis'">
                  mdi-circle
                </v-icon>
              </template>
              <v-list-item-title class="text-body-2">
                <span v-if="user.prefix" class="font-weight-bold user-prefix">{{ user.prefix }}</span>
                {{ user.nickname }}
              </v-list-item-title>
            </v-list-item>
            <v-list-item v-if="!channelUsers.length">
              <v-list-item-title class="text-caption text-medium-emphasis text-center">
                {{ $t('irc.client.noUsers') }}
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
import { useSettingsStore } from '@/store/settingStore.js';
import IrcConnectionDialog from '@/components/irc/IrcConnectionDialog.vue';
import IrcJoinDialog from '@/components/irc/IrcJoinDialog.vue';
import ComicChatView from '@/components/irc/ComicChatView.vue';
import ComicCharacterSelector from '@/components/irc/ComicCharacterSelector.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';

export default {
  name: 'IrcClient',
  components: {
    IrcConnectionDialog,
    IrcJoinDialog,
    ComicChatView,
    ComicCharacterSelector,
    PageHeader,
    EmptyState,
  },
  data() {
    return {
      connections: [],
      servers: [],
      activeChannel: null,
      activeServerLog: null,
      messages: [],
      newMessage: '',
      sending: false,
      // Ids with a request in flight (connect/disconnect/delete, part/rejoin/favorite)
      busyConnectionIds: [],
      busyChannelIds: [],
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
      comicBackground: localStorage.getItem('irc:comicBackground') || 'room',
      // Visible by default; the user hides it explicitly and the choice sticks.
      showUserList: localStorage.getItem('irc:showUserList') !== '0',
      channelUsers: [],
      containerHeight: '100%',
      // Per-connection console log: status changes, notices, raw server lines.
      serverLogs: {},
      serverLogId: 0,
      layoutObserver: null,
    };
  },
  computed: {
    // Comic chat can be turned off in Admin → Settings → IRC → Client.
    comicChatEnabled() {
      return useSettingsStore().ircComicChatEnabled;
    },
    isComicMode() {
      return this.comicChatEnabled && this.viewMode === 'comic';
    },
    currentConnection() {
      if (this.activeChannel) {
        return this.connections.find(c => c.id === this.activeChannel.irc_connection_id);
      }
      return this.activeServerLog;
    },
    currentServerLog() {
      return this.activeServerLog ? (this.serverLogs[this.activeServerLog.id] || []) : [];
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
    // The app bar/fonts settle after mount and shift our top offset — a
    // one-shot measure leaves the client taller than the viewport, pushing
    // the send input below the fold. Re-measure when the layout changes.
    if (typeof ResizeObserver !== 'undefined') {
      this.layoutObserver = new ResizeObserver(() => this.calculateHeight());
      this.layoutObserver.observe(document.body);
    } else {
      setTimeout(() => this.calculateHeight(), 300);
    }
    this.fetchServers();
    this.fetchConnections().then(() => this.openChannelFromRoute());
    this.startEventPolling();
  },
  beforeUnmount() {
    if (this.eventPolling) {
      clearInterval(this.eventPolling);
    }
    window.removeEventListener('resize', this.calculateHeight);
    if (this.layoutObserver) {
      this.layoutObserver.disconnect();
    }
  },
  methods: {
    calculateHeight() {
      this.$nextTick(() => {
        const el = this.$el;
        if (!el) return;
        // Measure the top offset as if scrolled to the top, so a scrolled
        // page doesn't yield an oversized (or looping) height.
        const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
        const top = el.getBoundingClientRect().top + scrollTop;
        // The app footer is fixed at the viewport bottom — without
        // subtracting it, the client is one footer-height too tall and the
        // send input hides behind it.
        const footer = document.querySelector('.v-footer');
        const footerHeight = footer ? footer.offsetHeight : 0;
        const next = `${Math.max(320, window.innerHeight - top - footerHeight)}px`;
        if (next !== this.containerHeight) {
          this.containerHeight = next;
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
          }
          this.logServer(connection_id, `Status: ${data.status}`);
          if (data.error) {
            this.logServer(connection_id, `Connection error: ${data.error}`);
            this.addSystemMessage(`Connection error: ${data.error}`);
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
          this.logServer(connection_id, `[${data.from}] ${data.message}`);
          if (this.activeChannel) {
            this.addSystemMessage(`[${data.from}] ${data.message}`);
          }
          break;
        }
        case 'server_message': {
          // Persist in the connection's console log instead of flashing it
          // into the channel message list where the next fetch erases it.
          this.logServer(connection_id, data.message);
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
    logServer(connectionId, text) {
      if (!connectionId) return;
      const log = this.serverLogs[connectionId] || (this.serverLogs[connectionId] = []);
      log.push({
        id: ++this.serverLogId,
        message: text,
        sent_at: new Date().toISOString(),
      });
      if (log.length > 500) log.splice(0, log.length - 500);
      if (this.activeServerLog?.id === connectionId) {
        this.$nextTick(() => this.scrollToBottom());
      }
    },
    selectServerLog(connection) {
      this.activeServerLog = connection;
      this.activeChannel = null;
      this.messages = [];
      this.$nextTick(() => this.scrollToBottom());
    },
    toggleUserList() {
      this.showUserList = !this.showUserList;
      localStorage.setItem('irc:showUserList', this.showUserList ? '1' : '0');
    },
    setConnectionBusy(id, busy) {
      this.busyConnectionIds = busy
        ? [...this.busyConnectionIds, id]
        : this.busyConnectionIds.filter(i => i !== id);
    },
    setChannelBusy(id, busy) {
      this.busyChannelIds = busy
        ? [...this.busyChannelIds, id]
        : this.busyChannelIds.filter(i => i !== id);
    },
    async connect(connection) {
      if (this.busyConnectionIds.includes(connection.id)) return;

      this.setConnectionBusy(connection.id, true);
      try {
        this.logServer(connection.id, `Connecting to ${connection.server?.host}:${connection.server?.port}...`);
        const { data } = await axios.post(`/api/irc/connections/${connection.id}/connect`);
        if (data?.message) this.logServer(connection.id, data.message);
        this.fetchConnections();
      } catch (error) {
        this.logServer(connection.id, `Error connecting: ${error.response?.data?.message || error.message}`);
        console.error('Error connecting:', error);
      } finally {
        this.setConnectionBusy(connection.id, false);
      }
    },
    async disconnect(connection) {
      if (this.busyConnectionIds.includes(connection.id)) return;

      this.setConnectionBusy(connection.id, true);
      try {
        this.logServer(connection.id, 'Disconnecting...');
        const { data } = await axios.post(`/api/irc/connections/${connection.id}/disconnect`);
        if (data?.message) this.logServer(connection.id, data.message);
        this.fetchConnections();
      } catch (error) {
        this.logServer(connection.id, `Error disconnecting: ${error.response?.data?.message || error.message}`);
        console.error('Error disconnecting:', error);
      } finally {
        this.setConnectionBusy(connection.id, false);
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
      if (this.busyConnectionIds.includes(connection.id)) return;

      const confirmed = await this.$dialog.confirmDelete(
        this.$t('irc.client.deleteConnectionConfirm', { server: connection.server.name }),
        { title: this.$t('irc.client.deleteConnectionTitle') }
      );
      if (!confirmed) return;

      this.setConnectionBusy(connection.id, true);
      try {
        await axios.delete(`/api/irc/connections/${connection.id}`);
        this.fetchConnections();
        if (this.activeChannel?.irc_connection_id === connection.id) {
          this.activeChannel = null;
          this.messages = [];
        }
        if (this.activeServerLog?.id === connection.id) {
          this.activeServerLog = null;
        }
      } catch (error) {
        console.error('Error deleting connection:', error);
        await this.$dialog.requestError(error, this.$t('irc.client.deleteConnectionFailed'));
      } finally {
        this.setConnectionBusy(connection.id, false);
      }
    },
    async selectChannel(channel) {
      this.activeChannel = channel;
      this.activeServerLog = null;
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
      if (!this.newMessage.trim() || this.sending) return;

      this.sending = true;
      try {
        // Handle slash commands
        if (this.newMessage.startsWith('/')) {
          await this.handleSlashCommand(this.newMessage);
          this.newMessage = '';
          return;
        }

        if (!this.activeChannel) {
          if (this.activeServerLog) {
            this.logServer(this.activeServerLog.id, 'The console only takes commands — join a channel to chat (/join #channel).');
            this.newMessage = '';
          }
          return;
        }

        try {
          const payload = {
            message: this.newMessage,
          };

          // Add comic chat metadata if in comic mode
          if (this.isComicMode) {
            payload.emotion = this.selectedEmotion;
            payload.gesture = this.selectedGesture;
            payload.bubble_type = this.getBubbleType();
          }

          await axios.post(`/api/irc/channels/${this.activeChannel.id}/messages`, payload);
          this.newMessage = '';
          await this.fetchMessages(this.activeChannel);
        } catch (error) {
          console.error('Error sending message:', error);
          await this.$dialog.requestError(error, this.$t('irc.client.sendFailed'));
        }
      } finally {
        this.sending = false;
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
          // Typing /part is explicit enough — no extra confirmation
          await this.partChannel(channel, { confirm: false });
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
      // In the server console, feedback belongs in the persistent log —
      // the channel message list is not rendered there.
      if (!this.activeChannel && this.activeServerLog) {
        this.logServer(this.activeServerLog.id, text);
        return;
      }
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
      if (this.busyChannelIds.includes(channel.id)) return;

      this.setChannelBusy(channel.id, true);
      try {
        await axios.post(`/api/irc/connections/${connection.id}/join`, {
          channel: channel.name,
        });
        this.addSystemMessage(`Rejoining ${channel.name}...`);
      } catch (error) {
        this.addSystemMessage(`Error rejoining: ${error.response?.data?.message || error.message}`);
      } finally {
        this.setChannelBusy(channel.id, false);
      }
    },
    async partChannel(channel, { confirm = true } = {}) {
      if (this.busyChannelIds.includes(channel.id)) return;

      if (confirm) {
        const confirmed = await this.$dialog.confirm({
          title: this.$t('irc.client.leaveChannelTitle'),
          content: this.$t('irc.client.leaveChannelConfirm', { channel: channel.name }),
          confirmationText: this.$t('irc.client.leaveChannel'),
          color: 'warning',
        });
        if (!confirmed) return;
      }

      this.setChannelBusy(channel.id, true);
      try {
        await axios.post(`/api/irc/channels/${channel.id}/part`);
        this.fetchConnections();
        if (this.activeChannel?.id === channel.id) {
          this.activeChannel = null;
          this.messages = [];
        }
      } catch (error) {
        console.error('Error parting channel:', error);
        await this.$dialog.requestError(error, this.$t('irc.client.leaveChannelFailed'));
      } finally {
        this.setChannelBusy(channel.id, false);
      }
    },
    async toggleFavorite(channel) {
      if (this.busyChannelIds.includes(channel.id)) return;

      this.setChannelBusy(channel.id, true);
      try {
        const { data } = await axios.post(`/api/irc/channels/${channel.id}/favorite`);
        channel.is_favorite = data.is_favorite;
      } catch (error) {
        console.error('Error toggling favorite:', error);
        await this.$dialog.requestError(error, this.$t('irc.client.favoriteFailed'));
      } finally {
        this.setChannelBusy(channel.id, false);
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
      // Refs on Vuetify components resolve to component instances — scroll
      // their root element, not the instance (a no-op on the proxy).
      const ref = this.activeServerLog ? this.$refs.serverLogContainer : this.$refs.messagesContainer;
      const el = ref?.$el ?? ref;
      if (el) {
        el.scrollTop = el.scrollHeight;
      }
    },
    getStatusColor(status) {
      return {
        connected: 'success',
        connecting: 'warning',
        disconnected: 'medium-emphasis',
      }[status] || 'medium-emphasis';
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
    getGestureVerb(gesture) {
      return {
        wave: 'waves',
        laugh: 'laughs',
        think: 'thinks',
        shout: 'shouts',
        whisper: 'whispers',
      }[gesture] || gesture;
    },
    getGestureEmoji(gesture) {
      return { wave: '👋', laugh: '😂', think: '💭', shout: '📢', whisper: '🤫' }[gesture] || '';
    },
    getEmotionEmoji(emotion) {
      return {
        happy: '😊',
        sad: '😢',
        angry: '😠',
        surprised: '😲',
        confused: '😕',
        excited: '🤩',
      }[emotion] || '';
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
        localStorage.setItem('irc:comicBackground', data.background);
        await this.fetchConnections();
        this.editingConnectionForCharacter = null;
      } catch (error) {
        console.error("Error saving character:", error);
        await this.$dialog.requestError(error, this.$t('irc.client.characterSaveFailed'));
      }
    },
  },
};
</script>

<style scoped>
.irc-client {
  overflow: hidden;
}

.irc-header {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.irc-row {
  flex: 1 1 auto;
  min-height: 0;
}

.sidebar {
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  overflow-y: auto;
  height: 100%;
}

.chat-area {
  height: 100%;
  min-height: 0;
}

.users-sidebar {
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
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
  background-color: rgba(var(--v-theme-warning), 0.12);
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
  background-color: rgba(var(--v-theme-on-surface), 0.03);
}

.message.system .system-text {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
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
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  margin-right: 8px;
}

.user-prefix {
  color: rgb(var(--v-theme-error));
  margin-right: 1px;
}

.gesture-note {
  color: #9c27b0;
  font-style: italic;
  margin-left: 8px;
}

.emotion-note {
  margin-left: 6px;
}
</style>
