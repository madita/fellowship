<template>
    <div>
        <div v-if="config.title" class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold mb-2">
            {{ config.title }}
        </div>
        <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>
        <div class="d-flex flex-column">
            <div v-for="(link, i) in visibleLinks" :key="i" class="text-body-2 text-sm-body-1 mb-2">
                <router-link
                    v-if="!link.external"
                    class="text-decoration-none text-primary"
                    :to="link.url"
                >
                    <v-icon v-if="link.authOnly" size="x-small" class="mr-1">mdi-lock</v-icon>
                    {{ link.label }}
                </router-link>
                <a
                    v-else
                    class="text-decoration-none text-primary"
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

const props = defineProps({
    config: {
        type: Object,
        required: true
    }
});

const authStore = useAuthStore();

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
