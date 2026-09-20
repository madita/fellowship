<template>
  <div class="comic-chat-view" :class="`bg-${background}`">
    <div ref="comicContainer" class="comic-panels-container">
      <!-- The chat as a comic strip: panels flow left-to-right and wrap into
           rows. Each panel holds a few lines, with the speakers standing
           below their balloons and facing each other. -->
      <div
        v-for="panel in comicPanels"
        :key="panel.key"
        class="comic-panel"
      >
        <div class="panel-bubbles">
          <div
            v-for="line in panel.lines"
            :key="line.id"
            class="bubble-row"
            :class="line.side === 0 ? 'from-left' : 'from-right'"
          >
            <div class="speech-bubble" :class="`bubble-${line.bubble}`">
              <span v-if="showTimestamps && line.time" class="bubble-timestamp">{{ line.time }}</span>
              <span class="bubble-text">
                <template v-if="line.isAction">* {{ line.nick }} {{ line.text }}</template>
                <template v-else>{{ line.text }}</template>
              </span>
            </div>
          </div>
        </div>

        <div class="panel-stage">
          <div
            v-for="speaker in panel.speakers"
            :key="speaker.nick"
            class="character-container"
            :class="[speaker.facing < 0 ? 'stands-right' : 'stands-left', { 'is-listening': !speaker.speaking }]"
          >
            <svg
              viewBox="0 0 100 140"
              class="character-avatar"
              :class="speaker.speaking ? `emotion-${speaker.emotion}` : ''"
            >
              <comic-character
                :character="characterFor(speaker.nick)"
                :emotion="speaker.emotion"
                :gesture="speaker.gesture"
                :facing="speaker.facing"
                :speaking="speaker.speaking"
                :color="hueFor(speaker.nick)"
              />
            </svg>
            <div class="character-name" :title="speaker.nick">{{ speaker.nick }}</div>
          </div>
        </div>
      </div>

      <div v-if="!comicPanels.length" class="empty-comic">
        <svg viewBox="0 0 100 140" class="welcome-character">
          <comic-character :character="character" emotion="happy" gesture="wave" :color="200" />
        </svg>
        <div class="speech-bubble bubble-speech">
          <span class="bubble-text">{{ $t('irc.client.comic.emptyTitle') }}</span>
          <span class="bubble-hint">{{ $t('irc.client.comic.emptyText') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ComicCharacter from './ComicCharacterParts.vue';
import { expressionOf } from '@/utils/comicExpression.js';

export default {
  name: 'ComicChatView',
  components: { ComicCharacter },
  props: {
    messages: { type: Array, default: () => [] },
    character: { type: String, default: 'cat' },
    myNick: { type: String, default: '' },
    background: { type: String, default: 'room' },
    showTimestamps: { type: Boolean, default: true },
  },
  data() {
    return {
      messagesPerPanel: 3,
      characterTypes: ['cat', 'dog', 'robot', 'alien', 'wizard', 'ninja', 'pirate', 'knight'],
    };
  },
  computed: {
    /**
     * The strip. A panel closes once it holds messagesPerPanel lines or a
     * third speaker would walk in — a comic panel shows two people talking.
     * Each panel carries its lines and the speakers standing in it, the
     * last one to speak in the foreground.
     */
    comicPanels() {
      const spoken = this.messages.filter(m => m.type === 'message' || m.type === 'action');
      const panels = [];
      let lines = [];

      const close = () => {
        if (lines.length) panels.push(this.buildPanel(lines, panels.length));
        lines = [];
      };

      for (const message of spoken) {
        const speakers = new Set(lines.map(l => l.from_nick));
        if (lines.length >= this.messagesPerPanel || (!speakers.has(message.from_nick) && speakers.size >= 2)) {
          close();
        }
        lines.push(message);
      }
      close();

      return panels;
    },
  },
  watch: {
    messages() {
      this.$nextTick(() => this.scrollToBottom());
    },
  },
  methods: {
    /**
     * One panel: who stands where, and how each line is drawn. The member's
     * own emotion/gesture wins; otherwise the text decides.
     */
    buildPanel(messages, index) {
      const order = [];
      for (const message of messages) {
        if (!order.includes(message.from_nick)) order.push(message.from_nick);
      }

      const last = messages[messages.length - 1];
      const expressions = new Map();

      const lines = messages.map((message) => {
        const expression = expressionOf(message);
        expressions.set(message.from_nick, expression);

        return {
          id: message.id ?? `${message.from_nick}-${message.sent_at}`,
          nick: message.from_nick,
          text: message.message,
          isAction: message.type === 'action',
          bubble: expression.bubble,
          side: order.indexOf(message.from_nick),
          time: this.formatTime(message.sent_at),
        };
      });

      const speakers = order.map((nick, position) => ({
        nick,
        // Two characters turn toward each other; a lone one faces the reader's right
        facing: position === 1 ? -1 : 1,
        speaking: nick === last.from_nick,
        emotion: expressions.get(nick).emotion,
        gesture: expressions.get(nick).gesture,
      }));

      return { key: `${index}-${lines[0].id}`, lines, speakers };
    },
    characterFor(nick) {
      if (this.myNick && nick.toLowerCase() === this.myNick.toLowerCase()) {
        return this.character;
      }
      return this.characterTypes[this.hashCode(nick) % this.characterTypes.length];
    },
    hueFor(nick) {
      return this.hashCode(nick) % 360;
    },
    hashCode(str) {
      let hash = 0;
      for (let i = 0; i < String(str).length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
      }
      return Math.abs(hash);
    },
    formatTime(timestamp) {
      if (!timestamp) return '';
      return new Date(timestamp).toLocaleTimeString(this.$i18n?.locale || 'en', {
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    scrollToBottom() {
      const container = this.$refs.comicContainer;
      if (container) container.scrollTop = container.scrollHeight;
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

.bg-cafe {
  background-color: #efebe9;
  background-image:
    radial-gradient(ellipse at 30% 50%, rgba(121, 85, 72, 0.08) 0%, transparent 70%),
    radial-gradient(ellipse at 70% 50%, rgba(121, 85, 72, 0.06) 0%, transparent 70%);
}

.bg-beach {
  background: linear-gradient(180deg, #b3e5fc 0%, #b3e5fc 40%, #ffe0b2 40%, #ffcc80 100%);
}

/* The strip */
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
  position: relative;
  background: #fffdf8;
  border: 3px solid #222;
  border-radius: 4px;
  padding: 12px 12px 4px;
  box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.18);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 280px;
  overflow: hidden;
}

/* A floor line so the characters stand on something */
.comic-panel::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: 34px;
  border-top: 2px solid rgba(34, 34, 34, 0.25);
}

.panel-bubbles {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 10px;
  z-index: 1;
}

.bubble-row {
  display: flex;
}

.from-left { justify-content: flex-start; }
.from-right { justify-content: flex-end; }

/* The speakers stand at the bottom, facing each other */
.panel-stage {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 8px;
  padding: 0 4px;
}

.character-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  transition: opacity 0.2s;
}

.stands-right { margin-left: auto; }

.is-listening { opacity: 0.85; }

.character-avatar {
  width: 72px;
  height: 100px;
  filter: drop-shadow(1px 2px 1px rgba(0, 0, 0, 0.2));
}

.character-name {
  font-family: 'Comic Sans MS', 'Chalkboard SE', cursive, sans-serif;
  font-size: 11px;
  font-weight: bold;
  text-align: center;
  max-width: 86px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  background: #fffdf8;
  padding: 0 4px;
}

/* Balloons */
.speech-bubble {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: #fff;
  border: 2px solid #222;
  border-radius: 18px;
  padding: 9px 14px;
  max-width: 86%;
  min-width: 56px;
  font-family: 'Comic Sans MS', 'Chalkboard SE', cursive, sans-serif;
}

/* The tail is drawn twice: the outline, then the fill just inside it, so
   the balloon keeps its ink line all the way to the point. */
.speech-bubble::before,
.speech-bubble::after {
  content: '';
  position: absolute;
  width: 0;
  height: 0;
  border-style: solid;
}

.speech-bubble::before {
  bottom: -14px;
  border-width: 14px 11px 0 0;
  border-color: #222 transparent transparent transparent;
}

.speech-bubble::after {
  bottom: -10px;
  border-width: 11px 8px 0 0;
  border-color: #fff transparent transparent transparent;
}

.from-left .speech-bubble::before { left: 22px; }
.from-left .speech-bubble::after { left: 25px; }

/* Mirrored so the tail leans toward the speaker on the right */
.from-right .speech-bubble::before {
  right: 22px;
  border-width: 14px 0 0 11px;
  border-color: #222 transparent transparent transparent;
}

.from-right .speech-bubble::after {
  right: 25px;
  border-width: 11px 0 0 8px;
  border-color: #fff transparent transparent transparent;
}

/* A thought balloon trails little clouds instead of a point */
.bubble-thought {
  border-radius: 40% 40% 42% 42% / 46%;
  background: #f9f9ff;
  padding: 12px 18px;
}

.bubble-row .bubble-thought::before,
.bubble-row .bubble-thought::after {
  border: 2px solid #222;
  border-radius: 50%;
  background: #f9f9ff;
}

.bubble-row .bubble-thought::before {
  bottom: -15px;
  width: 12px;
  height: 12px;
}

.bubble-row .bubble-thought::after {
  bottom: -28px;
  width: 7px;
  height: 7px;
}

.from-left .bubble-thought::before { left: 22px; }
.from-left .bubble-thought::after { left: 15px; }
.from-right .bubble-thought::before { right: 22px; }
.from-right .bubble-thought::after { right: 15px; }

/* A shout bursts out of its edges */
.bubble-shout {
  background: #fff6e5;
  border: 3px solid #e65100;
  border-radius: 6px;
  font-weight: bold;
  clip-path: polygon(
    0% 12%, 6% 6%, 4% 0%, 18% 6%, 32% 0%, 46% 6%, 60% 0%, 74% 6%, 88% 0%, 96% 8%,
    100% 16%, 95% 30%, 100% 46%, 95% 62%, 100% 78%, 94% 92%, 82% 88%, 68% 100%,
    54% 90%, 40% 100%, 26% 90%, 12% 98%, 5% 88%, 0% 74%, 5% 58%, 0% 42%, 5% 26%
  );
  padding: 14px 18px;
}

.bubble-shout::before,
.bubble-shout::after { display: none; }

/* A whisper is drawn faintly, in a dashed outline */
.bubble-whisper {
  background: #f5f5f5;
  border-style: dashed;
  border-color: #9e9e9e;
  font-style: italic;
  color: #616161;
}

.bubble-row .bubble-whisper::before { border-top-color: #9e9e9e; }
.bubble-row .bubble-whisper::after { border-top-color: #f5f5f5; }

/* An action is narration, so it gets a caption box, not a balloon */
.bubble-action {
  background: #fdf6e3;
  border-color: #8d6e63;
  border-radius: 3px;
  font-style: italic;
  color: #5d4037;
}

.bubble-action::before,
.bubble-action::after { display: none; }

.bubble-timestamp {
  font-size: 9px;
  color: #9e9e9e;
}

.bubble-text {
  font-size: 13px;
  line-height: 1.4;
  word-break: break-word;
}

.bubble-hint {
  font-size: 11px;
  color: #757575;
}

.empty-comic {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 300px;
  gap: 18px;
}

.empty-comic .speech-bubble { text-align: center; }
.empty-comic .speech-bubble::before,
.empty-comic .speech-bubble::after { display: none; }

.welcome-character {
  width: 110px;
  height: 154px;
}

/* A beat of movement when a character takes the stage */
.emotion-happy { animation: bounce 0.5s ease-in-out; }
.emotion-excited { animation: bounce 0.45s ease-in-out 2; }
.emotion-angry { animation: shake 0.3s ease-in-out; }
.emotion-surprised { animation: pop 0.3s ease-out; }

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

@media (prefers-reduced-motion: reduce) {
  .character-avatar { animation: none !important; }
}

.bg-space .comic-panel,
.bg-space .character-name { background: #fffdf8; }

.comic-panels-container::-webkit-scrollbar { width: 8px; }
.comic-panels-container::-webkit-scrollbar-track { background: #ece6d6; }
.comic-panels-container::-webkit-scrollbar-thumb {
  background: #c4b99a;
  border-radius: 4px;
}
</style>
