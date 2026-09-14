<template>
    <section class="data-table-relation">
        <div class="d-flex align-center ga-2 mb-3">
            <v-icon :icon="relation.icon || 'mdi-link-variant'" color="primary" size="20" />
            <h3 class="text-subtitle-1 font-weight-medium">{{ title }}</h3>
            <v-chip v-if="!loading && !error" size="x-small" variant="tonal">{{ rows.length }}</v-chip>
            <v-spacer />
            <v-btn
                icon="mdi-refresh"
                size="x-small"
                variant="text"
                :loading="loading"
                :disabled="loading"
                :title="$t('dataTable.refresh')"
                :aria-label="$t('dataTable.refresh')"
                @click="load"
            />
        </div>

        <loading-state v-if="loading && !rows.length" compact />

        <empty-state
            v-else-if="error"
            compact
            icon="mdi-alert-circle-outline"
            :title="$t('dataTable.relation.loadFailed')"
        >
            <template #actions>
                <v-btn variant="tonal" size="small" @click="load">{{ $t('common.retry') }}</v-btn>
            </template>
        </empty-state>

        <empty-state
            v-else-if="!rows.length"
            compact
            :icon="relation.icon || 'mdi-link-variant-off'"
            :title="$t('dataTable.relation.empty')"
        />

        <v-expansion-panels v-else variant="accordion" multiple>
            <v-expansion-panel v-for="row in rows" :key="row.id">
                <v-expansion-panel-title>
                    <div class="relation-row d-flex flex-column ga-1">
                        <div class="text-body-2 font-weight-medium text-truncate">{{ primaryText(row) }}</div>
                        <div class="d-flex flex-wrap align-center ga-2 text-caption text-medium-emphasis">
                            <template v-for="column in secondaryColumns" :key="column.key">
                                <v-chip
                                    v-if="column.key === 'status' && row.values && row.values.status"
                                    size="x-small"
                                    variant="tonal"
                                    :color="statusColor(row.values.status)"
                                >
                                    {{ statusText(row.values.status) }}
                                </v-chip>
                                <span v-else-if="column.key !== 'status' && cellText(column, row.values && row.values[column.key])">
                                    {{ cellText(column, row.values[column.key]) }}
                                </span>
                            </template>
                        </div>
                    </div>
                </v-expansion-panel-title>

                <v-expansion-panel-text>
                    <v-list v-if="row.details && row.details.length" density="compact" class="pa-0">
                        <v-list-item v-for="(detail, index) in row.details" :key="index" class="px-0">
                            <v-list-item-subtitle>{{ detail.label }}</v-list-item-subtitle>
                            <v-list-item-title class="text-body-2 text-wrap">{{ detail.value }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                    <p v-else class="text-body-2 text-medium-emphasis mb-0">{{ $t('dataTable.relation.noDetails') }}</p>

                    <v-btn
                        v-if="row.url"
                        :to="row.url"
                        size="small"
                        variant="tonal"
                        prepend-icon="mdi-open-in-new"
                        class="mt-3"
                    >
                        {{ $t('dataTable.relation.open') }}
                    </v-btn>
                </v-expansion-panel-text>
            </v-expansion-panel>
        </v-expansion-panels>
    </section>
</template>

<script>
import axios from 'axios';
import LoadingState from '@/components/common/LoadingState.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { describeCell } from '@/utils/dataTableCells.js';

/**
 * One related-records section in the data table drawer, e.g. a user's
 * event profiles. The table response lists these under `relations`; each
 * endpoint returns { data: { columns, rows } } and rows expand to their
 * details (label/value pairs) with a link to the record.
 */
export default {
    name: 'DataTableRelation',
    components: { LoadingState, EmptyState },
    props: {
        relation: { type: Object, required: true },
        itemId: { type: [Number, String], required: true },
    },
    data() {
        return {
            columns: [],
            rows: [],
            loading: false,
            error: false,
            requestId: 0,
        };
    },
    computed: {
        title() {
            const key = `dataTable.relations.${this.relation.key}`;
            return this.$te(key) ? this.$t(key) : this.relation.title;
        },
        secondaryColumns() {
            return this.columns.slice(1);
        },
        url() {
            return '/api' + String(this.relation.endpoint || '').replace('{id}', encodeURIComponent(this.itemId));
        },
    },
    watch: {
        itemId() {
            this.load();
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            // Only the latest request may update the list (fast item switches)
            const requestId = ++this.requestId;
            this.loading = true;
            this.error = false;
            try {
                const { data } = await axios.get(this.url);
                if (requestId !== this.requestId) return;
                this.columns = data?.data?.columns || [];
                this.rows = data?.data?.rows || [];
            } catch (e) {
                if (requestId !== this.requestId) return;
                this.error = true;
                this.rows = [];
            } finally {
                if (requestId === this.requestId) this.loading = false;
            }
        },
        primaryText(row) {
            const first = this.columns[0];
            return (first && row.values && row.values[first.key]) || `#${row.id}`;
        },
        cellText(column, value) {
            const cell = describeCell(column, value);
            if (cell.kind === 'empty') return '';
            // Date-only values are shown as stored so they don't shift a day between timezones
            if (cell.kind === 'date') return String(cell.value);
            if (cell.kind === 'datetime') return this.$formatDate ? this.$formatDate(cell.value, 'Y-m-d H:i') : String(cell.value);
            if (cell.kind === 'boolean') return cell.value ? this.$t('common.yes') : this.$t('common.no');
            return cell.text !== undefined ? cell.text : String(value);
        },
        statusText(status) {
            const key = `dataTable.relation.status.${status}`;
            return this.$te(key) ? this.$t(key) : status;
        },
        statusColor(status) {
            return { approved: 'success', pending: 'warning' }[status];
        },
    },
};
</script>

<style scoped>
.relation-row {
    min-width: 0;
}
</style>
