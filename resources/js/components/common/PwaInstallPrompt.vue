<template>
    <v-banner
        v-if="showPrompt && !dismissed"
        color="primary"
        icon="mdi-cellphone-arrow-down"
        class="pwa-install-banner"
        sticky
    >
        <template #text>
            <div class="d-flex flex-column">
                <span class="text-subtitle-1 font-weight-medium">{{ $t('pwa.installApp', { appName }) }}</span>
                <span class="text-caption">{{ $t('pwa.installDescription') }}</span>
            </div>
        </template>

        <template #actions>
            <v-btn
                color="white"
                variant="text"
                size="small"
                @click="install"
                :loading="installing"
            >
                {{ $t('common.install') }}
            </v-btn>
            <v-btn
                color="white"
                variant="text"
                size="small"
                @click="dismiss"
            >
                {{ $t('common.notNow') }}
            </v-btn>
        </template>
    </v-banner>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/store/settingStore.js';

const { t } = useI18n();

const settingsStore = useSettingsStore();

const showPrompt = ref(false);
const dismissed = ref(false);
const installing = ref(false);
let deferredPrompt = null;

const appName = computed(() => settingsStore.settings?.app_name || 'Fellowship');

onMounted(() => {
    // Check if user has already dismissed the prompt
    const dismissedTimestamp = localStorage.getItem('pwa-install-dismissed');
    if (dismissedTimestamp) {
        const daysSinceDismissed = (Date.now() - parseInt(dismissedTimestamp)) / (1000 * 60 * 60 * 24);
        if (daysSinceDismissed < 7) {
            // Don't show again for 7 days
            dismissed.value = true;
            return;
        }
    }

    // Check if already installed
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        dismissed.value = true;
        return;
    }

    // Listen for the beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing
        e.preventDefault();
        // Stash the event
        deferredPrompt = e;
        // Show the install banner
        showPrompt.value = true;
    });

    // Handle successful installation
    window.addEventListener('appinstalled', () => {
        showPrompt.value = false;
        dismissed.value = true;
        deferredPrompt = null;
        console.log('PWA was installed successfully');
    });
});

async function install() {
    if (!deferredPrompt) {
        return;
    }

    installing.value = true;

    try {
        // Show the install prompt
        await deferredPrompt.prompt();

        // Wait for the user's response
        const { outcome } = await deferredPrompt.userChoice;

        if (outcome === 'accepted') {
            console.log('User accepted the install prompt');
            showPrompt.value = false;
        } else {
            console.log('User dismissed the install prompt');
        }
    } catch (error) {
        console.error('Error during install prompt:', error);
    } finally {
        installing.value = false;
        deferredPrompt = null;
    }
}

function dismiss() {
    showPrompt.value = false;
    dismissed.value = true;
    // Remember dismissal for 7 days
    localStorage.setItem('pwa-install-dismissed', Date.now().toString());
}
</script>

<style scoped>
.pwa-install-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
}
</style>
