<template>
    <div class="user-profile-container">
        <v-container class="py-6">
            <!-- Enhanced Header Section -->
            <div class="profile-header mb-6">
                <v-row align="center">
                    <v-col cols="12" lg="8">
                        <div class="header-content">
                            <!-- User Avatar and Basic Info -->
                            <div class="d-flex align-center mb-4">
                                <v-avatar
                                    :size="mobile ? 64 : 80"
                                    class="profile-avatar mr-4"
                                    :color="user?.avatar ? 'transparent' : 'primary'"
                                >
                                    <v-img
                                        v-if="user?.avatar"
                                        :src="user.avatar"
                                        :alt="`${user.name}'s avatar`"
                                    />
                                    <v-icon
                                        v-else
                                        :size="mobile ? 32 : 40"
                                        color="white"
                                    >
                                        mdi-account
                                    </v-icon>
                                </v-avatar>

                                <div class="profile-info">
                                    <h1 class="profile-title text-h4 font-weight-bold text-gradient mb-1">
                                        {{ user?.username || $t('userProfile.unknownUser') }}
                                    </h1>
                                    <div class="d-flex align-center flex-wrap ga-2 mb-2">
                                        <v-chip
                                            :color="userStatus.color"
                                            variant="tonal"
                                            size="small"
                                            :prepend-icon="userStatus.icon"
                                        >
                                            {{ userStatus.text }}
                                        </v-chip>
                                        <v-chip
                                            v-if="user?.email_verified_at"
                                            color="success"
                                            variant="tonal"
                                            size="small"
                                            prepend-icon="mdi-check-circle"
                                        >
                                            {{ $t('userProfile.verified') }}
                                        </v-chip>
                                        <v-chip
                                            v-if="isOnline"
                                            color="success"
                                            variant="tonal"
                                            size="small"
                                            prepend-icon="mdi-circle"
                                        >
                                            {{ $t('userProfile.online') }}
                                        </v-chip>
                                    </div>
                                    <v-breadcrumbs
                                        :items="breadcrumbs"
                                        class="pa-0 py-1"
                                        density="compact"
                                    />
                                </div>
                            </div>

                            <!-- Admin Badge -->
                            <v-alert
                                v-if="roles.includes('admin')"
                                type="info"
                                variant="tonal"
                                class="mb-4"
                                prominent
                            >
                                <template #prepend>
                                    <v-icon size="24">mdi-shield-crown</v-icon>
                                </template>
                                <div class="d-flex justify-space-between align-center">
                                    <div>
                                        <div class="text-subtitle-1 font-weight-medium mb-1">{{ $t('userProfile.administratorAccess') }}</div>
                                        <p class="mb-0 text-body-2">{{ $t('userProfile.adminPrivileges') }}</p>
                                    </div>
                                    <v-btn
                                        variant="tonal"
                                        size="small"
                                        prepend-icon="mdi-cog"
                                        @click="showAdminSettings = true"
                                    >
                                        {{ $t('userProfile.manage') }}
                                    </v-btn>
                                </div>
                            </v-alert>
                        </div>
                    </v-col>

                    <v-col cols="12" lg="4" class="text-right">
                        <div class="header-actions d-flex align-center flex-wrap ga-2 justify-lg-end">
                            <v-btn-group variant="outlined" density="compact">
                                <v-btn
                                    @click="refreshUser"
                                    :loading="refreshing"
                                    prepend-icon="mdi-refresh"
                                >
                                    {{ $t('userProfile.refresh') }}
                                </v-btn>
                                <v-btn
                                    @click="exportProfile"
                                    prepend-icon="mdi-download"
                                >
                                    {{ $t('userProfile.export') }}
                                </v-btn>
                                <v-menu>
                                    <template #activator="{ props }">
                                        <v-btn
                                            v-bind="props"
                                            append-icon="mdi-chevron-down"
                                        >
                                            {{ $t('userProfile.actions') }}
                                        </v-btn>
                                    </template>
                                    <v-list>
                                        <v-list-item @click="sendMessage">
                                            <template #prepend>
                                                <v-icon>mdi-message</v-icon>
                                            </template>
                                            <v-list-item-title>{{ $t('userProfile.sendMessage') }}</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item @click="viewActivity">
                                            <template #prepend>
                                                <v-icon>mdi-chart-line</v-icon>
                                            </template>
                                            <v-list-item-title>{{ $t('userProfile.viewActivity') }}</v-list-item-title>
                                        </v-list-item>
                                        <v-divider />
                                        <v-list-item @click="confirmResetPassword" :disabled="confirming" class="text-warning">
                                            <template #prepend>
                                                <v-icon color="warning">mdi-lock-reset</v-icon>
                                            </template>
                                            <v-list-item-title>{{ $t('userProfile.resetPassword') }}</v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-menu>
                            </v-btn-group>
                        </div>
                    </v-col>
                </v-row>
            </div>

            <!-- Quick Info Cards -->
            <v-row class="mb-6">
                <v-col cols="12" md="6" lg="3">
                    <v-card class="info-card" elevation="2" rounded="lg">
                        <v-card-text class="pa-4">
                            <div class="d-flex align-center justify-space-between">
                                <div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('userProfile.email') }}</div>
                                    <div class="text-body-1 font-weight-medium">
                                        {{ user?.email || $t('userProfile.notProvided') }}
                                    </div>
                                </div>
                                <div class="d-flex align-center">
                                    <copy-label :text="user?.email || ''" />
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        icon="mdi-email"
                                        @click="sendEmail"
                                        class="ml-2"
                                    />
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6" lg="3">
                    <v-card class="info-card" elevation="2" rounded="lg">
                        <v-card-text class="pa-4">
                            <div class="d-flex align-center justify-space-between">
                                <div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('userProfile.userId') }}</div>
                                    <div class="text-body-1 font-weight-medium">
                                        {{ user?.id || $t('userProfile.unknown') }}
                                    </div>
                                </div>
                                <div class="d-flex align-center">
                                    <copy-label :text="user?.id?.toString() || ''" />
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        icon="mdi-identifier"
                                        class="ml-2"
                                    />
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6" lg="3">
                    <v-card class="info-card" elevation="2" rounded="lg">
                        <v-card-text class="pa-4">
                            <div class="d-flex align-center justify-space-between">
                                <div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('userProfile.memberSince') }}</div>
                                    <div class="text-body-1 font-weight-medium">
                                        {{ memberSince }}
                                    </div>
                                </div>
                                <v-icon color="info">mdi-calendar</v-icon>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6" lg="3">
                    <v-card class="info-card" elevation="2" rounded="lg">
                        <v-card-text class="pa-4">
                            <div class="d-flex align-center justify-space-between">
                                <div>
                                    <div class="text-caption text-medium-emphasis">{{ $t('userProfile.lastActive') }}</div>
                                    <div class="text-body-1 font-weight-medium">
                                        {{ lastActive }}
                                    </div>
                                </div>
                                <v-icon :color="isOnline ? 'success' : 'error'">
                                    {{ isOnline ? 'mdi-circle' : 'mdi-circle-outline' }}
                                </v-icon>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Enhanced Tabs Section -->
            <v-card class="tabs-card" elevation="2" rounded="lg">
                <v-tabs
                    v-model="activeTab"
                    bg-color="transparent"
                    color="primary"
                    grow
                    class="tabs-header"
                >
                    <v-tab
                        value="account"
                        prepend-icon="mdi-account-cog"
                        class="tab-button"
                    >
                        {{ $t('userProfile.account') }}
                        <v-chip
                            v-if="hasAccountChanges"
                            color="warning"
                            variant="tonal"
                            size="x-small"
                            class="ml-2"
                        >
                            {{ $t('userProfile.modified') }}
                        </v-chip>
                    </v-tab>

                    <v-tab
                        value="info"
                        prepend-icon="mdi-information"
                        class="tab-button"
                    >
                        {{ $t('userProfile.information') }}
                        <v-chip
                            v-if="hasInfoChanges"
                            color="warning"
                            variant="tonal"
                            size="x-small"
                            class="ml-2"
                        >
                            {{ $t('userProfile.modified') }}
                        </v-chip>
                    </v-tab>

                    <v-tab
                        value="social"
                        prepend-icon="mdi-link-variant"
                        class="tab-button"
                    >
                        {{ $t('userProfile.socialAccounts') }}
                    </v-tab>

                    <v-tab
                        value="activity"
                        prepend-icon="mdi-chart-timeline"
                        class="tab-button"
                    >
                        {{ $t('userProfile.activity') }}
                    </v-tab>

                    <v-tab
                        value="permissions"
                        prepend-icon="mdi-shield-account"
                        class="tab-button"
                    >
                        {{ $t('userProfile.permissions') }}
                        <v-chip
                            v-if="roles.length > 1"
                            color="info"
                            variant="tonal"
                            size="x-small"
                            class="ml-2"
                        >
                            {{ roles.length }}
                        </v-chip>
                    </v-tab>
                </v-tabs>

                <v-divider />

                <v-card-text class="pa-6">
                    <v-window v-model="activeTab" class="tab-content">
                        <!-- Account Tab -->
                        <v-window-item value="account">
                            <div class="tab-header pb-4 mb-6">
                                <h3 class="text-h6 font-weight-bold">{{ $t('userProfile.accountSettings') }}</h3>
                                <p class="text-body-2 text-medium-emphasis">
                                    {{ $t('userProfile.accountSettingsDescription') }}
                                </p>
                            </div>
                            <account-tab
                                :user="user"
                                :roles="roles"
                                @change="onAccountChange"
                                @save="onAccountSave"
                            />
                        </v-window-item>

                        <!-- Information Tab -->
                        <v-window-item value="info">
                            <div class="tab-header pb-4 mb-6">
                                <h3 class="text-h6 font-weight-bold">{{ $t('userProfile.personalInformation') }}</h3>
                                <p class="text-body-2 text-medium-emphasis">
                                    {{ $t('userProfile.personalInfoDescription') }}
                                </p>
                            </div>
                            <information-tab
                                :user="user"
                                @change="onInfoChange"
                                @save="onInfoSave"
                            />
                        </v-window-item>

                        <!-- Social Accounts Tab -->
                        <v-window-item value="social">
                            <div class="tab-header pb-4 mb-6">
                                <h3 class="text-h6 font-weight-bold">{{ $t('userProfile.connectedSocialAccounts') }}</h3>
                                <p class="text-body-2 text-medium-emphasis">
                                    {{ $t('userProfile.connectedSocialDescription') }}
                                </p>
                            </div>
                            <social-accounts-tab :user="user" />
                        </v-window-item>

                        <!-- Activity Tab -->
                        <v-window-item value="activity">
                            <div class="tab-header pb-4 mb-6">
                                <h3 class="text-h6 font-weight-bold">{{ $t('userProfile.userActivity') }}</h3>
                                <p class="text-body-2 text-medium-emphasis">
                                    {{ $t('userProfile.userActivityDescription') }}
                                </p>
                            </div>
                            <activity-tab :user="user" />
                        </v-window-item>

                        <!-- Permissions Tab -->
                        <v-window-item value="permissions">
                            <div class="tab-header pb-4 mb-6">
                                <h3 class="text-h6 font-weight-bold">{{ $t('userProfile.rolesPermissions') }}</h3>
                                <p class="text-body-2 text-medium-emphasis">
                                    {{ $t('userProfile.rolesPermissionsDescription') }}
                                </p>
                            </div>
                            <permissions-tab
                                :user="user"
                                :roles="roles"
                                @change="onPermissionChange"
                                @save="onPermissionSave"
                            />
                        </v-window-item>
                    </v-window>
                </v-card-text>
            </v-card>

            <!-- Floating Action Buttons -->
            <div class="fab-container">
                <v-fab
                    v-if="hasAnyChanges"
                    location="bottom end"
                    size="large"
                    color="primary"
                    icon="mdi-content-save"
                    @click="saveAllChanges"
                    :loading="saving"
                    app
                    class="save-fab"
                />

                <v-fab
                    v-if="mobile"
                    location="bottom start"
                    size="default"
                    color="secondary"
                    icon="mdi-message"
                    @click="sendMessage"
                    app
                    class="message-fab"
                />
            </div>
        </v-container>

        <!-- Admin Settings Dialog -->
        <v-dialog v-model="showAdminSettings" max-width="600">
            <v-card>
                <v-card-title class="text-h6 d-flex align-center">
                    <v-icon color="primary" class="mr-2">mdi-shield-crown</v-icon>
                    {{ $t('userProfile.administratorManagement') }}
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-alert type="warning" variant="tonal" class="mb-4">
                        <template #prepend>
                            <v-icon>mdi-alert</v-icon>
                        </template>
                        {{ $t('userProfile.adminWarning') }}
                    </v-alert>

                    <div class="admin-actions pa-4">
                        <v-btn
                            block
                            color="error"
                            variant="tonal"
                            prepend-icon="mdi-account-minus"
                            class="mb-3"
                            :loading="confirming"
                            @click="confirmRemoveAdmin"
                        >
                            {{ $t('userProfile.removeAdminPrivileges') }}
                        </v-btn>
                        <v-btn
                            block
                            variant="tonal"
                            prepend-icon="mdi-history"
                            @click="viewAdminHistory"
                        >
                            {{ $t('userProfile.viewAdminHistory') }}
                        </v-btn>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showAdminSettings = false">{{ $t('common.close') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Success Snackbar -->
        <v-snackbar
            v-model="showSuccess"
            color="success"
            timeout="4000"
            location="top"
        >
            <template #prepend>
                <v-icon>mdi-check-circle</v-icon>
            </template>
            {{ successMessage }}
        </v-snackbar>

        <!-- Error Snackbar -->
        <v-snackbar
            v-model="showError"
            color="error"
            timeout="6000"
            location="top"
        >
            <template #prepend>
                <v-icon>mdi-alert-circle</v-icon>
            </template>
            {{ errorMessage }}
        </v-snackbar>
    </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import { useDialog } from '@/composables/useDialog.js'
import { formatDate, formatDateDistanceToNow } from '@/plugins/formatDate.js'
import CopyLabel from '../../components/common/CopyLabel.vue'
import AccountTab from './EditUser/AccountTab.vue'
import InformationTab from './EditUser/InformationTab.vue'
import SocialAccountsTab from './EditUser/SocialAccountsTab.vue'
import ActivityTab from './EditUser/ActivityTab.vue'
import PermissionsTab from './EditUser/PermissionsTab.vue'

export default {
    name: 'UserProfilePage',
    components: {
        CopyLabel,
        AccountTab,
        InformationTab,
        SocialAccountsTab,
        ActivityTab,
        PermissionsTab,
    },
    setup() {
        // Reactive data
        const currentStep = ref(1)
        const activeTab = ref('account')
        const refreshing = ref(false)
        const saving = ref(false)
        const confirming = ref(false)
        const showAdminSettings = ref(false)
        const showSuccess = ref(false)
        const showError = ref(false)
        const successMessage = ref('')
        const errorMessage = ref('')

        // Change tracking
        const hasAccountChanges = ref(false)
        const hasInfoChanges = ref(false)
        const hasPermissionChanges = ref(false)

        // Composables
        const { t } = useI18n()
        const { mobile } = useDisplay()
        const authStore = useAuthStore()
        const userStore = useUserStore()
        // Confirmations of the admin actions are modal
        const dialog = useDialog()

        // Computed properties
        const authenticated = computed(() => authStore.isLoggedIn)
        const user = computed(() => userStore.user)
        const roles = computed(() => userStore.roles || [])

        const breadcrumbs = computed(() => [
            {
                title: t('userProfile.users'),
                to: '/users/list',
                disabled: false
            },
            {
                title: user.value?.name || t('userProfile.editUser'),
                disabled: true
            }
        ])

        const userStatus = computed(() => {
            if (!user.value) return { color: 'error', icon: 'mdi-account-off', text: t('userProfile.unknown') }
            if (user.value.email_verified_at) {
                return { color: 'success', icon: 'mdi-account-check', text: t('userProfile.active') }
            }
            return { color: 'warning', icon: 'mdi-account-clock', text: t('userProfile.pending') }
        })

        const isOnline = computed(() => {
            // Simulate online status - would be real data in production
            return Math.random() > 0.3
        })

        const memberSince = computed(() => {
            if (!user.value?.created_at) return t('userProfile.unknown')
            return formatDate(user.value.created_at, 'd F Y')
        })

        const lastActive = computed(() => {
            if (!user.value?.last_login_at) return t('userProfile.never')
            return formatDateDistanceToNow(user.value.last_login_at)
        })

        const hasAnyChanges = computed(() => {
            return hasAccountChanges.value || hasInfoChanges.value || hasPermissionChanges.value
        })

        // Steps configuration
        const steps = computed(() => [
            { title: t('userProfile.overview'), subtitle: t('userProfile.profileSummary'), value: 1 },
            { title: t('userProfile.account'), subtitle: t('userProfile.securitySettings'), value: 2 },
            { title: t('userProfile.information'), subtitle: t('userProfile.personalDetails'), value: 3 }
        ])

        // Methods
        const refreshUser = async () => {
            if (refreshing.value) return
            refreshing.value = true
            try {
                await userStore.fetchUser()
                successMessage.value = t('userProfile.userDataRefreshed')
                showSuccess.value = true
            } catch (error) {
                errorMessage.value = t('userProfile.failedRefresh')
                showError.value = true
            } finally {
                refreshing.value = false
            }
        }

        const exportProfile = () => {
            const userData = {
                user: user.value,
                roles: roles.value,
                exportedAt: new Date().toISOString()
            }

            const blob = new Blob([JSON.stringify(userData, null, 2)], {
                type: 'application/json'
            })

            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = `user-${user.value?.id || 'profile'}-export.json`
            document.body.appendChild(a)
            a.click()
            document.body.removeChild(a)
            URL.revokeObjectURL(url)
        }

        const sendMessage = () => {
            // Implement message sending functionality
            console.log('Sending message to user:', user.value?.id)
        }

        const sendEmail = () => {
            if (user.value?.email) {
                window.location.href = `mailto:${user.value.email}`
            }
        }

        const viewActivity = () => {
            activeTab.value = 'activity'
        }

        const confirmResetPassword = async () => {
            if (confirming.value) return
            const confirmed = await dialog.confirm({
                title: t('userProfile.confirmAction'),
                content: t('userProfile.passwordResetConfirm', { name: user.value?.name || t('userProfile.unknownUser') }),
                color: 'warning',
            })
            if (confirmed) {
                await resetPassword()
            }
        }

        const resetPassword = async () => {
            confirming.value = true
            try {
                // Implement password reset API call
                await new Promise(resolve => setTimeout(resolve, 1000)) // Simulate API call
                successMessage.value = t('userProfile.passwordResetSent')
                showSuccess.value = true
            } catch (error) {
                errorMessage.value = t('userProfile.passwordResetFailed')
                showError.value = true
            } finally {
                confirming.value = false
            }
        }

        const confirmRemoveAdmin = async () => {
            if (confirming.value) return
            const confirmed = await dialog.confirm({
                title: t('userProfile.confirmAction'),
                content: t('userProfile.removeAdminConfirm', { name: user.value?.name || t('userProfile.unknownUser') }),
                confirmationText: t('userProfile.removeAdminPrivileges'),
            })
            if (confirmed) {
                await removeAdminPrivileges()
            }
        }

        const removeAdminPrivileges = async () => {
            confirming.value = true
            try {
                // Implement remove admin API call
                await new Promise(resolve => setTimeout(resolve, 1000)) // Simulate API call
                successMessage.value = t('userProfile.adminRemoved')
                showSuccess.value = true
                showAdminSettings.value = false
            } catch (error) {
                errorMessage.value = t('userProfile.adminRemoveFailed')
                showError.value = true
            } finally {
                confirming.value = false
            }
        }

        const viewAdminHistory = () => {
            // Implement admin history view
            console.log('Viewing admin history for user:', user.value?.id)
        }

        // Change handlers
        const onAccountChange = (hasChanges) => {
            hasAccountChanges.value = hasChanges
        }

        const onInfoChange = (hasChanges) => {
            hasInfoChanges.value = hasChanges
        }

        const onPermissionChange = (hasChanges) => {
            hasPermissionChanges.value = hasChanges
        }

        // Save handlers
        const onAccountSave = async (data) => {
            try {
                // Implement account save API call
                await new Promise(resolve => setTimeout(resolve, 1000)) // Simulate API call
                hasAccountChanges.value = false
                successMessage.value = t('userProfile.accountSaved')
                showSuccess.value = true
            } catch (error) {
                errorMessage.value = t('userProfile.accountSaveFailed')
                showError.value = true
            }
        }

        const onInfoSave = async (data) => {
            try {
                // Implement info save API call
                await new Promise(resolve => setTimeout(resolve, 1000)) // Simulate API call
                hasInfoChanges.value = false
                successMessage.value = t('userProfile.infoUpdated')
                showSuccess.value = true
            } catch (error) {
                errorMessage.value = t('userProfile.infoUpdateFailed')
                showError.value = true
            }
        }

        const onPermissionSave = async (data) => {
            try {
                // Implement permission save API call
                await new Promise(resolve => setTimeout(resolve, 1000)) // Simulate API call
                hasPermissionChanges.value = false
                successMessage.value = t('userProfile.permissionsUpdated')
                showSuccess.value = true
            } catch (error) {
                errorMessage.value = t('userProfile.permissionsUpdateFailed')
                showError.value = true
            }
        }

        const saveAllChanges = async () => {
            if (saving.value) return
            saving.value = true
            try {
                const promises = []

                if (hasAccountChanges.value) {
                    promises.push(onAccountSave())
                }
                if (hasInfoChanges.value) {
                    promises.push(onInfoSave())
                }
                if (hasPermissionChanges.value) {
                    promises.push(onPermissionSave())
                }

                await Promise.all(promises)
                successMessage.value = t('userProfile.allChangesSaved')
                showSuccess.value = true
            } catch (error) {
                errorMessage.value = t('userProfile.someChangesFailed')
                showError.value = true
            } finally {
                saving.value = false
            }
        }

        // Lifecycle
        onMounted(() => {
            // Set initial step based on active tab
            if (activeTab.value === 'account') currentStep.value = 2
            else if (activeTab.value === 'info') currentStep.value = 3
            else currentStep.value = 1
        })

        return {
            // Reactive data
            currentStep,
            activeTab,
            refreshing,
            saving,
            confirming,
            showAdminSettings,
            showSuccess,
            showError,
            successMessage,
            errorMessage,
            hasAccountChanges,
            hasInfoChanges,
            hasPermissionChanges,

            // Computed
            mobile,
            authenticated,
            user,
            roles,
            breadcrumbs,
            userStatus,
            isOnline,
            memberSince,
            lastActive,
            hasAnyChanges,
            steps,

            // Methods
            refreshUser,
            exportProfile,
            sendMessage,
            sendEmail,
            viewActivity,
            confirmResetPassword,
            confirmRemoveAdmin,
            viewAdminHistory,
            onAccountChange,
            onInfoChange,
            onPermissionChange,
            onAccountSave,
            onInfoSave,
            onPermissionSave,
            saveAllChanges
        }
    },
}
</script>

<style scoped>
.user-profile-container {
    position: relative;
}

.profile-header {
    background: linear-gradient(
        135deg,
        rgba(var(--v-theme-primary), 0.1) 0%,
        rgba(var(--v-theme-secondary), 0.1) 100%
    );
    border-radius: 16px;
    padding: 24px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    backdrop-filter: blur(10px);
}

.v-theme--dark .profile-header {
    background: linear-gradient(
        135deg,
        rgba(var(--v-theme-primary), 0.18) 0%,
        rgba(var(--v-theme-secondary), 0.12) 100%
    );
}

.profile-avatar {
    border: 3px solid rgba(var(--v-theme-primary), 0.2);
    box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.1);
}

.profile-title {
    line-height: 1.2;
}

.info-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(var(--v-theme-on-surface), 0.1) !important;
}

.tabs-header {
    background: rgba(var(--v-theme-on-surface), 0.04);
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.tab-button {
    text-transform: none !important;
    font-weight: 500 !important;
}

.tab-button:hover {
    background: rgba(var(--v-theme-primary), 0.1) !important;
}

.tab-content {
    min-height: 400px;
}

.tab-header {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.fab-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1000;
}

.save-fab {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%,
    100% {
        box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.3);
    }
    50% {
        box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.5);
    }
}

.message-fab {
    margin-bottom: 80px;
}

.admin-actions {
    background: rgba(var(--v-theme-on-surface), 0.04);
    border-radius: 12px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.copy-label {
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.info-card:hover .copy-label {
    opacity: 1;
}

/* Mobile optimizations */
@media (max-width: 960px) {
    .profile-header {
        padding: 16px;
    }

    .header-content .d-flex {
        flex-direction: column;
        align-items: flex-start;
    }

    .profile-avatar {
        margin-bottom: 16px;
        margin-right: 0 !important;
    }

    .header-actions {
        justify-content: center;
        margin-top: 16px;
        width: 100%;
    }

    .header-actions .v-btn-group {
        width: 100%;
    }

    .tab-content {
        min-height: 300px;
    }
}

@media (max-width: 600px) {
    .tabs-header .v-tab {
        font-size: 0.875rem !important;
        padding: 8px 12px !important;
    }

    .tabs-header .v-tab .v-icon {
        display: none;
    }

    .profile-title {
        font-size: 1.75rem !important;
    }
}

/* Print styles */
@media print {
    .fab-container,
    .header-actions,
    .v-snackbar {
        display: none !important;
    }

    .profile-header,
    .info-card,
    .tabs-card {
        background: rgb(var(--v-theme-surface)) !important;
        border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
        box-shadow: none !important;
    }
}
</style>
