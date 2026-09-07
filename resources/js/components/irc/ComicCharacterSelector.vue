<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="700">
    <v-card>
      <v-card-title>
        Choose Your Comic Character
      </v-card-title>

      <v-card-text>
        <p class="text-subtitle-2 mb-4">
          Select a character avatar for Comic Chat mode. Your character will appear in comic panels with different emotions!
        </p>

        <v-row>
          <v-col
            v-for="character in characters"
            :key="character.id"
            cols="6"
            sm="4"
            md="3"
          >
            <v-card
              :class="{ 'selected-character': selectedCharacter === character.id }"
              class="character-card pa-2"
              @click="selectedCharacter = character.id"
              hover
            >
              <div class="d-flex justify-center">
                <svg viewBox="0 0 100 140" class="character-preview">
                  <comic-character
                    :character="character.id"
                    emotion="happy"
                    :color="character.hue"
                  />
                </svg>
              </div>
              <v-card-subtitle class="text-center pt-1">
                {{ character.name }}
              </v-card-subtitle>
            </v-card>
          </v-col>
        </v-row>

        <!-- Background Selection -->
        <v-divider class="my-4" />

        <p class="text-subtitle-2 mb-3">
          Choose Comic Background Scene
        </p>

        <v-chip-group v-model="selectedBackground" mandatory>
          <v-chip value="room">Room</v-chip>
          <v-chip value="office">Office</v-chip>
          <v-chip value="outdoor">Outdoor</v-chip>
          <v-chip value="space">Space</v-chip>
          <v-chip value="cafe">Cafe</v-chip>
          <v-chip value="beach">Beach</v-chip>
        </v-chip-group>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn @click="$emit('update:modelValue', false)">
          Cancel
        </v-btn>
        <v-btn color="primary" @click="save">
          Save
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import ComicCharacter from './ComicCharacterParts.vue';

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
      characters: [
        { id: 'cat', name: 'Cat', hue: 30 },
        { id: 'dog', name: 'Dog', hue: 25 },
        { id: 'robot', name: 'Robot', hue: 200 },
        { id: 'alien', name: 'Alien', hue: 120 },
        { id: 'wizard', name: 'Wizard', hue: 270 },
        { id: 'ninja', name: 'Ninja', hue: 0 },
        { id: 'pirate', name: 'Pirate', hue: 45 },
        { id: 'knight', name: 'Knight', hue: 210 },
      ],
    };
  },
  watch: {
    // The dialog component is created once at page mount; re-sync the
    // selection from the props each time it opens, otherwise it always
    // shows the initial (default) character.
    modelValue(open) {
      if (open) {
        this.selectedCharacter = this.currentCharacter || 'cat';
        this.selectedBackground = this.currentBackground || 'room';
      }
    },
  },
  methods: {
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
  border-color: #1976d2;
  box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
}

.character-preview {
  width: 80px;
  height: 112px;
}
</style>
