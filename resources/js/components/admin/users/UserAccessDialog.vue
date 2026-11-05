<template>
    <v-dialog :model-value="modelValue" max-width="860" scrollable @update:model-value="close">
        <v-card>
            <v-card-title class="d-flex align-center text-h6">
                <v-icon color="primary" class="me-2">mdi-shield-account-outline</v-icon>
                {{ $t('access.title', { name: displayName }) }}
            </v-card-title>
            <v-divider />

            <v-card-text>
                <loading-state v-if="loading" />

                <template v-else>
                    <!-- Changing your own access from here is refused by the
                         API, so the dialog says so rather than offering a
                         form that cannot be saved. -->
                    <v-alert
                        v-if="isSelf"
                        type="info"
                        variant="tonal"
                        density="compact"
                        class="mb-4"
                    >
                        {{ $t('access.notYourOwn') }}
                    </v-alert>

                    <div class="text-body-2 text-medium-emphasis mb-4">{{ $t('access.intro') }}</div>

                    <!-- Roles first: a role says why somebody has what they
                         have, where a list of permissions does not. -->
                    <div class="text-subtitle-2 mb-2">{{ $t('access.roles') }}</div>
                    <v-row dense class="mb-2">
                        <v-col
                            v-for="role in roles"
                            :key="role.name"
                            cols="12"
                            sm="6"
                        >
                            <v-checkbox
                                v-model="chosenRoles"
                                :value="role.name"
                                :disabled="disabled || !mayChooseRole(role)"
                                density="compact"
                                hide-details
                            >
                                <template #label>
                                    <div>
                                        <div>{{ role.display_name }}</div>
                                        <div v-if="role.description" class="text-caption text-medium-emphasis">
                                            {{ role.description }}
                                        </div>
                                        <div v-else-if="!mayChooseRole(role)" class="text-caption text-warning">
                                            {{ $t('access.roleBeyondYou') }}
                                        </div>
                                    </div>
                                </template>
                            </v-checkbox>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4" />

                    <div class="d-flex align-center justify-space-between mb-2">
                        <div class="text-subtitle-2">{{ $t('access.permissions') }}</div>
                        <div class="text-caption text-medium-emphasis">{{ $t('access.permissionsHint') }}</div>
                    </div>

                    <!-- One group per kind of work, which is what makes a
                         list this long readable at all. -->
                    <v-expansion-panels v-model="openGroups" multiple variant="accordion" class="mb-2">
                        <v-expansion-panel
                            v-for="group in groups"
                            :key="group.key"
                            :value="group.key"
                        >
                            <v-expansion-panel-title>
                                <span class="text-subtitle-2">{{ group.label }}</span>
                                <v-chip
                                    v-if="group.held"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    class="ms-2"
                                >
                                    {{ group.held }}
                                </v-chip>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div
                                    v-for="permission in group.permissions"
                                    :key="permission.name"
                                    class="mb-1"
                                >
                                    <v-checkbox
                                        :model-value="isTicked(permission.name)"
                                        :disabled="disabled || !mayGrant(permission.name) || fromRole(permission.name).length > 0"
                                        density="compact"
                                        hide-details
                                        @update:model-value="toggle(permission.name, $event)"
                                    >
                                        <template #label>
                                            <div>
                                                <div>
                                                    {{ permission.display_name }}
                                                    <!-- A tick that came with a role, not
                                                         by hand: saying where it came from
                                                         is why it cannot be unticked here. -->
                                                    <v-chip
                                                        v-for="role in fromRole(permission.name)"
                                                        :key="role"
                                                        size="x-small"
                                                        variant="tonal"
                                                        class="ms-1"
                                                    >
                                                        {{ role }}
                                                    </v-chip>
                                                </div>
                                                <div v-if="permission.description" class="text-caption text-medium-emphasis">
                                                    {{ permission.description }}
                                                </div>
                                            </div>
                                        </template>
                                    </v-checkbox>
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>

                    <div class="text-caption text-medium-emphasis">
                        {{ $t('access.effective', { count: effectiveCount }) }}
                    </div>
                </template>
            </v-card-text>

            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" :disabled="saving" @click="close">{{ $t('common.cancel') }}</v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    :loading="saving"
                    :disabled="disabled || !changed"
                    @click="save"
                >
                    {{ $t('common.save') }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';
import { useUserStore } from '@/store/userStore.js';
import LoadingState from '../../common/LoadingState.vue';

const { t } = useI18n();
const feedback = useDialog();
const userStore = useUserStore();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    // The member whose access this is. An id is enough; everything else
    // is read from the API, so a row out of a table and a profile page
    // can both open this.
    user: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const loading = ref(false);
const saving = ref(false);

const roles = ref([]);
const permissions = ref([]);
const categories = ref({});
const grantable = ref([]);
const mayGrantAdmin = ref(false);
const isSelf = ref(false);
const fromRoles = ref({});

const chosenRoles = ref([]);
const chosenPermissions = ref([]);
const openGroups = ref([]);

let heldRoles = [];
let heldPermissions = [];

const displayName = computed(() => props.user?.username || props.user?.name || '');

const disabled = computed(() => saving.value || loading.value || isSelf.value);

const sameSet = (a, b) => a.length === b.length && [...a].sort().join() === [...b].sort().join();

const changed = computed(() =>
    !sameSet(chosenRoles.value, heldRoles) || !sameSet(chosenPermissions.value, heldPermissions)
);

// What a role brings with it, so a tick nobody put there by hand says so
const fromRole = (name) => fromRoles.value[name] || [];

const isTicked = (name) => chosenPermissions.value.includes(name) || fromRole(name).length > 0;

const mayGrant = (name) => grantable.value.includes(name);

// A role can only be handed out by somebody who holds everything in it —
// otherwise handing out "moderator" would be a way to hand out whatever
// else happens to be in it.
const mayChooseRole = (role) => {
    if (role.name === 'admin' && !mayGrantAdmin.value) return false;
    if (mayGrantAdmin.value) return true;

    return (role.permissions || []).every(name => grantable.value.includes(name));
};

const groups = computed(() => {
    const order = Object.keys(categories.value);
    const byCategory = {};

    for (const permission of permissions.value) {
        const key = order.includes(permission.category) ? permission.category : 'other';
        (byCategory[key] = byCategory[key] || []).push(permission);
    }

    return [...order, 'other']
        .filter(key => byCategory[key]?.length)
        .map(key => ({
            key,
            label: categories.value[key] || t('access.otherGroup'),
            permissions: byCategory[key],
            held: byCategory[key].filter(permission => isTicked(permission.name)).length,
        }));
});

const effectiveCount = computed(() => {
    const names = new Set(chosenPermissions.value);

    for (const role of roles.value) {
        if (chosenRoles.value.includes(role.name)) {
            for (const name of role.permissions || []) names.add(name);
        }
    }

    return names.size;
});

const toggle = (name, on) => {
    const without = chosenPermissions.value.filter(held => held !== name);
    chosenPermissions.value = on ? [...without, name] : without;
};

const load = async () => {
    if (!props.user?.id) return;

    loading.value = true;

    try {
        const [catalogue, access] = await Promise.all([
            axios.get('/api/admin/users/access/catalogue'),
            axios.get(`/api/admin/users/${props.user.id}/access`),
        ]);

        roles.value = catalogue.data.roles || [];
        permissions.value = catalogue.data.permissions || [];
        categories.value = catalogue.data.categories || {};

        grantable.value = access.data.grantable || [];
        mayGrantAdmin.value = !!access.data.may_grant_admin;
        isSelf.value = !!access.data.is_self;
        fromRoles.value = access.data.from_roles || {};

        heldRoles = access.data.roles || [];
        heldPermissions = access.data.permissions || [];
        chosenRoles.value = [...heldRoles];
        chosenPermissions.value = [...heldPermissions];

        // Open the groups that already hold something, so the dialog
        // shows what this member has without anything being clicked
        openGroups.value = groups.value.filter(group => group.held).map(group => group.key);
    } catch (error) {
        await feedback.requestError(error);
        close();
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    if (saving.value) return;
    saving.value = true;

    try {
        const { data } = await axios.put(`/api/admin/users/${props.user.id}/access`, {
            roles: chosenRoles.value,
            permissions: chosenPermissions.value,
        });

        heldRoles = data.roles || [];
        heldPermissions = data.permissions || [];
        fromRoles.value = data.from_roles || {};

        // Changing your own is refused, so this can only be somebody
        // else's — except for the roles a role change may have given the
        // person doing it through a role they share. Re-reading is cheap
        // and keeps the menu honest.
        await userStore.storeInfo();

        await feedback.success(data.message);
        emit('saved', data);
        close();
    } catch (error) {
        await feedback.requestError(error);
    } finally {
        saving.value = false;
    }
};

const close = () => emit('update:modelValue', false);

watch(() => props.modelValue, (open) => {
    if (open) load();
});
</script>
