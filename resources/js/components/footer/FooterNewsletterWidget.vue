<template>
    <div>
        <div v-if="config.title" class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold mb-2">
            {{ config.title }}
        </div>
        <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>

        <div v-if="config.description" class="text-body-2 mb-3">
            {{ config.description }}
        </div>

        <div class="d-flex flex-column flex-sm-row w-full">
            <v-text-field
                v-model="email"
                variant="outlined"
                label="Your email"
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
                {{ config.buttonText || 'Subscribe' }}
            </v-btn>
        </div>

        <v-alert v-if="success" type="success" variant="tonal" density="compact" class="mt-2">
            {{ successMessage }}
        </v-alert>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
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
        error.value = 'Please enter a valid email address';
        return;
    }

    loading.value = true;

    try {
        const response = await axios.post('/api/newsletter/subscribe', {
            email: email.value
        });

        success.value = true;
        successMessage.value = response.data.message || 'Thank you for subscribing!';
        email.value = '';

        setTimeout(() => {
            success.value = false;
        }, 5000);
    } catch (err) {
        console.error('Newsletter subscription error:', err);
        error.value = err.response?.data?.message || 'Failed to subscribe. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>
