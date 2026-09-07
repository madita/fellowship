<template>
    <v-card flat>
        <v-card-text>
            <h3 class="text-h6 mb-2">{{ $t('account.legacyClaim.title') }}</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">
                {{ $t('account.legacyClaim.intro') }}
            </p>

            <!-- Username OR e-mail identifies the old account — one is enough. -->
            <v-row dense>
                <v-col cols="12" md="5">
                    <v-text-field
                        v-model="legacyUsername"
                        :label="$t('account.legacyClaim.usernameLabel')"
                        variant="outlined"
                        density="compact"
                        @keyup.enter="preview"
                    />
                </v-col>
                <v-col cols="12" md="5">
                    <v-text-field
                        v-model="legacyEmail"
                        :label="$t('account.legacyClaim.emailLabel')"
                        :hint="$t('account.legacyClaim.emailHint')"
                        persistent-hint
                        type="email"
                        variant="outlined"
                        density="compact"
                        @keyup.enter="preview"
                    />
                </v-col>
                <v-col cols="12" md="2">
                    <v-btn
                        variant="tonal"
                        block
                        :loading="previewing"
                        :disabled="!legacyUsername && !legacyEmail"
                        @click="preview"
                    >
                        {{ $t('account.legacyClaim.check') }}
                    </v-btn>
                </v-col>
            </v-row>

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
                    rows="3"
                    variant="outlined"
                    density="compact"
                    class="mb-3"
                />
                <v-btn
                    color="primary"
                    :loading="submitting"
                    @click="submit"
                >
                    {{ $t('account.legacyClaim.submit') }}
                </v-btn>
            </template>

            <v-alert v-if="result" :type="resultType" variant="tonal" density="compact" class="mt-4">
                {{ result }}
            </v-alert>
        </v-card-text>
    </v-card>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const legacyUsername = ref('');
const legacyEmail = ref('');
const legacyUserId = ref('');
const message = ref('');
const previewing = ref(false);
const submitting = ref(false);
const previewResult = ref(null);
const result = ref('');
const resultType = ref('success');

// The account was looked up by e-mail (no username typed).
const resolvedFromEmail = computed(() => !!previewResult.value && previewResult.value.email_known !== null);
// What to call the account in messages: the resolved username(s), or
// whatever was typed.
const previewName = computed(() => {
    const names = previewResult.value?.legacy_usernames || [];
    return names.length ? names.join(', ') : (previewResult.value?.legacy_username || previewResult.value?.legacy_email || '');
});

const identity = () => ({
    legacy_username: legacyUsername.value || null,
    legacy_email: legacyEmail.value || null,
});

const preview = async () => {
    if (!legacyUsername.value && !legacyEmail.value) return;
    previewing.value = true;
    result.value = '';
    previewResult.value = null;
    try {
        const { data } = await axios.post('/api/account/legacy-claim/preview', identity());
        previewResult.value = data;
    } catch (e) {
        result.value = e.response?.data?.message || e.message;
        resultType.value = 'error';
    } finally {
        previewing.value = false;
    }
};

const submit = async () => {
    submitting.value = true;
    result.value = '';
    try {
        const { data } = await axios.post('/api/account/legacy-claim', {
            ...identity(),
            legacy_user_id: legacyUserId.value || null,
            message: message.value || null,
        });
        result.value = data.message;
        resultType.value = 'success';
        previewResult.value = null;
        message.value = '';
    } catch (e) {
        result.value = e.response?.data?.message || e.message;
        resultType.value = e.response?.status === 409 ? 'warning' : 'error';
    } finally {
        submitting.value = false;
    }
};
</script>
