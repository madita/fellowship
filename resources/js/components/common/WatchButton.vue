<template>
    <v-btn
        :variant="watching ? 'flat' : 'outlined'"
        :color="watching ? 'primary' : undefined"
        :prepend-icon="watching ? 'mdi-eye-check' : 'mdi-eye-outline'"
        :loading="busy"
        :size="size"
        :title="watching ? $t('watch.watchingHint') : $t('watch.watchHint')"
        @click="toggle"
    >
        {{ watching ? $t('watch.watching') : $t('watch.watch') }}
        <template v-if="showCount && count > 0">
            <span class="ms-1 text-caption">({{ count }})</span>
        </template>
    </v-btn>
</template>

<script setup>
import { ref, computed, watch as watchRef } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';

/**
 * Follow anything the server is willing to call watchable.
 *
 * The forum and the feedback tickets each had their own button, their own
 * endpoint and their own state before this; `kind` is the registry slug the
 * API expects ("ticket", "forum-thread"), never a class name.
 */
const props = defineProps({
    kind: { type: String, required: true },
    id: { type: [Number, String], required: true },
    // Whether the member watches it, as the page already knows it
    modelValue: { type: Boolean, default: false },
    watchersCount: { type: Number, default: 0 },
    showCount: { type: Boolean, default: false },
    size: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue', 'update:watchersCount']);

const { t } = useI18n();
const dialog = useDialog();

const busy = ref(false);
const watching = computed(() => props.modelValue);
const count = ref(props.watchersCount);

// The page may load its data after this mounts
watchRef(() => props.watchersCount, value => {
    count.value = value;
});

const toggle = async () => {
    if (busy.value) return;

    busy.value = true;
    try {
        const { data } = await axios.post(`/api/watch/${props.kind}/${props.id}`);

        emit('update:modelValue', data.watching);
        count.value = data.watchers_count;
        emit('update:watchersCount', data.watchers_count);
    } catch (error) {
        await dialog.requestError(error, t('watch.failed'));
    } finally {
        busy.value = false;
    }
};
</script>
