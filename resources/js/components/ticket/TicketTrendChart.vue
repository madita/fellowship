<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useElementSize } from '@vueuse/core';

/**
 * Tickets created vs. resolved per day: two lines on one axis, a crosshair
 * tooltip (pointer and arrow keys) and a table view with the same numbers.
 */
const props = defineProps({
    // [{ date: 'YYYY-MM-DD', created: n, resolved: n }], oldest first
    points: { type: Array, required: true },
});

const { t, locale } = useI18n();

const SERIES = [
    { key: 'created', className: 'series-created' },
    { key: 'resolved', className: 'series-resolved' },
];
const HEIGHT = 220;
const PAD = { top: 12, right: 88, bottom: 28, left: 32 };

const view = ref('chart');
const plotRef = ref(null);
const { width } = useElementSize(plotRef);
const activeIndex = ref(null);

const innerWidth = computed(() => Math.max(width.value - PAD.left - PAD.right, 10));
const innerHeight = HEIGHT - PAD.top - PAD.bottom;

// A clean axis maximum with at most four intervals (1, 2, 5, 10, 20, 50 …)
const yScale = computed(() => {
    const max = Math.max(...props.points.flatMap(p => [p.created, p.resolved]), 0);
    let step = 1;
    for (let magnitude = 1; ; magnitude *= 10) {
        const found = [1, 2, 5].map(m => m * magnitude).find(s => s * 4 >= max);
        if (found) {
            step = found;
            break;
        }
    }
    const top = Math.max(step * Math.ceil(max / step), step * 2);
    const ticks = [];
    for (let value = 0; value <= top; value += step) ticks.push(value);
    return { top, ticks };
});

const x = (index) => PAD.left + (props.points.length > 1 ? (index / (props.points.length - 1)) * innerWidth.value : 0);
const y = (value) => PAD.top + innerHeight - (value / yScale.value.top) * innerHeight;

const paths = computed(() => SERIES.map(series => ({
    ...series,
    d: props.points.map((point, index) => `${index ? 'L' : 'M'}${x(index).toFixed(1)},${y(point[series.key]).toFixed(1)}`).join(' '),
})));

const lastIndex = computed(() => props.points.length - 1);
const last = computed(() => props.points[lastIndex.value] || { created: 0, resolved: 0 });

// End labels only when the two line ends are far enough apart; otherwise legend + tooltip carry them
const showEndLabels = computed(() => Math.abs(y(last.value.created) - y(last.value.resolved)) >= 16);

const dayFormat = computed(() => new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'short' }));
const longDayFormat = computed(() => new Intl.DateTimeFormat(locale.value, { weekday: 'short', day: 'numeric', month: 'long' }));
const parseDay = (date) => new Date(`${date}T12:00:00`);
const formatDay = (date) => dayFormat.value.format(parseDay(date));

const xTicks = computed(() => {
    const count = props.points.length;
    if (!count) return [];
    const indexes = [...new Set([0, Math.round((count - 1) / 2), count - 1])];
    return indexes.map(index => ({ index, label: formatDay(props.points[index].date) }));
});

const active = computed(() => (activeIndex.value === null ? null : props.points[activeIndex.value]));

const tooltipStyle = computed(() => {
    if (activeIndex.value === null) return {};
    const left = x(activeIndex.value);
    // Flip to the left of the crosshair in the right half
    return left > width.value / 2
        ? { right: `${width.value - left + 12}px`, top: `${PAD.top}px` }
        : { left: `${left + 12}px`, top: `${PAD.top}px` };
});

const onPointerMove = (event) => {
    const rect = event.currentTarget.getBoundingClientRect();
    const relative = (event.clientX - rect.left - PAD.left) / innerWidth.value;
    activeIndex.value = Math.min(Math.max(Math.round(relative * lastIndex.value), 0), lastIndex.value);
};

const onKeydown = (event) => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const current = activeIndex.value ?? lastIndex.value;
    activeIndex.value = {
        ArrowLeft: Math.max(current - 1, 0),
        ArrowRight: Math.min(current + 1, lastIndex.value),
        Home: 0,
        End: lastIndex.value,
    }[event.key];
};

const totals = computed(() => ({
    created: props.points.reduce((sum, p) => sum + p.created, 0),
    resolved: props.points.reduce((sum, p) => sum + p.resolved, 0),
}));
</script>

<template>
    <div class="trend-chart">
        <div class="d-flex align-center flex-wrap ga-4 mb-2">
            <!-- Legend: line keys mirror the marks -->
            <div class="d-flex align-center flex-wrap ga-4 text-body-2">
                <span v-for="series in SERIES" :key="series.key" class="d-inline-flex align-center ga-2">
                    <span class="line-key" :class="series.className" />
                    {{ t(`tickets.overview.trend.${series.key}`) }}
                    <strong>{{ totals[series.key] }}</strong>
                </span>
            </div>
            <v-spacer />
            <v-btn-toggle v-model="view" mandatory density="compact" variant="outlined" divided>
                <v-btn value="chart" size="small" icon="mdi-chart-line" :title="t('tickets.overview.trend.chartView')" :aria-label="t('tickets.overview.trend.chartView')" />
                <v-btn value="table" size="small" icon="mdi-table" :title="t('tickets.overview.trend.tableView')" :aria-label="t('tickets.overview.trend.tableView')" />
            </v-btn-toggle>
        </div>

        <div v-show="view === 'chart'" ref="plotRef" class="plot">
            <svg
                v-if="width"
                :width="width"
                :height="HEIGHT"
                role="img"
                :aria-label="t('tickets.overview.trend.aria', { created: totals.created, resolved: totals.resolved, days: points.length })"
            >
                <!-- Grid and y ticks -->
                <g v-for="tick in yScale.ticks" :key="tick">
                    <line class="grid" :x1="PAD.left" :x2="PAD.left + innerWidth" :y1="y(tick)" :y2="y(tick)" />
                    <text class="axis-label" :x="PAD.left - 8" :y="y(tick)" text-anchor="end" dominant-baseline="middle">{{ tick }}</text>
                </g>
                <text
                    v-for="tick in xTicks"
                    :key="tick.index"
                    class="axis-label"
                    :x="x(tick.index)"
                    :y="HEIGHT - 8"
                    :text-anchor="tick.index === 0 ? 'start' : tick.index === lastIndex ? 'end' : 'middle'"
                >{{ tick.label }}</text>

                <!-- Lines -->
                <path v-for="path in paths" :key="path.key" :d="path.d" class="line" :class="path.className" />

                <!-- Crosshair -->
                <g v-if="active">
                    <line class="crosshair" :x1="x(activeIndex)" :x2="x(activeIndex)" :y1="PAD.top" :y2="PAD.top + innerHeight" />
                    <circle
                        v-for="series in SERIES"
                        :key="series.key"
                        class="dot"
                        :class="series.className"
                        :cx="x(activeIndex)"
                        :cy="y(active[series.key])"
                        r="4"
                    />
                </g>

                <!-- End markers and direct labels -->
                <g v-else>
                    <circle
                        v-for="series in SERIES"
                        :key="series.key"
                        class="dot"
                        :class="series.className"
                        :cx="x(lastIndex)"
                        :cy="y(last[series.key])"
                        r="4"
                    />
                </g>
                <template v-if="showEndLabels">
                    <text
                        v-for="series in SERIES"
                        :key="series.key"
                        class="end-label"
                        :x="x(lastIndex) + 10"
                        :y="y(last[series.key])"
                        dominant-baseline="middle"
                    >{{ last[series.key] }} {{ t(`tickets.overview.trend.${series.key}`) }}</text>
                </template>

                <!-- Hit area: the whole plot, keyboard focusable -->
                <rect
                    class="hit-area"
                    :x="0"
                    :y="0"
                    :width="width"
                    :height="HEIGHT"
                    tabindex="0"
                    :aria-label="t('tickets.overview.trend.keyboardHint')"
                    @pointermove="onPointerMove"
                    @pointerleave="activeIndex = null"
                    @keydown="onKeydown"
                    @blur="activeIndex = null"
                />
            </svg>

            <div v-if="active" class="chart-tooltip" :style="tooltipStyle" role="status">
                <div class="text-caption text-medium-emphasis mb-1">{{ longDayFormat.format(parseDay(active.date)) }}</div>
                <div v-for="series in SERIES" :key="series.key" class="d-flex align-center ga-2 text-body-2">
                    <span class="line-key" :class="series.className" />
                    <strong>{{ active[series.key] }}</strong>
                    <span class="text-medium-emphasis">{{ t(`tickets.overview.trend.${series.key}`) }}</span>
                </div>
            </div>
        </div>

        <div v-if="view === 'table'" class="table-wrap">
            <v-table density="compact">
                <thead>
                    <tr>
                        <th>{{ t('tickets.overview.trend.day') }}</th>
                        <th class="text-end">{{ t('tickets.overview.trend.created') }}</th>
                        <th class="text-end">{{ t('tickets.overview.trend.resolved') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="point in [...points].reverse()" :key="point.date">
                        <td>{{ longDayFormat.format(parseDay(point.date)) }}</td>
                        <td class="text-end">{{ point.created }}</td>
                        <td class="text-end">{{ point.resolved }}</td>
                    </tr>
                </tbody>
            </v-table>
        </div>
    </div>
</template>

<style scoped>
/* Categorical slots 1 and 2, validated against the app's light and dark card surfaces */
.trend-chart {
    --series-created: #2a78d6;
    --series-resolved: #eb6834;
}

:global(.v-theme--dark) .trend-chart {
    --series-created: #3987e5;
    --series-resolved: #d95926;
}

.series-created {
    --series: var(--series-created);
}

.series-resolved {
    --series: var(--series-resolved);
}

.plot {
    position: relative;
    height: 220px;
}

.line {
    fill: none;
    stroke: var(--series);
    stroke-width: 2;
    stroke-linejoin: round;
    stroke-linecap: round;
}

.dot {
    fill: var(--series);
    stroke: rgb(var(--v-theme-surface));
    stroke-width: 2;
}

.grid {
    stroke: rgba(var(--v-theme-on-surface), 0.08);
    stroke-width: 1;
}

.crosshair {
    stroke: rgba(var(--v-theme-on-surface), 0.3);
    stroke-width: 1;
}

.axis-label {
    fill: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 11px;
}

.end-label {
    fill: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-size: 12px;
}

.hit-area {
    fill: transparent;
    outline: none;
}

.hit-area:focus-visible {
    stroke: rgb(var(--v-theme-primary));
    stroke-width: 2;
}

.line-key {
    display: inline-block;
    width: 14px;
    height: 2px;
    border-radius: 1px;
    background: var(--series);
}

.chart-tooltip {
    position: absolute;
    pointer-events: none;
    padding: 8px 12px;
    border-radius: 8px;
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    white-space: nowrap;
}

.table-wrap {
    max-height: 220px;
    overflow-y: auto;
}
</style>
