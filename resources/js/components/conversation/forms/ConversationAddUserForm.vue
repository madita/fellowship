<template>
    <v-form ref="formRef" @submit.prevent="addUser">
        <div class="d-flex align-center gap-3">
<!--            <v-autocomplete-->
<!--                v-model="selectedUser"-->
<!--                :items="filteredUserList"-->
<!--                label="Search and add users"-->
<!--                variant="outlined"-->
<!--                item-title="name"-->
<!--                item-value="id"-->
<!--                :loading="loadingUsers"-->
<!--                :disabled="isSubmitting"-->
<!--                placeholder="Type to search users..."-->
<!--                no-data-text="No users found"-->
<!--                density="comfortable"-->
<!--                class="flex-grow-1"-->
<!--                clearable-->
<!--                hide-details="auto"-->
<!--                :rules="[rules.required]"-->
<!--            >-->
<!--                <template #prepend-inner>-->
<!--                    <v-icon color="grey-darken-1">mdi-account-search</v-icon>-->
<!--                </template>-->

<!--                <template #item="{ props, item }">-->
<!--                    <v-list-item v-bind="props">-->
<!--                        <template #prepend>-->
<!--                            <v-avatar size="32">-->
<!--                                <v-img :src="item.raw.avatar || defaultAvatar(item.raw)" :alt="`${item.raw.name}'s avatar`"></v-img>-->
<!--                            </v-avatar>-->
<!--                        </template>-->
<!--                        <v-list-item-title>{{ item.raw.name }}</v-list-item-title>-->
<!--                        <v-list-item-subtitle>@{{ item.raw.username }}</v-list-item-subtitle>-->
<!--                    </v-list-item>-->
<!--                </template>-->

<!--                <template #selection="{ item }">-->
<!--                    <v-chip color="primary" size="small">-->
<!--                        <v-avatar start>-->
<!--                            <v-img :src="item.raw.avatar || defaultAvatar(item.raw)"></v-img>-->
<!--                        </v-avatar>-->
<!--                        {{ item.raw.name }}-->
<!--                    </v-chip>-->
<!--                </template>-->
<!--            </v-autocomplete>-->

            <v-autocomplete
                v-model="selectedUser"
                :items="userList"
                :loading="loadingUsers"
                :search="userSearch"
                @update:search="handleUserSearch"
                label="Select Recipients"
                placeholder="Type to search for users..."
                variant="outlined"
                item-title="username"
                item-value="id"
                multiple
                chips
                closable-chips
                clearable
                class="mb-4"
            >
                <template #chip="{ props, item }">
                    <v-chip
                        v-bind="props"
                        :text="item.raw.username"
                        :prepend-avatar="item.raw.avatar"
                        closable
                        size="small"
                    />
                </template>

                <template #item="{ props, item }">
                    <v-list-item
                        v-bind="props"
                        :prepend-avatar="item.raw.avatar"
                        :title="item.raw.username"
                        :subtitle="item.raw.email"
                    />
                </template>

                <template #no-data>
                    <v-list-item>
                        <v-list-item-title>
                            {{ userSearch ? 'No users found' : 'Start typing to search for users' }}
                        </v-list-item-title>
                    </v-list-item>
                </template>
            </v-autocomplete>
            <v-btn
                type="submit"
                color="primary"
                :loading="isSubmitting"
                :disabled="!selectedUser || isSubmitting"
                icon
                size="large"
            >
                <v-icon>mdi-account-plus</v-icon>
                <v-tooltip activator="parent" location="top">
                    Add user to conversation
                </v-tooltip>
            </v-btn>
        </div>

        <!-- Success/Error Messages -->
        <v-alert
            v-if="successMessage"
            type="success"
            variant="tonal"
            class="mt-3"
            density="compact"
            closable
            @click:close="successMessage = ''"
        >
            <v-icon>mdi-check-circle</v-icon>
            {{ successMessage }}
        </v-alert>

        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mt-3"
            density="compact"
            closable
            @click:close="errorMessage = ''"
        >
            <v-icon>mdi-alert-circle</v-icon>
            {{ errorMessage }}
        </v-alert>
    </v-form>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useConversationStore } from '@/store/conversationStore';
import { useUsersStore } from '@/store/usersStore';
import axios from "axios"; // You'll need this store

export default {
    name: "ConversationAddUserForm",
    setup() {
        const conversationStore = useConversationStore();
        const usersStore = useUsersStore();

        const selectedUser = ref(null);
        const isSubmitting = ref(false);
        const loadingUsers = ref(false);
        const successMessage = ref('');
        const errorMessage = ref('');
        const userSearch = ref('')
        const userList = ref([])
        const formRef = ref(null);
        const debounceTimer = ref(null)

        const currentConversation = computed(() => conversationStore.currentConversation);
        const allUsers = computed(() => usersStore.allUsers || []);

        // Filter out users who are already in the conversation
        const filteredUserList = computed(() => {
            if (!currentConversation.value?.data?.data?.users) return allUsers.value;

            const participantIds = currentConversation.value.data.data.users.map(user => user.id);
            return allUsers.value.filter(user => !participantIds.includes(user.id));
        });

        // Validation rules
        const rules = {
            required: (value) => !!value || 'Please select a user to add'
        };

        const defaultAvatar = (user) => {
            const name = user.name || user.username || 'User';
            return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=667eea&color=fff&size=64`;
        };

        const handleUserSearch = (query) => {
            userSearch.value = query

            if (!query || query.length < 2) {
                userList.value = []
                return
            }

            // Clear existing timer
            if (debounceTimer.value) {
                clearTimeout(debounceTimer.value)
            }

            // Set new timer for debounced search
            debounceTimer.value = setTimeout(async () => {
                await searchUsers(query)
            }, 500)
        }
        const searchUsers = async (query) => {
            loadingUsers.value = true

            try {
                //const users = await conversationStore.searchUsers(query)

                /*axios.post('/api/users/search', {
                    query: query
                }).then((response) => {
                    resolve(response);
                }).catch(reject);*/



                try {
                    const response = await axios.post('/api/users/search', {
                        query: query,
                    });
                    console.log('response', response)
                    userList.value = response.data;
                    //userList.value = users;
                } catch (error) {
                    console.warn(error);
                }


                // userList.value = users;
                //console.log(users);

                // Filter out current user and already selected users
                /*  userList.value = users.filter(user =>
                      !recipients.value.includes(user.id) &&
                      user.id !== getCurrentUserId()
                  )*/

            } catch (error) {
                console.error('Error searching users:', error)
                //notificationStore.showError('Failed to search users')
            } finally {
                loadingUsers.value = false
            }
        }

        const addUser = async () => {
            if (!selectedUser.value || isSubmitting.value) return;

            // Validate form
            const { valid } = await formRef.value.validate();
            if (!valid) return;

            if (!currentConversation.value?.data?.data?.uuid) {
                errorMessage.value = 'No conversation selected';
                return;
            }

            isSubmitting.value = true;
            errorMessage.value = '';
            successMessage.value = '';

            try {
                await conversationStore.addUserToConversation(
                    currentConversation.value.data.data.uuid,
                    selectedUser.value
                );

                // Find the added user for success message
                //const addedUser = allUsers.value.find(user => user.id === selectedUser.value);
                const addedUser = "testuser"
                successMessage.value = `${addedUser?.name || 'User'} has been added to the conversation`;

                // Reset form
                selectedUser.value = null;
                formRef.value.reset();

            } catch (error) {
                console.error('Error adding user to conversation:', error);
                errorMessage.value = error.message || 'Failed to add user to conversation';
            } finally {
                isSubmitting.value = false;
            }
        };

        // Fetch users when component mounts
        onMounted(async () => {
            if (filteredUserList.value.length === 0) {
                loadingUsers.value = true;
                try {
                    await usersStore.fetchUsers();
                } catch (error) {
                    console.error('Error fetching users:', error);
                    errorMessage.value = 'Failed to load users';
                } finally {
                    loadingUsers.value = false;
                }
            }
        });

        return {
            handleUserSearch,
            searchUsers,
            userList,
            userSearch,
            selectedUser,
            filteredUserList,
            loadingUsers,
            isSubmitting,
            successMessage,
            errorMessage,
            formRef,
            rules,
            addUser,
            defaultAvatar,
        };
    },
};
</script>

<style scoped>
.gap-3 {
    gap: 12px;
}

@media (max-width: 600px) {
    .d-flex.align-center {
        flex-direction: column;
        align-items: stretch;
    }

    .gap-3 {
        gap: 16px;
    }
}
</style>
