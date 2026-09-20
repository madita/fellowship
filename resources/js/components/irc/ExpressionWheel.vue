<template>
  <div class="expression-wheel" :style="{ width: `${size}px`, height: `${size}px` }">
    <!-- The wheel itself: drag the knob toward a face, or click one. The
         middle is the neutral face, the way the comic chat clients had it. -->
    <svg
      class="wheel-surface"
      :viewBox="`0 0 ${size} ${size}`"
      role="presentation"
      @pointerdown="startDrag"
      @pointermove="onDrag"
      @pointerup="endDrag"
      @pointercancel="endDrag"
    >
      <circle :cx="centre" :cy="centre" :r="rim + 14" class="wheel-face" />
      <circle :cx="centre" :cy="centre" :r="rim" class="wheel-track" />

      <line
        v-for="(spoke, index) in spokes"
        :key="`spoke-${index}`"
        :x1="centre"
        :y1="centre"
        :x2="spoke.x"
        :y2="spoke.y"
        class="wheel-spoke"
      />

      <circle :cx="centre" :cy="centre" :r="deadZone" class="wheel-centre" />

      <!-- The line from the middle out to the chosen mood -->
      <line
        :x1="centre"
        :y1="centre"
        :x2="knob.x"
        :y2="knob.y"
        class="wheel-needle"
      />
      <circle :cx="knob.x" :cy="knob.y" :r="9" class="wheel-knob" />
    </svg>

    <!-- The character in the middle, wearing what is currently picked -->
    <button
      type="button"
      class="wheel-preview"
      :class="{ 'is-picked': modelValue === 'normal' }"
      :title="$t('irc.client.emotions.normal')"
      @click="pick('normal')"
    >
      <svg viewBox="0 0 100 140" class="preview-figure">
        <comic-character
          :character="character"
          :emotion="modelValue"
          :gesture="gesture"
          :color="color"
        />
      </svg>
    </button>

    <!-- One face per mood around the rim, each its own button so the wheel
         works from the keyboard as well as under a finger -->
    <button
      v-for="face in faces"
      :key="face.emotion"
      type="button"
      class="wheel-face-button"
      :class="{ 'is-picked': modelValue === face.emotion }"
      :style="{ left: `${face.x}px`, top: `${face.y}px` }"
      :title="$t(`irc.client.emotions.${face.emotion}`)"
      :aria-pressed="modelValue === face.emotion"
      @click="pick(face.emotion)"
    >
      <svg viewBox="22 14 56 56" class="face-figure">
        <comic-character
          :character="character"
          :emotion="face.emotion"
          :color="color"
        />
      </svg>
    </button>
  </div>
</template>

<script>
import ComicCharacter from './ComicCharacterParts.vue';

// Clockwise from the top. The neutral face lives in the middle, so it is
// not on the rim.
const WHEEL = ['happy', 'excited', 'surprised', 'confused', 'sad', 'angry'];

export default {
  name: 'ExpressionWheel',
  components: { ComicCharacter },
  props: {
    modelValue: { type: String, default: 'normal' },
    gesture: { type: String, default: null },
    character: { type: String, default: 'cat' },
    color: { type: Number, default: 200 },
    size: { type: Number, default: 168 },
  },
  emits: ['update:modelValue'],
  data() {
    return { dragging: false };
  },
  computed: {
    centre() {
      return this.size / 2;
    },
    rim() {
      return this.size / 2 - 26;
    },
    // Inside this the pick is the neutral face
    deadZone() {
      return Math.max(14, this.rim * 0.34);
    },
    faces() {
      return WHEEL.map((emotion, index) => {
        const point = this.pointAt(index, this.rim);

        return { emotion, x: point.x - 21, y: point.y - 21 };
      });
    },
    spokes() {
      return WHEEL.map((_, index) => this.pointAt(index, this.rim));
    },
    knob() {
      const index = WHEEL.indexOf(this.modelValue);

      return index === -1
        ? { x: this.centre, y: this.centre }
        : this.pointAt(index, this.rim - 20);
    },
  },
  methods: {
    /**
     * Where a mood sits on the wheel — the first at the top, the rest
     * clockwise from there.
     */
    pointAt(index, radius) {
      const angle = (index * (360 / WHEEL.length) - 90) * (Math.PI / 180);

      return {
        x: this.centre + Math.cos(angle) * radius,
        y: this.centre + Math.sin(angle) * radius,
      };
    },
    pick(emotion) {
      if (emotion !== this.modelValue) {
        this.$emit('update:modelValue', emotion);
      }
    },
    /**
     * Which mood a point on the wheel means: near the middle the neutral
     * face, otherwise whichever face it points at.
     */
    emotionAt(x, y) {
      const dx = x - this.centre;
      const dy = y - this.centre;

      if (Math.hypot(dx, dy) < this.deadZone) return 'normal';

      const step = 360 / WHEEL.length;
      const angle = (Math.atan2(dy, dx) * 180) / Math.PI + 90;
      const index = Math.round(((angle % 360) + 360) % 360 / step) % WHEEL.length;

      return WHEEL[index];
    },
    pointIn(event) {
      const box = event.currentTarget.getBoundingClientRect();

      // The surface is square, so one scale covers both axes
      const scale = this.size / (box.width || this.size);

      return {
        x: (event.clientX - box.left) * scale,
        y: (event.clientY - box.top) * scale,
      };
    },
    startDrag(event) {
      this.dragging = true;
      event.currentTarget.setPointerCapture?.(event.pointerId);

      const { x, y } = this.pointIn(event);
      this.pick(this.emotionAt(x, y));
    },
    onDrag(event) {
      if (!this.dragging) return;

      const { x, y } = this.pointIn(event);
      this.pick(this.emotionAt(x, y));
    },
    endDrag(event) {
      this.dragging = false;
      event.currentTarget.releasePointerCapture?.(event.pointerId);
    },
  },
};
</script>

<style scoped>
.expression-wheel {
  position: relative;
  flex-shrink: 0;
  touch-action: none;
}

.wheel-surface {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
}

.wheel-face {
  fill: rgba(var(--v-theme-surface-variant), 0.4);
  stroke: rgba(var(--v-border-color), var(--v-border-opacity));
  stroke-width: 1;
}

.wheel-track {
  fill: none;
  stroke: rgba(var(--v-border-color), var(--v-border-opacity));
  stroke-width: 1;
  stroke-dasharray: 3 4;
}

.wheel-spoke {
  stroke: rgba(var(--v-border-color), var(--v-border-opacity));
  stroke-width: 1;
  opacity: 0.5;
}

.wheel-centre {
  fill: rgba(var(--v-theme-surface), 0.85);
  stroke: rgba(var(--v-border-color), var(--v-border-opacity));
  stroke-width: 1;
}

.wheel-needle {
  stroke: rgb(var(--v-theme-primary));
  stroke-width: 3;
  stroke-linecap: round;
}

.wheel-knob {
  fill: rgb(var(--v-theme-primary));
  stroke: rgb(var(--v-theme-surface));
  stroke-width: 2;
}

/* The middle doubles as the neutral face and the preview */
.wheel-preview {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 44px;
  height: 58px;
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
}

.preview-figure {
  width: 100%;
  height: 100%;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.25));
}

.wheel-face-button {
  position: absolute;
  width: 42px;
  height: 42px;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  cursor: pointer;
  transition: transform 0.15s, border-color 0.15s;
}

.wheel-face-button:hover {
  transform: scale(1.12);
}

.wheel-face-button.is-picked,
.wheel-preview.is-picked {
  border-color: rgb(var(--v-theme-primary));
}

.wheel-face-button.is-picked {
  transform: scale(1.16);
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.3);
}

.face-figure {
  width: 100%;
  height: 100%;
}

@media (prefers-reduced-motion: reduce) {
  .wheel-face-button {
    transition: none;
  }
}
</style>
