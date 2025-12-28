<template>
    <div class="flex-grow-1">
        <v-container>
            <v-card elevation="2">
                <v-card-title class="text-h5 font-weight-bold pa-6 bg-gradient">
                    <v-icon class="mr-3" size="28">mdi-cog</v-icon>
                    Application Settings
                </v-card-title>

                <v-divider></v-divider>

                <v-alert v-if="message" :type="alertType" class="mx-6 mt-4 mb-0" closable>
                    {{ message }}
                </v-alert>

                <v-alert
                    v-if="settings.maintenance_mode"
                    type="warning"
                    variant="tonal"
                    class="mx-6 mt-4 mb-0"
                >
                    <strong>Maintenance Mode is Currently Active!</strong>
                </v-alert>

                <v-tabs v-model="currentTab" bg-color="transparent" color="primary" show-arrows>
                    <v-tab value="general">
                        <v-icon class="mr-2">mdi-cog-outline</v-icon>
                        General
                    </v-tab>
                    <v-tab value="localization">
                        <v-icon class="mr-2">mdi-earth</v-icon>
                        Localization
                    </v-tab>
                    <v-tab value="branding">
                        <v-icon class="mr-2">mdi-palette</v-icon>
                        Branding
                    </v-tab>
                    <v-tab value="seo">
                        <v-icon class="mr-2">mdi-search-web</v-icon>
                        SEO
                    </v-tab>
                    <v-tab value="advanced">
                        <v-icon class="mr-2">mdi-cog-sync</v-icon>
                        Advanced
                    </v-tab>
                </v-tabs>

                <v-divider></v-divider>

                <v-card-text class="pa-6">
                    <v-window v-model="currentTab">
                        <v-window-item value="general">
                            <general-tab
                                :settings="settings"
                                :errors="errors"
                                :is-saving="isSaving"
                                @save="saveSettings"
                                @message="handleMessage"
                            />
                        </v-window-item>

                        <v-window-item value="localization">
                            <localization-tab
                                :settings="settings"
                                :errors="errors"
                                :is-saving="isSaving"
                                @save="saveSettings"
                            />
                        </v-window-item>

                        <v-window-item value="branding">
                            <branding-tab
                                :settings="settings"
                                :errors="errors"
                                :is-saving="isSaving"
                                @save="saveSettings"
                                @message="handleMessage"
                            />
                        </v-window-item>

                        <v-window-item value="seo">
                            <seo-tab
                                :settings="settings"
                                :errors="errors"
                                :is-saving="isSaving"
                                @save="saveSettings"
                                @message="handleMessage"
                            />
                        </v-window-item>

                        <v-window-item value="advanced">
                            <advanced-tab
                                :settings="settings"
                                :errors="errors"
                                :is-saving="isSaving"
                                @save="saveSettings"
                            />
                        </v-window-item>
                    </v-window>
                </v-card-text>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useSettings } from '../../composables/useSettings';
import GeneralTab from '../../components/settings/tabs/GeneralTab.vue';
import LocalizationTab from '../../components/settings/tabs/LocalizationTab.vue';
import BrandingTab from '../../components/settings/tabs/BrandingTab.vue';
import SeoTab from '../../components/settings/tabs/SeoTab.vue';
import AdvancedTab from '../../components/settings/tabs/AdvancedTab.vue';

const currentTab = ref('general');
const { settings, isSaving, message, alertType, errors, fetchSettings, saveSettings, showMessage } = useSettings();

onMounted(() => {
    fetchSettings();
});

function handleMessage({ text, type }) {
    showMessage(text, type);
}
</script>

<style scoped>
.bg-gradient {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    color: white;
}
</style>
