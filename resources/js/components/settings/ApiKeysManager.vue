<template>
    <div>
        <v-alert v-if="!isEnabled" type="info" variant="tonal" class="mb-4">
            {{ $t('apiKeys.notEnabled') }}
        </v-alert>

        <template v-else>
            <div class="d-flex justify-space-between align-center mb-4">
                <div>
                    <div class="text-h6">{{ $t('apiKeys.title') }}</div>
                    <div class="text-caption text-medium-emphasis">
                        {{ $t('apiKeys.description', { rateLimit: rateLimit }) }}
                    </div>
                </div>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    @click="showCreateDialog = true"
                >
                    {{ $t('apiKeys.createKey') }}
                </v-btn>
            </div>

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
                                    {{ $t('apiKeys.lastUsed') }}: {{ formatDate(key.last_used_at) }}
                                </span>
                                <span v-else class="text-medium-emphasis">{{ $t('apiKeys.neverUsed') }}</span>
                                <span class="mx-2">|</span>
                                <span>{{ $t('apiKeys.requestCount', { count: key.request_count }) }}</span>
                                <span v-if="key.expires_at" class="mx-2">|</span>
                                <span v-if="key.expires_at" :class="isExpired(key.expires_at) ? 'text-error' : ''">
                                    {{ isExpired(key.expires_at) ? $t('apiKeys.expired') : $t('apiKeys.expires', { date: formatDate(key.expires_at) }) }}
                                </span>
                            </v-list-item-subtitle>

                            <template v-slot:append>
                                <v-btn
                                    icon
                                    variant="text"
                                    size="small"
                                    :loading="isBusy(key, 'toggle')"
                                    :disabled="busy.id !== null"
                                    @click="toggleKey(key)"
                                    :title="key.is_active ? $t('apiKeys.deactivate') : $t('apiKeys.activate')"
                                >
                                    <v-icon>{{ key.is_active ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                                </v-btn>
                                <v-btn
                                    icon
                                    variant="text"
                                    size="small"
                                    :loading="isBusy(key, 'regenerate')"
                                    :disabled="busy.id !== null"
                                    @click="confirmRegenerate(key)"
                                    :title="$t('apiKeys.regenerate')"
                                >
                                    <v-icon>mdi-refresh</v-icon>
                                </v-btn>
                                <v-btn
                                    icon
                                    variant="text"
                                    size="small"
                                    color="error"
                                    :loading="isBusy(key, 'delete')"
                                    :disabled="busy.id !== null"
                                    @click="confirmDelete(key)"
                                    :title="$t('common.delete')"
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
                <div class="text-h6 text-medium-emphasis mb-2">{{ $t('apiKeys.noKeys') }}</div>
                <div class="text-body-2 text-medium-emphasis mb-4">
                    {{ $t('apiKeys.noKeysDescription') }}
                </div>
                <v-btn color="primary" @click="showCreateDialog = true">
                    {{ $t('apiKeys.createFirstKey') }}
                </v-btn>
            </v-card>
        </template>

        <!-- Create Dialog -->
        <v-dialog v-model="showCreateDialog" max-width="500">
            <v-card>
                <v-card-title>{{ $t('apiKeys.createKey') }}</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="newKey.name"
                        :label="$t('apiKeys.keyName')"
                        :placeholder="$t('apiKeys.keyNamePlaceholder')"
                        variant="outlined"
                        :error-messages="formErrors.name"
                        class="mb-4"
                    />

                    <v-text-field
                        v-model="newKey.expires_at"
                        :label="$t('apiKeys.expirationDate')"
                        type="date"
                        variant="outlined"
                        :min="minDate"
                        :hint="$t('apiKeys.expirationHint')"
                        persistent-hint
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="isCreating" @click="showCreateDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="isCreating" @click="createKey">{{ $t('common.create') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Secret Display Dialog -->
        <v-dialog v-model="showSecretDialog" max-width="600" persistent>
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="warning" class="mr-2">mdi-alert</v-icon>
                    {{ $t('apiKeys.saveYourSecret') }}
                </v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal" class="mb-4">
                        {{ $t('apiKeys.secretWarning') }}
                    </v-alert>

                    <div class="mb-4">
                        <div class="text-caption text-medium-emphasis mb-1">{{ $t('apiKeys.apiKey') }}</div>
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
                        <div class="text-caption text-medium-emphasis mb-1">{{ $t('apiKeys.apiSecret') }}</div>
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
                            <strong>{{ $t('apiKeys.usage') }}:</strong> {{ $t('apiKeys.usageDescription') }}
                            <pre class="mt-2 pa-2 bg-grey-darken-3 rounded text-white">X-API-Key: {{ createdKey?.key }}
X-API-Secret: {{ createdKey?.secret }}</pre>
                        </div>
                    </v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" @click="closeSecretDialog">{{ $t('apiKeys.savedSecret') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { formatDate as formatDateUtil } from '@/plugins/formatDate.js';
import { useDialog } from '@/composables/useDialog.js';

const { t } = useI18n();
const dialog = useDialog();

const isEnabled = ref(false);
const rateLimit = ref(60);
const apiKeys = ref([]);
const isLoading = ref(false);

// Which key row has a request in flight, and for which action
const busy = ref({ id: null, action: null });

function isBusy(key, action) {
    return busy.value.id === key.id && busy.value.action === action;
}

async function runForKey(key, action, work) {
    if (busy.value.id !== null) return;
    busy.value = { id: key.id, action };
    try {
        await work();
    } catch (err) {
        dialog.requestError(err);
    } finally {
        busy.value = { id: null, action: null };
    }
}

// Create dialog
const showCreateDialog = ref(false);
const isCreating = ref(false);
const newKey = ref({ name: '', expires_at: '' });
const formErrors = ref({});

// Secret display dialog
const showSecretDialog = ref(false);
const createdKey = ref(null);

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
        dialog.requestError(err);
    } finally {
        isLoading.value = false;
    }
}

async function createKey() {
    if (isCreating.value) return;
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
            dialog.requestError(err);
        }
    } finally {
        isCreating.value = false;
    }
}

function closeSecretDialog() {
    showSecretDialog.value = false;
    createdKey.value = null;
}

function toggleKey(key) {
    return runForKey(key, 'toggle', async () => {
        await axios.patch(`/api/api-keys/${key.id}`, {
            is_active: !key.is_active,
        });
        await fetchKeys();
    });
}

async function confirmDelete(key) {
    if (busy.value.id !== null) return;
    const ok = await dialog.confirmDelete(
        t('apiKeys.confirmDelete', { name: key.name }),
        { title: t('apiKeys.deleteKey') }
    );
    if (!ok) return;

    await runForKey(key, 'delete', async () => {
        await axios.delete(`/api/api-keys/${key.id}`);
        await fetchKeys();
    });
}

async function confirmRegenerate(key) {
    if (busy.value.id !== null) return;
    const ok = await dialog.confirm({
        title: t('apiKeys.regenerateKey'),
        content: t('apiKeys.confirmRegenerate', { name: key.name }),
        confirmationText: t('apiKeys.regenerate'),
        color: 'warning',
    });
    if (!ok) return;

    await runForKey(key, 'regenerate', async () => {
        const response = await axios.post(`/api/api-keys/${key.id}/regenerate`);
        createdKey.value = response.data.api_key;
        showSecretDialog.value = true;
        await fetchKeys();
    });
}

function copyToClipboard(text, label) {
    navigator.clipboard.writeText(text).then(() => {
        // Could show a toast here
        console.log(`${label} copied to clipboard`);
    });
}

function formatDate(dateString) {
    if (!dateString) return '';
    return formatDateUtil(dateString, 'd M Y');
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
