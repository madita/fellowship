<template>
    <div>
    <page-header
        fluid
        :title="$t('dashboard.welcomeBack', { username: user?.username || 'User' })"
        :subtitle="$t('dashboard.customizeDashboard')"
        icon="mdi-view-dashboard"
    >
        <template #actions>
            <v-btn
                color="primary"
                variant="elevated"
                prepend-icon="mdi-widgets"
                @click="showWidgetPanel = true"
            >
                {{ $t('dashboard.addWidgets') }}
            </v-btn>
            <v-btn
                variant="tonal"
                prepend-icon="mdi-restore"
                @click="resetLayout"
            >
                {{ $t('dashboard.resetLayout') }}
            </v-btn>
        </template>
    </page-header>

    <v-container fluid class="dashboard-container">
        <loading-state v-if="loadingLayout" />

        <!-- Empty dashboard -->
        <empty-state
            v-else-if="activeWidgets.length === 0"
            icon="mdi-view-dashboard-outline"
            :title="$t('dashboard.emptyTitle')"
            :text="$t('dashboard.emptyHint')"
        >
            <template #actions>
                <v-btn color="primary" variant="flat" prepend-icon="mdi-widgets" @click="showWidgetPanel = true">
                    {{ $t('dashboard.addWidgets') }}
                </v-btn>
            </template>
        </empty-state>

        <!-- Drag & Drop Widget Grid -->
        <div
            ref="widgetGrid"
            class="widget-grid"
            :class="{ 'widget-grid--dragging': draggingWidgetId }"
            :style="gridStyle"
            @dragover.prevent="onGridDragOver"
            @dragleave="onGridDragLeave"
            @drop="onDrop"
        >
            <!-- The cells that are available while a widget is being dragged -->
            <div
                v-if="draggingWidgetId"
                class="grid-cells"
                :style="{ width: `${pxOfCells(gridColumns)}px` }"
            ></div>

            <!-- Where the dragged widget will land -->
            <div
                v-if="dropPreview"
                class="drop-marker"
                :style="dropPreviewStyle"
            >
                <v-icon size="28">mdi-arrow-down-bold-box-outline</v-icon>
                <span class="text-caption font-weight-medium mt-1">{{ $t('dashboard.dropHere') }}</span>
            </div>

            <div
                v-for="widget in activeWidgets"
                :key="widget.id"
                :class="[
          'widget-container',
          `widget-size-${widget.size}`,
          `widget-height-${widget.height || 'single'}`,
          { 'widget-dragging': widget.id === draggingWidgetId }
        ]"
                :style="getWidgetStyle(widget)"
                :data-widget-id="widget.id"
                draggable="true"
                @dragstart="onDragStart(widget, $event)"
                @dragend="onDragEnd"
            >
                <v-card
                    class="widget-card h-100"
                    variant="elevated"
                    :class="{ 'dragging': widget.id === draggingWidgetId }"
                >
                    <!-- Widget Header -->
                    <v-card-title class="widget-header d-flex align-center pa-4 pb-2">
                        <v-avatar :color="widget.color" size="32" class="mr-3">
                            <v-icon color="white" size="18">{{ definition(widget).icon }}</v-icon>
                        </v-avatar>
                        <div class="flex-grow-1">
                            <div class="text-subtitle-1 font-weight-medium">{{ widgetTitle(widget) }}</div>
                            <div class="text-caption text-medium-emphasis">{{ widget.subtitle }}</div>
                        </div>
                        <v-menu>
                            <template v-slot:activator="{ props }">
                                <v-btn
                                    icon="mdi-dots-vertical"
                                    size="small"
                                    variant="text"
                                    v-bind="props"
                                    class="drag-handle"
                                ></v-btn>
                            </template>
                            <v-list density="compact">
                                <v-list-item
                                    prepend-icon="mdi-refresh"
                                    :title="$t('dashboard.refresh')"
                                    @click="refreshWidget(widget.id)"
                                ></v-list-item>
                                <v-list-item
                                    prepend-icon="mdi-cog"
                                    :title="$t('dashboard.settings')"
                                    @click="openWidgetSettings(widget.id)"
                                ></v-list-item>
                                <v-list-item
                                    prepend-icon="mdi-close"
                                    :title="$t('dashboard.remove')"
                                    @click="removeWidget(widget.id)"
                                ></v-list-item>
                            </v-list>
                        </v-menu>
                    </v-card-title>

                    <!-- Widget Content: each widget loads its own live data -->
                    <v-card-text class="widget-content pa-4 pt-2">
                        <component
                            :is="definition(widget).component"
                            :widget-config="widget.config"
                            :columns="columnsFor(widget)"
                            :rows="rowsOf(widget.height)"
                            :refresh-key="widget.refreshKey"
                            @update-meta="updateWidgetMeta(widget.id, $event)"
                        />
                    </v-card-text>

                    <!-- Widget Action -->
                    <v-card-actions v-if="definition(widget).action" class="pa-4 pt-0">
                        <v-btn
                            :color="widget.color"
                            variant="elevated"
                            size="small"
                            :prepend-icon="definition(widget).action.icon"
                            :to="definition(widget).action.to"
                            block
                        >
                            {{ $t(`dashboard.widgets.${widget.type}.action`) }}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </div>
        </div>

        <!-- Widget Panel Dialog -->
        <v-dialog v-model="showWidgetPanel" max-width="900">
            <v-card>
                <v-card-title class="text-h6 d-flex align-center">
                    <v-icon color="primary" class="mr-2">mdi-widgets</v-icon>
                    {{ $t('dashboard.availableWidgets') }}
                </v-card-title>
                <v-divider />

                <v-card-text>
                    <v-row>
                        <v-col
                            v-for="widget in availableWidgets"
                            :key="widget.type"
                            cols="12" sm="6" md="4"
                        >
                            <v-card
                                class="widget-preview"
                                variant="outlined"
                                hover
                                @click="addWidget(widget.type)"
                            >
                                <v-card-text class="text-center pa-4">
                                    <v-avatar :color="widget.color" size="48" class="mb-3">
                                        <v-icon color="white" size="24">{{ widget.icon }}</v-icon>
                                    </v-avatar>
                                    <div class="text-subtitle-1 font-weight-medium mb-1">
                                        {{ $t(`dashboard.widgets.${widget.type}.title`) }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis mb-2">
                                        {{ $t(`dashboard.widgets.${widget.type}.description`) }}
                                    </div>
                                    <v-chip v-if="widget.count" color="primary" variant="tonal" size="small">
                                        {{ $t('dashboard.onDashboard', { count: widget.count }) }}
                                    </v-chip>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="showWidgetPanel = false">{{ $t('common.close') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Widget Settings Dialog -->
        <v-dialog v-model="showWidgetSettings" max-width="600">
            <v-card v-if="selectedWidget">
                <v-card-title class="text-h6">
                    {{ $t('dashboard.widgetSettings') }}: {{ widgetTitle(selectedWidget) }}
                </v-card-title>
                <v-divider />

                <v-card-text>
                    <v-form>
                        <v-text-field
                            v-model="selectedWidget.title"
                            :label="$t('dashboard.widgetTitle')"
                            :placeholder="$t(`dashboard.widgets.${selectedWidget.type}.title`)"
                            variant="outlined"
                            clearable
                            class="mb-4"
                        ></v-text-field>

                        <v-select
                            v-model="selectedWidget.size"
                            :items="widgetSizes"
                            :label="$t('dashboard.widgetSize')"
                            variant="outlined"
                            class="mb-4"
                        ></v-select>

                        <v-select
                            v-model="selectedWidget.height"
                            :items="widgetHeights"
                            :label="$t('dashboard.widgetHeight')"
                            variant="outlined"
                            class="mb-4"
                        ></v-select>

                        <v-select
                            v-for="setting in widgetSettings(selectedWidget)"
                            :key="setting.key"
                            v-model="selectedWidget.config[setting.key]"
                            :items="setting.items"
                            :label="$t(`dashboard.widgets.${selectedWidget.type}.settings.${setting.key}.label`)"
                            variant="outlined"
                            class="mb-4"
                        ></v-select>

                        <v-text-field
                            v-if="selectedWidget.type !== 'stats'"
                            v-model.number="selectedWidget.config.limit"
                            type="number"
                            min="1"
                            max="20"
                            :label="$t('dashboard.widgetLimit')"
                            variant="outlined"
                            class="mb-4"
                        ></v-text-field>

                        <v-color-picker
                            v-model="selectedWidget.color"
                            hide-inputs
                            class="mb-4"
                        ></v-color-picker>
                    </v-form>
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="showWidgetSettings = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveWidgetSettings">{{ $t('common.save') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
    </div>
</template>

<script>
import axios from 'axios';
import { useUserStore } from '@/store/userStore.js';
import { useSettingsStore } from '@/store/settingStore.js';
import { WIDGET_TYPES, DEFAULT_LAYOUT, LAYOUT_STORAGE_KEY, isWidgetEnabled } from '@/configs/dashboardWidgets.js';
import EventsWidget from '@/components/dashboard/EventsWidget.vue';
import WikiWidget from '@/components/dashboard/WikiWidget.vue';
import NotificationsWidget from '@/components/dashboard/NotificationsWidget.vue';
import StatsWidget from '@/components/dashboard/StatsWidget.vue';
import TicketsWidget from '@/components/dashboard/TicketsWidget.vue';
import ForumWidget from '@/components/dashboard/ForumWidget.vue';
import ConversationsWidget from '@/components/dashboard/ConversationsWidget.vue';
import SandboxWidget from '@/components/dashboard/SandboxWidget.vue';
import GalleryWidget from '@/components/dashboard/GalleryWidget.vue';
import TicketOverviewWidget from '@/components/dashboard/TicketOverviewWidget.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

// Widgets are all one row tall; only the column span differs. Older
// layouts may still carry the removed "small" size.
const SIZE_ALIASES = { small: 'medium' };
const normalizeSize = size => SIZE_ALIASES[size] || size || 'medium';
// Grid columns a size spans, and rows a height spans.
const SIZE_COLUMNS = { medium: 1, large: 2, xl: 3 };
const normalizeHeight = height => (height === 'double' ? 'double' : 'single');
const rowsOf = height => (normalizeHeight(height) === 'double' ? 2 : 1);
const columnsOf = size => SIZE_COLUMNS[normalizeSize(size)] || 1;
const GRID_CELL = 300;
const GRID_GAP = 20;
const GRID_STEP = GRID_CELL + GRID_GAP;
const pxOfCells = n => n * GRID_STEP - GRID_GAP;
// Do two widgets' cell rectangles intersect?
const overlaps = (a, b, span) =>
    a.position.x < b.position.x + span(b) &&
    b.position.x < a.position.x + span(a) &&
    a.position.y < b.position.y + rowsOf(b.height) &&
    b.position.y < a.position.y + rowsOf(a.height);

/**
 * Personal dashboard: a drag & drop grid of widgets, each showing live
 * data of one feature (see configs/dashboardWidgets.js). The layout —
 * which widgets, where, how big, with which settings — is stored on the
 * user's account. Confirmations and feedback use the app-wide `$dialog`.
 */
export default {
    name: 'DynamicDashboard',
    components: {
        PageHeader,
        EmptyState,
        LoadingState,
        EventsWidget,
        WikiWidget,
        NotificationsWidget,
        StatsWidget,
        TicketsWidget,
        ForumWidget,
        ConversationsWidget,
        SandboxWidget,
        GalleryWidget,
        TicketOverviewWidget
    },
    data() {
        return {
            showWidgetPanel: false,
            showWidgetSettings: false,
            selectedWidget: null,
            draggingWidgetId: null,
            // Pointer offset inside the dragged widget, so the marker follows
            // the widget's top-left corner rather than the cursor.
            dragOffset: { x: 0, y: 0 },
            // Cell the dragged widget will land in: { x, y, cols, rows }
            dropPreview: null,
            // Width of the grid container, kept current by a ResizeObserver;
            // decides how many columns fit.
            gridWidth: 0,
            gridObserver: null,
            activeWidgets: [],
            loadingLayout: true,
            saveTimer: null,
            // Saving is automatic, so a failure is reported once and not on
            // every following auto-save until one succeeds again.
            saveErrorShown: false,
        }
    },
    computed: {
        user() {
            return useUserStore().user;
        },
        widgetSizes() {
            return [
                { title: this.$t('dashboard.sizeStandard'), value: 'medium' },
                { title: this.$t('dashboard.sizeWide'), value: 'large' },
                { title: this.$t('dashboard.sizeExtraWide'), value: 'xl' }
            ];
        },
        // As many columns as fit in the grid's width, at least one.
        gridColumns() {
            const width = this.gridWidth || (this.$vuetify.display.width - 48);
            return Math.max(1, Math.floor((width + GRID_GAP) / GRID_STEP));
        },
        // The grid grows with its lowest widget (plus one spare row while
        // dragging, so a widget can be dropped below everything).
        gridStyle() {
            const rows = Math.max(1, ...this.activeWidgets.map(w => w.position.y + rowsOf(w.height)));
            const spare = this.draggingWidgetId ? 1 : 0;
            return { height: `${pxOfCells(rows + spare)}px` };
        },
        dropPreviewStyle() {
            const p = this.dropPreview;
            if (!p) return {};
            return {
                left: `${p.x * GRID_STEP}px`,
                top: `${p.y * GRID_STEP}px`,
                width: `${pxOfCells(p.cols)}px`,
                height: `${pxOfCells(p.rows)}px`,
            };
        },
        widgetHeights() {
            return [
                { title: this.$t('dashboard.heightSingle'), value: 'single' },
                { title: this.$t('dashboard.heightDouble'), value: 'double' }
            ];
        },
        // Widgets of enabled features, with how many of each are already placed.
        availableWidgets() {
            const settings = useSettingsStore();
            return Object.entries(WIDGET_TYPES)
                .filter(([, def]) => isWidgetEnabled(def, settings))
                .map(([type, def]) => ({
                    type,
                    icon: def.icon,
                    color: def.color,
                    count: this.activeWidgets.filter(w => w.type === type).length,
                }));
        }
    },
    methods: {
        definition(widget) {
            return WIDGET_TYPES[widget.type];
        },

        widgetTitle(widget) {
            return widget.title || this.$t(`dashboard.widgets.${widget.type}.title`);
        },

        createWidget(type, saved = {}) {
            const def = WIDGET_TYPES[type];
            // Widget-specific options start at their declared defaults.
            const defaults = Object.fromEntries((def.settings || []).map(s => [s.key, s.default]));
            return {
                id: saved.id || `${type}-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
                type,
                title: saved.title || null,
                subtitle: '',
                color: saved.color || def.color,
                size: normalizeSize(saved.size || def.size),
                height: normalizeHeight(saved.height),
                position: saved.position || this.findAvailablePosition(
                    Math.min(columnsOf(saved.size || def.size), this.gridColumns),
                    rowsOf(saved.height)
                ),
                config: { limit: 5, ...defaults, ...(saved.config || {}) },
                refreshKey: 0,
            };
        },

        // Options of a widget type for the settings dialog, without the
        // choices reserved for admins.
        widgetSettings(widget) {
            const isAdmin = useUserStore().hasRole('admin');
            return (WIDGET_TYPES[widget.type].settings || []).map(setting => ({
                ...setting,
                items: setting.items
                    .filter(item => !item.adminOnly || isAdmin)
                    .map(item => ({
                        value: item.value,
                        title: this.$t(`dashboard.widgets.${widget.type}.settings.${setting.key}.items.${item.value}`),
                    })),
            }));
        },

        pxOfCells,
        rowsOf,

        // Columns a widget occupies on this screen: its size, capped to
        // what the grid can show.
        spanOf(widget) {
            return Math.min(columnsOf(widget.size), this.gridColumns);
        },

        getWidgetStyle(widget) {
            return {
                left: `${widget.position.x * GRID_STEP}px`,
                top: `${widget.position.y * GRID_STEP}px`,
                width: `${pxOfCells(this.spanOf(widget))}px`,
                height: `${pxOfCells(rowsOf(widget.height))}px`,
                zIndex: widget.id === this.draggingWidgetId ? 1000 : 1
            };
        },

        onDragStart(widget, event) {
            this.draggingWidgetId = widget.id;
            const rect = event.currentTarget.getBoundingClientRect();
            this.dragOffset = { x: event.clientX - rect.left, y: event.clientY - rect.top };
            this.dropPreview = { ...widget.position, cols: this.spanOf(widget), rows: rowsOf(widget.height) };

            event.dataTransfer.setData('text/plain', widget.id);
            event.dataTransfer.effectAllowed = 'move';

            // Fade the source while its ghost is being dragged
            setTimeout(() => {
                event.target.style.opacity = '0.5';
            }, 0);
        },

        onDragEnd(event) {
            event.target.style.opacity = '';
            this.draggingWidgetId = null;
            this.dropPreview = null;
        },

        // Cell under the dragged widget's top-left corner, clamped so the
        // widget stays inside the grid.
        cellFromPointer(event, cols) {
            const rect = this.$refs.widgetGrid.getBoundingClientRect();
            const left = event.clientX - rect.left - this.dragOffset.x;
            const top = event.clientY - rect.top - this.dragOffset.y;
            return {
                x: Math.min(Math.max(0, Math.round(left / GRID_STEP)), Math.max(0, this.gridColumns - cols)),
                y: Math.max(0, Math.round(top / GRID_STEP)),
            };
        },

        onGridDragOver(event) {
            if (!this.draggingWidgetId || !this.dropPreview) return;
            event.dataTransfer.dropEffect = 'move';
            const cell = this.cellFromPointer(event, this.dropPreview.cols);
            if (cell.x !== this.dropPreview.x || cell.y !== this.dropPreview.y) {
                this.dropPreview = { ...this.dropPreview, ...cell };
            }
        },

        onGridDragLeave(event) {
            // Leaving the grid itself (not moving between its children)
            if (!this.$refs.widgetGrid.contains(event.relatedTarget)) {
                this.dropPreview = null;
            }
        },

        onDrop(event) {
            event.preventDefault();
            const widgetId = event.dataTransfer.getData('text/plain') || this.draggingWidgetId;
            const draggedWidget = this.activeWidgets.find(w => w.id === widgetId);
            if (!draggedWidget) return;

            const target = this.dropPreview
                ? { x: this.dropPreview.x, y: this.dropPreview.y }
                : this.cellFromPointer(event, this.spanOf(draggedWidget));

            draggedWidget.position = target;
            // Whatever it now covers moves out of the way; the dropped widget stays put.
            this.resolveOverlaps(draggedWidget.id);

            this.draggingWidgetId = null;
            this.dropPreview = null;
            this.saveLayout();
        },

        // Guarantee that no two widgets share a cell and none sticks out of
        // the grid. The pinned widget (just dropped or resized) keeps its
        // place; anything it collides with is moved to the nearest free spot
        // at or below its current row.
        resolveOverlaps(pinnedId = null) {
            const byPosition = () => [...this.activeWidgets].sort((a, b) =>
                a.position.y - b.position.y || a.position.x - b.position.x
            );
            const relocate = widget => {
                widget.position = this.findAvailablePosition(this.spanOf(widget), rowsOf(widget.height), {
                    except: widget.id,
                    fromY: widget.position.y,
                });
            };

            this.activeWidgets.forEach(w => {
                if (w.position.x + this.spanOf(w) > this.gridColumns || w.position.x < 0 || w.position.y < 0) {
                    relocate(w);
                }
            });

            for (let guard = 0; guard < 200; guard++) {
                const list = byPosition();
                let mover = null;
                outer: for (let i = 0; i < list.length; i++) {
                    for (let j = i + 1; j < list.length; j++) {
                        if (!overlaps(list[i], list[j], this.spanOf)) continue;
                        mover = list[i].id === pinnedId ? list[j] : list[j].id === pinnedId ? list[i] : list[j];
                        break outer;
                    }
                }
                if (!mover) return;
                relocate(mover);
            }
        },

        addWidget(type) {
            this.activeWidgets.push(this.createWidget(type));
            this.showWidgetPanel = false;
            this.saveLayout();
        },

        async removeWidget(widgetId) {
            const widget = this.activeWidgets.find(w => w.id === widgetId);
            if (!widget) return;
            const confirmed = await this.$dialog.confirmDelete(
                this.$t('dashboard.confirmRemove', { title: this.widgetTitle(widget) }),
                { title: this.$t('dashboard.remove'), confirmationText: this.$t('dialogs.confirm.remove') }
            );
            if (!confirmed) return;

            const index = this.activeWidgets.findIndex(w => w.id === widgetId);
            if (index > -1) {
                this.activeWidgets.splice(index, 1);
                this.saveLayout();
            }
        },

        // Widgets reload their data when their refresh counter changes.
        refreshWidget(widgetId) {
            const widget = this.activeWidgets.find(w => w.id === widgetId);
            if (widget) {
                widget.refreshKey++;
            }
        },

        openWidgetSettings(widgetId) {
            this.selectedWidget = this.activeWidgets.find(w => w.id === widgetId);
            this.showWidgetSettings = true;
        },

        saveWidgetSettings() {
            if (this.selectedWidget) {
                const limit = parseInt(this.selectedWidget.config.limit, 10);
                this.selectedWidget.config.limit = limit > 0 ? Math.min(limit, 20) : 5;
                if (!this.selectedWidget.title?.trim()) {
                    this.selectedWidget.title = null;
                }
            }
            this.showWidgetSettings = false;
            if (this.selectedWidget) this.resolveOverlaps(this.selectedWidget.id);
            this.saveLayout();
        },

        // Back to the default widget set and grid order — after confirming,
        // since it discards the user's customised layout.
        async resetLayout() {
            const confirmed = await this.$dialog.confirm({
                title: this.$t('dashboard.resetLayout'),
                content: this.$t('dashboard.confirmReset'),
                confirmationText: this.$t('dialogs.confirm.confirm'),
                color: 'warning',
            });
            if (confirmed) {
                this.applyDefaultLayout();
            }
        },

        applyDefaultLayout() {
            this.activeWidgets = [];
            DEFAULT_LAYOUT
                .filter(type => this.availableWidgets.some(w => w.type === type))
                .forEach(type => this.activeWidgets.push(this.createWidget(type)));
            this.activeWidgets.forEach((widget, index) => {
                widget.position = { x: index % 3, y: Math.floor(index / 3) };
            });
            this.saveLayout();
        },

        // First grid cell where a widget spanning cols × rows fits without
        // overlapping the cells the other widgets already cover.
        findAvailablePosition(cols = 1, rows = 1, { except = null, fromY = 0 } = {}) {
            const occupied = new Set();
            this.activeWidgets.forEach(w => {
                if (w.id === except) return;
                for (let dx = 0; dx < this.spanOf(w); dx++) {
                    for (let dy = 0; dy < rowsOf(w.height); dy++) {
                        occupied.add(`${w.position.x + dx},${w.position.y + dy}`);
                    }
                }
            });
            const fits = (x, y) => {
                if (x + cols > this.gridColumns) return false;
                for (let dx = 0; dx < cols; dx++) {
                    for (let dy = 0; dy < rows; dy++) {
                        if (occupied.has(`${x + dx},${y + dy}`)) return false;
                    }
                }
                return true;
            };

            for (let y = fromY; y < fromY + 50; y++) {
                for (let x = 0; x < this.gridColumns; x++) {
                    if (fits(x, y)) return { x, y };
                }
            }

            // Below everything else
            const bottom = Math.max(...this.activeWidgets.filter(w => w.id !== except).map(w => w.position.y + rowsOf(w.height)), 0);
            return { x: 0, y: bottom };
        },

        // Columns the widget really spans on this screen, so lists inside
        // can lay their items out side by side.
        columnsFor(widget) {
            if (this.$vuetify.display.width < 960) return 1;
            return this.spanOf(widget);
        },

        updateWidgetMeta(widgetId, meta) {
            const widget = this.activeWidgets.find(w => w.id === widgetId);
            if (widget && meta?.subtitle !== undefined) {
                widget.subtitle = meta.subtitle;
            }
        },

        serializeLayout() {
            return this.activeWidgets.map(w => ({
                id: w.id,
                type: w.type,
                title: w.title,
                color: w.color,
                position: w.position,
                size: w.size,
                height: w.height,
                config: w.config
            }));
        },

        // The layout is stored on the user's account so it follows them
        // across browsers and logins. Saves are coalesced (drag & drop
        // fires many) and the last one wins.
        saveLayout() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(async () => {
                try {
                    await axios.put('/api/account/dashboard/layout', { layout: this.serializeLayout() });
                    this.saveErrorShown = false;
                } catch (e) {
                    if (this.saveErrorShown) return;
                    this.saveErrorShown = true;
                    this.$dialog.warning(this.$t('dashboard.saveFailed', { error: e.response?.data?.message || e.message }));
                }
            }, 400);
        },

        applyLayout(layout) {
            const enabled = new Set(this.availableWidgets.map(w => w.type));
            layout
                .filter(saved => saved?.type && enabled.has(saved.type))
                .forEach(saved => this.activeWidgets.push(this.createWidget(saved.type, saved)));
            this.resolveOverlaps();
        },

        // Restore the account's layout; widgets of unknown (retired) or
        // disabled types are dropped. A layout left in this browser by the
        // previous localStorage-only version is adopted once, then the
        // defaults are used.
        async loadLayout() {
            this.loadingLayout = true;
            let layout = null;
            try {
                const { data } = await axios.get('/api/account/dashboard/layout');
                layout = data.data;
            } catch (e) {
                this.$dialog.error(this.$t('dashboard.layoutLoadFailed', { error: e.response?.data?.message || e.message }));
            }

            if (!Array.isArray(layout)) {
                try {
                    layout = JSON.parse(localStorage.getItem(LAYOUT_STORAGE_KEY));
                    localStorage.removeItem(LAYOUT_STORAGE_KEY);
                } catch (e) {
                    layout = null;
                }
                if (Array.isArray(layout)) {
                    this.applyLayout(layout);
                    this.saveLayout();
                } else {
                    this.applyDefaultLayout();
                }
            } else {
                this.applyLayout(layout);
            }
            this.loadingLayout = false;
        }
    },

    watch: {
        // Fewer or more columns: keep every widget inside the grid and
        // apart from the others. Not saved — the layout is only persisted
        // when the user changes something.
        gridColumns() {
            if (!this.loadingLayout) this.resolveOverlaps();
        },
    },
    mounted() {
        this.loadLayout();
        this.gridObserver = new ResizeObserver(entries => {
            this.gridWidth = entries[0]?.contentRect?.width || 0;
        });
        this.gridObserver.observe(this.$el);
    },
    beforeUnmount() {
        this.gridObserver?.disconnect();
    }
}
</script>


<style scoped>
.widget-grid {
    position: relative;
    min-height: 320px;
    width: 100%;
    transition: height 0.2s ease;
}

/* The available cells, shown while a widget is being dragged */
.grid-cells {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    pointer-events: none;
    background-image:
        repeating-linear-gradient(90deg, rgba(var(--v-theme-primary), 0.05) 0 300px, transparent 300px 320px),
        repeating-linear-gradient(180deg, rgba(var(--v-theme-primary), 0.05) 0 300px, transparent 300px 320px);
}

/* Landing spot of the dragged widget */
.drop-marker {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px dashed rgb(var(--v-theme-primary));
    border-radius: 16px;
    background: rgba(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
    pointer-events: none;
    z-index: 500;
    transition: left 0.15s ease, top 0.15s ease;
}

.widget-container {
    position: absolute;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: move;
}

/* Widget width and height come from getWidgetStyle(): one grid row per
   height step, one column per size step, capped to the columns that fit. */

.widget-card {
    border-radius: 16px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
    background-color: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.v-theme--light .widget-card {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
}

.v-theme--dark .widget-card {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
}

.widget-card:hover {
    transform: translateY(-2px);
}

.v-theme--light .widget-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12) !important;
}

.v-theme--dark .widget-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6) !important;
}

.widget-card.dragging {
    transform: rotate(2deg) scale(1.05);
    z-index: 1000;
    opacity: 0.8;
}

.v-theme--light .widget-card.dragging {
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2) !important;
}

.v-theme--dark .widget-card.dragging {
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7) !important;
}

.widget-dragging {
    z-index: 1000;
}

.widget-header {
    cursor: grab;
    background: rgba(var(--v-theme-on-surface), 0.03);
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.widget-header:active {
    cursor: grabbing;
}

/* The card is a flex column: header, scrolling content, action pinned
   to the bottom whatever the widget height. */
.widget-card {
    display: flex;
    flex-direction: column;
}

.widget-content {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}

.widget-card > .v-card-actions {
    margin-top: auto;
    flex: 0 0 auto;
}

.widget-preview {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border-radius: 12px !important;
}

.widget-preview:hover {
    transform: translateY(-4px);
}

.v-theme--light .widget-preview:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12) !important;
}

.v-theme--dark .widget-preview:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5) !important;
}

.drag-handle {
    cursor: grab;
}

.drag-handle:active {
    cursor: grabbing;
}

/* Animation for new widgets */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.widget-container {
    animation: slideInUp 0.5s ease-out;
}

/* Phones and small tablets: a plain stacked list */
@media (max-width: 960px) {
    .widget-grid {
        position: static;
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .widget-grid {
        height: auto !important;
    }

    .drop-marker {
        display: none;
    }

    .widget-container {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        min-height: 200px;
    }
}

/* Custom scrollbar for widget content */
.widget-content::-webkit-scrollbar {
    width: 4px;
}

.widget-content::-webkit-scrollbar-track {
    background: rgba(var(--v-theme-on-surface), 0.05);
    border-radius: 2px;
}

.widget-content::-webkit-scrollbar-thumb {
    background: rgba(var(--v-theme-on-surface), 0.2);
    border-radius: 2px;
}

.widget-content::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--v-theme-on-surface), 0.3);
}

/* Color picker in the settings dialog */
.v-color-picker {
    box-shadow: none !important;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
