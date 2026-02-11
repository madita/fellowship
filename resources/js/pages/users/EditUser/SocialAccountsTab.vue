<template>
    <div class="my-2">
        <v-card>
            <v-card-title>{{ $t('socialAccounts.title') }}</v-card-title>
            <v-card-subtitle>
                {{ $t('socialAccounts.subtitle') }}
            </v-card-subtitle>
            <v-card-text>
                <v-alert
                    v-if="!hasPassword && connectedAccounts.length === 1"
                    type="warning"
                    variant="tonal"
                    class="mb-4"
                >
                    <strong>{{ $t('socialAccounts.warning') }}</strong> {{ $t('socialAccounts.keepAuthMethod') }}
                </v-alert>

                <!-- Loading State -->
                <div v-if="loading" class="text-center py-6">
                    <v-progress-circular indeterminate color="primary"></v-progress-circular>
                </div>

                <!-- Error State -->
                <v-alert
                    v-if="error"
                    type="error"
                    variant="tonal"
                    dismissible
                    @click:close="error = null"
                    class="mb-4"
                >
                    {{ error }}
                </v-alert>

                <!-- Success Message -->
                <v-alert
                    v-if="successMessage"
                    type="success"
                    variant="tonal"
                    dismissible
                    @click:close="successMessage = null"
                    class="mb-4"
                >
                    {{ successMessage }}
                </v-alert>

                <!-- Connected Accounts -->
                <div v-if="!loading && connectedAccounts.length > 0">
                    <div class="text-subtitle-2 font-weight-bold mb-3">{{ $t('socialAccounts.connectedAccounts') }}</div>
                    <v-list lines="two">
                        <v-list-item
                            v-for="account in connectedAccounts"
                            :key="account.provider"
                            class="mb-2"
                        >
                            <template #prepend>
                                <v-avatar :color="getProviderColor(account.provider)">
                                    <v-icon :icon="getProviderIcon(account.provider)" color="white"></v-icon>
                                </v-avatar>
                            </template>

                            <v-list-item-title class="font-weight-medium">
                                {{ getProviderLabel(account.provider) }}
                            </v-list-item-title>
                            <v-list-item-subtitle>
                                {{ $t('socialAccounts.connected', { date: account.connected_at }) }}
                            </v-list-item-subtitle>

                            <template #append>
                                <v-btn
                                    variant="outlined"
                                    color="error"
                                    size="small"
                                    :loading="disconnecting === account.provider"
                                    :disabled="!canDisconnect"
                                    @click="disconnectProvider(account.provider)"
                                >
                                    {{ $t('socialAccounts.disconnect') }}
                                </v-btn>
                            </template>
                        </v-list-item>
                    </v-list>
                </div>

                <!-- No Connected Accounts -->
                <v-alert
                    v-if="!loading && connectedAccounts.length === 0"
                    type="info"
                    variant="tonal"
                    class="mb-4"
                >
                    {{ $t('socialAccounts.noAccountsConnected') }}
                </v-alert>

                <!-- Available Providers -->
                <div v-if="!loading && availableProviders.length > 0" class="mt-6">
                    <div class="text-subtitle-2 font-weight-bold mb-3">{{ $t('socialAccounts.availableToConnect') }}</div>
                    <v-row dense>
                        <v-col
                            v-for="provider in availableProviders"
                            :key="provider"
                            cols="12"
                            sm="6"
                        >
                            <v-btn
                                block
                                variant="outlined"
                                :color="getProviderColor(provider)"
                                :prepend-icon="getProviderIcon(provider)"
                                :loading="connecting === provider"
                                @click="connectProvider(provider)"
                            >
                                {{ $t('socialAccounts.connect', { provider: getProviderLabel(provider) }) }}
                            </v-btn>
                        </v-col>
                    </v-row>
                </div>

                <!-- All Providers Connected -->
                <v-alert
                    v-if="!loading && availableProviders.length === 0 && connectedAccounts.length > 0"
                    type="success"
                    variant="tonal"
                    class="mt-4"
                >
                    {{ $t('socialAccounts.allConnected') }}
                </v-alert>
            </v-card-text>
        </v-card>

        <!-- Confirmation Dialog -->
        <v-dialog v-model="confirmDialog" max-width="400">
            <v-card>
                <v-card-title class="headline">{{ $t('socialAccounts.disconnectConfirm', { provider: getProviderLabel(providerToDisconnect) }) }}</v-card-title>
                <v-card-text>
                    {{ $t('socialAccounts.disconnectMessage', { provider: getProviderLabel(providerToDisconnect) }) }}
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="confirmDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" @click="confirmDisconnect">{{ $t('socialAccounts.disconnect') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

export default {
    props: {
        user: {
            default: () => ({})
        },
    },
    setup(props) {
        const { t } = useI18n();

        // State
        const loading = ref(false);
        const error = ref(null);
        const successMessage = ref(null);
        const connectedAccounts = ref([]);
        const availableProviders = ref([]);
        const hasPassword = ref(true);
        const connecting = ref(null);
        const disconnecting = ref(null);
        const confirmDialog = ref(false);
        const providerToDisconnect = ref(null);

        // Provider configuration
        const providerConfig = {
            google: {
                label: 'Google',
                icon: 'mdi-google',
                color: 'red'
            },
            discord: {
                label: 'Discord',
                icon: 'mdi-discord',
                color: 'indigo'
            },
            github: {
                label: 'GitHub',
                icon: 'mdi-github',
                color: 'grey-darken-3'
            },
            facebook: {
                label: 'Facebook',
                icon: 'mdi-facebook',
                color: 'blue'
            }
        };

        // Computed
        const canDisconnect = computed(() => {
            return hasPassword.value || connectedAccounts.value.length > 1;
        });

        // Methods
        const getProviderLabel = (provider) => {
            return providerConfig[provider]?.label || provider.charAt(0).toUpperCase() + provider.slice(1);
        };

        const getProviderIcon = (provider) => {
            return providerConfig[provider]?.icon || 'mdi-account';
        };

        const getProviderColor = (provider) => {
            return providerConfig[provider]?.color || 'primary';
        };

        const fetchSocialAccounts = async () => {
            loading.value = true;
            error.value = null;

            try {
                const response = await axios.get('/api/account/social-accounts');
                connectedAccounts.value = response.data.connected || [];
                availableProviders.value = response.data.available || [];
                hasPassword.value = response.data.has_password || false;
            } catch (err) {
                error.value = t('socialAccounts.loadFailed');
                console.error('Error fetching social accounts:', err);
            } finally {
                loading.value = false;
            }
        };

        const connectProvider = (provider) => {
            connecting.value = provider;
            // Redirect to OAuth link endpoint
            window.location.href = `/api/account/social-accounts/${provider}/link`;
        };

        const disconnectProvider = (provider) => {
            providerToDisconnect.value = provider;
            confirmDialog.value = true;
        };

        const confirmDisconnect = async () => {
            const provider = providerToDisconnect.value;
            confirmDialog.value = false;
            disconnecting.value = provider;
            error.value = null;
            successMessage.value = null;

            try {
                const response = await axios.delete(`/api/account/social-accounts/${provider}`);
                successMessage.value = response.data.message || t('socialAccounts.disconnectedSuccess', { provider: getProviderLabel(provider) });

                // Refresh the list
                await fetchSocialAccounts();
            } catch (err) {
                if (err.response?.data?.error) {
                    error.value = err.response.data.error;
                } else {
                    error.value = t('socialAccounts.disconnectFailed', { provider: getProviderLabel(provider) });
                }
                console.error('Error disconnecting provider:', err);
            } finally {
                disconnecting.value = null;
                providerToDisconnect.value = null;
            }
        };

        // Lifecycle
        onMounted(() => {
            fetchSocialAccounts();
        });

        return {
            loading,
            error,
            successMessage,
            connectedAccounts,
            availableProviders,
            hasPassword,
            connecting,
            disconnecting,
            confirmDialog,
            providerToDisconnect,
            canDisconnect,
            getProviderLabel,
            getProviderIcon,
            getProviderColor,
            connectProvider,
            disconnectProvider,
            confirmDisconnect,
        };
    }
};
</script>
