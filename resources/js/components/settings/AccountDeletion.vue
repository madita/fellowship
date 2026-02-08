<template>
    <div>
        <v-alert v-if="!isEnabled" type="info" variant="tonal" class="mb-4">
            {{ $t('accountDeletion.notEnabled') }}
        </v-alert>

        <template v-else>
            <v-alert type="warning" variant="tonal" class="mb-4">
                <v-alert-title>{{ $t('accountDeletion.deleteYourAccount') }}</v-alert-title>
                <p class="mt-2 mb-0">
                    {{ $t('accountDeletion.permanentWarning') }}
                </p>
            </v-alert>

            <!-- Data Export Section -->
            <v-card variant="outlined" class="mb-4">
                <v-card-text>
                    <div class="d-flex align-center">
                        <v-icon color="primary" class="mr-3">mdi-download</v-icon>
                        <div class="flex-grow-1">
                            <div class="font-weight-medium">{{ $t('accountDeletion.exportYourData') }}</div>
                            <div class="text-body-2 text-medium-emphasis">
                                {{ $t('accountDeletion.exportDescription') }}
                            </div>
                        </div>
                        <v-btn
                            color="primary"
                            variant="outlined"
                            :loading="isExporting"
                            @click="exportData"
                        >
                            {{ $t('accountDeletion.exportData') }}
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
                            <div class="font-weight-medium text-error">{{ $t('accountDeletion.deleteAccount') }}</div>
                            <div class="text-body-2 text-medium-emphasis">
                                {{ $t('accountDeletion.deleteDescription') }}
                            </div>
                        </div>
                    </div>

                    <v-btn
                        color="error"
                        variant="outlined"
                        @click="showConfirmDialog = true"
                    >
                        {{ $t('accountDeletion.deleteMyAccount') }}
                    </v-btn>
                </v-card-text>
            </v-card>
        </template>

        <!-- Are You Sure Dialog -->
        <v-dialog v-model="showConfirmDialog" max-width="450">
            <v-card>
                <v-card-title class="d-flex align-center text-error">
                    <v-icon color="error" class="mr-2">mdi-alert</v-icon>
                    {{ $t('accountDeletion.areYouSure') }}
                </v-card-title>

                <v-card-text>
                    <p class="text-body-1 mb-4">
                        {{ $t('accountDeletion.aboutToDelete') }}
                    </p>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                        {{ $t('accountDeletion.allDataRemoved') }}
                    </p>
                </v-card-text>

                <v-card-actions class="pa-4 pt-0">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        @click="showConfirmDialog = false"
                    >
                        {{ $t('common.cancel') }}
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="tonal"
                        @click="proceedToDelete"
                    >
                        {{ $t('accountDeletion.yesDelete') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="showDeleteDialog" max-width="500" persistent>
            <v-card>
                <v-card-title class="d-flex align-center text-error">
                    <v-icon color="error" class="mr-2">mdi-alert-circle</v-icon>
                    {{ $t('accountDeletion.deleteAccount') }}
                </v-card-title>

                <v-card-text>
                    <v-alert type="error" variant="tonal" class="mb-4">
                        {{ $t('accountDeletion.permanentActionWarning') }}
                        <ul class="mt-2 mb-0">
                            <li>{{ $t('accountDeletion.dataList.profile') }}</li>
                            <li>{{ $t('accountDeletion.dataList.posts') }}</li>
                            <li>{{ $t('accountDeletion.dataList.messages') }}</li>
                            <li>{{ $t('accountDeletion.dataList.apiKeys') }}</li>
                        </ul>
                    </v-alert>

                    <v-text-field
                        v-model="deleteForm.password"
                        :label="$t('accountDeletion.enterPassword')"
                        type="password"
                        variant="outlined"
                        :error-messages="formErrors.password"
                        class="mb-4"
                    />

                    <v-textarea
                        v-model="deleteForm.reason"
                        :label="$t('accountDeletion.reasonLabel')"
                        variant="outlined"
                        rows="2"
                        :hint="$t('accountDeletion.reasonHint')"
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
                                {{ $t('accountDeletion.understandCheckbox') }}
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
                        {{ $t('common.cancel') }}
                    </v-btn>
                    <v-btn
                        color="error"
                        :loading="isDeleting"
                        :disabled="!deleteForm.confirm"
                        @click="deleteAccount"
                    >
                        {{ $t('accountDeletion.permanentlyDelete') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Export Data Dialog -->
        <v-dialog v-model="showExportDialog" max-width="600">
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="primary" class="mr-2">mdi-download</v-icon>
                    {{ $t('accountDeletion.exportSuccess') }}
                </v-card-title>

                <v-card-text>
                    <v-alert type="success" variant="tonal" class="mb-4">
                        {{ $t('accountDeletion.exportSuccessMessage') }}
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
                    <v-btn variant="text" @click="showExportDialog = false">{{ $t('common.close') }}</v-btn>
                    <v-btn color="primary" @click="downloadExport">
                        <v-icon start>mdi-download</v-icon>
                        {{ $t('accountDeletion.downloadJson') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Error Alert -->
        <v-snackbar v-model="showError" color="error" :timeout="5000">
            {{ errorMessage }}
            <template v-slot:actions>
                <v-btn variant="text" @click="showError = false">{{ $t('common.close') }}</v-btn>
            </template>
        </v-snackbar>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/store/authStore';
import { useUserStore } from '@/store/userStore';
import axios from 'axios';

const { t } = useI18n();

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
        formErrors.password = t('accountDeletion.passwordRequired');
        return;
    }

    if (!deleteForm.confirm) {
        formErrors.confirm = t('accountDeletion.mustConfirm');
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
