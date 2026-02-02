<template>
    <div>
        <v-alert v-if="!isEnabled" type="info" variant="tonal" class="mb-4">
            API keys feature is not enabled. Contact your administrator to enable it.
        </v-alert>

        <template v-else>
            <div class="d-flex justify-space-between align-center mb-4">
                <div>
                    <div class="text-h6">API Keys</div>
                    <div class="text-caption text-medium-emphasis">
                        Manage your API keys for external integrations. Rate limit: {{ rateLimit }} requests/minute
                    </div>
                </div>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    @click="showCreateDialog = true"
                >
                    Create API Key
                </v-btn>
            </div>

            <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
                {{ error }}
            </v-alert>

            <!-- API Keys List -->
            <v-card v-if="apiKeys.length > 0" variant="outlined">
                <v-list>
                    <template v-for="(key, index) in apiKeys" :key="key.id">
                        <v-list-item>
                            <template v-slot:prepend>
                                <v-icon :color="key.is_active ? 'success' : 'grey'">
                                    {{ key.is_active ? 'mdi-key' : 'mdi-key-off' }}
                                </v-icon>
                            </template>

                            <v-list-item-title class="font-weight-medium">
                                {{ key.name }}
                            </v-list-item-title>

                            <v-list-item-subtitle>
                                <code class="text-caption">{{ key.key_preview }}</code>
                                <span class="mx-2">|</span>
                                <span v-if="key.last_used_at">
                                    Last used: {{ formatDate(key.last_used_at) }}
                                </span>
                                <span v-else class="text-medium-emphasis">Never used</span>
                                <span class="mx-2">|</span>
                                <span>{{ key.request_count }} requests</span>
                                <span v-if="key.expires_at" class="mx-2">|</span>
                                <span v-if="key.expires_at" :class="isExpired(key.expires_at) ? 'text-error' : ''">
                                    {{ isExpired(key.expires_at) ? 'Expired' : `Expires: ${formatDate(key.expires_at)}` }}
                                </span>
                            </v-list-item-subtitle>

                            <template v-slot:append>
                                <v-btn
                                    icon
                                    variant="text"
                                    size="small"
                                    @click="toggleKey(key)"
                                    :title="key.is_active ? 'Deactivate' : 'Activate'"
                                >
                                    <v-icon>{{ key.is_active ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                                </v-btn>
                                <v-btn
                                    icon
                                    variant="text"
                                    size="small"
                                    @click="confirmRegenerate(key)"
                                    title="Regenerate"
                                >
                                    <v-icon>mdi-refresh</v-icon>
                                </v-btn>
                                <v-btn
                                    icon
                                    variant="text"
                                    size="small"
                                    color="error"
                                    @click="confirmDelete(key)"
                                    title="Delete"
                                >
                                    <v-icon>mdi-delete</v-icon>
                                </v-btn>
                            </template>
                        </v-list-item>
                        <v-divider v-if="index < apiKeys.length - 1" />
                    </template>
                </v-list>
            </v-card>

            <v-card v-else variant="outlined" class="pa-8 text-center">
                <v-icon size="64" color="grey-lighten-1" class="mb-4">mdi-key-outline</v-icon>
                <div class="text-h6 text-medium-emphasis mb-2">No API Keys</div>
                <div class="text-body-2 text-medium-emphasis mb-4">
                    Create an API key to access the API programmatically.
                </div>
                <v-btn color="primary" @click="showCreateDialog = true">
                    Create Your First API Key
                </v-btn>
            </v-card>
        </template>

        <!-- Create Dialog -->
        <v-dialog v-model="showCreateDialog" max-width="500">
            <v-card>
                <v-card-title>Create API Key</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="newKey.name"
                        label="Key Name"
                        placeholder="e.g., My Integration"
                        variant="outlined"
                        :error-messages="formErrors.name"
                        class="mb-4"
                    />

                    <v-text-field
                        v-model="newKey.expires_at"
                        label="Expiration Date (Optional)"
                        type="date"
                        variant="outlined"
                        :min="minDate"
                        hint="Leave empty for no expiration"
                        persistent-hint
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateDialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="isCreating" @click="createKey">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Secret Display Dialog -->
        <v-dialog v-model="showSecretDialog" max-width="600" persistent>
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="warning" class="mr-2">mdi-alert</v-icon>
                    Save Your API Secret
                </v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal" class="mb-4">
                        This is the only time you will see this secret. Save it securely!
                    </v-alert>

                    <div class="mb-4">
                        <div class="text-caption text-medium-emphasis mb-1">API Key</div>
                        <v-text-field
                            :model-value="createdKey?.key"
                            readonly
                            variant="outlined"
                            density="compact"
                            append-inner-icon="mdi-content-copy"
                            @click:append-inner="copyToClipboard(createdKey?.key, 'Key')"
                        />
                    </div>

                    <div class="mb-4">
                        <div class="text-caption text-medium-emphasis mb-1">API Secret</div>
                        <v-text-field
                            :model-value="createdKey?.secret"
                            readonly
                            variant="outlined"
                            density="compact"
                            append-inner-icon="mdi-content-copy"
                            @click:append-inner="copyToClipboard(createdKey?.secret, 'Secret')"
                        />
                    </div>

                    <v-alert type="info" variant="tonal" density="compact">
                        <div class="text-caption">
                            <strong>Usage:</strong> Include these headers in your API requests:
                            <pre class="mt-2 pa-2 bg-grey-darken-3 rounded text-white">X-API-Key: {{ createdKey?.key }}
X-API-Secret: {{ createdKey?.secret }}</pre>
                        </div>
                    </v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" @click="closeSecretDialog">I've Saved the Secret</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Confirm Delete Dialog -->
        <v-dialog v-model="showDeleteDialog" max-width="400">
            <v-card>
                <v-card-title>Delete API Key</v-card-title>
                <v-card-text>
                    Are you sure you want to delete the API key "{{ keyToDelete?.name }}"?
                    This action cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteDialog = false">Cancel</v-btn>
                    <v-btn color="error" :loading="isDeleting" @click="deleteKey">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Confirm Regenerate Dialog -->
        <v-dialog v-model="showRegenerateDialog" max-width="400">
            <v-card>
                <v-card-title>Regenerate API Key</v-card-title>
                <v-card-text>
                    Are you sure you want to regenerate the API key "{{ keyToRegenerate?.name }}"?
                    The current key and secret will stop working immediately.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showRegenerateDialog = false">Cancel</v-btn>
                    <v-btn color="warning" :loading="isRegenerating" @click="regenerateKey">Regenerate</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const isEnabled = ref(false);
const rateLimit = ref(60);
const apiKeys = ref([]);
const isLoading = ref(false);
const error = ref('');

// Create dialog
const showCreateDialog = ref(false);
const isCreating = ref(false);
const newKey = ref({ name: '', expires_at: '' });
const formErrors = ref({});

// Secret display dialog
const showSecretDialog = ref(false);
const createdKey = ref(null);

// Delete dialog
const showDeleteDialog = ref(false);
const keyToDelete = ref(null);
const isDeleting = ref(false);

// Regenerate dialog
const showRegenerateDialog = ref(false);
const keyToRegenerate = ref(null);
const isRegenerating = ref(false);

const minDate = computed(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().split('T')[0];
});

async function fetchStatus() {
    try {
        const response = await axios.get('/api/api-keys/status');
        isEnabled.value = response.data.enabled;
        rateLimit.value = response.data.rate_limit;
    } catch (err) {
        console.error('Failed to fetch API key status:', err);
    }
}

async function fetchKeys() {
    if (!isEnabled.value) return;

    isLoading.value = true;
    try {
        const response = await axios.get('/api/api-keys');
        apiKeys.value = response.data.api_keys;
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to fetch API keys';
    } finally {
        isLoading.value = false;
    }
}

async function createKey() {
    formErrors.value = {};
    isCreating.value = true;

    try {
        const payload = {
            name: newKey.value.name,
        };
        if (newKey.value.expires_at) {
            payload.expires_at = newKey.value.expires_at;
        }

        const response = await axios.post('/api/api-keys', payload);
        createdKey.value = response.data.api_key;
        showCreateDialog.value = false;
        showSecretDialog.value = true;
        newKey.value = { name: '', expires_at: '' };
        await fetchKeys();
    } catch (err) {
        if (err.response?.data?.errors) {
            formErrors.value = err.response.data.errors;
        } else {
            error.value = err.response?.data?.message || 'Failed to create API key';
        }
    } finally {
        isCreating.value = false;
    }
}

function closeSecretDialog() {
    showSecretDialog.value = false;
    createdKey.value = null;
}

async function toggleKey(key) {
    try {
        await axios.patch(`/api/api-keys/${key.id}`, {
            is_active: !key.is_active,
        });
        await fetchKeys();
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to toggle API key';
    }
}

function confirmDelete(key) {
    keyToDelete.value = key;
    showDeleteDialog.value = true;
}

async function deleteKey() {
    isDeleting.value = true;
    try {
        await axios.delete(`/api/api-keys/${keyToDelete.value.id}`);
        showDeleteDialog.value = false;
        keyToDelete.value = null;
        await fetchKeys();
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to delete API key';
    } finally {
        isDeleting.value = false;
    }
}

function confirmRegenerate(key) {
    keyToRegenerate.value = key;
    showRegenerateDialog.value = true;
}

async function regenerateKey() {
    isRegenerating.value = true;
    try {
        const response = await axios.post(`/api/api-keys/${keyToRegenerate.value.id}/regenerate`);
        createdKey.value = response.data.api_key;
        showRegenerateDialog.value = false;
        keyToRegenerate.value = null;
        showSecretDialog.value = true;
        await fetchKeys();
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to regenerate API key';
    } finally {
        isRegenerating.value = false;
    }
}

function copyToClipboard(text, label) {
    navigator.clipboard.writeText(text).then(() => {
        // Could show a toast here
        console.log(`${label} copied to clipboard`);
    });
}

function formatDate(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function isExpired(dateString) {
    if (!dateString) return false;
    return new Date(dateString) < new Date();
}

onMounted(async () => {
    await fetchStatus();
    await fetchKeys();
});
</script>
