<template>
    <div>
        <footer-widget-heading :title="config.title" />

        <div v-if="config.description" class="text-body-2 mb-3">
            {{ config.description }}
        </div>

        <div class="d-flex flex-column flex-sm-row ga-2">
            <v-text-field
                v-model="email"
                :label="$t('common.yourEmail')"
                density="compact"
                :error-messages="error"
                :disabled="loading"
                @keyup.enter="subscribe"
            ></v-text-field>
            <v-btn
                color="primary"
                variant="elevated"
                class="flex-shrink-0"
                :loading="loading"
                @click="subscribe"
            >
                {{ config.buttonText || $t('common.subscribe') }}
            </v-btn>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import FooterWidgetHeading from './FooterWidgetHeading.vue';
import { useDialog } from '@/composables/useDialog.js';

const { t } = useI18n();
// The outcome of the subscription is shown as a modal; the field keeps
// its inline validation error.
const dialog = useDialog();

defineProps({
    config: {
        type: Object,
        required: true
    }
});

const email = ref('');
const loading = ref(false);
const error = ref('');

async function subscribe() {
    if (loading.value) return;
    error.value = '';

    if (!email.value || !email.value.includes('@')) {
        error.value = t('newsletter.invalidEmail');
        return;
    }

    loading.value = true;

    try {
        const response = await axios.post('/api/newsletter/subscribe', {
            email: email.value
        });

        email.value = '';
        await dialog.success(response.data.message || t('newsletter.thankYou'));
    } catch (err) {
        console.error('Newsletter subscription error:', err);
        const validation = err.response?.status === 422 ? err.response.data?.errors?.email?.[0] : null;
        if (validation) {
            error.value = validation;
        } else {
            await dialog.requestError(err, t('newsletter.failedToSubscribe'));
        }
    } finally {
        loading.value = false;
    }
}
</script>
