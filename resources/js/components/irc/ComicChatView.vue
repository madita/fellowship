<template>
  <div class="comic-chat-view" :style="{ backgroundImage: `url(${backgroundImage})` }">
    <!-- Comic Panels Container -->
    <div ref="comicContainer" class="comic-panels-container">
      <!-- Group messages into panels (3-4 messages per panel) -->
      <div
        v-for="(panel, panelIndex) in comicPanels"
        :key="panelIndex"
        class="comic-panel"
      >
        <!-- Panel Background -->
        <div class="panel-background">
          <!-- Messages in this panel -->
          <div
            v-for="(message, msgIndex) in panel"
            :key="message.id"
            :class="getMessagePositionClass(msgIndex, panel.length)"
          >
            <!-- Character Avatar -->
            <div class="character-container">
              <img
                :src="getCharacterImage(message.from_nick, message.emotion)"
                :alt="message.from_nick"
                :class="['character-avatar', getEmotionClass(message.emotion)]"
              />
              <div class="character-name">{{ message.from_nick }}</div>
            </div>

            <!-- Speech Bubble -->
            <div :class="['speech-bubble', getBubbleClass(message.bubble_type)]">
              <div class="bubble-tail" />
              <div class="bubble-content">
                <span v-if="showTimestamps" class="bubble-timestamp">
                  {{ formatTime(message.sent_at) }}
                </span>
                <span class="bubble-text">{{ message.message }}</span>
                <span v-if="message.gesture !== 'none'" class="gesture-indicator">
                  {{ getGestureEmoji(message.gesture) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel Border -->
        <div class="panel-border" />
      </div>

      <!-- Empty State -->
      <div v-if="!comicPanels.length" class="empty-comic">
        <img src="/images/comic/welcome.svg" alt="Welcome" class="welcome-character" />
        <div class="speech-bubble speech">
          <div class="bubble-content">
            Welcome to Comic Chat! Start chatting to see the magic! 🎨
          </div>
        </div>
      </div>
    </div>

    <!-- Emotion/Gesture Selector (when composing) -->
    <div v-if="showEmotionBar" class="emotion-bar">
      <div class="emotion-selector">
        <v-btn-toggle v-model="selectedEmotion" mandatory>
          <v-btn value="normal" size="small">
            😐 Normal
          </v-btn>
          <v-btn value="happy" size="small">
            😊 Happy
          </v-btn>
          <v-btn value="sad" size="small">
            😢 Sad
          </v-btn>
          <v-btn value="angry" size="small">
            😠 Angry
          </v-btn>
          <v-btn value="surprised" size="small">
            😲 Surprised
          </v-btn>
          <v-btn value="confused" size="small">
            😕 Confused
          </v-btn>
          <v-btn value="excited" size="small">
            🤩 Excited
          </v-btn>
        </v-btn-toggle>
      </div>

      <div class="gesture-selector">
        <v-btn-toggle v-model="selectedGesture">
          <v-btn value="none" size="small">
            None
          </v-btn>
          <v-btn value="wave" size="small">
            👋 Wave
          </v-btn>
          <v-btn value="laugh" size="small">
            😂 Laugh
          </v-btn>
          <v-btn value="think" size="small">
            🤔 Think
          </v-btn>
          <v-btn value="shout" size="small">
            📢 Shout
          </v-btn>
          <v-btn value="whisper" size="small">
            🤫 Whisper
          </v-btn>
        </v-btn-toggle>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ComicChatView',
  props: {
    messages: {
      type: Array,
      default: () => [],
    },
    character: {
      type: String,
      default: 'cat',
    },
    background: {
      type: String,
      default: 'room',
    },
    showTimestamps: {
      type: Boolean,
      default: true,
    },
    showEmotionBar: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      selectedEmotion: 'normal',
      selectedGesture: 'none',
      messagesPerPanel: 3,
    };
  },
  computed: {
    comicPanels() {
      // Group messages into panels of 3-4 messages each
      const panels = [];
      const msgs = [...this.messages];

      while (msgs.length > 0) {
        const panelSize = Math.min(this.messagesPerPanel, msgs.length);
        panels.push(msgs.splice(0, panelSize));
      }

      return panels;
    },
    backgroundImage() {
      return `/images/comic/backgrounds/${this.background}.jpg`;
    },
  },
  watch: {
    messages() {
      this.$nextTick(() => this.scrollToBottom());
    },
  },
  methods: {
    getCharacterImage(nick, emotion = 'normal') {
      // Get character based on nickname hash
      const characters = ['cat', 'dog', 'robot', 'alien', 'wizard', 'ninja', 'pirate', 'knight'];
      const charIndex = this.hashCode(nick) % characters.length;
      const character = characters[charIndex];

      // Return character image with emotion
      return `/images/comic/characters/${character}-${emotion}.svg`;
    },
    getEmotionClass(emotion) {
      return `emotion-${emotion}`;
    },
    getBubbleClass(bubbleType) {
      return bubbleType || 'speech';
    },
    getMessagePositionClass(index, total) {
      const positions = ['message-position-left', 'message-position-center', 'message-position-right'];
      return `comic-message ${positions[index % positions.length]}`;
    },
    getGestureEmoji(gesture) {
      const emojis = {
        wave: '👋',
        laugh: '😂',
        think: '🤔',
        shout: '📢',
        whisper: '🤫',
      };
      return emojis[gesture] || '';
    },
    hashCode(str) {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
      }
      return Math.abs(hash);
    },
    formatTime(timestamp) {
      return new Date(timestamp).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    scrollToBottom() {
      const container = this.$refs.comicContainer;
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    },
    getCurrentEmotion() {
      return this.selectedEmotion;
    },
    getCurrentGesture() {
      return this.selectedGesture;
    },
    getBubbleType() {
      // Map gesture to bubble type
      if (this.selectedGesture === 'whisper') return 'whisper';
      if (this.selectedGesture === 'shout') return 'shout';
      if (this.selectedGesture === 'think') return 'thought';
      return 'speech';
    },
  },
};
</script>

<style scoped>
.comic-chat-view {
  height: 100%;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-color: #f5f5dc;
  display: flex;
  flex-direction: column;
}

.comic-panels-container {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.comic-panel {
  position: relative;
  background: white;
  border: 3px solid #000;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.2);
  min-height: 200px;
}

.panel-background {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.comic-message {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  position: relative;
}

.message-position-left {
  justify-content: flex-start;
}

.message-position-center {
  justify-content: center;
}

.message-position-right {
  justify-content: flex-end;
  flex-direction: row-reverse;
}

.character-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 80px;
}

.character-avatar {
  width: 80px;
  height: 120px;
  object-fit: contain;
  filter: drop-shadow(2px 2px 2px rgba(0, 0, 0, 0.3));
}

.character-name {
  font-family: 'Comic Sans MS', cursive, sans-serif;
  font-size: 12px;
  font-weight: bold;
  margin-top: 5px;
  text-align: center;
}

.speech-bubble {
  position: relative;
  background: white;
  border: 2px solid #000;
  border-radius: 20px;
  padding: 12px 16px;
  max-width: 400px;
  font-family: 'Comic Sans MS', cursive, sans-serif;
  box-shadow: 2px 2px 0 rgba(0, 0, 0, 0.2);
}

.speech-bubble.thought {
  border-radius: 50%;
  padding: 20px;
}

.speech-bubble.shout {
  background: #ffe6e6;
  border-width: 3px;
  font-size: 1.2em;
  font-weight: bold;
}

.speech-bubble.whisper {
  background: #f0f0f0;
  border-style: dashed;
  font-size: 0.9em;
  font-style: italic;
}

.bubble-tail {
  position: absolute;
  width: 0;
  height: 0;
  border-style: solid;
}

.message-position-left .bubble-tail {
  left: -10px;
  top: 20px;
  border-width: 10px 10px 10px 0;
  border-color: transparent #000 transparent transparent;
}

.message-position-right .bubble-tail {
  right: -10px;
  top: 20px;
  border-width: 10px 0 10px 10px;
  border-color: transparent transparent transparent #000;
}

.bubble-content {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.bubble-timestamp {
  font-size: 10px;
  color: #666;
}

.bubble-text {
  font-size: 14px;
  line-height: 1.4;
}

.gesture-indicator {
  font-size: 20px;
  text-align: right;
}

.empty-comic {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 400px;
  gap: 20px;
}

.welcome-character {
  width: 150px;
  height: 200px;
}

.emotion-bar {
  background: white;
  border-top: 2px solid #000;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-family: 'Comic Sans MS', cursive, sans-serif;
}

.emotion-selector,
.gesture-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

/* Emotion animations */
.emotion-happy {
  animation: bounce 0.5s ease-in-out;
}

.emotion-sad {
  filter: brightness(0.8);
}

.emotion-angry {
  animation: shake 0.3s ease-in-out;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-5px); }
  75% { transform: translateX(5px); }
}

/* Scrollbar styling */
.comic-panels-container::-webkit-scrollbar {
  width: 10px;
}

.comic-panels-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border: 2px solid #000;
}

.comic-panels-container::-webkit-scrollbar-thumb {
  background: #888;
  border: 2px solid #000;
}

.comic-panels-container::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>
