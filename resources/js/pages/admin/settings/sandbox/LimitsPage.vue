<template>
    <settings-page-layout
        title="Limits & Quotas"
        description="Configure sandbox limits per role"
        icon="mdi-numeric"
        :category-title="category?.title"
        :back-route="{ name: 'admin-settings-category', params: { category: 'sandbox' } }"
        :is-saving="isSaving"
        :message="message"
        :alert-type="alertType"
        @save="$emit('save')"
        @clear-message="message = ''"
    >
        <settings-card icon="mdi-account-cog" title="Role-based Limits">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                <div class="text-caption">
                    Configure sandbox limits per role. A value of <strong>0</strong> means unlimited.
                </div>
            </v-alert>

            <div v-if="loadingRoles" class="text-center py-4">
                <v-progress-circular indeterminate size="24"></v-progress-circular>
            </div>

            <template v-else>
                <v-card
                    v-for="role in roles"
                    :key="role"
                    variant="outlined"
                    class="mb-4 pa-4"
                >
                    <div class="d-flex align-center mb-3">
                        <v-avatar :color="role === 'admin' ? 'error' : 'primary'" size="28" class="mr-2">
                            <v-icon size="16" color="white">
                                {{ role === 'admin' ? 'mdi-shield-crown' : 'mdi-account' }}
                            </v-icon>
                        </v-avatar>
                        <span class="text-subtitle-1 font-weight-medium text-capitalize">{{ role }}</span>
                        <v-chip v-if="role === 'admin'" size="x-small" color="warning" variant="tonal" class="ml-2">
                            Typically unlimited
                        </v-chip>
                    </div>

                    <v-row dense>
                        <v-col cols="12" sm="4">
                            <v-text-field
                                :model-value="getRoleLimit(role, 'max_sandboxes')"
                                @update:model-value="setRoleLimit(role, 'max_sandboxes', $event)"
                                label="Max Sandboxes"
                                prepend-inner-icon="mdi-notebook-multiple"
                                variant="outlined"
                                type="number"
                                density="compact"
                                hint="Per user (0 = unlimited)"
                                persistent-hint
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="4">
                            <v-text-field
                                :model-value="getRoleLimit(role, 'max_collaborators')"
                                @update:model-value="setRoleLimit(role, 'max_collaborators', $event)"
                                label="Max Collaborators"
                                prepend-inner-icon="mdi-account-group"
                                variant="outlined"
                                type="number"
                                density="compact"
                                hint="Per sandbox (0 = unlimited)"
                                persistent-hint
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="4">
                            <v-text-field
                                :model-value="getRoleLimit(role, 'max_versions')"
                                @update:model-value="setRoleLimit(role, 'max_versions', $event)"
                                label="Max Versions"
                                prepend-inner-icon="mdi-history"
                                variant="outlined"
                                type="number"
                                density="compact"
                                hint="Per sandbox (0 = unlimited)"
                                persistent-hint
                            ></v-text-field>
                        </v-col>
                    </v-row>
                </v-card>
            </template>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
            class="d-sm-none"
        >
            Save Settings
        </v-btn>
    </settings-page-layout>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useApi } from '@/api/useAPI.js';
import SettingsPageLayout from '@/components/settings/SettingsPageLayout.vue';
import SettingsCard from '@/components/settings/SettingsCard.vue';

const api = useApi('api');

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
    category: Object,
    setting: Object,
});

defineEmits(['save', 'message']);

const message = ref('');
const alertType = ref('success');
const loadingRoles = ref(true);
const roles = ref([]);

const defaultLimits = { max_sandboxes: 0, max_collaborators: 0, max_versions: 0 };

function ensureRoleLimits() {
    if (typeof props.settings.sandbox_role_limits === 'string') {
        try {
            props.settings.sandbox_role_limits = JSON.parse(props.settings.sandbox_role_limits);
        } catch {
            props.settings.sandbox_role_limits = {};
        }
    }
    if (!props.settings.sandbox_role_limits || typeof props.settings.sandbox_role_limits !== 'object' || Array.isArray(props.settings.sandbox_role_limits)) {
        props.settings.sandbox_role_limits = {};
    }
    for (const role of roles.value) {
        if (!props.settings.sandbox_role_limits[role]) {
            props.settings.sandbox_role_limits[role] = { ...defaultLimits };
        }
    }
}

function getRoleLimit(role, key) {
    return props.settings.sandbox_role_limits?.[role]?.[key] ?? 0;
}

function setRoleLimit(role, key, value) {
    ensureRoleLimits();
    props.settings.sandbox_role_limits[role][key] = Number(value) || 0;
}

async function fetchRoles() {
    loadingRoles.value = true;
    try {
        const response = await api.get('/datatable/permissions/roles');
        roles.value = response.data.data.map(r => r.name);
        ensureRoleLimits();
    } catch (error) {
        console.error('Failed to fetch roles:', error);
        roles.value = ['admin', 'user'];
        ensureRoleLimits();
    } finally {
        loadingRoles.value = false;
    }
}

watch(() => props.settings.sandbox_role_limits, () => {
    if (roles.value.length > 0) {
        ensureRoleLimits();
    }
});

onMounted(() => {
    fetchRoles();
});
</script>
