<template>
    <div>
        <div v-if="config.title" class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold mb-2">
            {{ config.title }}
        </div>
        <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>

        <div v-if="config.showAddress && contactAddress" class="d-flex align-start mb-2 font-weight-bold text-body-2 text-sm-body-1">
            <v-icon color="primary lighten-1" class="mr-2 flex-shrink-0" size="small">mdi-map-marker-outline</v-icon>
            <span>{{ contactAddress }}</span>
        </div>

        <div v-if="config.showPhone && contactPhone" class="d-flex align-center mb-2 text-body-2 text-sm-body-1">
            <v-icon color="primary lighten-1" class="mr-2 flex-shrink-0" size="small">mdi-phone-outline</v-icon>
            <a :href="`tel:${contactPhone}`" class="text-decoration-none text-primary">{{ contactPhone }}</a>
        </div>

        <div v-if="config.showEmail && contactEmail" class="d-flex align-center mb-2 text-body-2 text-sm-body-1">
            <v-icon color="primary lighten-1" class="mr-2 flex-shrink-0" size="small">mdi-email-outline</v-icon>
            <a :href="`mailto:${contactEmail}`" class="text-decoration-none text-primary text-truncate">{{ contactEmail }}</a>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useSettingsStore } from '@/store/settingStore.js';

const props = defineProps({
    config: {
        type: Object,
        required: true
    }
});

const settingsStore = useSettingsStore();

const contactAddress = computed(() => settingsStore.contactAddress);
const contactPhone = computed(() => settingsStore.contactPhone);
const contactEmail = computed(() => settingsStore.contactEmail);
</script>
