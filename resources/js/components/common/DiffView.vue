<template>
    <div class="diff-view">
        <!-- Summary bar: what is being compared, and how much moved -->
        <div class="diff-toolbar">
            <div class="diff-labels">
                <span class="diff-label diff-label--old" :title="oldLabel">{{ oldLabel }}</span>
                <v-icon size="16" class="mx-1">mdi-arrow-right</v-icon>
                <span class="diff-label diff-label--new" :title="newLabel">{{ newLabel }}</span>
            </div>

            <v-spacer />

            <div class="diff-stats">
                <span v-if="stats.added" class="diff-stat diff-stat--added">+{{ stats.added }}</span>
                <span v-if="stats.removed" class="diff-stat diff-stat--removed">−{{ stats.removed }}</span>
                <span v-if="stats.changed" class="diff-stat diff-stat--changed">~{{ stats.changed }}</span>
            </div>

            <v-btn-toggle
                v-if="allowModeSwitch"
                :model-value="activeMode"
                density="compact"
                variant="outlined"
                divided
                mandatory
                class="diff-mode"
                @update:model-value="setMode"
            >
                <v-btn value="unified" size="small" :title="$t('diff.unified')">
                    <v-icon size="18">mdi-format-align-justify</v-icon>
                </v-btn>
                <v-btn value="split" size="small" :title="$t('diff.split')">
                    <v-icon size="18">mdi-format-columns</v-icon>
                </v-btn>
            </v-btn-toggle>
        </div>

        <div v-if="isEmpty" class="diff-empty text-body-2 text-medium-emphasis">
            {{ emptyText || $t('diff.identical') }}
        </div>

        <!-- Split: old on the left, new on the right -->
        <div v-else-if="effectiveMode === 'split'" class="diff-body diff-body--split">
            <template v-for="(entry, index) in visibleRows" :key="index">
                <button
                    v-if="entry.type === 'gap'"
                    type="button"
                    class="diff-row diff-row--gap"
                    @click="expandGap(index)"
                >
                    <v-icon size="16" class="mr-1">mdi-unfold-more-horizontal</v-icon>
                    {{ $t('diff.unchangedLines', { count: entry.count }, entry.count) }}
                </button>

                <div v-else class="diff-row diff-row--split">
                    <div class="diff-side" :class="sideClass(entry, 'old')">
                        <span class="diff-gutter">{{ entry.oldNumber ?? '' }}</span>
                        <span class="diff-text">
                            <template v-if="entry.type === 'modified'">
                                <span
                                    v-for="(part, partIndex) in words(entry).old"
                                    :key="partIndex"
                                    :class="{ 'diff-word diff-word--removed': part.changed }"
                                >{{ part.text }}</span>
                            </template>
                            <template v-else>{{ entry.oldLine }}</template>
                        </span>
                    </div>

                    <div class="diff-side" :class="sideClass(entry, 'new')">
                        <span class="diff-gutter">{{ entry.newNumber ?? '' }}</span>
                        <span class="diff-text">
                            <template v-if="entry.type === 'modified'">
                                <span
                                    v-for="(part, partIndex) in words(entry).new"
                                    :key="partIndex"
                                    :class="{ 'diff-word diff-word--added': part.changed }"
                                >{{ part.text }}</span>
                            </template>
                            <template v-else>{{ entry.newLine }}</template>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Unified: removals above the additions that replaced them -->
        <div v-else class="diff-body diff-body--unified">
            <template v-for="(entry, index) in visibleRows" :key="index">
                <button
                    v-if="entry.type === 'gap'"
                    type="button"
                    class="diff-row diff-row--gap"
                    @click="expandGap(index)"
                >
                    <v-icon size="16" class="mr-1">mdi-unfold-more-horizontal</v-icon>
                    {{ $t('diff.unchangedLines', { count: entry.count }, entry.count) }}
                </button>

                <template v-else>
                    <div
                        v-for="line in unifiedLines(entry)"
                        :key="`${index}-${line.side}`"
                        class="diff-row diff-row--unified"
                        :class="`diff-row--${line.state}`"
                    >
                        <span class="diff-gutter">{{ line.number ?? '' }}</span>
                        <span class="diff-marker">{{ line.marker }}</span>
                        <span class="diff-text">
                            <template v-if="line.parts">
                                <span
                                    v-for="(part, partIndex) in line.parts"
                                    :key="partIndex"
                                    :class="part.changed ? `diff-word diff-word--${line.state}` : null"
                                >{{ part.text }}</span>
                            </template>
                            <template v-else>{{ line.text }}</template>
                        </span>
                    </div>
                </template>
            </template>
        </div>
    </div>
</template>

<script>
import { buildDiff, diffWords } from '@/utils/diff.js';

/**
 * Line diff of two texts, the way a code review shows one.
 *
 *   <diff-view :old-value="before.content" :new-value="after.content"
 *              :old-label="…" :new-label="…" />
 *
 * Html input is flattened to one line per block first (see utils/diff.js), so
 * what the reader compares is the text, not the markup. Long runs of untouched
 * text collapse into a gap row that expands on click.
 */
export default {
    name: 'DiffView',
    props: {
        oldValue: { type: String, default: '' },
        newValue: { type: String, default: '' },
        oldLabel: { type: String, default: '' },
        newLabel: { type: String, default: '' },
        // Input is html and needs flattening before it can be compared
        html: { type: Boolean, default: true },
        // 'unified' or 'split'
        mode: { type: String, default: 'unified' },
        allowModeSwitch: { type: Boolean, default: true },
        // Unchanged lines kept either side of a change
        context: { type: Number, default: 3 },
        emptyText: { type: String, default: '' },
    },
    emits: ['update:mode'],
    data() {
        return {
            innerMode: this.mode,
            // Gaps the reader opened, by their position in the collapsed list
            expanded: [],
            narrow: false,
        };
    },
    computed: {
        diff() {
            return buildDiff(this.oldValue, this.newValue, { html: this.html, context: this.context });
        },
        stats() {
            return this.diff.stats;
        },
        isEmpty() {
            return this.diff.isEmpty;
        },
        activeMode() {
            return this.innerMode === 'split' ? 'split' : 'unified';
        },
        // Two columns do not fit on a phone, whatever the toggle says.
        effectiveMode() {
            return this.narrow ? 'unified' : this.activeMode;
        },
        visibleRows() {
            return this.diff.collapsed.flatMap((entry, index) => (
                entry.type === 'gap' && this.expanded.includes(index) ? entry.rows : [entry]
            ));
        },
    },
    watch: {
        mode(value) {
            this.innerMode = value;
        },
        // A different comparison starts over with everything collapsed again.
        diff() {
            this.expanded = [];
            this.wordCache = new Map();
        },
    },
    created() {
        // Deliberately not in data(): the word diff of a row is filled in
        // while that row renders, and writing to reactive state mid-render
        // would ask for another render.
        this.wordCache = new Map();
    },
    mounted() {
        this.measure();
        window.addEventListener('resize', this.measure);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.measure);
    },
    methods: {
        measure() {
            this.narrow = window.innerWidth < 960;
        },
        setMode(value) {
            if (!value) return;
            this.innerMode = value;
            this.$emit('update:mode', value);
        },
        expandGap(index) {
            // visibleRows is flattened, so map back to the collapsed position.
            const entry = this.visibleRows[index];
            if (!entry || entry.type !== 'gap') return;

            const position = this.diff.collapsed.indexOf(entry);
            if (position !== -1 && !this.expanded.includes(position)) {
                this.expanded = [...this.expanded, position];
            }
        },
        /** Word level split of a modified row, computed once per row. */
        words(entry) {
            const key = `${entry.oldNumber}:${entry.newNumber}`;
            if (!this.wordCache.has(key)) {
                this.wordCache.set(key, diffWords(entry.oldLine, entry.newLine));
            }
            return this.wordCache.get(key);
        },
        sideClass(entry, side) {
            if (entry.type === 'equal') return null;
            if (entry.type === 'modified') return side === 'old' ? 'diff-side--removed' : 'diff-side--added';
            if (entry.type === 'removed') return side === 'old' ? 'diff-side--removed' : 'diff-side--blank';
            return side === 'new' ? 'diff-side--added' : 'diff-side--blank';
        },
        /** One diff row as the one or two lines the unified view prints. */
        unifiedLines(entry) {
            if (entry.type === 'equal') {
                return [{ side: 'equal', state: 'equal', marker: ' ', number: entry.newNumber, text: entry.newLine }];
            }
            if (entry.type === 'removed') {
                return [{ side: 'old', state: 'removed', marker: '−', number: entry.oldNumber, text: entry.oldLine }];
            }
            if (entry.type === 'added') {
                return [{ side: 'new', state: 'added', marker: '+', number: entry.newNumber, text: entry.newLine }];
            }

            const parts = this.words(entry);
            return [
                { side: 'old', state: 'removed', marker: '−', number: entry.oldNumber, parts: parts.old },
                { side: 'new', state: 'added', marker: '+', number: entry.newNumber, parts: parts.new },
            ];
        },
    },
};
</script>

<style scoped>
.diff-view {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 6px;
    overflow: hidden;
    background: rgb(var(--v-theme-surface));
}

.diff-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding: 8px 12px;
    background: rgba(var(--v-theme-on-surface), 0.04);
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.diff-labels {
    display: flex;
    align-items: center;
    min-width: 0;
    font-size: 0.8125rem;
}

.diff-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 180px;
    font-weight: 500;
}

.diff-label--old {
    color: rgb(var(--v-theme-error));
}

.diff-label--new {
    color: rgb(var(--v-theme-success));
}

.diff-stats {
    display: flex;
    gap: 8px;
    font-family: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;
    font-size: 0.8125rem;
}

.diff-stat--added {
    color: rgb(var(--v-theme-success));
}

.diff-stat--removed {
    color: rgb(var(--v-theme-error));
}

.diff-stat--changed {
    color: rgb(var(--v-theme-warning));
}

.diff-empty {
    padding: 24px 16px;
    text-align: center;
}

.diff-body {
    max-height: 55vh;
    overflow: auto;
    font-family: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;
    font-size: 0.8125rem;
    line-height: 1.6;
}

.diff-row {
    display: flex;
    align-items: flex-start;
    width: 100%;
}

.diff-row--gap {
    justify-content: center;
    align-items: center;
    padding: 4px 8px;
    background: rgba(var(--v-theme-on-surface), 0.04);
    color: rgba(var(--v-theme-on-surface), 0.6);
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    cursor: pointer;
    font: inherit;
}

.diff-row--gap:hover {
    background: rgba(var(--v-theme-primary), 0.08);
}

.diff-gutter {
    flex: 0 0 44px;
    padding: 2px 8px;
    text-align: right;
    color: rgba(var(--v-theme-on-surface), 0.38);
    user-select: none;
}

.diff-marker {
    flex: 0 0 16px;
    padding: 2px 0;
    text-align: center;
    user-select: none;
}

.diff-text {
    flex: 1 1 auto;
    padding: 2px 8px 2px 4px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

/* Unified rows */
.diff-row--added {
    background: rgba(var(--v-theme-success), 0.12);
}

.diff-row--removed {
    background: rgba(var(--v-theme-error), 0.12);
}

/* Split rows */
.diff-row--split {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.4);
}

.diff-side {
    display: flex;
    align-items: flex-start;
    width: 50%;
    min-width: 0;
}

.diff-side:first-child {
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.diff-side--added {
    background: rgba(var(--v-theme-success), 0.12);
}

.diff-side--removed {
    background: rgba(var(--v-theme-error), 0.12);
}

.diff-side--blank {
    background: rgba(var(--v-theme-on-surface), 0.04);
}

/* Changed words inside an edited line */
.diff-word--added {
    background: rgba(var(--v-theme-success), 0.3);
    border-radius: 2px;
}

.diff-word--removed {
    background: rgba(var(--v-theme-error), 0.3);
    border-radius: 2px;
    text-decoration: line-through;
    text-decoration-color: rgba(var(--v-theme-error), 0.6);
}
</style>
