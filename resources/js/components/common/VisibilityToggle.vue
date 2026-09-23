<template>
    <!-- The eye beside a field: on means other members see it -->
    <v-btn
        :icon="modelValue ? 'mdi-eye-outline' : 'mdi-eye-off-outline'"
        :title="modelValue ? $t('users.edit.shown') : $t('users.edit.hidden')"
        :color="modelValue ? 'primary' : undefined"
        variant="text"
        size="small"
        density="comfortable"
        @click="$emit('update:modelValue', !modelValue)"
    />
</template>

<script>
export default {
    name: 'VisibilityToggle',
    props: {
        modelValue: { type: Boolean, default: true },
    },
    emits: ['update:modelValue'],
};
</script>
