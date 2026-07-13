<template>
    <div>
        <footer-widget-heading :title="config.title" />

        <div v-if="config.description" class="text-body-2 mb-3">
            {{ config.description }}
        </div>

        <div class="d-flex flex-column flex-sm-row w-full">
            <v-text-field
                v-model="email"
                variant="outlined"
                :label="$t('common.yourEmail')"
                density="compact"
                class="mr-sm-2 mb-2 mb-sm-0"
                :error-messages="error"
                @keyup.enter="subscribe"
            ></v-text-field>
            <v-btn
                color="primary"
                class="flex-shrink-0"
                :loading="loading"
                @click="subscribe"
            >
                {{ config.buttonText || $t('common.subscribe') }}
            </v-btn>
        </div>

        <v-alert v-if="success" type="success" variant="tonal" density="compact" class="mt-2">
            {{ successMessage }}
        </v-alert>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import FooterWidgetHeading from './FooterWidgetHeading.vue';

const { t } = useI18n();

defineProps({
    config: {
        type: Object,
        required: true
    }
});

const email = ref('');
const loading = ref(false);
const error = ref('');
const success = ref(false);
const successMessage = ref('');

async function subscribe() {
    error.value = '';
    success.value = false;

    if (!email.value || !email.value.includes('@')) {
        error.value = t('newsletter.invalidEmail');
        return;
    }

    loading.value = true;

    try {
        const response = await axios.post('/api/newsletter/subscribe', {
            email: email.value
        });

        success.value = true;
        successMessage.value = response.data.message || t('newsletter.thankYou');
        email.value = '';

        setTimeout(() => {
            success.value = false;
        }, 5000);
    } catch (err) {
        console.error('Newsletter subscription error:', err);
        error.value = err.response?.data?.message || t('newsletter.failedToSubscribe');
    } finally {
        loading.value = false;
    }
}
</script>
