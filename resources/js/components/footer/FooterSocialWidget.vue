<template>
    <div>
        <div v-if="config.title" class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold mb-2">
            {{ config.title }}
        </div>
        <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>

        <div v-if="hasSocialLinks" class="d-flex gap-2 flex-wrap">
            <v-btn
                v-if="config.showTwitter && socialTwitter"
                :href="socialTwitter"
                target="_blank"
                icon
                size="small"
                color="primary"
                title="Twitter / X"
            >
                <v-icon size="small">mdi-twitter</v-icon>
            </v-btn>
            <v-btn
                v-if="config.showFacebook && socialFacebook"
                :href="socialFacebook"
                target="_blank"
                icon
                size="small"
                color="primary"
                title="Facebook"
            >
                <v-icon size="small">mdi-facebook</v-icon>
            </v-btn>
            <v-btn
                v-if="config.showInstagram && socialInstagram"
                :href="socialInstagram"
                target="_blank"
                icon
                size="small"
                color="primary"
                title="Instagram"
            >
                <v-icon size="small">mdi-instagram</v-icon>
            </v-btn>
            <v-btn
                v-if="config.showLinkedin && socialLinkedin"
                :href="socialLinkedin"
                target="_blank"
                icon
                size="small"
                color="primary"
                title="LinkedIn"
            >
                <v-icon size="small">mdi-linkedin</v-icon>
            </v-btn>
            <v-btn
                v-if="config.showYoutube && socialYoutube"
                :href="socialYoutube"
                target="_blank"
                icon
                size="small"
                color="primary"
                title="YouTube"
            >
                <v-icon size="small">mdi-youtube</v-icon>
            </v-btn>
            <v-btn
                v-if="config.showDiscord && socialDiscord"
                :href="socialDiscord"
                target="_blank"
                icon
                size="small"
                color="primary"
                title="Discord"
            >
                <v-icon size="small">mdi-discord</v-icon>
            </v-btn>
        </div>

        <div v-else class="text-caption text-medium-emphasis">
            No social media links configured
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useSettingsStore } from '@/store/settingStore.js';

const props = defineProps({
    config: {
        type: Object,
        required: true
    }
});

const settingsStore = useSettingsStore();

const socialTwitter = computed(() => settingsStore.socialTwitter);
const socialFacebook = computed(() => settingsStore.socialFacebook);
const socialInstagram = computed(() => settingsStore.socialInstagram);
const socialLinkedin = computed(() => settingsStore.socialLinkedin);
const socialYoutube = computed(() => settingsStore.socialYoutube);
const socialDiscord = computed(() => settingsStore.socialDiscord);

const hasSocialLinks = computed(() => {
    return (
        (props.config.showTwitter && socialTwitter.value) ||
        (props.config.showFacebook && socialFacebook.value) ||
        (props.config.showInstagram && socialInstagram.value) ||
        (props.config.showLinkedin && socialLinkedin.value) ||
        (props.config.showYoutube && socialYoutube.value) ||
        (props.config.showDiscord && socialDiscord.value)
    );
});
</script>
