<template>
    <div>
        <div v-if="config.title" class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold mb-2">
            {{ config.title }}
        </div>
        <div v-if="config.title" style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary"/>

        <div v-if="config.content" class="text-body-2" v-html="processedContent"></div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    config: {
        type: Object,
        required: true
    }
});

const processedContent = computed(() => {
    if (!props.config.content) return '';

    // Convert newlines to <br> tags for better formatting
    return props.config.content.replace(/\n/g, '<br>');
});
</script>
