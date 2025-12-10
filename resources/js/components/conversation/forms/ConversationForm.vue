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
import { ref, computed, watch } from 'vue'
import { useConversationStore } from '@/store/conversationStore.js'
import { useUserSearch } from '@/composables/conversation/useUserSearch'
import { VALIDATION_RULES, MESSAGE_LIMITS } from '../constants'
import axios from "axios"

// Emits
const emit = defineEmits(['conversation-created', 'cancel'])

// Composables
const conversationStore = useConversationStore()
const { userList, userSearch, loadingUsers, handleUserSearch } = useUserSearch()

// Reactive state
const formRef = ref(null)
const recipients = ref([])
const body = ref('')
const isSubmitting = ref(false)

// Computed properties
const currentConversation = computed(() => conversationStore.currentConversation)

const isFormValid = computed(() => {
    return recipients.value.length > 0 &&
        body.value.trim().length > 0 &&
        !isSubmitting.value
})

// Form validation rules
const recipientRules = [VALIDATION_RULES.recipientRequired]
const bodyRules = [
    VALIDATION_RULES.required,
    VALIDATION_RULES.maxLength(MESSAGE_LIMITS.MAX_BODY_LENGTH)
]

// Methods
const handleSubmit = async () => {
    if (!formRef.value) return

    const { valid } = await formRef.value.validate()
    if (!valid) return

    isSubmitting.value = true

    try {
        const conversationData = {
            recipients: recipients.value,
            body: body.value.trim(),
            created_at: new Date().toISOString()
        }

        const newConversation = await createConversation(conversationData)
        resetForm()
        emit('conversation-created', newConversation)
    } catch (error) {
        console.error('Error creating conversation:', error)
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

    if (formRef.value) {
        formRef.value.resetValidation()
    }
}

const createConversation = async (data) => {
    return new Promise((resolve, reject) => {
        axios.post('/api/conversations', data).then((response) => {
            resolve(response)
        }).catch(reject)
    })
}

// Watchers
watch(recipients, (newRecipients) => {
    userList.value = userList.value.filter(user =>
        !newRecipients.includes(user.id)
    )
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
