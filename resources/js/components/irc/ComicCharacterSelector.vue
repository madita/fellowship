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
              class="character-card"
              @click="selectedCharacter = character.id"
              hover
            >
              <v-img
                :src="character.image"
                :alt="character.name"
                height="120"
                contain
              />
              <v-card-subtitle class="text-center">
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
          <v-chip value="room">🏠 Room</v-chip>
          <v-chip value="office">🏢 Office</v-chip>
          <v-chip value="outdoor">🌳 Outdoor</v-chip>
          <v-chip value="space">🚀 Space</v-chip>
          <v-chip value="cafe">☕ Cafe</v-chip>
          <v-chip value="beach">🏖️ Beach</v-chip>
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
export default {
  name: 'ComicCharacterSelector',
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
        { id: 'cat', name: 'Cat', image: '/images/comic/characters/cat-normal.svg' },
        { id: 'dog', name: 'Dog', image: '/images/comic/characters/dog-normal.svg' },
        { id: 'robot', name: 'Robot', image: '/images/comic/characters/robot-normal.svg' },
        { id: 'alien', name: 'Alien', image: '/images/comic/characters/alien-normal.svg' },
        { id: 'wizard', name: 'Wizard', image: '/images/comic/characters/wizard-normal.svg' },
        { id: 'ninja', name: 'Ninja', image: '/images/comic/characters/ninja-normal.svg' },
        { id: 'pirate', name: 'Pirate', image: '/images/comic/characters/pirate-normal.svg' },
        { id: 'knight', name: 'Knight', image: '/images/comic/characters/knight-normal.svg' },
      ],
    };
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
</style>
