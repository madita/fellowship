<template>
    <div class="flex-grow-1">
        <div class="d-flex align-center py-3">
            <div>
                <div class="display-1">Edit User {{ user.name && `- ${user.name}` }}</div>
                <v-breadcrumbs :items="breadcrumbs" class="pa-0 py-2"></v-breadcrumbs>
            </div>
            <v-spacer></v-spacer>
            <v-btn icon>
                <v-icon>mdi-refresh</v-icon>
            </v-btn>
        </div>

        <div
            v-if="roles.includes('admin')"
            class="d-flex align-center font-weight-bold text-primary my-2"
        >
            <v-icon small color="bg-primary">mdi-security</v-icon>
            <span class="ma-1">Administrator</span>
        </div>

        <div class="mb-4">
            <div class="d-flex">
                <span class="font-weight-bold">Email</span>
                <span class="mx-1">
          <copy-label :text="user.email"/>
        </span>
            </div>
            <div class="d-flex">
                <span class="font-weight-bold">ID</span>
                <span class="mx-1">
          <copy-label :text="user.id + ''"/>
        </span>
            </div>
        </div>


        <v-tabs
            v-model="activeTab"
            bg-color="transparent"
        >
            <v-tab value="account">Account</v-tab>
            <v-tab value="info">Information</v-tab>

        </v-tabs>

        <v-card>
        <v-card-text>
            <v-window v-model="activeTab">
                <v-window-item value="account">
                    <account-tab :user="user" :roles="roles"></account-tab>
                </v-window-item>

                <v-window-item value="info">
                    <information-tab :user="user"></information-tab>
                </v-window-item>

            </v-window>
        </v-card-text>
        </v-card>

    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import CopyLabel from '../../components/common/CopyLabel.vue';
import AccountTab from './EditUser/AccountTab.vue';
import InformationTab from './EditUser/InformationTab.vue';

export default {
    components: {
        CopyLabel,
        AccountTab,
        InformationTab,
    },
    setup() {
        const authStore = useAuthStore();
        const userStore = useUserStore();

        const breadcrumbs = [
            {
                text: 'Users',
                to: '/users/list',
                exact: true
            },
            {
                text: 'Edit User'
            }
        ]

        const activeTab = ref(null);
        const bc = ref([
            { text: 'Users', to: '/users/list', exact: true },
            { text: 'Edit User' },
        ]);

        const authenticated = computed(() => authStore.isLoggedIn);
        const user = computed(() => userStore.user);
        const roles = computed(() => userStore.roles);

        return {
            breadcrumbs,
            activeTab,
            bc,
            authenticated,
            user,
            roles,
        };
    },
};
</script>
