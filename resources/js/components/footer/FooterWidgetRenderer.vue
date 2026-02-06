<template>
    <component
        :is="widgetComponent"
        v-if="widgetComponent"
        :config="widget.config"
        :widget="widget"
    />
    <div v-else class="text-caption text-medium-emphasis">
        Unknown widget type: {{ widget.type }}
    </div>
</template>

<script setup>
import { computed } from 'vue';
import FooterQuicklinksWidget from './FooterQuicklinksWidget.vue';
import FooterContactWidget from './FooterContactWidget.vue';
import FooterNewsletterWidget from './FooterNewsletterWidget.vue';
import FooterSocialWidget from './FooterSocialWidget.vue';
import FooterTextWidget from './FooterTextWidget.vue';

const props = defineProps({
    widget: {
        type: Object,
        required: true
    }
});

const widgetComponents = {
    'quicklinks': FooterQuicklinksWidget,
    'contact': FooterContactWidget,
    'newsletter': FooterNewsletterWidget,
    'social': FooterSocialWidget,
    'text': FooterTextWidget,
};

const widgetComponent = computed(() => {
    return widgetComponents[props.widget.type] || null;
});
</script>
