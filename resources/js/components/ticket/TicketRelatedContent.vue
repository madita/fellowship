<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDisplay } from 'vuetify';
import axios from 'axios';
import LoadingState from '@/components/common/LoadingState.vue';
import { useDialog } from '@/composables/useDialog.js';
import { sanitizeHtml } from '@/utils/sanitize.js';

/**
 * What a ticket is about (a wiki page, a page …): a card with an excerpt,
 * and a large preview to read the whole content. For approvable content
 * the approve / reject decision sits in the card and in the preview
 * footer, so it can be taken right after reading.
 */
const props = defineProps({
    // Ticket from GET /api/tickets/{id}
    ticket: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
});

// `updated` carries the ticket returned by approve / reject
const emit = defineEmits(['updated']);

const { t } = useI18n();
const dialog = useDialog();
const { smAndDown } = useDisplay();

const KINDS = {
    'App\\Models\\Wiki': { key: 'wiki', icon: 'mdi-book-open-variant', path: slug => `/wiki/${slug}`, api: slug => `/api/wiki/${slug}` },
    'App\\Models\\Page': { key: 'page', icon: 'mdi-file-document-outline', path: slug => `/pages/${slug}`, api: slug => `/api/pages/${slug}` },
};

const kind = computed(() => KINDS[props.ticket.ticketable_type] || null);
const ticketable = computed(() => props.ticket.ticketable);

const content = ref(null);
const loading = ref(false);
const loadFailed = ref(false);
const previewOpen = ref(false);
// 'approve' | 'reject' while the request runs
const approving = ref(null);

const title = computed(() => content.value?.title || ticketable.value?.title || ticketable.value?.slug
    || `${props.ticket.ticketable_type?.split('\\').pop()} #${props.ticket.ticketable_id}`);

const link = computed(() => (kind.value && ticketable.value?.slug ? kind.value.path(ticketable.value.slug) : null));

const html = computed(() => {
    const raw = content.value?.content || content.value?.body || '';
    return raw ? sanitizeHtml(raw) : '';
});

const excerpt = computed(() => {
    if (!html.value) return '';
    const text = new DOMParser().parseFromString(html.value, 'text/html').body.textContent || '';
    const clean = text.replace(/\s+/g, ' ').trim();
    return clean.length > 320 ? `${clean.slice(0, 320).trimEnd()}…` : clean;
});

let requestId = 0;

const loadContent = async () => {
    const id = ++requestId;
    content.value = null;
    loadFailed.value = false;
    if (!kind.value || !ticketable.value?.slug) return;

    loading.value = true;
    try {
        const response = await axios.get(kind.value.api(ticketable.value.slug));
        if (id !== requestId) return;
        content.value = response.data.page || response.data;
    } catch (err) {
        if (id !== requestId) return;
        console.error('Failed to load related content:', err);
        loadFailed.value = true;
    } finally {
        if (id === requestId) loading.value = false;
    }
};

watch(() => [props.ticket.ticketable_type, props.ticket.ticketable_id], loadContent, { immediate: true });

const decide = async (action) => {
    if (approving.value) return;
    approving.value = action;
    try {
        const response = await axios.post(`/api/tickets/${props.ticket.id}/${action}`);
        previewOpen.value = false;
        emit('updated', response.data);
    } catch (err) {
        console.error(`Failed to ${action}:`, err);
        await dialog.requestError(err, t('tickets.messages.approvalFailed'));
    } finally {
        approving.value = null;
    }
};
</script>

<template>
    <v-card rounded="lg" elevation="2" border>
        <v-card-title class="d-flex align-center ga-2 text-subtitle-1">
            <v-icon :icon="kind?.icon || 'mdi-link-variant'" color="primary" size="small" />
            <span class="text-medium-emphasis">{{ t('tickets.related.title') }}</span>
        </v-card-title>

        <v-card-text>
            <div class="d-flex align-center flex-wrap ga-2 mb-2">
                <span class="text-h6 font-weight-medium">{{ title }}</span>
                <v-chip v-if="kind" size="x-small" variant="tonal">{{ t(`tickets.related.kinds.${kind.key}`) }}</v-chip>
                <template v-if="ticket.is_approvable">
                    <v-chip v-if="ticket.is_approved" size="x-small" variant="tonal" color="success" prepend-icon="mdi-check-circle-outline">
                        {{ t('tickets.detail.approved') }}
                    </v-chip>
                    <v-chip v-else size="x-small" variant="tonal" color="warning" prepend-icon="mdi-clock-outline">
                        {{ t('tickets.detail.pendingApproval') }}
                    </v-chip>
                </template>
            </div>

            <loading-state v-if="loading" compact />
            <p v-else-if="excerpt" class="text-body-2 text-medium-emphasis mb-0">{{ excerpt }}</p>
            <p v-else class="text-body-2 text-medium-emphasis mb-0">
                {{ loadFailed ? t('tickets.related.loadFailed') : t('tickets.related.empty') }}
            </p>
        </v-card-text>

        <v-card-actions class="px-4 pb-4 flex-wrap ga-2">
            <v-btn
                v-if="html"
                color="primary"
                variant="tonal"
                prepend-icon="mdi-eye-outline"
                @click="previewOpen = true"
            >
                {{ t('tickets.related.preview') }}
            </v-btn>
            <v-btn v-if="link" variant="text" prepend-icon="mdi-open-in-new" :to="link">
                {{ t('tickets.related.open') }}
            </v-btn>
            <v-spacer />
            <template v-if="isAdmin && ticket.is_approvable">
                <template v-if="!ticket.is_approved">
                    <v-btn color="error" variant="tonal" prepend-icon="mdi-close" :loading="approving === 'reject'" :disabled="!!approving" @click="decide('reject')">
                        {{ t('tickets.detail.reject') }}
                    </v-btn>
                    <v-btn color="success" variant="flat" prepend-icon="mdi-check" :loading="approving === 'approve'" :disabled="!!approving" @click="decide('approve')">
                        {{ t('tickets.detail.approve') }}
                    </v-btn>
                </template>
                <v-btn v-else color="warning" variant="tonal" prepend-icon="mdi-undo" :loading="approving === 'reject'" @click="decide('reject')">
                    {{ t('tickets.detail.revokeApproval') }}
                </v-btn>
            </template>
        </v-card-actions>

        <!-- Full preview -->
        <v-dialog v-model="previewOpen" max-width="960" scrollable :fullscreen="smAndDown">
            <v-card>
                <v-card-title class="d-flex align-center ga-2 py-3">
                    <v-icon :icon="kind?.icon || 'mdi-link-variant'" color="primary" />
                    <span class="text-truncate">{{ title }}</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" size="small" :aria-label="t('tickets.close')" @click="previewOpen = false" />
                </v-card-title>
                <v-divider />
                <v-card-text class="preview-content py-6">
                    <!-- Sanitized with sanitizeHtml -->
                    <div v-html="html" />
                </v-card-text>
                <v-divider />
                <v-card-actions class="px-4 py-3 flex-wrap ga-2">
                    <v-btn v-if="link" variant="text" prepend-icon="mdi-open-in-new" :to="link" @click="previewOpen = false">
                        {{ t('tickets.related.open') }}
                    </v-btn>
                    <v-spacer />
                    <template v-if="isAdmin && ticket.is_approvable && !ticket.is_approved">
                        <v-btn color="error" variant="tonal" prepend-icon="mdi-close" :loading="approving === 'reject'" :disabled="!!approving" @click="decide('reject')">
                            {{ t('tickets.detail.reject') }}
                        </v-btn>
                        <v-btn color="success" variant="flat" prepend-icon="mdi-check" :loading="approving === 'approve'" :disabled="!!approving" @click="decide('approve')">
                            {{ t('tickets.detail.approve') }}
                        </v-btn>
                    </template>
                    <v-btn v-else variant="text" @click="previewOpen = false">{{ t('tickets.close') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<style scoped>
.preview-content {
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.preview-content :deep(img) {
    max-width: 100%;
    height: auto;
}

.preview-content :deep(table) {
    border-collapse: collapse;
    display: block;
    overflow-x: auto;
}

.preview-content :deep(th),
.preview-content :deep(td) {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding: 4px 8px;
}
</style>
