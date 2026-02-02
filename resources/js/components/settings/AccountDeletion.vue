<template>
    <div>
        <v-alert v-if="!isEnabled" type="info" variant="tonal" class="mb-4">
            Account deletion is not enabled. Please contact support if you wish to delete your account.
        </v-alert>

        <template v-else>
            <v-alert type="warning" variant="tonal" class="mb-4">
                <v-alert-title>Delete Your Account</v-alert-title>
                <p class="mt-2 mb-0">
                    This action is permanent and cannot be undone. All your data will be permanently deleted.
                </p>
            </v-alert>

            <!-- Data Export Section -->
            <v-card variant="outlined" class="mb-4">
                <v-card-text>
                    <div class="d-flex align-center">
                        <v-icon color="primary" class="mr-3">mdi-download</v-icon>
                        <div class="flex-grow-1">
                            <div class="font-weight-medium">Export Your Data</div>
                            <div class="text-body-2 text-medium-emphasis">
                                Download a copy of all your data before deleting your account.
                            </div>
                        </div>
                        <v-btn
                            color="primary"
                            variant="outlined"
                            :loading="isExporting"
                            @click="exportData"
                        >
                            Export Data
                        </v-btn>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Delete Account Section -->
            <v-card variant="outlined" class="border-error">
                <v-card-text>
                    <div class="d-flex align-center mb-4">
                        <v-icon color="error" class="mr-3">mdi-account-remove</v-icon>
                        <div>
                            <div class="font-weight-medium text-error">Delete Account</div>
                            <div class="text-body-2 text-medium-emphasis">
                                Permanently delete your account and all associated data.
                            </div>
                        </div>
                    </div>

                    <v-btn
                        color="error"
                        variant="outlined"
                        @click="showConfirmDialog = true"
                    >
                        Delete My Account
                    </v-btn>
                </v-card-text>
            </v-card>
        </template>

        <!-- Are You Sure Dialog -->
        <v-dialog v-model="showConfirmDialog" max-width="450">
            <v-card>
                <v-card-title class="d-flex align-center text-error">
                    <v-icon color="error" class="mr-2">mdi-alert</v-icon>
                    Are you sure?
                </v-card-title>

                <v-card-text>
                    <p class="text-body-1 mb-4">
                        You are about to permanently delete your account. This action <strong>cannot be undone</strong>.
                    </p>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                        All your data, including your profile, posts, messages, and API keys will be permanently removed.
                    </p>
                </v-card-text>

                <v-card-actions class="pa-4 pt-0">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        @click="showConfirmDialog = false"
                    >
                        Cancel
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="tonal"
                        @click="proceedToDelete"
                    >
                        Yes, I want to delete my account
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="showDeleteDialog" max-width="500" persistent>
            <v-card>
                <v-card-title class="d-flex align-center text-error">
                    <v-icon color="error" class="mr-2">mdi-alert-circle</v-icon>
                    Delete Account
                </v-card-title>

                <v-card-text>
                    <v-alert type="error" variant="tonal" class="mb-4">
                        This action is <strong>permanent</strong> and <strong>cannot be undone</strong>.
                        All your data will be permanently deleted including:
                        <ul class="mt-2 mb-0">
                            <li>Your profile and account information</li>
                            <li>All posts and content you've created</li>
                            <li>Messages and conversations</li>
                            <li>API keys and integrations</li>
                        </ul>
                    </v-alert>

                    <v-text-field
                        v-model="deleteForm.password"
                        label="Enter your password to confirm"
                        type="password"
                        variant="outlined"
                        :error-messages="formErrors.password"
                        class="mb-4"
                    />

                    <v-textarea
                        v-model="deleteForm.reason"
                        label="Reason for leaving (optional)"
                        variant="outlined"
                        rows="2"
                        hint="Help us improve by sharing why you're leaving"
                        persistent-hint
                        class="mb-4"
                    />

                    <v-checkbox
                        v-model="deleteForm.confirm"
                        :error-messages="formErrors.confirm"
                        density="compact"
                    >
                        <template v-slot:label>
                            <span class="text-body-2">
                                I understand that this action is permanent and all my data will be deleted.
                            </span>
                        </template>
                    </v-checkbox>
                </v-card-text>

                <v-card-actions class="pa-4 pt-0">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        @click="closeDeleteDialog"
                    >
                        Cancel
                    </v-btn>
                    <v-btn
                        color="error"
                        :loading="isDeleting"
                        :disabled="!deleteForm.confirm"
                        @click="deleteAccount"
                    >
                        Permanently Delete Account
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Export Data Dialog -->
        <v-dialog v-model="showExportDialog" max-width="600">
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="primary" class="mr-2">mdi-download</v-icon>
                    Your Data Export
                </v-card-title>

                <v-card-text>
                    <v-alert type="success" variant="tonal" class="mb-4">
                        Your data has been exported successfully.
                    </v-alert>

                    <v-textarea
                        :model-value="JSON.stringify(exportedData, null, 2)"
                        readonly
                        variant="outlined"
                        rows="15"
                        class="font-monospace"
                    />
                </v-card-text>

                <v-card-actions class="pa-4 pt-0">
                    <v-spacer />
                    <v-btn variant="text" @click="showExportDialog = false">Close</v-btn>
                    <v-btn color="primary" @click="downloadExport">
                        <v-icon start>mdi-download</v-icon>
                        Download JSON
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Error Alert -->
        <v-snackbar v-model="showError" color="error" :timeout="5000">
            {{ errorMessage }}
            <template v-slot:actions>
                <v-btn variant="text" @click="showError = false">Close</v-btn>
            </template>
        </v-snackbar>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/store/authStore';
import { useUserStore } from '@/store/userStore';
import axios from 'axios';

const router = useRouter();
const authStore = useAuthStore();
const userStore = useUserStore();

const isEnabled = ref(false);
const isExporting = ref(false);
const isDeleting = ref(false);
const showConfirmDialog = ref(false);
const showDeleteDialog = ref(false);
const showExportDialog = ref(false);
const showError = ref(false);
const errorMessage = ref('');
const exportedData = ref(null);

const deleteForm = reactive({
    password: '',
    reason: '',
    confirm: false,
});

const formErrors = reactive({
    password: '',
    confirm: '',
});

async function fetchStatus() {
    try {
        const response = await axios.get('/api/account/deletion/status');
        isEnabled.value = response.data.enabled;
    } catch (err) {
        console.error('Failed to fetch deletion status:', err);
    }
}

async function exportData() {
    isExporting.value = true;
    try {
        const response = await axios.get('/api/account/export-data');
        exportedData.value = response.data.data;
        showExportDialog.value = true;
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Failed to export data';
        showError.value = true;
    } finally {
        isExporting.value = false;
    }
}

function downloadExport() {
    const dataStr = JSON.stringify(exportedData.value, null, 2);
    const blob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `my-data-export-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

async function deleteAccount() {
    formErrors.password = '';
    formErrors.confirm = '';

    if (!deleteForm.password) {
        formErrors.password = 'Password is required';
        return;
    }

    if (!deleteForm.confirm) {
        formErrors.confirm = 'You must confirm to proceed';
        return;
    }

    isDeleting.value = true;
    try {
        await axios.post('/api/account/delete', {
            password: deleteForm.password,
            reason: deleteForm.reason,
            confirm: deleteForm.confirm,
        });

        // Clear all local state (user is already deleted on backend)
        authStore.clearState();
        userStore.clearState();
        localStorage.clear();

        // Close the dialog
        showDeleteDialog.value = false;

        // Redirect to home with message
        router.push({ name: 'home', query: { deleted: 'true' } });
    } catch (err) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            if (errors.password) formErrors.password = errors.password[0];
            if (errors.confirm) formErrors.confirm = errors.confirm[0];
        } else {
            errorMessage.value = err.response?.data?.message || 'Failed to delete account';
            showError.value = true;
        }
    } finally {
        isDeleting.value = false;
    }
}

function proceedToDelete() {
    showConfirmDialog.value = false;
    showDeleteDialog.value = true;
}

function closeDeleteDialog() {
    showDeleteDialog.value = false;
    deleteForm.password = '';
    deleteForm.reason = '';
    deleteForm.confirm = false;
    formErrors.password = '';
    formErrors.confirm = '';
}

onMounted(() => {
    fetchStatus();
});
</script>

<style scoped>
.border-error {
    border-color: rgb(var(--v-theme-error)) !important;
}

.font-monospace {
    font-family: monospace;
}
</style>
