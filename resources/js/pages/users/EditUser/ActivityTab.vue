<template>
    <div class="activity-tab">
        <!-- Activity Overview Cards -->
        <v-row class="mb-6">
            <v-col cols="12" sm="6" md="3">
                <v-card class="activity-card" elevation="2" rounded="lg">
                    <v-card-text class="pa-4">
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <div class="text-h4 font-weight-bold text-primary">{{ loginCount }}</div>
                                <div class="text-caption text-medium-emphasis">Total Logins</div>
                            </div>
                            <v-icon color="primary" size="32">mdi-login</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="3">
                <v-card class="activity-card" elevation="2" rounded="lg">
                    <v-card-text class="pa-4">
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <div class="text-h4 font-weight-bold text-success">{{ sessionsToday }}</div>
                                <div class="text-caption text-medium-emphasis">Sessions Today</div>
                            </div>
                            <v-icon color="success" size="32">mdi-clock-outline</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="3">
                <v-card class="activity-card" elevation="2" rounded="lg">
                    <v-card-text class="pa-4">
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <div class="text-h4 font-weight-bold text-info">{{ avgSessionTime }}</div>
                                <div class="text-caption text-medium-emphasis">Avg Session</div>
                            </div>
                            <v-icon color="info" size="32">mdi-timer-outline</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="3">
                <v-card class="activity-card" elevation="2" rounded="lg">
                    <v-card-text class="pa-4">
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <div class="text-h4 font-weight-bold text-warning">{{ pageViews }}</div>
                                <div class="text-caption text-medium-emphasis">Page Views</div>
                            </div>
                            <v-icon color="warning" size="32">mdi-eye-outline</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Activity Timeline -->
        <v-row>
            <v-col cols="12" lg="8">
                <v-card class="timeline-card" elevation="2" rounded="lg">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <div class="d-flex align-center">
                            <v-icon class="mr-2" color="primary">mdi-timeline</v-icon>
                            Recent Activity
                        </div>
                        <v-btn-group density="compact" variant="outlined">
                            <v-btn
                                size="small"
                                :variant="timeFilter === 'today' ? 'flat' : 'outlined'"
                                @click="timeFilter = 'today'"
                            >
                                Today
                            </v-btn>
                            <v-btn
                                size="small"
                                :variant="timeFilter === 'week' ? 'flat' : 'outlined'"
                                @click="timeFilter = 'week'"
                            >
                                Week
                            </v-btn>
                            <v-btn
                                size="small"
                                :variant="timeFilter === 'month' ? 'flat' : 'outlined'"
                                @click="timeFilter = 'month'"
                            >
                                Month
                            </v-btn>
                        </v-btn-group>
                    </v-card-title>

                    <v-card-text class="pa-0">
                        <div class="timeline-container">
                            <v-timeline density="compact" class="timeline">
                                <v-timeline-item
                                    v-for="(activity, index) in filteredActivities"
                                    :key="index"
                                    :dot-color="activity.color"
                                    size="small"
                                >
                                    <template #icon>
                                        <v-icon :color="activity.color" size="16">{{ activity.icon }}</v-icon>
                                    </template>

                                    <div class="activity-item">
                                        <div class="d-flex justify-space-between align-start">
                                            <div>
                                                <div class="text-body-2 font-weight-medium">{{ activity.title }}</div>
                                                <div class="text-caption text-medium-emphasis mb-1">{{ activity.description }}</div>
                                                <div class="text-caption">
                                                    <v-chip size="x-small" :color="activity.color" variant="tonal">
                                                        {{ activity.type }}
                                                    </v-chip>
                                                </div>
                                            </div>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ formatTime(activity.timestamp) }}
                                            </div>
                                        </div>
                                    </div>
                                </v-timeline-item>
                            </v-timeline>

                            <!-- Load More Button -->
                            <div v-if="hasMoreActivities" class="text-center pa-4">
                                <v-btn
                                    variant="outlined"
                                    @click="loadMoreActivities"
                                    :loading="loadingMore"
                                    prepend-icon="mdi-reload"
                                >
                                    Load More Activities
                                </v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Login History & Device Info -->
            <v-col cols="12" lg="4">
                <!-- Login History -->
                <v-card class="login-history-card mb-4" elevation="2" rounded="lg">
                    <v-card-title class="d-flex align-center">
                        <v-icon class="mr-2" color="success">mdi-shield-check</v-icon>
                        Login History
                    </v-card-title>

                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item
                                v-for="(login, index) in recentLogins"
                                :key="index"
                                class="login-item"
                            >
                                <template #prepend>
                                    <v-avatar size="32" :color="getDeviceColor(login.device)">
                                        <v-icon size="16" color="white">{{ getDeviceIcon(login.device) }}</v-icon>
                                    </v-avatar>
                                </template>

                                <v-list-item-title class="text-body-2">
                                    {{ login.location }}
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    {{ login.device }} • {{ formatTime(login.timestamp) }}
                                </v-list-item-subtitle>

                                <template #append>
                                    <v-chip
                                        :color="login.success ? 'success' : 'error'"
                                        size="x-small"
                                        variant="tonal"
                                    >
                                        {{ login.success ? 'Success' : 'Failed' }}
                                    </v-chip>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <!-- Device Information -->
                <v-card class="device-info-card" elevation="2" rounded="lg">
                    <v-card-title class="d-flex align-center">
                        <v-icon class="mr-2" color="info">mdi-devices</v-icon>
                        Active Devices
                    </v-card-title>

                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item
                                v-for="(device, index) in activeDevices"
                                :key="index"
                                class="device-item"
                            >
                                <template #prepend>
                                    <v-avatar size="32" :color="getDeviceColor(device.type)">
                                        <v-icon size="16" color="white">{{ getDeviceIcon(device.type) }}</v-icon>
                                    </v-avatar>
                                </template>

                                <div class="device-details">
                                    <div class="text-body-2 font-weight-medium">{{ device.name }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ device.browser }} • {{ device.os }}
                                    </div>
                                    <div class="text-caption">
                                        Last active: {{ formatTime(device.lastActive) }}
                                    </div>
                                </div>

                                <template #append>
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        icon="mdi-logout"
                                        @click="logoutDevice(device.id)"
                                        class="logout-btn"
                                    />
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Activity Chart (Optional) -->
        <v-row class="mt-6">
            <v-col cols="12">
                <v-card class="chart-card" elevation="2" rounded="lg">
                    <v-card-title class="d-flex align-center">
                        <v-icon class="mr-2" color="primary">mdi-chart-line</v-icon>
                        Activity Chart (Last 30 Days)
                    </v-card-title>

                    <v-card-text>
                        <div class="chart-placeholder">
                            <div class="text-center pa-8">
                                <v-icon size="64" color="medium-emphasis">mdi-chart-areaspline</v-icon>
                                <div class="text-h6 mt-4">Activity Chart</div>
                                <div class="text-body-2 text-medium-emphasis">
                                    Chart component would be integrated here
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js'

export default {
    name: 'ActivityTab',
    props: {
        user: {
            type: Object,
            required: true
        }
    },
    setup() {
        // Reactive data
        const timeFilter = ref('week')
        const loadingMore = ref(false)
        const activities = ref([])
        const logins = ref([])
        const devices = ref([])

        // Mock data - replace with real API calls
        const mockActivities = [
            {
                title: 'Logged in successfully',
                description: 'User logged in from Chrome on Windows',
                type: 'Authentication',
                color: 'success',
                icon: 'mdi-login',
                timestamp: new Date(Date.now() - 1000 * 60 * 30) // 30 minutes ago
            },
            {
                title: 'Profile updated',
                description: 'Changed profile information',
                type: 'Profile',
                color: 'info',
                icon: 'mdi-account-edit',
                timestamp: new Date(Date.now() - 1000 * 60 * 60 * 2) // 2 hours ago
            },
            {
                title: 'Password changed',
                description: 'User updated their password',
                type: 'Security',
                color: 'warning',
                icon: 'mdi-lock',
                timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24) // 1 day ago
            },
            {
                title: 'Document accessed',
                description: 'Viewed wiki page: Getting Started',
                type: 'Content',
                color: 'primary',
                icon: 'mdi-file-document',
                timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 2) // 2 days ago
            },
            {
                title: 'Failed login attempt',
                description: 'Invalid password entered',
                type: 'Authentication',
                color: 'error',
                icon: 'mdi-alert',
                timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 3) // 3 days ago
            }
        ]

        const mockLogins = [
            {
                location: 'New York, NY',
                device: 'Chrome 120.0 on Windows',
                success: true,
                timestamp: new Date(Date.now() - 1000 * 60 * 30)
            },
            {
                location: 'San Francisco, CA',
                device: 'Safari 17.0 on macOS',
                success: true,
                timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24)
            },
            {
                location: 'London, UK',
                device: 'Firefox 121.0 on Linux',
                success: false,
                timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 2)
            }
        ]

        const mockDevices = [
            {
                id: 1,
                name: 'My MacBook Pro',
                type: 'desktop',
                browser: 'Chrome 120.0',
                os: 'macOS Sonoma',
                lastActive: new Date(Date.now() - 1000 * 60 * 30)
            },
            {
                id: 2,
                name: 'iPhone 15',
                type: 'mobile',
                browser: 'Safari 17.0',
                os: 'iOS 17.2',
                lastActive: new Date(Date.now() - 1000 * 60 * 60 * 2)
            },
            {
                id: 3,
                name: 'Work Laptop',
                type: 'desktop',
                browser: 'Edge 120.0',
                os: 'Windows 11',
                lastActive: new Date(Date.now() - 1000 * 60 * 60 * 24)
            }
        ]

        // Computed properties
        const loginCount = computed(() => 1247) // Mock data
        const sessionsToday = computed(() => 3) // Mock data
        const avgSessionTime = computed(() => '45m') // Mock data
        const pageViews = computed(() => 892) // Mock data

        const filteredActivities = computed(() => {
            const now = new Date()
            let cutoff

            switch (timeFilter.value) {
                case 'today':
                    cutoff = new Date(now.getFullYear(), now.getMonth(), now.getDate())
                    break
                case 'week':
                    cutoff = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
                    break
                case 'month':
                    cutoff = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
                    break
                default:
                    cutoff = new Date(0)
            }

            return activities.value.filter(activity => activity.timestamp >= cutoff)
        })

        const recentLogins = computed(() => logins.value.slice(0, 5))
        const activeDevices = computed(() => devices.value)
        const hasMoreActivities = computed(() => activities.value.length > 10)

        // Methods
        const formatTime = (timestamp) => {
            return formatDateDistanceToNow(timestamp)
        }

        const getDeviceIcon = (deviceType) => {
            switch (deviceType.toLowerCase()) {
                case 'mobile':
                    return 'mdi-cellphone'
                case 'tablet':
                    return 'mdi-tablet'
                case 'desktop':
                default:
                    return 'mdi-monitor'
            }
        }

        const getDeviceColor = (deviceType) => {
            switch (deviceType.toLowerCase()) {
                case 'mobile':
                    return 'success'
                case 'tablet':
                    return 'info'
                case 'desktop':
                default:
                    return 'primary'
            }
        }

        const loadMoreActivities = async () => {
            loadingMore.value = true
            try {
                // Simulate API call
                await new Promise(resolve => setTimeout(resolve, 1000))
                // Add more activities...
            } catch (error) {
                console.error('Failed to load more activities:', error)
            } finally {
                loadingMore.value = false
            }
        }

        const logoutDevice = async (deviceId) => {
            try {
                // Implement device logout API call
                console.log('Logging out device:', deviceId)
                devices.value = devices.value.filter(device => device.id !== deviceId)
            } catch (error) {
                console.error('Failed to logout device:', error)
            }
        }

        // Lifecycle
        onMounted(() => {
            activities.value = mockActivities
            logins.value = mockLogins
            devices.value = mockDevices
        })

        return {
            // Reactive data
            timeFilter,
            loadingMore,

            // Computed
            loginCount,
            sessionsToday,
            avgSessionTime,
            pageViews,
            filteredActivities,
            recentLogins,
            activeDevices,
            hasMoreActivities,

            // Methods
            formatTime,
            getDeviceIcon,
            getDeviceColor,
            loadMoreActivities,
            logoutDevice
        }
    }
}
</script>

<style scoped>
.activity-tab {
    width: 100%;
}

.activity-card {
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95) !important;
    transition: all 0.3s ease;
}

.activity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

.timeline-card,
.login-history-card,
.device-info-card,
.chart-card {
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95) !important;
}

.timeline-container {
    max-height: 600px;
    overflow-y: auto;
}

.timeline-container::-webkit-scrollbar {
    width: 6px;
}

.timeline-container::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
}

.timeline-container::-webkit-scrollbar-thumb {
    background: rgba(var(--v-theme-primary), 0.3);
    border-radius: 3px;
}

.activity-item {
    padding: 8px 16px;
    border-radius: 8px;
    margin: 8px 0;
    background: rgba(var(--v-theme-surface-variant), 0.3);
    transition: all 0.2s ease;
}

.activity-item:hover {
    background: rgba(var(--v-theme-surface-variant), 0.5);
    transform: translateX(4px);
}

.login-item,
.device-item {
    transition: all 0.2s ease;
}

.login-item:hover,
.device-item:hover {
    background: rgba(var(--v-theme-surface-variant), 0.3);
}

.logout-btn {
    opacity: 0;
    transition: opacity 0.2s ease;
}

.device-item:hover .logout-btn {
    opacity: 1;
}

.chart-placeholder {
    min-height: 300px;
    background: rgba(var(--v-theme-surface-variant), 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Mobile optimizations */
@media (max-width: 960px) {
    .timeline-container {
        max-height: 400px;
    }

    .activity-item {
        padding: 6px 12px;
        margin: 6px 0;
    }
}

@media (max-width: 600px) {
    .activity-card .text-h4 {
        font-size: 1.5rem !important;
    }

    .timeline {
        padding-left: 16px;
    }

    .device-details {
        min-width: 0;
    }
}
</style>
