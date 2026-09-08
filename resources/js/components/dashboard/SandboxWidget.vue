<template>
    <widget-state
        :loading="loading"
        :error="error"
        :empty="sandboxes.length === 0"
        empty-icon="mdi-file-document-edit-outline"
        :empty-text="$t('dashboard.widgets.sandbox.empty')"
    >
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="sandbox in sandboxes"
                :key="sandbox.uuid"
                :to="{ name: 'sandbox.show', params: { uuid: sandbox.uuid } }"
                class="px-0 mb-1"
            >
                <template v-slot:prepend>
                    <v-avatar :color="relationColor(sandbox.relationship)" size="24">
                        <v-icon size="12" color="white">{{ relationIcon(sandbox.relationship) }}</v-icon>
                    </v-avatar>
                </template>
                <v-list-item-title class="text-body-2">{{ sandbox.title }}</v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                    {{ $t(`dashboard.widgets.sandbox.${relationKey(sandbox.relationship)}`, { name: sandbox.owner?.username }) }}
                    <span v-if="sandbox.collaborators_count">
                        · {{ $t('dashboard.widgets.sandbox.collaborators', { count: sandbox.collaborators_count }) }}
                    </span>
                    · {{ relative(sandbox.last_edited_at || sandbox.updated_at) }}
                </v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </widget-state>
</template>

<script>
import axios from 'axios';
import widgetMixin from './widgetMixin.js';
import WidgetState from './WidgetState.vue';
import { formatDateDistanceToNow } from '@/plugins/formatDate.js';

/**
 * Sandboxes the user can open, most recently edited first, from /api/sandbox.
 */
export default {
    name: 'SandboxWidget',
    components: { WidgetState },
    mixins: [widgetMixin],
    data() {
        return {
            sandboxes: [],
            total: 0,
        };
    },
    methods: {
        async fetch() {
            const { data } = await axios.get('/api/sandbox', { params: { filter: this.widgetConfig?.filter || 'all' } });
            const all = data.data || [];
            this.sandboxes = all.slice(0, this.limit);
            this.total = data.total ?? all.length;
            this.setSubtitle(this.$t('dashboard.widgets.sandbox.subtitle', { count: this.total }));
        },
        relationKey(relationship) {
            return ['owner', 'shared', 'public', 'members'].includes(relationship) ? relationship : 'members';
        },
        relationIcon(relationship) {
            return {
                owner: 'mdi-account-edit',
                shared: 'mdi-account-multiple',
                public: 'mdi-earth',
            }[relationship] || 'mdi-account-group';
        },
        relationColor(relationship) {
            return { owner: 'indigo', shared: 'primary', public: 'success' }[relationship] || 'secondary';
        },
        relative(date) {
            return formatDateDistanceToNow(date);
        },
    },
};
</script>
