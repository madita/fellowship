<template>
    <div>
        <footer-widget-heading :title="config.title" />

        <div v-if="hasSocialLinks" class="d-flex gap-2 flex-wrap">
            <v-btn
                v-if="config.showTwitter && socialTwitter"
                :href="socialTwitter"
                target="_blank"
                icon
                size="small"
                color="primary"
                :title="$t('settings.footer.social.twitter')"
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
                :title="$t('settings.footer.social.facebook')"
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
                :title="$t('settings.footer.social.instagram')"
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
                :title="$t('settings.footer.social.linkedin')"
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
                :title="$t('settings.footer.social.youtube')"
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
                :title="$t('settings.footer.social.discord')"
            >
                <v-icon size="small">mdi-discord</v-icon>
            </v-btn>
        </div>

        <div v-else class="text-caption text-medium-emphasis">
            {{ $t('settings.footer.social.noLinksConfigured') }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/store/settingStore.js';
import FooterWidgetHeading from './FooterWidgetHeading.vue';

const { t } = useI18n();

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
