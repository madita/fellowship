<template>
    <settings-page-layout
        :title="$t('settings.footer.customHtml')"
        :description="$t('settings.footer.customHtmlDescription')"
        icon="mdi-code-tags"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'footer' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-code-tags" :title="$t('settings.footer.customFooterHtml')">
            <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                <div class="text-caption">
                    {{ $t('settings.footer.customHtmlDescription') }}
                </div>
            </v-alert>

            <v-switch
                v-model="settings.custom_footer_enabled"
                :label="$t('settings.footer.enableCustomFooter')"
                color="primary"
                class="mb-4"
                :hint="$t('settings.footer.enableCustomFooterHint')"
                persistent-hint
            ></v-switch>

            <template v-if="settings.custom_footer_enabled">
                <v-textarea
                    v-model="settings.custom_footer_html"
                    :label="$t('settings.footer.customFooterHtml')"
                    prepend-inner-icon="mdi-code-tags"
                    variant="outlined"
                    rows="16"
                    :error-messages="errors?.custom_footer_html"
                    :hint="$t('settings.footer.customHtmlHint')"
                    persistent-hint
                    :placeholder="$t('settings.footer.customHtmlPlaceholder')"
                    class="mb-4"
                ></v-textarea>

                <div class="d-flex gap-2 mb-4">
                    <v-btn
                        color="primary"
                        variant="outlined"
                        @click="loadSimpleFooterTemplate"
                        prepend-icon="mdi-code-braces"
                    >
                        {{ $t('settings.footer.loadSimpleTemplate') }}
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="outlined"
                        @click="loadComplexFooterTemplate"
                        prepend-icon="mdi-code-tags"
                    >
                        {{ $t('settings.footer.loadComplexTemplate') }}
                    </v-btn>
                </div>

                <v-alert type="info" variant="tonal" class="mb-4">
                    <div class="text-body-2">
                        <strong>{{ $t('settings.footer.availableVariables') }}:</strong>
                        <ul class="mt-2">
                            <li><code v-pre>{{appName}}</code> - {{ $t('settings.footer.varAppName') }}</li>
                            <li><code v-pre>{{appCopyright}}</code> - {{ $t('settings.footer.varCopyright') }}</li>
                            <li><code v-pre>{{contactEmail}}</code> - {{ $t('settings.footer.varContactEmail') }}</li>
                            <li><code v-pre>{{contactPhone}}</code> - {{ $t('settings.footer.varContactPhone') }}</li>
                            <li><code v-pre>{{contactAddress}}</code> - {{ $t('settings.footer.varContactAddress') }}</li>
                            <li><code v-pre>{{socialTwitter}}</code> - {{ $t('settings.footer.varTwitter') }}</li>
                            <li><code v-pre>{{socialFacebook}}</code> - {{ $t('settings.footer.varFacebook') }}</li>
                            <li><code v-pre>{{socialInstagram}}</code> - {{ $t('settings.footer.varInstagram') }}</li>
                        </ul>
                    </div>
                </v-alert>
            </template>
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
            {{ $t('settings.footer.saveSettings') }}
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

function loadSimpleFooterTemplate() {
    props.settings.custom_footer_html = `<div class="v-footer v-theme--light bg-transparent" style="padding: 16px;">
  <div class="v-container">
    <div class="text-center">
      <div class="mb-2">
        <strong>{{appName}}</strong>
      </div>
      <div class="text-caption mb-2">
        {{appCopyright}}
      </div>
      <div class="text-caption mb-2" data-if="contactEmail">
        Contact: <a href="mailto:{{contactEmail}}" style="text-decoration: none; color: rgb(var(--v-theme-primary));">{{contactEmail}}</a>
      </div>
    </div>
  </div>
</div>`;
}

function loadComplexFooterTemplate() {
    props.settings.custom_footer_html = `<div class="v-footer v-theme--light bg-transparent" style="padding: 40px 0;">
  <div class="v-container">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 32px; margin-bottom: 24px;">
      <div>
        <div style="font-size: 1.25rem; font-weight: bold; margin-bottom: 8px;">Navigation</div>
        <div style="width: 80px; height: 2px; background: rgb(var(--v-theme-primary)); margin-bottom: 20px;"></div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <a href="/" style="text-decoration: none; color: rgb(var(--v-theme-primary));">Home</a>
          <a href="/about" style="text-decoration: none; color: rgb(var(--v-theme-primary));">About</a>
        </div>
      </div>
    </div>
  </div>
</div>`;
}
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
