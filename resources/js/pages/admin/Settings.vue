<template>
    <div class="flex-grow-1">
        <v-container fluid class="pa-2 pa-sm-4">
            <v-card elevation="2">
                <v-card-title class="text-h6 text-sm-h5 font-weight-bold pa-3 pa-sm-6 bg-gradient">
                    <v-icon class="mr-2 mr-sm-3" :size="$vuetify.display.mobile ? 24 : 28">mdi-cog</v-icon>
                    <span class="d-none d-sm-inline">Application </span>Settings
                </v-card-title>

                <v-divider></v-divider>

                <div v-if="message" class="alert-container">
                    <v-alert :type="alertType" class="mx-2 mx-sm-6 mt-4 mb-0 message-alert" closable @click:close="message = ''">
                        <div class="text-body-2">{{ message }}</div>
                    </v-alert>
                </div>

                <v-alert
                    v-if="settings.maintenance_mode"
                    type="warning"
                    variant="tonal"
                    class="mx-2 mx-sm-6 mt-4 mb-0"
                >
                    <strong>Maintenance Mode is Currently Active!</strong>
                </v-alert>

                <v-tabs v-model="currentTab" bg-color="transparent" color="primary" show-arrows>
                    <v-tab value="general">
                        <v-icon :class="$vuetify.display.mobile ? '' : 'mr-2'">mdi-cog-outline</v-icon>
                        <span class="d-none d-sm-inline">General</span>
                    </v-tab>
                    <v-tab value="localization">
                        <v-icon :class="$vuetify.display.mobile ? '' : 'mr-2'">mdi-earth</v-icon>
                        <span class="d-none d-sm-inline">Localization</span>
                    </v-tab>
                    <v-tab value="branding">
                        <v-icon :class="$vuetify.display.mobile ? '' : 'mr-2'">mdi-palette</v-icon>
                        <span class="d-none d-sm-inline">Branding</span>
                    </v-tab>
                    <v-tab value="seo">
                        <v-icon :class="$vuetify.display.mobile ? '' : 'mr-2'">mdi-search-web</v-icon>
                        <span class="d-none d-sm-inline">SEO</span>
                    </v-tab>
                    <v-tab value="homepage">
                        <v-icon :class="$vuetify.display.mobile ? '' : 'mr-2'">mdi-home-edit</v-icon>
                        <span class="d-none d-sm-inline">Homepage</span>
                    </v-tab>
                    <v-tab value="footer">
                        <v-icon :class="$vuetify.display.mobile ? '' : 'mr-2'">mdi-page-layout-footer</v-icon>
                        <span class="d-none d-sm-inline">Footer</span>
                    </v-tab>
                    <v-tab value="advanced">
                        <v-icon :class="$vuetify.display.mobile ? '' : 'mr-2'">mdi-cog-sync</v-icon>
                        <span class="d-none d-sm-inline">Advanced</span>
                    </v-tab>
                </v-tabs>

                <v-divider></v-divider>

                <v-card-text class="pa-2 pa-sm-4 pa-md-6">
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

                        <v-window-item value="homepage">
                            <homepage-tab />
                        </v-window-item>

                        <v-window-item value="footer">
                            <footer-tab
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
import HomepageTab from '../../components/settings/tabs/HomepageTab.vue';
import FooterTab from '../../components/settings/tabs/FooterTab.vue';
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

.alert-container {
    position: sticky;
    top: 0;
    z-index: 10;
    background: transparent;
}

.message-alert {
    word-break: break-word;
    white-space: pre-wrap;
}
</style>
