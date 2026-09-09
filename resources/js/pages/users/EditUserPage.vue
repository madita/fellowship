<template>
    <div>
        <page-header
            :title="$t('users.edit.title')"
            :subtitle="user.username"
            icon="mdi-account-edit"
            back-to="/users/list"
        >
            <template #actions>
                <v-btn
                    icon="mdi-refresh"
                    variant="text"
                    :aria-label="$t('common.refresh')"
                    :title="$t('common.refresh')"
                />
            </template>
        </page-header>

        <v-container fluid>
            <div v-if="roles.includes('admin')" class="d-flex flex-wrap align-center ga-2 mb-4">
                <v-chip
                    color="primary"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-security"
                >
                    {{ $t('users.edit.administrator') }}
                </v-chip>
            </div>

            <div class="mb-4 text-body-2">
                <div class="d-flex">
                    <span class="font-weight-bold">{{ $t('users.edit.email') }}</span>
                    <span class="mx-1">
                        <copy-label :text="user.email"/>
                    </span>
                </div>
                <div class="d-flex">
                    <span class="font-weight-bold">{{ $t('users.edit.id') }}</span>
                    <span class="mx-1">
                        <copy-label :text="user.id + ''"/>
                    </span>
                </div>
            </div>

            <v-tabs
                v-model="activeTab"
                bg-color="transparent"
            >
                <v-tab value="account">{{ $t('users.edit.tabs.account') }}</v-tab>
                <v-tab value="info">{{ $t('users.edit.tabs.information') }}</v-tab>
                <v-tab value="social">{{ $t('users.edit.tabs.socialAccounts') }}</v-tab>
                <v-tab value="api-keys">{{ $t('users.edit.tabs.apiKeys') }}</v-tab>
                <v-tab value="legacy">{{ $t('account.legacyClaim.tab') }}</v-tab>
            </v-tabs>

            <v-card rounded="lg">
                <v-card-text>
                    <v-window v-model="activeTab">
                        <v-window-item value="account">
                            <account-tab :user="user" :roles="roles"></account-tab>
                        </v-window-item>

                        <v-window-item value="info">
                            <information-tab :user="user"></information-tab>
                        </v-window-item>

                        <v-window-item value="social">
                            <social-accounts-tab :user="user"></social-accounts-tab>
                        </v-window-item>

                        <v-window-item value="api-keys">
                            <api-keys-tab></api-keys-tab>
                        </v-window-item>

                        <v-window-item value="legacy">
                            <legacy-claim-tab></legacy-claim-tab>
                        </v-window-item>
                    </v-window>
                </v-card-text>
            </v-card>
        </v-container>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import CopyLabel from '../../components/common/CopyLabel.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import AccountTab from './EditUser/AccountTab.vue';
import InformationTab from './EditUser/InformationTab.vue';
import SocialAccountsTab from './EditUser/SocialAccountsTab.vue';
import ApiKeysTab from './EditUser/ApiKeysTab.vue';
import LegacyClaimTab from './EditUser/LegacyClaimTab.vue';

export default {
    components: {
        CopyLabel,
        PageHeader,
        AccountTab,
        InformationTab,
        SocialAccountsTab,
        ApiKeysTab,
        LegacyClaimTab,
    },
    setup() {
        const authStore = useAuthStore();
        const userStore = useUserStore();

        const activeTab = ref(null);

        const authenticated = computed(() => authStore.isLoggedIn);
        const user = computed(() => userStore.user);
        const roles = computed(() => userStore.roles);

        return {
            activeTab,
            authenticated,
            user,
            roles,
        };
    },
};
</script>
