<template>
    <v-avatar :size="size" :color="background" :class="{ 'is-locked': locked }">
        <!-- A badge can wear a picture of its own; the icon is the fallback
             for the ones that do not have one -->
        <v-img v-if="showImage" :src="achievement.image_url" :alt="achievement.name" cover />
        <v-icon v-else :icon="achievement.icon || 'mdi-trophy-outline'" :size="iconSize" color="white" />
    </v-avatar>
</template>

<script>
export default {
    name: 'AchievementBadge',
    props: {
        achievement: { type: Object, required: true },
        size: { type: [Number, String], default: 44 },
        // Not earned yet: drawn back so the earned ones stand out
        locked: { type: Boolean, default: false },
    },
    computed: {
        showImage() {
            return Boolean(this.achievement.image_url);
        },
        background() {
            // A picture fills the whole avatar, so it needs no colour behind
            if (this.showImage) return undefined;

            return this.locked ? 'grey-lighten-1' : (this.achievement.color || 'amber');
        },
        iconSize() {
            const size = parseInt(this.size, 10) || 44;

            return Math.round(size * 0.55);
        },
    },
};
</script>

<style scoped>
.is-locked {
    filter: grayscale(0.8);
}
</style>
