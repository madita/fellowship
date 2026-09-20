<template>
  <v-dialog :model-value="modelValue" max-width="760" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <v-card-title>{{ $t('irc.client.characterDialog.title') }}</v-card-title>

      <v-card-text>
        <p class="text-subtitle-2 mb-4">{{ $t('irc.client.characterDialog.intro') }}</p>

        <v-row>
          <v-col
            v-for="character in characters"
            :key="character.key"
            cols="6"
            sm="4"
            md="3"
          >
            <v-card
              :class="{ 'selected-character': selectedCharacter === character.key }"
              class="character-card pa-2"
              hover
              @click="selectedCharacter = character.key"
            >
              <div class="d-flex justify-center">
                <!-- The preview cycles through the faces so the member sees
                     what the character does, not just a still portrait -->
                <svg viewBox="0 0 100 140" class="character-preview">
                  <comic-character
                    :character="character.key"
                    :emotion="previewEmotion"
                    :gesture="selectedCharacter === character.key ? 'wave' : null"
                  />
                </svg>
              </div>
              <v-card-subtitle class="text-center pt-1">
                {{ characterName(character) }}
              </v-card-subtitle>
            </v-card>
          </v-col>
        </v-row>

        <v-divider class="my-4" />

        <p class="text-subtitle-2 mb-3">{{ $t('irc.client.characterDialog.backgroundTitle') }}</p>

        <v-chip-group v-model="selectedBackground" mandatory>
          <v-chip v-for="scene in backgrounds" :key="scene" :value="scene">
            {{ $t(`irc.client.backgrounds.${scene}`) }}
          </v-chip>
        </v-chip-group>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn @click="$emit('update:modelValue', false)">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" @click="save">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import ComicCharacter from './ComicCharacterParts.vue';
import { useComicCharacterStore } from '@/store/comicCharacterStore.js';

const PREVIEW_EMOTIONS = ['happy', 'surprised', 'excited', 'confused'];

export default {
  name: 'ComicCharacterSelector',
  components: { ComicCharacter },
  props: {
    modelValue: Boolean,
    currentCharacter: String,
    currentBackground: String,
  },
  emits: ['update:modelValue', 'saved'],
  data() {
    return {
      selectedCharacter: this.currentCharacter || 'cat',
      selectedBackground: this.currentBackground || 'room',
      previewEmotion: 'happy',
      previewTimer: null,
      backgrounds: ['room', 'office', 'outdoor', 'space', 'cafe', 'beach'],
    };
  },
  computed: {
    // The ones that ship with the site plus whatever an admin has built
    characters() {
      return useComicCharacterStore().available;
    },
  },
  watch: {
    // The dialog component is created once at page mount; re-sync the
    // selection from the props each time it opens, otherwise it always
    // shows the initial (default) character.
    modelValue(open) {
      if (open) {
        this.selectedCharacter = this.currentCharacter || 'cat';
        this.selectedBackground = this.currentBackground || 'room';
        useComicCharacterStore().load();
        this.startPreview();
      } else {
        this.stopPreview();
      }
    },
  },
  beforeUnmount() {
    this.stopPreview();
  },
  methods: {
    /**
     * A character that ships with the site is named in the member's
     * language; one an admin built carries the name they gave it.
     */
    characterName(character) {
      const key = `irc.client.characters.${character.key}`;

      return this.$te(key) ? this.$t(key) : character.name;
    },
    startPreview() {
      this.stopPreview();
      if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

      let index = 0;
      this.previewTimer = setInterval(() => {
        index = (index + 1) % PREVIEW_EMOTIONS.length;
        this.previewEmotion = PREVIEW_EMOTIONS[index];
      }, 1800);
    },
    stopPreview() {
      if (this.previewTimer) clearInterval(this.previewTimer);
      this.previewTimer = null;
      this.previewEmotion = 'happy';
    },
    save() {
      this.$emit('saved', {
        character: this.selectedCharacter,
        background: this.selectedBackground,
      });
      this.$emit('update:modelValue', false);
    },
  },
};
</script>

<style scoped>
.character-card {
  cursor: pointer;
  transition: all 0.2s;
  border: 3px solid transparent;
}

.character-card:hover {
  transform: scale(1.05);
}

.selected-character {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
}

.character-preview {
  width: 84px;
  height: 118px;
}
</style>
