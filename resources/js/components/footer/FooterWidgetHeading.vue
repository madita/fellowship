<template>
    <div v-if="title">
        <div class="text-subtitle-1 text-sm-h6 text-lg-h5 font-weight-bold mb-2">
            {{ title }}
        </div>
        <div style="width: 80px; height: 2px" class="mb-3 mb-sm-5 mt-1 bg-primary" />
    </div>
</template>

<script setup>
// Shared footer widget heading: the bold title plus the primary accent bar.
// Rendered only when a title is set, so widgets never show an orphan accent bar.
defineProps({
    title: { type: String, default: '' },
});
</script>
