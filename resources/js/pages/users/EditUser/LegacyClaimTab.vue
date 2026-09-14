<template>
    <v-card flat>
        <v-card-text>
            <h3 class="text-h6 mb-2">{{ $t('account.legacyClaim.title') }}</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">
                {{ $t('account.legacyClaim.intro') }}
            </p>

            <!-- One field: the old username, or the e-mail used there (detected by the "@"). -->
            <v-row dense>
                <v-col cols="12" md="8">
                    <v-text-field
                        v-model="legacyIdentity"
                        :label="$t('account.legacyClaim.identityLabel')"
                        :hint="$t('account.legacyClaim.identityHint')"
                        persistent-hint
                        :prepend-inner-icon="isEmail ? 'mdi-email-outline' : 'mdi-account-outline'"
                        :disabled="previewing || submitting"
                        variant="outlined"
                        density="compact"
                        @keyup.enter="preview"
                    />
                </v-col>
                <v-col cols="12" md="3">
                    <v-btn
                        variant="tonal"
                        :loading="previewing"
                        :disabled="!legacyIdentity.trim() || submitting"
                        @click="preview"
                    >
                        {{ $t('account.legacyClaim.check') }}
                    </v-btn>
                </v-col>
            </v-row>

            <!-- Result of the check: part of the form flow, so shown inline -->
            <template v-if="previewResult">
                <v-alert
                    v-if="previewResult.found"
                    type="success"
                    variant="tonal"
                    density="compact"
                    class="mb-3"
                >
                    {{ $t('account.legacyClaim.found', { total: previewResult.total, name: previewName }) }}
                    <div v-if="resolvedFromEmail" class="text-caption mt-1">
                        {{ $t('account.legacyClaim.resolvedFromEmail', { email: previewResult.legacy_email }) }}
                    </div>
                    <div
                        v-for="(source, name) in previewResult.sources"
                        :key="name"
                        class="mt-1"
                    >
                        <v-chip size="x-small" variant="outlined" prepend-icon="mdi-database-outline" class="mr-1">
                            {{ name }}
                        </v-chip>
                        <v-chip
                            v-for="(count, type) in source.types"
                            :key="type"
                            size="x-small"
                            variant="tonal"
                            class="mr-1"
                        >
                            {{ type }}: {{ count }}
                        </v-chip>
                    </div>
                </v-alert>
                <v-alert v-else-if="previewResult.email_known === false" type="info" variant="tonal" density="compact" class="mb-3">
                    {{ $t('account.legacyClaim.emailUnknown', { email: previewResult.legacy_email }) }}
                </v-alert>
                <v-alert v-else type="info" variant="tonal" density="compact" class="mb-3">
                    {{ $t('account.legacyClaim.notFound', { name: previewName }) }}
                </v-alert>
            </template>

            <template v-if="previewResult?.found">
                <v-row dense>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="legacyUserId"
                            :label="$t('account.legacyClaim.idLabel')"
                            :hint="$t('account.legacyClaim.idHint')"
                            persistent-hint
                            :disabled="submitting"
                            variant="outlined"
                            density="compact"
                            class="mb-3"
                        />
                    </v-col>
                </v-row>
                <v-textarea
                    v-model="message"
                    :label="$t('account.legacyClaim.messageLabel')"
                    :hint="$t('account.legacyClaim.messageHint')"
                    persistent-hint
                    :disabled="submitting"
                    rows="3"
                    variant="outlined"
                    density="compact"
                    class="mb-3"
                />
                <v-btn
                    color="primary"
                    :loading="submitting"
                    :disabled="previewing"
                    @click="submit"
                >
                    {{ $t('account.legacyClaim.submit') }}
                </v-btn>
            </template>
        </v-card-text>
    </v-card>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';

// Outcome of the requests (and errors) is shown as a modal
const dialog = useDialog();

const legacyIdentity = ref('');
const legacyUserId = ref('');
const message = ref('');
const previewing = ref(false);
const submitting = ref(false);
const previewResult = ref(null);

// The account was looked up by e-mail (no username typed).
const resolvedFromEmail = computed(() => !!previewResult.value && previewResult.value.email_known !== null);
// What to call the account in messages: the resolved username(s), or
// whatever was typed.
const previewName = computed(() => {
    const names = previewResult.value?.legacy_usernames || [];
    return names.length ? names.join(', ') : (previewResult.value?.legacy_username || previewResult.value?.legacy_email || '');
});

// The single input is an e-mail when it contains an "@", a username otherwise.
const isEmail = computed(() => legacyIdentity.value.includes('@'));
const identity = () => ({
    legacy_username: isEmail.value ? null : (legacyIdentity.value.trim() || null),
    legacy_email: isEmail.value ? legacyIdentity.value.trim() : null,
});

const preview = async () => {
    // Enter and the button can both trigger this; ignore while a request runs.
    if (previewing.value || submitting.value || !legacyIdentity.value.trim()) return;
    previewing.value = true;
    previewResult.value = null;
    try {
        const { data } = await axios.post('/api/account/legacy-claim/preview', identity());
        previewResult.value = data;
    } catch (e) {
        dialog.requestError(e);
    } finally {
        previewing.value = false;
    }
};

const submit = async () => {
    if (submitting.value || previewing.value) return;
    submitting.value = true;
    try {
        const { data } = await axios.post('/api/account/legacy-claim', {
            ...identity(),
            legacy_user_id: legacyUserId.value || null,
            message: message.value || null,
        });
        dialog.success(data.message);
        previewResult.value = null;
        message.value = '';
    } catch (e) {
        const text = e.response?.data?.message || e.message;
        e.response?.status === 409 ? dialog.warning(text) : dialog.error(text);
    } finally {
        submitting.value = false;
    }
};
</script>
