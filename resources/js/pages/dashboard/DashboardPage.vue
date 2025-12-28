<template>
    <v-container fluid class="pa-0">
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header mb-6">
            <v-row align="center" class="mb-4">
                <v-col cols="12" md="6">
                    <h1 class="dashboard-title text-h3 font-weight-bold mb-2">
                        Welcome back, {{ user?.username || 'User' }}! 👋
                    </h1>
                    <p class="text-subtitle-1 text-medium-emphasis">
                        Customize your dashboard by dragging widgets around
                    </p>
                </v-col>
                <v-col cols="12" md="6" class="text-right">
                    <v-btn
                        color="primary"
                        variant="elevated"
                        prepend-icon="mdi-widgets"
                        @click="showWidgetPanel = true"
                        class="mr-3"
                    >
                        Add Widgets
                    </v-btn>
                    <v-btn
                        color="secondary"
                        variant="tonal"
                        prepend-icon="mdi-restore"
                        @click="resetLayout"
                    >
                        Reset Layout
                    </v-btn>
                </v-col>
            </v-row>
        </div>

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
                            <v-icon color="white" size="18">{{ widget.icon }}</v-icon>
                        </v-avatar>
                        <div class="flex-grow-1">
                            <div class="text-subtitle-1 font-weight-bold">{{ widget.title }}</div>
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
                                    title="Refresh"
                                    @click="refreshWidget(widget.id)"
                                ></v-list-item>
                                <v-list-item
                                    prepend-icon="mdi-cog"
                                    title="Settings"
                                    @click="openWidgetSettings(widget.id)"
                                ></v-list-item>
                                <v-list-item
                                    prepend-icon="mdi-close"
                                    title="Remove"
                                    @click="removeWidget(widget.id)"
                                ></v-list-item>
                            </v-list>
                        </v-menu>
                    </v-card-title>

                    <!-- Widget Content -->
                    <v-card-text class="widget-content pa-4 pt-2">
                        <component
                            :is="getWidgetComponent(widget.type)"
                            :widget-data="widget.data"
                            :widget-config="widget.config"
                            @update-data="updateWidgetData(widget.id, $event)"
                        />
                    </v-card-text>

                    <!-- Widget Actions (if any) -->
                    <v-card-actions v-if="widget.actions" class="pa-4 pt-0">
                        <v-btn
                            v-for="action in widget.actions"
                            :key="action.text"
                            :color="action.color"
                            variant="elevated"
                            size="small"
                            :prepend-icon="action.icon"
                            :to="action.to"
                            :href="action.href"
                            block
                        >
                            {{ action.text }}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </div>
        </div>

        <!-- Widget Panel Dialog -->
        <v-dialog v-model="showWidgetPanel" max-width="800">
            <v-card>
                <v-card-title class="d-flex align-center pa-6">
                    <v-icon color="primary" class="mr-3">mdi-widgets</v-icon>
                    <span class="text-h5">Available Widgets</span>
                </v-card-title>

                <v-card-text class="pa-6">
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
                                @click="addWidget(widget)"
                            >
                                <v-card-text class="text-center pa-4">
                                    <v-avatar :color="widget.color" size="48" class="mb-3">
                                        <v-icon color="white" size="24">{{ widget.icon }}</v-icon>
                                    </v-avatar>
                                    <div class="text-subtitle-1 font-weight-bold mb-1">{{ widget.title }}</div>
                                    <div class="text-caption text-medium-emphasis mb-2">{{ widget.description }}</div>
                                    <v-chip
                                        :color="widget.category === 'core' ? 'primary' : 'secondary'"
                                        size="small"
                                    >
                                        {{ widget.category }}
                                    </v-chip>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="pa-6 pt-0">
                    <v-spacer></v-spacer>
                    <v-btn @click="showWidgetPanel = false">Close</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Widget Settings Dialog -->
        <v-dialog v-model="showWidgetSettings" max-width="600">
            <v-card v-if="selectedWidget">
                <v-card-title class="pa-6">
                    <span class="text-h5">Widget Settings: {{ selectedWidget.title }}</span>
                </v-card-title>

                <v-card-text class="pa-6">
                    <v-form>
                        <v-text-field
                            v-model="selectedWidget.title"
                            label="Widget Title"
                            variant="outlined"
                            class="mb-4"
                        ></v-text-field>

                        <v-select
                            v-model="selectedWidget.size"
                            :items="widgetSizes"
                            label="Widget Size"
                            variant="outlined"
                            class="mb-4"
                        ></v-select>

                        <v-color-picker
                            v-model="selectedWidget.color"
                            hide-inputs
                            class="mb-4"
                        ></v-color-picker>
                    </v-form>
                </v-card-text>

                <v-card-actions class="pa-6 pt-0">
                    <v-spacer></v-spacer>
                    <v-btn @click="showWidgetSettings = false">Cancel</v-btn>
                    <v-btn color="primary" @click="saveWidgetSettings">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
    </v-container>
</template>

<script>
import { useUserStore } from '@/store/userStore.js';

// Widget Components
const EventsWidget = {
    props: ['widgetData', 'widgetConfig'],
    template: `
        <div>
            <div v-if="widgetData.nextEvent" class="mb-4">
                <v-card color="primary" variant="tonal">
                    <v-card-text class="pa-3">
                        <div class="d-flex align-center mb-2">
                            <v-chip color="primary" size="small" class="mr-2">NEXT</v-chip>
                            <div class="text-caption">{{ widgetData.nextEvent.timeUntil }}</div>
                        </div>
                        <div class="text-subtitle-2 font-weight-bold mb-1">{{ widgetData.nextEvent.title }}</div>
                        <div class="text-caption">{{ widgetData.nextEvent.date }}</div>
                    </v-card-text>
                </v-card>
            </div>
            <v-list density="compact" class="pa-0">
                <v-list-item
                    v-for="event in widgetData.upcomingEvents?.slice(0, 3)"
                    :key="event.id"
                    class="px-0 mb-1"
                >
                    <template v-slot:prepend>
                        <v-avatar color="surface-variant" size="24">
                            <v-icon size="12">mdi-calendar</v-icon>
                        </v-avatar>
                    </template>
                    <v-list-item-title class="text-body-2">{{ event.title }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">{{ event.date }}</v-list-item-subtitle>
                </v-list-item>
            </v-list>
        </div>
    `
};

const WikiWidget = {
    props: ['widgetData', 'widgetConfig'],
    template: `
        <div>
            <div v-for="change in widgetData.recentChanges?.slice(0, 4)" :key="change.id" class="mb-3">
                <div class="d-flex align-center mb-1">
                    <v-chip
                        :color="change.type === 'edit' ? 'warning' : 'success'"
                        size="x-small"
                        class="mr-2"
                    >
                        {{ change.type }}
                    </v-chip>
                    <div class="text-caption text-medium-emphasis">{{ change.date }}</div>
                </div>
                <div class="text-body-2 font-weight-medium">{{ change.page }}</div>
                <div class="text-caption text-medium-emphasis">by {{ change.author }}</div>
            </div>
        </div>
    `
};

const NotificationsWidget = {
    props: ['widgetData', 'widgetConfig'],
    template: `
        <div>
            <div v-for="notification in widgetData.recent?.slice(0, 4)" :key="notification.id" class="d-flex align-center mb-3">
                <v-avatar :color="notification.type === 'info' ? 'blue' : notification.type === 'warning' ? 'orange' : 'green'" size="24" class="mr-3">
                    <v-icon color="white" size="12">{{ getNotificationIcon(notification.type) }}</v-icon>
                </v-avatar>
                <div class="flex-grow-1">
                    <div class="text-body-2">{{ notification.message }}</div>
                    <div class="text-caption text-medium-emphasis">{{ notification.time }}</div>
                </div>
                <v-btn v-if="!notification.read" icon="mdi-circle" size="x-small" color="primary"></v-btn>
            </div>
        </div>
    `,
    methods: {
        getNotificationIcon(type) {
            const icons = {
                info: 'mdi-information',
                warning: 'mdi-alert',
                success: 'mdi-check-circle'
            };
            return icons[type] || 'mdi-bell';
        }
    }
};

const StatsWidget = {
    props: ['widgetData', 'widgetConfig'],
    template: `
        <div class="text-center">
            <div class="text-h2 font-weight-bold mb-2" :style="{ color: widgetConfig.color }">
                {{ widgetData.value }}
            </div>
            <div class="text-body-2 text-medium-emphasis mb-3">{{ widgetData.label }}</div>
            <div class="d-flex align-center justify-center">
                <v-icon
                    :color="widgetData.trend > 0 ? 'success' : 'error'"
                    size="16"
                    class="mr-1"
                >
                    {{ widgetData.trend > 0 ? 'mdi-trending-up' : 'mdi-trending-down' }}
                </v-icon>
                <span class="text-caption" :class="widgetData.trend > 0 ? 'text-success' : 'text-error'">
          {{ Math.abs(widgetData.trend) }}% this week
        </span>
            </div>
        </div>
    `
};

// More widget components...
const TasksWidget = {
    props: ['widgetData', 'widgetConfig'],
    template: `
        <div>
            <div class="mb-3">
                <v-progress-linear
                    :model-value="(widgetData.completed / widgetData.total) * 100"
                    color="success"
                    height="8"
                    rounded
                ></v-progress-linear>
                <div class="text-caption mt-1 text-center">
                    {{ widgetData.completed }}/{{ widgetData.total }} tasks completed
                </div>
            </div>
            <v-list density="compact" class="pa-0">
                <v-list-item
                    v-for="task in widgetData.recent?.slice(0, 3)"
                    :key="task.id"
                    class="px-0 mb-1"
                >
                    <template v-slot:prepend>
                        <v-checkbox
                            :model-value="task.completed"
                            color="success"
                            density="compact"
                            hide-details
                        ></v-checkbox>
                    </template>
                    <v-list-item-title class="text-body-2" :class="{ 'text-decoration-line-through': task.completed }">
                        {{ task.title }}
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-caption">{{ task.dueDate }}</v-list-item-subtitle>
                </v-list-item>
            </v-list>
        </div>
    `
};

const WeatherWidget = {
    props: ['widgetData', 'widgetConfig'],
    template: `
        <div class="text-center">
            <v-icon size="48" color="primary" class="mb-2">{{ widgetData.icon }}</v-icon>
            <div class="text-h4 font-weight-bold mb-1">{{ widgetData.temperature }}°</div>
            <div class="text-body-2 mb-2">{{ widgetData.condition }}</div>
            <div class="text-caption text-medium-emphasis">{{ widgetData.location }}</div>
        </div>
    `
};

import ConversationsWidget from '@/components/dashboard/ConversationsWidget.vue';

export default {
    name: 'DynamicDashboard',
    components: {
        EventsWidget,
        WikiWidget,
        NotificationsWidget,
        StatsWidget,
        TasksWidget,
        WeatherWidget,
        ConversationsWidget
    },
    data() {
        return {
            showWidgetPanel: false,
            showWidgetSettings: false,
            selectedWidget: null,
            draggingWidgetId: null,
            dragTargetId: null,

            widgetSizes: [
                { title: 'Small', value: 'small' },
                { title: 'Medium', value: 'medium' },
                { title: 'Large', value: 'large' },
                { title: 'Extra Large', value: 'xl' }
            ],

            // Active widgets on dashboard
            activeWidgets: [
                {
                    id: 'events-1',
                    type: 'events',
                    title: 'Upcoming Events',
                    subtitle: '8 events scheduled',
                    icon: 'mdi-calendar-clock',
                    color: 'primary',
                    size: 'medium',
                    position: { x: 0, y: 0 },
                    actions: [{ text: 'View All Events', color: 'primary', icon: 'mdi-calendar-multiple', to: '/events' }],
                    data: {
                        nextEvent: {
                            title: 'Monthly Community Meeting',
                            date: 'June 15, 2025 at 7:00 PM',
                            timeUntil: 'in 12 days'
                        },
                        upcomingEvents: [
                            { id: 1, title: 'Developer Workshop', date: 'June 20, 2025' },
                            { id: 2, title: 'Community Cleanup', date: 'June 25, 2025' },
                            { id: 3, title: 'Summer BBQ', date: 'July 2, 2025' }
                        ]
                    }
                },
                {
                    id: 'wiki-1',
                    type: 'wiki',
                    title: 'Wiki Updates',
                    subtitle: '3 recent changes',
                    icon: 'mdi-book-edit',
                    color: 'warning',
                    size: 'small',
                    position: { x: 1, y: 0 },
                    actions: [{ text: 'View Wiki', color: 'warning', icon: 'mdi-book-open-variant', to: '/wiki' }],
                    data: {
                        recentChanges: [
                            { id: 1, type: 'edit', page: 'Community Guidelines', author: 'Admin', date: '1 hour ago' },
                            { id: 2, type: 'new', page: 'Event Planning Guide', author: 'John Doe', date: '6 hours ago' },
                            { id: 3, type: 'edit', page: 'FAQ', author: 'Jane Smith', date: '1 day ago' }
                        ]
                    }
                },
                {
                    id: 'notifications-1',
                    type: 'notifications',
                    title: 'Notifications',
                    subtitle: '5 unread',
                    icon: 'mdi-bell',
                    color: 'info',
                    size: 'medium',
                    position: { x: 2, y: 0 },
                    data: {
                        recent: [
                            { id: 1, type: 'info', message: 'New member joined', time: '2m ago', read: false },
                            { id: 2, type: 'warning', message: 'Server maintenance scheduled', time: '1h ago', read: false },
                            { id: 3, type: 'success', message: 'Event published successfully', time: '3h ago', read: true }
                        ]
                    }
                },
                {
                    id: 'conversations-1',
                    type: 'conversations',
                    title: 'Messages',
                    subtitle: 'Recent conversations',
                    icon: 'mdi-message-text',
                    color: 'teal',
                    size: 'medium',
                    position: { x: 0, y: 1 },
                    actions: [{ text: 'View All Conversations', color: 'teal', icon: 'mdi-message-text-outline', to: '/conversations' }],
                    data: {},
                    config: { maxItems: 5 }
                }
            ],

            // Available widgets to add
            availableWidgets: [
                {
                    type: 'events',
                    title: 'Events',
                    description: 'Show upcoming events and meetings',
                    icon: 'mdi-calendar-clock',
                    color: 'primary',
                    category: 'core'
                },
                {
                    type: 'wiki',
                    title: 'Wiki Changes',
                    description: 'Recent wiki page updates and edits',
                    icon: 'mdi-book-edit',
                    color: 'warning',
                    category: 'core'
                },
                {
                    type: 'notifications',
                    title: 'Notifications',
                    description: 'System and community notifications',
                    icon: 'mdi-bell',
                    color: 'info',
                    category: 'core'
                },
                {
                    type: 'stats',
                    title: 'Statistics',
                    description: 'Key metrics and numbers',
                    icon: 'mdi-chart-line',
                    color: 'success',
                    category: 'analytics'
                },
                {
                    type: 'tasks',
                    title: 'Tasks',
                    description: 'Personal and team task management',
                    icon: 'mdi-check-circle',
                    color: 'purple',
                    category: 'productivity'
                },
                {
                    type: 'weather',
                    title: 'Weather',
                    description: 'Current weather conditions',
                    icon: 'mdi-weather-partly-cloudy',
                    color: 'blue',
                    category: 'utility'
                },
                {
                    type: 'analytics',
                    title: 'Analytics',
                    description: 'Traffic and engagement metrics',
                    icon: 'mdi-google-analytics',
                    color: 'orange',
                    category: 'analytics'
                },
                {
                    type: 'social',
                    title: 'Social Feed',
                    description: 'Recent posts and social activity',
                    icon: 'mdi-account-group',
                    color: 'pink',
                    category: 'social'
                },
                {
                    type: 'calendar',
                    title: 'Calendar',
                    description: 'Monthly calendar view',
                    icon: 'mdi-calendar-month',
                    color: 'indigo',
                    category: 'utility'
                },
                {
                    type: 'conversations',
                    title: 'Conversations',
                    description: 'Recent messages and chats',
                    icon: 'mdi-message-text',
                    color: 'teal',
                    category: 'social'
                }
            ]
        }
    },
    computed: {
        user() {
            const userStore = useUserStore();
            return userStore.user;
        }
    },
    methods: {
        getWidgetComponent(type) {
            const components = {
                events: 'EventsWidget',
                wiki: 'WikiWidget',
                notifications: 'NotificationsWidget',
                stats: 'StatsWidget',
                tasks: 'TasksWidget',
                weather: 'WeatherWidget',
                conversations: 'ConversationsWidget'
            };
            return components[type] || 'div';
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
                    const occupyingElement = document.querySelector(`[data-widget-id="${occupyingWidget.id}"]`);
                    const draggedElement = document.querySelector(`[data-widget-id="${draggedWidget.id}"]`);

                    if (occupyingElement) {
                        occupyingElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        occupyingElement.style.transform = 'scale(1.05)';
                        setTimeout(() => {
                            occupyingElement.style.transform = '';
                        }, 200);
                    }

                    if (draggedElement) {
                        draggedElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        draggedElement.style.transform = 'scale(1.05)';
                        setTimeout(() => {
                            draggedElement.style.transform = '';
                        }, 200);
                    }
                });

                console.log(`Swapped positions: ${draggedWidget.title} ↔ ${occupyingWidget.title}`);
            } else {
                // Position is free, just move there
                draggedWidget.position = newPosition;
            }

            this.saveLayout();
        },

        addWidget(widgetTemplate) {
            const newWidget = {
                id: `${widgetTemplate.type}-${Date.now()}`,
                type: widgetTemplate.type,
                title: widgetTemplate.title,
                subtitle: widgetTemplate.description,
                icon: widgetTemplate.icon,
                color: widgetTemplate.color,
                size: 'medium',
                position: this.findAvailablePosition(),
                data: this.getDefaultWidgetData(widgetTemplate.type),
                config: {}
            };

            // Add actions based on widget type
            if (widgetTemplate.type === 'events') {
                newWidget.actions = [{ text: 'View All Events', color: 'primary', icon: 'mdi-calendar-multiple', to: '/events' }];
            } else if (widgetTemplate.type === 'wiki') {
                newWidget.actions = [{ text: 'View Wiki', color: 'warning', icon: 'mdi-book-open-variant', to: '/wiki' }];
            } else if (widgetTemplate.type === 'conversations') {
                newWidget.actions = [{ text: 'View All Conversations', color: 'teal', icon: 'mdi-message-text-outline', to: '/conversations' }];
            }

            this.activeWidgets.push(newWidget);
            this.showWidgetPanel = false;
            this.saveLayout();
        },

        removeWidget(widgetId) {
            const index = this.activeWidgets.findIndex(w => w.id === widgetId);
            if (index > -1) {
                this.activeWidgets.splice(index, 1);
                this.saveLayout();
            }
        },

        refreshWidget(widgetId) {
            const widget = this.activeWidgets.find(w => w.id === widgetId);
            if (widget) {
                // Simulate refresh by updating data
                widget.data = this.getDefaultWidgetData(widget.type);
                console.log(`Refreshing widget: ${widget.title}`);
            }
        },

        openWidgetSettings(widgetId) {
            this.selectedWidget = this.activeWidgets.find(w => w.id === widgetId);
            this.showWidgetSettings = true;
        },

        saveWidgetSettings() {
            this.showWidgetSettings = false;
            this.saveLayout();
        },

        resetLayout() {
            // Reset to default layout
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

        getDefaultWidgetData(type) {
            const defaultData = {
                events: {
                    nextEvent: {
                        title: 'Sample Event',
                        date: 'Coming Soon',
                        timeUntil: 'TBD'
                    },
                    upcomingEvents: []
                },
                wiki: {
                    recentChanges: [
                        { id: 1, type: 'new', page: 'New Page', author: 'System', date: 'Just now' }
                    ]
                },
                notifications: {
                    recent: [
                        { id: 1, type: 'info', message: 'Welcome to your new widget!', time: 'now', read: false }
                    ]
                },
                stats: {
                    value: '0',
                    label: 'New Metric',
                    trend: 0
                },
                tasks: {
                    completed: 0,
                    total: 1,
                    recent: [
                        { id: 1, title: 'Configure this widget', completed: false, dueDate: 'Today' }
                    ]
                },
                weather: {
                    temperature: '22',
                    condition: 'Sunny',
                    location: 'Your Location',
                    icon: 'mdi-weather-sunny'
                },
                conversations: {
                    // This will be populated by the widget component from the store
                }
            };

            return defaultData[type] || {};
        },

        updateWidgetData(widgetId, newData) {
            const widget = this.activeWidgets.find(w => w.id === widgetId);
            if (widget) {
                widget.data = { ...widget.data, ...newData };
            }
        },

        saveLayout() {
            // Save layout to localStorage or send to API
            const layout = this.activeWidgets.map(w => ({
                id: w.id,
                type: w.type,
                position: w.position,
                size: w.size,
                config: w.config
            }));

            localStorage.setItem('dashboardLayout', JSON.stringify(layout));
            console.log('Layout saved');
        },

        loadLayout() {
            // Load layout from localStorage or API
            const savedLayout = localStorage.getItem('dashboardLayout');
            if (savedLayout) {
                try {
                    const layout = JSON.parse(savedLayout);
                    // Apply saved positions and configurations
                    layout.forEach(saved => {
                        const widget = this.activeWidgets.find(w => w.id === saved.id);
                        if (widget) {
                            widget.position = saved.position;
                            widget.size = saved.size;
                            widget.config = saved.config || {};
                        }
                    });
                } catch (e) {
                    console.error('Failed to load layout:', e);
                }
            }
        }
    },

    mounted() {
        this.loadLayout();
    }
}
</script>

<style scoped>
.dashboard-container {
    padding: 24px;
    min-height: 100vh;
    background-color: rgb(var(--v-theme-background));
}

/* Light mode background gradient */
.v-theme--light .dashboard-container {
    background: linear-gradient(135deg, #f5f7fa 0%, #e0e7ea 100%);
}

/* Dark mode background */
.v-theme--dark .dashboard-container {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
}

.dashboard-header {
    background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.08) 0%, rgba(var(--v-theme-surface), 0.1) 100%);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(10px);
}

.dashboard-title {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

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
}

/* Light theme widget cards */
.v-theme--light .widget-card {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.v-theme--light .widget-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12) !important;
    transform: translateY(-2px);
}

/* Dark theme widget cards */
.v-theme--dark .widget-card {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.v-theme--dark .widget-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6) !important;
    transform: translateY(-2px);
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
}

.v-theme--light .widget-header {
    background: rgba(0, 0, 0, 0.02);
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.v-theme--dark .widget-header {
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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
    .dashboard-container {
        padding: 16px;
    }

    .dashboard-header {
        padding: 16px;
    }

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

.v-theme--light .widget-content::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 2px;
}

.v-theme--light .widget-content::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 2px;
}

.v-theme--light .widget-content::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.3);
}

.v-theme--dark .widget-content::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 2px;
}

.v-theme--dark .widget-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

.v-theme--dark .widget-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Button styling */
.v-btn {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
}

/* Dialog styling */
.v-dialog .v-card {
    border-radius: 20px !important;
}

.v-theme--light .v-dialog .v-card {
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2) !important;
}

.v-theme--dark .v-dialog .v-card {
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6) !important;
}

/* Color picker styling */
.v-color-picker {
    border-radius: 12px !important;
    box-shadow: none !important;
}

.v-theme--light .v-color-picker {
    border: 1px solid rgba(0, 0, 0, 0.12);
}

.v-theme--dark .v-color-picker {
    border: 1px solid rgba(255, 255, 255, 0.12);
}

</style>
