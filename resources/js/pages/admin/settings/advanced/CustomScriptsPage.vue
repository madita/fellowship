<template>
    <settings-page-layout
        title="Custom Scripts"
        description="Add custom scripts to your site's head and body"
        icon="mdi-script-text-outline"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'advanced' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-code-tags" title="Custom Scripts">
            <v-alert type="warning" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    <v-icon size="small" class="mr-1">mdi-alert</v-icon>
                    <strong>Security Warning:</strong> Only add scripts from trusted sources. Malicious scripts can compromise your site and user data.
                </div>
            </v-alert>

            <v-textarea
                v-model="settings.custom_head_scripts"
                label="Head Scripts"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_head_scripts"
                hint="Scripts to insert in the <head> section. Include full <script> tags. Great for analytics, meta pixels, etc."
                persistent-hint
                placeholder="<script>
  // Your head scripts here
  // e.g., Google Tag Manager, Meta Pixel
</script>"
            ></v-textarea>

            <v-textarea
                v-model="settings.custom_body_scripts"
                label="Body Scripts (before </body>)"
                prepend-inner-icon="mdi-xml"
                variant="outlined"
                class="mb-4 monospace-input"
                rows="6"
                :error-messages="errors.custom_body_scripts"
                hint="Scripts to insert before the closing </body> tag. Include full <script> tags. Great for chat widgets, tracking scripts, etc."
                persistent-hint
                placeholder="<script>
  // Your body scripts here
  // e.g., Chat widgets, deferred analytics
</script>"
            ></v-textarea>

            <v-alert type="info" variant="tonal" density="compact">
                <div class="text-caption">
                    <strong>Common uses:</strong>
                    <ul class="mt-1 ml-4">
                        <li><strong>Head:</strong> Google Tag Manager, Meta/Facebook Pixel, Google Analytics</li>
                        <li><strong>Body:</strong> Chat widgets (Intercom, Crisp), HotJar, deferred loading scripts</li>
                    </ul>
                </div>
            </v-alert>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
        >
            Save Settings
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref } from 'vue';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');
</script>

<style scoped>
.monospace-input :deep(textarea) {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
}
</style>
