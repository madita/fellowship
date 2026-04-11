<template>
    <div>
        <settings-card icon="mdi-shield-check" :title="$t('settings.moderation.contentAutoApproval')">
            <v-alert type="info" variant="tonal" density="compact" class="mb-6">
                <div class="text-caption">
                    {{ $t('settings.moderation.autoApprovalDesc') }}
                </div>
            </v-alert>

            <v-select
                v-model="settings.auto_approve_roles_wiki"
                :label="$t('settings.moderation.autoApproveRolesWiki')"
                :items="roles"
                item-title="name"
                item-value="name"
                multiple
                chips
                closable-chips
                prepend-inner-icon="mdi-book-open-page-variant"
                variant="outlined"
                class="mb-4"
                :hint="$t('settings.moderation.autoApproveRolesWikiHint')"
                persistent-hint
                :loading="loadingRoles"
            ></v-select>

            <v-alert
                v-if="!settings.auto_approve_roles_wiki || settings.auto_approve_roles_wiki.length === 0"
                type="warning"
                variant="tonal"
                density="compact"
                class="mt-2"
            >
                <div class="text-caption">
                    {{ $t('settings.moderation.noRolesSelected') }}
                </div>
            </v-alert>
        </settings-card>

        <v-btn
            :loading="isSaving"
            block
            size="large"
            color="primary"
            @click="$emit('save')"
            prepend-icon="mdi-content-save"
        >
            {{ $t('settings.moderation.saveSettings') }}
        </v-btn>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useApi } from '@/api/useAPI.js';
import SettingsCard from '../SettingsCard.vue';

const api = useApi('api');

const props = defineProps({
    settings: Object,
    errors: Object,
    isSaving: Boolean,
});

const emit = defineEmits(['save']);

const roles = ref([]);
const loadingRoles = ref(false);

async function fetchRoles() {
    loadingRoles.value = true;
    try {
        const response = await api.get('/datatable/roles');
        const records = response.data?.data?.records;
        // Records can be a paginated object (with .data) or a plain array
        roles.value = Array.isArray(records) ? records : (records?.data || []);
    } catch (error) {
        console.error('Failed to fetch roles:', error);
    } finally {
        loadingRoles.value = false;
    }
}

onMounted(() => {
    fetchRoles();
});
</script>
