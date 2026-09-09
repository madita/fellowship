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
            @dragover.prevent
            @drop="onDrop"
        >
            <div
                v-for="widget in activeWidgets"
                :key="widget.id"
                :class="[
          'widget-container',
          `widget-size-${widget.size}`,
          { 'widget-dragging': widget.id === draggingWidgetId }
        ]"
                :style="getWidgetStyle(widget)"
                :data-widget-id="widget.id"
                draggable="true"
                @dragstart="onDragStart(widget, $event)"
                @dragend="onDragEnd"
                @dragover="onDragOver($event, widget)"
                @dragenter="onDragEnter($event, widget)"
                @dragleave="onDragLeave($event, widget)"
            >
                <v-card
                    class="widget-card h-100"
                    variant="elevated"
                    :class="{
            'dragging': widget.id === draggingWidgetId,
            'drag-target': widget.id === dragTargetId && widget.id !== draggingWidgetId
          }"
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
            dragTargetId: null,
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
                { title: this.$t('dashboard.small'), value: 'small' },
                { title: this.$t('dashboard.medium'), value: 'medium' },
                { title: this.$t('dashboard.large'), value: 'large' },
                { title: this.$t('dashboard.extraLarge'), value: 'xl' }
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
                size: saved.size || def.size,
                position: saved.position || this.findAvailablePosition(),
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

        getWidgetStyle(widget) {
            const gridSize = 300; // Base grid size
            const gap = 20;

            return {
                left: `${widget.position.x * (gridSize + gap)}px`,
                top: `${widget.position.y * (gridSize + gap)}px`,
                zIndex: widget.id === this.draggingWidgetId ? 1000 : 1
            };
        },

        onDragStart(widget, event) {
            this.draggingWidgetId = widget.id;

            // Set drag data
            event.dataTransfer.setData('text/plain', widget.id);
            event.dataTransfer.effectAllowed = 'move';

            // Add visual feedback
            setTimeout(() => {
                event.target.style.opacity = '0.5';
            }, 0);
        },

        onDragEnd(event) {
            event.target.style.opacity = '';
            this.draggingWidgetId = null;
            this.dragTargetId = null;

            // Remove any remaining visual feedback
            document.querySelectorAll('.widget-card').forEach(card => {
                card.style.transition = '';
                card.style.transform = '';
            });
        },

        onDragOver(event, targetWidget) {
            if (this.draggingWidgetId && this.draggingWidgetId !== targetWidget.id) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            }
        },

        onDragEnter(event, targetWidget) {
            if (this.draggingWidgetId && this.draggingWidgetId !== targetWidget.id) {
                event.preventDefault();
                this.dragTargetId = targetWidget.id;

                // Add visual feedback for swap target
                const targetElement = event.currentTarget.querySelector('.widget-card');
                if (targetElement) {
                    targetElement.style.transition = 'all 0.3s ease';
                    targetElement.style.transform = 'scale(0.95)';
                    targetElement.style.opacity = '0.7';
                }
            }
        },

        onDragLeave(event, targetWidget) {
            // Only remove highlight if we're actually leaving the widget area
            const rect = event.currentTarget.getBoundingClientRect();
            const x = event.clientX;
            const y = event.clientY;

            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                if (this.dragTargetId === targetWidget.id) {
                    this.dragTargetId = null;

                    // Remove visual feedback
                    const targetElement = event.currentTarget.querySelector('.widget-card');
                    if (targetElement) {
                        targetElement.style.transform = '';
                        targetElement.style.opacity = '';
                    }
                }
            }
        },

        onDrop(event) {
            event.preventDefault();
            const widgetId = event.dataTransfer.getData('text/plain');

            if (!widgetId) return;

            const draggedWidget = this.activeWidgets.find(w => w.id === widgetId);
            if (!draggedWidget) return;

            // Calculate new position based on drop location
            const rect = this.$refs.widgetGrid.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const gridSize = 300;
            const gap = 20;

            const newPosition = {
                x: Math.max(0, Math.round(x / (gridSize + gap))),
                y: Math.max(0, Math.round(y / (gridSize + gap)))
            };

            // Store original position in case we need to revert
            const originalPosition = { ...draggedWidget.position };

            // Check if the new position is occupied by another widget
            const occupyingWidget = this.activeWidgets.find(w =>
                w.id !== widgetId &&
                w.position.x === newPosition.x &&
                w.position.y === newPosition.y
            );

            if (occupyingWidget) {
                // Swap positions instead of stacking
                occupyingWidget.position = originalPosition;
                draggedWidget.position = newPosition;

                // Add visual feedback for the swap
                this.$nextTick(() => {
                    [occupyingWidget, draggedWidget].forEach(widget => {
                        const element = document.querySelector(`[data-widget-id="${widget.id}"]`);
                        if (element) {
                            element.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                            element.style.transform = 'scale(1.05)';
                            setTimeout(() => {
                                element.style.transform = '';
                            }, 200);
                        }
                    });
                });
            } else {
                // Position is free, just move there
                draggedWidget.position = newPosition;
            }

            this.saveLayout();
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

        findAvailablePosition() {
            const occupiedPositions = new Set(
                this.activeWidgets.map(w => `${w.position.x},${w.position.y}`)
            );

            for (let y = 0; y < 10; y++) {
                for (let x = 0; x < 4; x++) {
                    if (!occupiedPositions.has(`${x},${y}`)) {
                        return { x, y };
                    }
                }
            }

            // If no free position found, place at end of grid
            const maxY = Math.max(...this.activeWidgets.map(w => w.position.y), -1);
            return { x: 0, y: maxY + 1 };
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

    mounted() {
        this.loadLayout();
    }
}
</script>


<style scoped>
.widget-grid {
    position: relative;
    min-height: 600px;
    width: 100%;
}

.widget-container {
    position: absolute;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: move;
}

.widget-size-small {
    width: 280px;
    height: 200px;
}

.widget-size-medium {
    width: 280px;
    height: 300px;
}

.widget-size-large {
    width: 600px;
    height: 300px;
}

.widget-size-xl {
    width: 600px;
    height: 400px;
}

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

.widget-card.drag-target {
    border: 2px dashed rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.1) !important;
    transform: scale(0.95) !important;
    opacity: 0.7 !important;
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

.widget-content {
    overflow-y: auto;
    max-height: calc(100% - 120px);
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

/* Responsive design */
@media (max-width: 1200px) {
    .widget-size-large,
    .widget-size-xl {
        width: 280px;
    }

    .widget-size-xl {
        height: 350px;
    }
}

@media (max-width: 960px) {
    .widget-grid {
        position: static;
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .widget-container {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        min-height: 200px;
    }

    .widget-size-small,
    .widget-size-medium,
    .widget-size-large,
    .widget-size-xl {
        width: 100% !important;
        height: auto !important;
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
