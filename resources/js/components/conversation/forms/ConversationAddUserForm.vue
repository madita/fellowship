<template>
    <v-form ref="formRef" @submit.prevent="addUser">
        <div class="d-flex align-center ga-3">
            <v-autocomplete
                v-model="selectedUser"
                :items="userList"
                :loading="loadingUsers"
                :search="userSearch"
                @update:search="handleUserSearch"
                :label="$t('conversation.selectRecipients')"
                :placeholder="$t('conversation.searchUsersPlaceholder')"
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
                            {{ userSearch ? $t('conversation.noUsersFound') : $t('conversation.startTypingToSearch') }}
                        </v-list-item-title>
                    </v-list-item>
                </template>
            </v-autocomplete>
            <v-btn
                type="submit"
                color="primary"
                :loading="isSubmitting"
                :disabled="!selectedUser || selectedUser.length === 0 || isSubmitting"
                icon
                size="large"
            >
                <v-icon>mdi-account-plus</v-icon>
                <v-tooltip activator="parent" location="top">
                    {{ $t('conversation.addUserToConversation') }}
                </v-tooltip>
            </v-btn>
        </div>
    </v-form>
</template>

<script>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConversationStore } from '@/store/conversationStore';
import { useUsersStore } from '@/store/usersStore';
import { useUserSearch } from '@/composables/conversation/useUserSearch';
import { useDialog } from '@/composables/useDialog.js';

export default {
    name: "ConversationAddUserForm",
    setup() {
        const { t } = useI18n();
        const conversationStore = useConversationStore();
        const usersStore = useUsersStore();
        // The outcome of adding a user is shown as a modal
        const dialog = useDialog();

        const selectedUser = ref(null);
        const isSubmitting = ref(false);
        const formRef = ref(null);

        const { userList, userSearch, loadingUsers, handleUserSearch } = useUserSearch();

        const currentConversation = computed(() => conversationStore.currentConversation);
        const allUsers = computed(() => usersStore.allUsers || []);

        const filteredUserList = computed(() => {
            if (!currentConversation.value?.users) return allUsers.value;

            const participantIds = currentConversation.value.users.map(user => user.id);
            return allUsers.value.filter(user => !participantIds.includes(user.id));
        });

        const rules = {
            required: (value) => !!value || t('conversation.pleaseSelectUser')
        };

        const defaultAvatar = (user) => {
            const name = user.name || user.username || 'User';
            return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=667eea&color=fff&size=64`;
        };

        const addUser = async () => {
            if (!selectedUser.value || isSubmitting.value) return;

            const { valid } = await formRef.value.validate();
            if (!valid) return;

            if (!currentConversation.value?.uuid) {
                await dialog.warning(t('conversation.noConversationSelected'));
                return;
            }

            isSubmitting.value = true;

            try {
                const ids = Array.isArray(selectedUser.value) ? selectedUser.value : [selectedUser.value];
                const names = ids
                    .map(id => userList.value.find(u => u.id === id)?.username)
                    .filter(Boolean)
                    .join(', ');

                await conversationStore.addUserToConversation(
                    currentConversation.value.uuid,
                    selectedUser.value
                );

                selectedUser.value = null;
                formRef.value.reset();
                await dialog.success(t('conversation.userAddedSuccess', { name: names || t('messaging.users') }));
            } catch (error) {
                console.error('Error adding user to conversation:', error);
                await dialog.requestError(error, t('conversation.failedToAddUser'));
            } finally {
                isSubmitting.value = false;
            }
        };

        return {
            handleUserSearch,
            userList,
            userSearch,
            selectedUser,
            filteredUserList,
            loadingUsers,
            isSubmitting,
            formRef,
            rules,
            addUser,
            defaultAvatar,
        };
    },
};
</script>

<style scoped>
@media (max-width: 600px) {
    .d-flex.align-center {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
