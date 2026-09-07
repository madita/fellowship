<template>
  <div class="comic-chat-view" :class="`bg-${background}`">
    <!-- Comic Panels Container -->
    <div ref="comicContainer" class="comic-panels-container">
      <!-- Comic strip: panels flow left-to-right in rows. Each panel shows
           its speakers standing in the scene with their bubbles above them,
           like classic comic chat — not a vertical chat log. -->
      <div
        v-for="(panel, panelIndex) in comicPanels"
        :key="panelIndex"
        class="comic-panel"
      >
        <!-- Speech bubbles, aligned toward their speaker -->
        <div class="panel-bubbles">
          <div
            v-for="message in panel"
            :key="message.id"
            :class="['bubble-row', speakerIndex(panel, message.from_nick) === 0 ? 'bubble-align-left' : 'bubble-align-right']"
          >
            <div :class="['speech-bubble', getBubbleClass(message)]">
              <div class="bubble-content">
                <span v-if="showTimestamps" class="bubble-timestamp">
                  {{ formatTime(message.sent_at) }}
                </span>
                <span v-if="message.type === 'action'" class="bubble-text action-text">
                  * {{ message.from_nick }} {{ message.message }}
                </span>
                <span v-else class="bubble-text">{{ message.message }}</span>
                <span v-if="message.gesture && message.gesture !== 'none'" class="gesture-indicator">
                  {{ getGestureEmoji(message.gesture) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- The speakers of this panel, standing side by side -->
        <div class="panel-characters">
          <div
            v-for="speaker in panelSpeakers(panel)"
            :key="speaker.nick"
            class="character-container"
          >
            <div class="character-figure">
              <svg
                viewBox="0 0 100 140"
                class="character-avatar"
                :class="getEmotionClass(speaker.emotion)"
              >
                <comic-character
                  :character="getCharacterType(speaker.nick)"
                  :emotion="speaker.emotion"
                  :color="getNickHue(speaker.nick)"
                />
              </svg>
              <span
                v-if="speaker.gesture && speaker.gesture !== 'none'"
                class="character-gesture"
              >{{ getGestureEmoji(speaker.gesture) }}</span>
            </div>
            <div class="character-name">{{ speaker.nick }}</div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!comicPanels.length" class="empty-comic">
        <svg viewBox="0 0 100 140" class="welcome-character">
          <comic-character character="cat" emotion="happy" :color="200" />
        </svg>
        <div class="speech-bubble speech">
          <div class="bubble-content">
            <span class="bubble-text">Welcome to Comic Chat! Start chatting to see the magic!</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ComicCharacter from './ComicCharacterParts.vue';

export default {
  name: 'ComicChatView',
  components: { ComicCharacter },
  props: {
    messages: { type: Array, default: () => [] },
    character: { type: String, default: 'cat' },
    myNick: { type: String, default: '' },
    background: { type: String, default: 'room' },
    showTimestamps: { type: Boolean, default: true },
    showEmotionBar: { type: Boolean, default: false },
  },
  emits: ['emotion-selected', 'gesture-selected'],
  data() {
    return {
      messagesPerPanel: 3,
      characterTypes: ['cat', 'dog', 'robot', 'alien', 'wizard', 'ninja', 'pirate', 'knight'],
    };
  },
  computed: {
    // Group consecutive messages into strip panels. A panel closes when it
    // reaches messagesPerPanel messages or a third speaker would enter —
    // classic comic panels show at most two characters talking.
    comicPanels() {
      const msgs = this.messages.filter(m => m.type === 'message' || m.type === 'action');
      const panels = [];
      let panel = [];
      let speakers = new Set();
      for (const message of msgs) {
        const isNewSpeaker = !speakers.has(message.from_nick);
        if (panel.length >= this.messagesPerPanel || (isNewSpeaker && speakers.size >= 2)) {
          panels.push(panel);
          panel = [];
          speakers = new Set();
        }
        panel.push(message);
        speakers.add(message.from_nick);
      }
      if (panel.length) panels.push(panel);
      return panels;
    },
  },
  watch: {
    messages() {
      this.$nextTick(() => this.scrollToBottom());
    },
  },
  methods: {
    // Unique speakers of a panel in speaking order, each carrying the
    // emotion/gesture of their latest message so the standing character
    // reflects what they last did.
    panelSpeakers(panel) {
      const speakers = new Map();
      for (const message of panel) {
        speakers.set(message.from_nick, {
          nick: message.from_nick,
          emotion: message.emotion || 'normal',
          gesture: message.gesture || 'none',
        });
      }
      return [...speakers.values()];
    },
    speakerIndex(panel, nick) {
      return this.panelSpeakers(panel).findIndex(s => s.nick === nick);
    },
    getCharacterType(nick) {
      // Use selected character for current user, hash-based for others
      if (this.myNick && nick.toLowerCase() === this.myNick.toLowerCase()) {
        return this.character;
      }
      const idx = this.hashCode(nick) % this.characterTypes.length;
      return this.characterTypes[idx];
    },
    getNickHue(nick) {
      return this.hashCode(nick) % 360;
    },
    getEmotionClass(emotion) {
      return emotion ? `emotion-${emotion}` : '';
    },
    getBubbleClass(message) {
      if (message.bubble_type === 'thought' || message.gesture === 'think') return 'thought';
      if (message.bubble_type === 'shout' || message.gesture === 'shout') return 'shout';
      if (message.bubble_type === 'whisper' || message.gesture === 'whisper') return 'whisper';
      if (message.type === 'action') return 'action-bubble';
      return 'speech';
    },
    getGestureEmoji(gesture) {
      return { wave: '👋', laugh: '😂', think: '💭', shout: '📢', whisper: '🤫' }[gesture] || '';
    },
    hashCode(str) {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
      }
      return Math.abs(hash);
    },
    formatTime(timestamp) {
      if (!timestamp) return '';
      return new Date(timestamp).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    },
    scrollToBottom() {
      const container = this.$refs.comicContainer;
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    },
  },
};
</script>

<style scoped>
.comic-chat-view {
  height: 100%;
  display: flex;
  flex-direction: column;
}

/* Background themes */
.bg-room {
  background-color: #f5f0e1;
  background-image:
    linear-gradient(90deg, rgba(139, 90, 43, 0.08) 1px, transparent 1px),
    linear-gradient(rgba(139, 90, 43, 0.08) 1px, transparent 1px);
  background-size: 40px 40px;
}

.bg-office {
  background-color: #e8eaf6;
  background-image:
    linear-gradient(90deg, rgba(63, 81, 181, 0.06) 1px, transparent 1px),
    linear-gradient(rgba(63, 81, 181, 0.06) 1px, transparent 1px);
  background-size: 30px 30px;
}

.bg-outdoor {
  background: linear-gradient(180deg, #bbdefb 0%, #c8e6c9 50%, #a5d6a7 100%);
}

.bg-space {
  background-color: #1a1a2e;
  background-image:
    radial-gradient(circle, rgba(255, 255, 255, 0.3) 1px, transparent 1px),
    radial-gradient(circle, rgba(255, 255, 255, 0.15) 1px, transparent 1px);
  background-size: 50px 50px, 30px 30px;
  background-position: 0 0, 15px 15px;
}
.bg-space .comic-panel {
  background: rgba(255, 255, 255, 0.92);
}
.bg-space .character-name {
  color: #e0e0e0;
}

.bg-cafe {
  background-color: #efebe9;
  background-image:
    radial-gradient(ellipse at 30% 50%, rgba(121, 85, 72, 0.08) 0%, transparent 70%),
    radial-gradient(ellipse at 70% 50%, rgba(121, 85, 72, 0.06) 0%, transparent 70%);
}

.bg-beach {
  background: linear-gradient(180deg, #b3e5fc 0%, #b3e5fc 40%, #ffe0b2 40%, #ffcc80 100%);
}

/* Comic strip: panels flow left-to-right, wrapping into rows */
.comic-panels-container {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
  align-content: start;
}

.comic-panel {
  background: white;
  border: 3px solid #222;
  border-radius: 6px;
  padding: 12px;
  box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 260px;
}

.panel-bubbles {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 8px;
}

.bubble-row {
  display: flex;
}

.bubble-align-left {
  justify-content: flex-start;
}

.bubble-align-right {
  justify-content: flex-end;
}

/* The speakers stand side by side at the bottom of the panel */
.panel-characters {
  display: flex;
  justify-content: space-around;
  align-items: flex-end;
  gap: 8px;
}

.character-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.character-figure {
  position: relative;
}

.character-gesture {
  position: absolute;
  top: -6px;
  right: -14px;
  font-size: 20px;
}

.character-avatar {
  width: 64px;
  height: 90px;
  filter: drop-shadow(1px 1px 1px rgba(0, 0, 0, 0.2));
}

.character-name {
  font-family: 'Comic Sans MS', 'Chalkboard SE', cursive, sans-serif;
  font-size: 11px;
  font-weight: bold;
  margin-top: 2px;
  text-align: center;
  max-width: 72px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.speech-bubble {
  position: relative;
  background: white;
  border: 2px solid #222;
  border-radius: 18px;
  padding: 10px 14px;
  max-width: 85%;
  min-width: 60px;
  font-family: 'Comic Sans MS', 'Chalkboard SE', cursive, sans-serif;
  box-shadow: 2px 2px 0 rgba(0, 0, 0, 0.12);
}

/* Tail pointing down toward the speaker standing below */
.speech-bubble::after {
  content: '';
  position: absolute;
  bottom: -10px;
  border-width: 10px 8px 0 8px;
  border-style: solid;
  border-color: #222 transparent transparent transparent;
}

.bubble-align-left .speech-bubble::after {
  left: 24px;
}

.bubble-align-right .speech-bubble::after {
  right: 24px;
}

/* Thought bubbles have no tail (classic comic convention) */
.speech-bubble.thought::after {
  display: none;
}

.speech-bubble.thought {
  border-radius: 50% / 40%;
  border-style: dotted;
  background: #f8f8ff;
}

.speech-bubble.shout {
  background: #fff3e0;
  border-width: 3px;
  border-color: #e65100;
  font-weight: bold;
  text-transform: uppercase;
  font-size: 0.95em;
}

.speech-bubble.whisper {
  background: #f3f3f3;
  border-style: dashed;
  border-color: #999;
  font-style: italic;
  font-size: 0.85em;
  color: #666;
}

.speech-bubble.action-bubble {
  background: #f3e5f5;
  border-color: #9c27b0;
  font-style: italic;
}

.bubble-content {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.bubble-timestamp {
  font-size: 9px;
  color: #999;
}

.bubble-text {
  font-size: 13px;
  line-height: 1.4;
}

.action-text {
  color: #7b1fa2;
}

.gesture-indicator {
  font-size: 18px;
  text-align: right;
}

.empty-comic {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 300px;
  gap: 16px;
}

.welcome-character {
  width: 100px;
  height: 140px;
}

/* Emotion animations */
.emotion-happy { animation: bounce 0.5s ease-in-out; }
.emotion-angry { animation: shake 0.3s ease-in-out; }
.emotion-surprised { animation: pop 0.3s ease-out; }
.emotion-excited { animation: bounce 0.4s ease-in-out infinite alternate; }

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-3px); }
  75% { transform: translateX(3px); }
}

@keyframes pop {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

.comic-panels-container::-webkit-scrollbar {
  width: 8px;
}

.comic-panels-container::-webkit-scrollbar-track {
  background: #ece6d6;
}

.comic-panels-container::-webkit-scrollbar-thumb {
  background: #c4b99a;
  border-radius: 4px;
}
</style>
