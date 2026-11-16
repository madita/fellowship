<template>
    <div>
        <footer-widget-heading :title="config.title" />

        <div v-if="config.content" class="text-body-2" v-html="processedContent"></div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import FooterWidgetHeading from './FooterWidgetHeading.vue';

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
