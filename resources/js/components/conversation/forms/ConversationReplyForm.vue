<template>
    <v-card class="reply-form-card" elevation="0" color="grey-lighten-5">
        <v-card-text class="pa-4">
            <v-form @submit.prevent="reply" ref="formRef">
                <div class="d-flex align-start gap-3">
                    <!-- User Avatar -->
                    <v-avatar size="40" class="mt-1">
                        <v-img
                            :src="currentUser?.avatar || '/default-avatar.png'"
                            :alt="`${currentUser?.username || 'User'}'s avatar`"
                        ></v-img>
                    </v-avatar>

                    <!-- Reply Input Section -->
                    <div class="flex-grow-1">
                        <v-textarea
                            v-model="body"
                            :label="$t('conversation.writeReply')"
                            variant="outlined"
                            rows="3"
                            auto-grow
                            :max-rows="8"
                            :rules="[rules.required, rules.minLength]"
                            :loading="isSubmitting"
                            :disabled="isSubmitting"
                            :placeholder="$t('conversation.shareThoughts')"
                            class="reply-textarea"
                            hide-details="auto"
                            @keydown.ctrl.enter="reply"
                            @keydown.meta.enter="reply"
                        >
                            <template #prepend-inner>
                                <v-icon color="grey-darken-1" size="20">mdi-message-text-outline</v-icon>
                            </template>
                        </v-textarea>

                        <!-- Action Bar -->
                        <div class="d-flex justify-space-between align-center mt-3">
                            <div class="d-flex align-center">
                                <v-tooltip bottom>
                                    <template #activator="{ props }">
                                        <v-btn
                                            v-bind="props"
                                            icon
                                            size="small"
                                            variant="text"
                                            color="grey-darken-1"
                                            @click="insertEmoji"
                                        >
                                            <v-icon>mdi-emoticon-happy-outline</v-icon>
                                        </v-btn>
                                    </template>
                                    <span>{{ $t('conversation.addEmoji') }}</span>
                                </v-tooltip>

                                <v-tooltip bottom>
                                    <template #activator="{ props }">
                                        <v-btn
                                            v-bind="props"
                                            icon
                                            size="small"
                                            variant="text"
                                            color="grey-darken-1"
                                            @click="attachFile"
                                        >
                                            <v-icon>mdi-attachment</v-icon>
                                        </v-btn>
                                    </template>
                                    <span>{{ $t('conversation.attachFile') }}</span>
                                </v-tooltip>

                                <v-chip
                                    v-if="body"
                                    size="x-small"
                                    color="grey"
                                    variant="outlined"
                                    class="ml-2"
                                >
                                    {{ characterCount }} {{ $t('conversation.characters') }}
                                </v-chip>
                            </div>

                            <div class="d-flex align-center gap-2">
                                <v-btn
                                    v-if="body"
                                    variant="outlined"
                                    size="small"
                                    color="grey"
                                    @click="clearMessage"
                                    :disabled="isSubmitting"
                                >
                                    {{ $t('conversation.clear') }}
                                </v-btn>

                                <v-btn
                                    type="submit"
                                    color="primary"
                                    :loading="isSubmitting"
                                    :disabled="!body?.trim() || isSubmitting"
                                    class="px-6"
                                    append-icon="mdi-send"
                                >
                                    {{ isSubmitting ? $t('conversation.sending') : $t('conversation.sendReply') }}
                                </v-btn>
                            </div>
                        </div>

                        <!-- Keyboard Shortcut Hint -->
                        <div class="text-caption text-grey-darken-1 mt-2">
                            <v-icon size="12" class="mr-1">mdi-keyboard</v-icon>
                            {{ $t('conversation.keyboardShortcutHint') }}
                        </div>
                    </div>
                </div>
            </v-form>
        </v-card-text>

        <!-- Success/Error Messages -->
        <v-snackbar
            v-model="showSuccess"
            color="success"
            timeout="3000"
            location="bottom right"
        >
            <v-icon class="mr-2">mdi-check-circle</v-icon>
            {{ $t('conversation.replySentSuccess') }}
        </v-snackbar>

        <v-snackbar
            v-model="showError"
            color="error"
            timeout="5000"
            location="bottom right"
        >
            <v-icon class="mr-2">mdi-alert-circle</v-icon>
            {{ errorMessage }}
        </v-snackbar>
    </v-card>
</template>

<script>
import { ref, computed, defineComponent } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConversationStore } from "@/store/conversationStore";
import { useUserStore } from "@/store/userStore";
import { VALIDATION_RULES } from '../constants';

export default defineComponent({
    name: "ConversationReplyForm",
    setup() {
        const { t } = useI18n();
        const conversationStore = useConversationStore();
        const userStore = useUserStore();

        const body = ref('');
        const isSubmitting = ref(false);
        const showSuccess = ref(false);
        const showError = ref(false);
        const errorMessage = ref('');
        const formRef = ref(null);

        const conversation = computed(() => conversationStore.currentConversation);
        const currentUser = computed(() => userStore.currentUser);
        const characterCount = computed(() => body.value?.length || 0);

        const rules = {
            required: VALIDATION_RULES.required,
            minLength: VALIDATION_RULES.minLength(1)
        };

        const reply = async () => {
            if (!body.value?.trim() || isSubmitting.value) return;

            const { valid } = await formRef.value.validate();
            if (!valid) return;

            isSubmitting.value = true;
            showError.value = false;

            try {
                await conversationStore.createConversationReply({
                    id: conversation.value.id,
                    uuid: conversation.value.uuid,
                    body: body.value.trim(),
                });

                body.value = '';
                showSuccess.value = true;
                formRef.value.reset();
            } catch (error) {
                console.error('Error sending reply:', error);
                errorMessage.value = error.message || t('conversation.replyFailedError');
                showError.value = true;
            } finally {
                isSubmitting.value = false;
            }
        };

        const clearMessage = () => {
            body.value = '';
            formRef.value.resetValidation();
        };

        const insertEmoji = () => {
            const emojis = ['😊', '👍', '❤️', '😂', '🔥', '💯', '🎉', '👏'];
            const randomEmoji = emojis[Math.floor(Math.random() * emojis.length)];
            body.value += randomEmoji;
        };

        const attachFile = () => {
            console.log('File attachment clicked');
        };

        return {
            t,
            body,
            isSubmitting,
            showSuccess,
            showError,
            errorMessage,
            formRef,
            conversation,
            currentUser,
            characterCount,
            rules,
            reply,
            clearMessage,
            insertEmoji,
            attachFile,
        };
    },
});
</script>

<style scoped>
.reply-form-card {
    border: 1px solid rgba(0, 0, 0, 0.12);
    border-radius: 16px !important;
    background: linear-gradient(145deg, #fafafa 0%, #f5f5f5 100%);
}

.reply-textarea :deep(.v-field) {
    border-radius: 12px;
    background-color: white;
}

.reply-textarea :deep(.v-field--focused) {
    box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.2);
}

.reply-textarea :deep(.v-textarea .v-field__input) {
    padding-top: 12px;
}

.gap-2 {
    gap: 8px;
}

.gap-3 {
    gap: 12px;
}

@media (max-width: 600px) {
    .d-flex.justify-space-between {
        flex-direction: column;
        gap: 12px;
    }

    .d-flex.align-center.gap-2 {
        justify-content: center;
    }
}
</style>
