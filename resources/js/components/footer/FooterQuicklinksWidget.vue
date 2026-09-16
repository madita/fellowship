<template>
    <div>
        <footer-widget-heading :title="config.title" />
        <!-- Button style -->
        <div v-if="isButton" class="d-flex flex-wrap ga-2">
            <v-btn
                v-for="(link, i) in visibleLinks"
                :key="i"
                :to="!link.external ? link.url : undefined"
                :href="link.external ? link.url : undefined"
                :target="link.external ? '_blank' : undefined"
                :prepend-icon="link.authOnly ? 'mdi-lock' : undefined"
                variant="tonal"
                color="primary"
                size="small"
            >
                {{ link.label }}
            </v-btn>
        </div>

        <!-- Text style (simple / bold) -->
        <div v-else class="d-flex flex-column">
            <div v-for="(link, i) in visibleLinks" :key="i" class="mb-2" :class="sizeClass">
                <router-link
                    v-if="!link.external"
                    class="footer-link text-decoration-none text-primary"
                    :class="{ 'font-weight-bold': isBold }"
                    :to="link.url"
                >
                    <v-icon v-if="link.authOnly" size="x-small" class="mr-1">mdi-lock</v-icon>
                    {{ link.label }}
                </router-link>
                <a
                    v-else
                    class="footer-link text-decoration-none text-primary"
                    :class="{ 'font-weight-bold': isBold }"
                    :href="link.url"
                    :target="link.external ? '_blank' : undefined"
                >
                    <v-icon v-if="link.authOnly" size="x-small" class="mr-1">mdi-lock</v-icon>
                    {{ link.label }}
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/store/authStore.js';
import FooterWidgetHeading from './FooterWidgetHeading.vue';

const props = defineProps({
    config: {
        type: Object,
        required: true
    }
});

const authStore = useAuthStore();

const isBold = computed(() => props.config.style === 'bold');
const isButton = computed(() => props.config.style === 'button');
const sizeClass = computed(() =>
    isBold.value ? 'text-body-1 text-sm-h6' : 'text-body-2 text-sm-body-1'
);

const visibleLinks = computed(() => {
    if (!props.config.links || !Array.isArray(props.config.links)) {
        return [];
    }

    return props.config.links.filter(link => {
        // Filter out auth-only links if user is not authenticated
        if (link.authOnly && !authStore.isLoggedIn) {
            return false;
        }
        return true;
    });
});
</script>

<style scoped>
.footer-link {
    transition: opacity 0.15s ease;
}

.footer-link:hover {
    text-decoration: underline !important;
    text-underline-offset: 3px;
}
</style>
