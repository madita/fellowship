<template>
    <v-form @submit.prevent="handleSubmit" ref="formRef">
        <v-card flat>
            <v-card-title class="text-h6 pa-0 mb-4">
                New Conversation
            </v-card-title>

            <v-card-text class="pa-0">
                <!-- Recipients Selection -->
                <v-autocomplete
                    v-model="recipients"
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
                    :rules="recipientRules"
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

                <!-- Message Body -->
                <v-textarea
                    v-model="body"
                    label="Message Body"
                    placeholder="Type your message here..."
                    variant="outlined"
                    rows="4"
                    auto-grow
                    counter
                    maxlength="1000"
                    :rules="bodyRules"
                    class="mb-4"
                />

                <!-- Action Buttons -->
                <div class="d-flex justify-end gap-2">
                    <v-btn
                        variant="outlined"
                        @click="handleCancel"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        type="submit"
                        :loading="isSubmitting"
                        :disabled="!isFormValid"
                    >
                        <v-icon start>mdi-send</v-icon>
                        Send Message
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>
    </v-form>
</template>

<script setup>
import { ref, computed, onUnmounted, onMounted, watch } from 'vue'
import { useConversationStore } from '@/store/conversationStore.js'
import axios from "axios";
//import { useNotificationStore } from '@/store/notificationStore.js'

// Emits
const emit = defineEmits(['conversation-created', 'cancel'])

// Composables
const conversationStore = useConversationStore()
//const notificationStore = useNotificationStore()

// Reactive state
const formRef = ref(null)
const recipients = ref([])
const body = ref('')
const userList = ref([])
const userSearch = ref('')
const loadingUsers = ref(false)
const isSubmitting = ref(false)
const debounceTimer = ref(null)

// Computed properties
const currentConversation = computed(() => conversationStore.currentConversation)

const isFormValid = computed(() => {
    return recipients.value.length > 0 &&
        body.value.trim().length > 0 &&
        !isSubmitting.value
})

// Form validation rules
const recipientRules = [
    (value) => {
        if (!value || value.length === 0) {
            return 'At least one recipient is required'
        }
        return true
    }
]

const bodyRules = [
    (value) => {
        if (!value || value.trim().length === 0) {
            return 'Message body is required'
        }
        if (value.length > 1000) {
            return 'Message body must be less than 1000 characters'
        }
        return true
    }
]

// Methods
const handleSubmit = async () => {
    if (!formRef.value) return

    // Validate form
    const { valid } = await formRef.value.validate()
    if (!valid) return

    isSubmitting.value = true

    try {
        const conversationData = {
            recipients: recipients.value,
            body: body.value.trim(),
            created_at: new Date().toISOString()
        }

        console.log('conversationData', conversationData)

        // Create conversation through store
       // const newConversation = await conversationStore.createConversation(conversationData)
        const newConversation = await createConversation(conversationData)

        // Show success notification
        //notificationStore.showSuccess('Conversation created successfully!')

        // Reset form
        resetForm()

        // Emit event to parent
        emit('conversation-created', newConversation)

    } catch (error) {
        console.error('Error creating conversation:', error)

        // Show error notification
        /*notificationStore.showError(
            error.message || 'Failed to create conversation. Please try again.'
        )*/
    } finally {
        isSubmitting.value = false
    }
}

const handleCancel = () => {
    resetForm()
    emit('cancel')
}

const resetForm = () => {
    recipients.value = []
    body.value = ''
    userSearch.value = ''

    // Reset form validation
    if (formRef.value) {
        formRef.value.resetValidation()
    }
}

const createConversation = async (data) => {
    return new Promise((resolve, reject) => {
        axios.post('/api/conversations', data).then((response) => {
            resolve(response);
        }).catch(reject);
    });
}

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

const getCurrentUserId = () => {
    // Get current user ID from your auth system
    return window.App?.user?.id || null
}

// Watchers
watch(recipients, (newRecipients) => {
    // Remove selected users from search results
    userList.value = userList.value.filter(user =>
        !newRecipients.includes(user.id)
    )
})

// Lifecycle
onMounted(async () => {
    try {
        // Load initial user list if needed
       // await conversationStore.loadRecentContacts()
    } catch (error) {
        console.error('Error loading recent contacts:', error)
    }
})

// Cleanup
onUnmounted(() => {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value)
    }
})
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}

/* Custom chip styling */
:deep(.v-chip) {
    margin: 2px;
}

/* Form styling */
:deep(.v-field--variant-outlined) {
    --v-field-border-width: 1px;
}

:deep(.v-field--variant-outlined.v-field--focused) {
    --v-field-border-width: 2px;
}

/* Loading overlay */
.v-autocomplete :deep(.v-progress-linear) {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
}
</style>
