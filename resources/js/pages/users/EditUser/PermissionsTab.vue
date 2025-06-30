<template>
    <div class="permissions-tab">
        <!-- Current Roles Overview -->
        <v-row class="mb-6">
            <v-col cols="12">
                <v-card class="roles-overview-card" elevation="2" rounded="lg">
                    <v-card-title class="d-flex align-center">
                        <v-icon class="mr-2" color="primary">mdi-shield-account</v-icon>
                        Current Roles
                    </v-card-title>

                    <v-card-text>
                        <div v-if="currentRoles.length === 0" class="text-center pa-4">
                            <v-icon size="48" color="medium-emphasis">mdi-account</v-icon>
                            <div class="text-h6 mt-2">No Roles Assigned</div>
                            <div class="text-body-2 text-medium-emphasis">
                                This user has no special roles or permissions
                            </div>
                        </div>

                        <v-row v-else>
                            <v-col
                                v-for="role in currentRoles"
                                :key="role.id"
                                cols="12"
                                sm="6"
                                md="4"
                            >
                                <v-card
                                    class="role-card"
                                    :color="getRoleColor(role.name)"
                                    variant="tonal"
                                    elevation="1"
                                >
                                    <v-card-text class="pa-4">
                                        <div class="d-flex align-center justify-space-between mb-3">
                                            <v-icon
                                                :color="getRoleColor(role.name)"
                                                size="32"
                                            >
                                                {{ getRoleIcon(role.name) }}
                                            </v-icon>
                                            <v-menu>
                                                <template #activator="{ props }">
                                                    <v-btn
                                                        v-bind="props"
                                                        variant="text"
                                                        size="small"
                                                        icon="mdi-dots-vertical"
                                                    />
                                                </template>
                                                <v-list>
                                                    <v-list-item @click="viewRoleDetails(role)">
                                                        <template #prepend>
                                                            <v-icon>mdi-information</v-icon>
                                                        </template>
                                                        <v-list-item-title>View Details</v-list-item-title>
                                                    </v-list-item>
                                                    <v-list-item
                                                        @click="confirmRemoveRole(role)"
                                                        class="text-error"
                                                    >
                                                        <template #prepend>
                                                            <v-icon color="error">mdi-minus-circle</v-icon>
                                                        </template>
                                                        <v-list-item-title>Remove Role</v-list-item-title>
                                                    </v-list-item>
                                                </v-list>
                                            </v-menu>
                                        </div>

                                        <div class="text-h6 font-weight-bold mb-1">{{ role.name }}</div>
                                        <div class="text-caption text-medium-emphasis mb-3">
                                            {{ role.description }}
                                        </div>

                                        <div class="d-flex align-center">
                                            <v-chip size="x-small" variant="outlined">
                                                {{ role.permissions?.length || 0 }} permissions
                                            </v-chip>
                                            <v-spacer />

                                            <v-btn
                                                v-if="role.name === 'ADMIN'"
                                                color="error"
                                                variant="text"
                                                size="small"
                                                @click="confirmRemoveAdmin"
                                            >
                                                Remove Admin
                                            </v-btn>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Role Management -->
        <v-row class="mb-6">
            <v-col cols="12" lg="8">
                <v-card class="role-management-card" elevation="2" rounded="lg">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <div class="d-flex align-center">
                            <v-icon class="mr-2" color="info">mdi-plus-circle</v-icon>

                            <v-btn
                                v-if="!showAdmin"
                                color="info"
                                variant="text"
                                size="small"
                                @click="showAdmin = true"
                            >
                                Add Admin
                            </v-btn>
                            <v-btn
                                v-else
                                color="info"
                                variant="text"
                                size="small"
                                @click="showAdmin = false"
                            >
                                Hide Admin
                            </v-btn>
                        </div>
                        <v-btn
                            variant="outlined"
                            size="small"
                            prepend-icon="mdi-refresh"
                            @click="refreshAvailableRoles"
                            :loading="refreshing"
                        >
                            Refresh
                        </v-btn>
                    </v-card-title>

                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="8">
                                <v-select
                                    v-model="selectedRole"
                                    :items="availableRoles"
                                    item-title="name"
                                    item-value="id"
                                    label="Select role to assign"
                                    variant="outlined"
                                    density="compact"
                                    prepend-inner-icon="mdi-shield-plus"
                                    clearable
                                    :loading="loadingRoles"
                                >
                                    <template #item="{ props, item }">
                                        <v-list-item v-bind="props">
                                            <template #prepend>
                                                <v-icon :color="getRoleColor(item.raw.name)">
                                                    {{ getRoleIcon(item.raw.name) }}
                                                </v-icon>
                                            </template>
                                            <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                                            <v-list-item-subtitle>
                                                {{ item.raw.description }}
                                            </v-list-item-subtitle>
                                        </v-list-item>
                                    </template>
                                </v-select>
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-btn
                                    block
                                    color="primary"
                                    variant="elevated"
                                    prepend-icon="mdi-plus"
                                    @click="assignRole"
                                    :disabled="!selectedRole"
                                    :loading="assigning"
                                >
                                    Assign Role
                                </v-btn>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    user: {
        type: Object,
        required: true
    },
    roles: {
        type: Object,
        required: true
    }
});

const currentRoles = ref([]);
const availableRoles = ref([]);
const selectedRole = ref(null);
const selectedRoleDetails = ref(null);
const assigning = ref(false);
const refreshing = ref(false);
const loadingRoles = ref(false);

const getRoleColor = (roleName) => {
    // ToDO Implement role color logic
};

const getRoleIcon = (roleName) => {
    // ToDO Implement role icon logic
};

const refreshAvailableRoles = async () => {
    refreshing.value = true;
    try {
        const response = await axios.get('/api/roles');
        availableRoles.value = response.data;
    } catch (error) {
        console.error('Failed to refresh roles:', error);
    }
    refreshing.value = false;
};

const assignRole = async () => {
    assigning.value = true;
    try {
        // Implement role assignment API call
        await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate API call
        currentRoles.value.push(selectedRole.value);
        selectedRole.value = null;
    } catch (error) {
        console.error('Failed to assign role:', error);
    }
    assigning.value = false;
};

const confirmRemoveRole = (role) => {
    // Implement role removal confirmation
};

const confirmRemoveAdmin = () => {
    // Implement admin removal confirmation
};

const viewRoleDetails = (role) => {
    // Implement role details view
};

// Lifecycle
onMounted(() => {
    currentRoles.value = props.user.roles;
    availableRoles.value = props.roles;
});
</script>
