<template>
    <div class="my-2">
        <div>
            <v-card v-if="user.disabled" class="warning mb-4" light>
                <v-card-title>User Disabled</v-card-title>
                <v-card-subtitle>This user has been disabled! Login accesss has been revoked.</v-card-subtitle>
                <v-card-text>
                    <v-btn dark @click="user.disabled = false">
                        <v-icon left small>mdi-account-check</v-icon>
                        Enable User
                    </v-btn>
                </v-card-text>
            </v-card>

            <v-card>
                <v-card-title>Basic Information</v-card-title>
                <v-card-text>
                    <div class="d-flex flex-column flex-sm-row">
                        <div>
                            <v-img
                                :src="user.avatar"
                                aspect-ratio="1"
                                class="blue-grey lighten-4 rounded elevation-3"
                                max-width="90"
                                max-height="90"
                            ></v-img>
                            <!--                <v-text-field label="Select Image" @click='pickFile' v-model='avatar' prepend-icon='attach_file'></v-text-field>-->
                            <image-upload v-show="false" ref="avatar" name="avatar" class="mr-1" @loaded="onLoad"></image-upload>

                            <v-btn class="mt-1" @click="trigger" small>Edit Avatar</v-btn>
                        </div>
                        <div class="flex-grow-1 pt-2 pa-sm-2">
                            <v-text-field v-model="user.name" label="Display name" placeholder="name"></v-text-field>
                            <v-text-field v-model="user.email" label="Email" hide-details></v-text-field>

                            <div class="d-flex flex-column">
                                <v-checkbox v-model="user.email_verified_at" dense label="Email Verified"></v-checkbox>
                                <div>
                                    <v-btn
                                        v-if="!user.email_verified_at"
                                    >
                                        <v-icon left small>mdi-email</v-icon>
                                        Send Verification Email
                                    </v-btn>
                                </div>
                            </div>

                            <div class="mt-2">
                                <v-btn color="bg-primary" @click="updateUser">Save</v-btn>
                            </div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>

            <v-card class="mt-4">
                <v-card-title>Localization Preferences</v-card-title>
                <v-card-subtitle>Customize how dates and times are displayed</v-card-subtitle>
                <v-card-text>
                    <!-- Timezone Selection -->
                    <v-autocomplete
                        v-model="userPreferences.timezone"
                        label="Timezone"
                        :items="timezones"
                        item-title="label"
                        item-value="value"
                        prepend-inner-icon="mdi-clock-outline"
                        variant="outlined"
                        clearable
                        :hint="timezoneHint"
                        persistent-hint
                        class="mb-4"
                    >
                        <template #append-inner>
                            <v-tooltip location="top">
                                <template #activator="{ props: tooltipProps }">
                                    <v-icon v-bind="tooltipProps" color="info">mdi-information</v-icon>
                                </template>
                                <span>Leave empty to use system default ({{ defaultTimezone }})</span>
                            </v-tooltip>
                        </template>
                    </v-autocomplete>

                    <!-- Date Format Selection -->
                    <v-select
                        v-model="userPreferences.date_format"
                        label="Date Format"
                        :items="dateFormats"
                        item-title="label"
                        item-value="value"
                        prepend-inner-icon="mdi-calendar"
                        variant="outlined"
                        clearable
                        :hint="dateFormatHint"
                        persistent-hint
                        class="mb-4"
                    >
                        <template #append-inner>
                            <v-tooltip location="top">
                                <template #activator="{ props: tooltipProps }">
                                    <v-icon v-bind="tooltipProps" color="info">mdi-information</v-icon>
                                </template>
                                <span>Leave empty to use system default ({{ defaultDateFormat }})</span>
                            </v-tooltip>
                        </template>
                    </v-select>

                    <!-- Time Format Selection -->
                    <v-select
                        v-model="userPreferences.time_format"
                        label="Time Format"
                        :items="timeFormats"
                        item-title="label"
                        item-value="value"
                        prepend-inner-icon="mdi-clock"
                        variant="outlined"
                        clearable
                        :hint="timeFormatHint"
                        persistent-hint
                        class="mb-4"
                    >
                        <template #append-inner>
                            <v-tooltip location="top">
                                <template #activator="{ props: tooltipProps }">
                                    <v-icon v-bind="tooltipProps" color="info">mdi-information</v-icon>
                                </template>
                                <span>Leave empty to use system default ({{ defaultTimeFormat }})</span>
                            </v-tooltip>
                        </template>
                    </v-select>

                    <!-- Preview -->
                    <v-alert
                        type="info"
                        variant="tonal"
                        border="start"
                        class="mt-4"
                    >
                        <template #prepend>
                            <v-icon>mdi-eye</v-icon>
                        </template>
                        <div class="text-body-2">
                            <strong>Preview:</strong><br>
                            Current time in your timezone: {{ previewDateTime }}
                        </div>
                    </v-alert>

                    <div class="mt-4">
                        <v-btn
                            color="primary"
                            @click="savePreferences"
                            :loading="savingPreferences"
                        >
                            Save Preferences
                        </v-btn>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Account Deletion (GDPR) - for own account -->
            <v-card class="mt-4">
                <v-card-title>Data & Privacy</v-card-title>
                <v-card-subtitle>Manage your data and account deletion options</v-card-subtitle>
                <v-card-text>
                    <account-deletion />
                </v-card-text>
            </v-card>

            <v-expansion-panels v-if="roles.includes('admin')" v-model="panel" multiple class="mt-3">
                <v-expansion-panel>
                    <v-expansion-panel-title class="title">Actions</v-expansion-panel-title>
                    <v-expansion-panel-text>
                        <div class="mb-2">
                            <div class="title">Reset User Password</div>
                            <div class="subtitle mb-2">Sends a reset password email to the user.</div>
                            <v-btn
                                class="mb-2"
                            >
                                <v-icon left small>mdi-email</v-icon>
                                Send Reset Password Email
                            </v-btn>
                        </div>

                        <v-divider></v-divider>

                        <div class="my-2">
                            <div class="title">Export Account Data</div>
                            <div class="subtitle mb-2">Export all the platform metadata for this user.</div>
                            <v-btn class="mb-2">
                                <v-icon left small>mdi-clipboard-account</v-icon>
                                Export User Data
                            </v-btn>
                        </div>

                        <v-divider></v-divider>

                        <div class="my-2">
                            <div class="error--text title">Danger Zone</div>
                            <div class="subtitle mb-2">Full administrator with access to this dashboard.</div>

                            <div class="my-2">
                                <v-btn
                                    v-if="user.role === 'ADMIN'"
                                    color="bg-primary"
                                    @click="user.role = 'USER'"
                                >
                                    <v-icon left small>mdi-security</v-icon>
                                    Remove admin access
                                </v-btn>
                                <v-btn v-else color="bg-primary" @click="user.role = 'ADMIN'">
                                    <v-icon left small>mdi-security</v-icon>
                                    Set User as Admin
                                </v-btn>
                            </div>

                            <v-divider></v-divider>

                            <div class="subtitle mt-3 mb-2">Prevent the user from signing in on the platform.</div>
                            <div class="my-2">
                                <v-btn
                                    v-if="user.disabled"
                                    color="warning"
                                    @click="user.disabled = false"
                                >
                                    <v-icon left small>mdi-account-check</v-icon>
                                    Enable User
                                </v-btn>
                                <v-btn
                                    v-else
                                    color="warning"
                                    @click="disableDialog = true"
                                >
                                    <v-icon left small>mdi-cancel</v-icon>
                                    Disable User
                                </v-btn>
                            </div>

                            <v-divider></v-divider>
                            <div
                                class="subtitle mt-3 mb-2"
                            >To delete the user please transfer ownership or delete user's subscriptions.
                            </div>
                            <v-btn color="error" @click="deleteDialog = true">
                                <v-icon left small>mdi-delete</v-icon>
                                Delete User
                            </v-btn>
                        </div>
                    </v-expansion-panel-text>
                </v-expansion-panel>
                <v-expansion-panel>
                    <v-expansion-panel-title class="title">Metadata</v-expansion-panel-title>
                    <v-expansion-panel-text class="body-2">
                        <span class="font-weight-bold">Created</span>
                        {{ $formatDate(new Date(user?.created_at), 'd LLL yyyy') }}
                        <br/>
                        <span class="font-weight-bold">Last Sign In</span>
                        {{ $formatDate(new Date(user?.last_login_at), 'd LLL yyyy') }}
                    </v-expansion-panel-text>
                </v-expansion-panel>
                <v-expansion-panel>
                    <v-expansion-panel-title class="title">Raw Data</v-expansion-panel-title>
                    <v-expansion-panel-text>
                        <pre class="body-2">{{ user }}</pre>
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>
        </div>

        <!-- disable modal -->
        <v-dialog v-model="disableDialog" max-width="290">
            <v-card>
                <v-card-title class="headline">Disable User</v-card-title>
                <v-card-text>Are you sure you want to disable this user?</v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="disableDialog = false">Cancel</v-btn>
                    <v-btn color="warning" @click="user.disabled = true; disableDialog = false">Disable</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- delete modal -->
        <v-dialog v-model="deleteDialog" max-width="290">
            <v-card>
                <v-card-title class="headline">Delete User</v-card-title>
                <v-card-text>Are you sure you want to delete this user?</v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="deleteDialog = false">Cancel</v-btn>
                    <v-btn color="error" @click="deleteDialog = false">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { useSettingsStore } from '@/store/settingStore.js';
import { useUserStore } from '@/store/userStore.js';
import { formatDateInTimezone } from '@/plugins/formatDate.js';
import ImageUpload from "../../../components/common/ImageUpload.vue";
import AccountDeletion from "../../../components/settings/AccountDeletion.vue";
import axios from 'axios';

export default {
    components: {
        ImageUpload,
        AccountDeletion,
    },
    props: {
        user: {
            default: () => ({})
        },
        roles: {
            default: () => ({})
        }
    },
    setup(props) {
        const settingsStore = useSettingsStore();
        const userStore = useUserStore();

        const avatar = ref(null);
        const panel = ref([1]);
        const deleteDialog = ref(false);
        const disableDialog = ref(false);
        const savingPreferences = ref(false);

        // User preferences state
        const userPreferences = ref({
            timezone: props.user?.timezone || null,
            date_format: props.user?.date_format || null,
            time_format: props.user?.time_format || null,
        });

        // Watch for changes in props.user to update preferences
        watch(
            () => props.user,
            (newUser) => {
                if (newUser) {
                    userPreferences.value = {
                        timezone: newUser.timezone || null,
                        date_format: newUser.date_format || null,
                        time_format: newUser.time_format || null,
                    };
                }
            },
            { deep: true, immediate: false }
        );

        // Timezone options (comprehensive list)
        const timezones = ref([
            { label: 'UTC', value: 'UTC' },
            { label: 'America/New_York (Eastern)', value: 'America/New_York' },
            { label: 'America/Chicago (Central)', value: 'America/Chicago' },
            { label: 'America/Denver (Mountain)', value: 'America/Denver' },
            { label: 'America/Los_Angeles (Pacific)', value: 'America/Los_Angeles' },
            { label: 'Europe/London (GMT)', value: 'Europe/London' },
            { label: 'Europe/Paris (CET)', value: 'Europe/Paris' },
            { label: 'Europe/Berlin (CET)', value: 'Europe/Berlin' },
            { label: 'Europe/Rome (CET)', value: 'Europe/Rome' },
            { label: 'Europe/Madrid (CET)', value: 'Europe/Madrid' },
            { label: 'Asia/Tokyo (JST)', value: 'Asia/Tokyo' },
            { label: 'Asia/Shanghai (CST)', value: 'Asia/Shanghai' },
            { label: 'Asia/Singapore (SGT)', value: 'Asia/Singapore' },
            { label: 'Asia/Dubai (GST)', value: 'Asia/Dubai' },
            { label: 'Australia/Sydney (AEST)', value: 'Australia/Sydney' },
            { label: 'Pacific/Auckland (NZST)', value: 'Pacific/Auckland' },
        ]);

        // Date format options
        const dateFormats = ref([
            { label: 'YYYY-MM-DD (2025-12-17)', value: 'Y-m-d' },
            { label: 'DD/MM/YYYY (17/12/2025)', value: 'd/m/Y' },
            { label: 'MM/DD/YYYY (12/17/2025)', value: 'm/d/Y' },
            { label: 'DD.MM.YYYY (17.12.2025)', value: 'd.m.Y' },
        ]);

        // Time format options
        const timeFormats = ref([
            { label: '24-hour with seconds (14:30:45)', value: 'H:i:s' },
            { label: '12-hour with seconds (02:30:45 PM)', value: 'h:i:s A' },
            { label: '24-hour no seconds (14:30)', value: 'H:i' },
            { label: '12-hour no seconds (02:30 PM)', value: 'h:i A' },
        ]);

        // Computed properties
        const defaultTimezone = computed(() =>
            settingsStore.defaultTimezone || 'UTC'
        );

        const defaultDateFormat = computed(() =>
            settingsStore.defaultDateFormat || 'Y-m-d'
        );

        const defaultTimeFormat = computed(() =>
            settingsStore.defaultTimeFormat || 'H:i:s'
        );

        const timezoneHint = computed(() => {
            const tz = userPreferences.value.timezone || defaultTimezone.value;
            return `Selected: ${tz}`;
        });

        const dateFormatHint = computed(() => {
            const fmt = userPreferences.value.date_format || defaultDateFormat.value;
            const selected = dateFormats.value.find(f => f.value === fmt);
            return `Selected: ${selected?.label || fmt}`;
        });

        const timeFormatHint = computed(() => {
            const fmt = userPreferences.value.time_format || defaultTimeFormat.value;
            const selected = timeFormats.value.find(f => f.value === fmt);
            return `Selected: ${selected?.label || fmt}`;
        });

        const previewDateTime = computed(() => {
            const tz = userPreferences.value.timezone || defaultTimezone.value;
            const dateFmt = userPreferences.value.date_format || defaultDateFormat.value;
            const timeFmt = userPreferences.value.time_format || defaultTimeFormat.value;

            try {
                // Combine date and time format
                const combinedFormat = `${dateFmt} ${timeFmt}`;
                return formatDateInTimezone(new Date().toISOString(), combinedFormat, tz);
            } catch (error) {
                return 'Preview unavailable';
            }
        });

        // Methods
        const onLoad = (avatarImage) => {
            props.user.avatar = avatarImage.src;
            persist(avatarImage.file);
        };

        const persist = (avatar) => {
            let data = new FormData();
            data.append('avatar', avatar);
            axios.post(`/api/account/avatar`, data)
                .then(() => {});
        };

        const trigger = () => {
            // Assuming ref is still set on the ImageUpload component
            this.$refs.avatar.$el.click()
        };

        const updateUser = () => {
            axios.patch(`/api/datatable/users/${this.user.id}`, this.user).then(() => {
                // console.log('done')
            }).catch((error) => {
                console.log(error);
                if (error.response.status === 422) {
                    this.editing.errors = error.response.data
                }
            })
        };

        const savePreferences = async () => {
            savingPreferences.value = true;
            try {
                await userStore.updatePreferences(userPreferences.value);
                // Show success notification (you can add a snackbar/toast here)
                console.log('Preferences saved successfully');
            } catch (error) {
                console.error('Failed to save preferences:', error);
                // Show error notification
            } finally {
                savingPreferences.value = false;
            }
        };

        return {
            avatar,
            panel,
            deleteDialog,
            disableDialog,
            savingPreferences,
            userPreferences,
            timezones,
            dateFormats,
            timeFormats,
            defaultTimezone,
            defaultDateFormat,
            defaultTimeFormat,
            timezoneHint,
            dateFormatHint,
            timeFormatHint,
            previewDateTime,
            onLoad,
            persist,
            trigger,
            updateUser,
            savePreferences,
        }
    }
}
</script>
