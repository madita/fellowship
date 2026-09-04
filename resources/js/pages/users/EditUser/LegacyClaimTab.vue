<template>
    <v-card flat>
        <v-card-text>
            <h3 class="text-h6 mb-2">{{ $t('account.legacyClaim.title') }}</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">
                {{ $t('account.legacyClaim.intro') }}
            </p>

            <v-row dense>
                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="legacyUsername"
                        :label="$t('account.legacyClaim.usernameLabel')"
                        variant="outlined"
                        density="compact"
                        @keyup.enter="preview"
                    />
                </v-col>
                <v-col cols="12" md="3">
                    <v-btn
                        variant="tonal"
                        :loading="previewing"
                        :disabled="!legacyUsername"
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
                    {{ $t('account.legacyClaim.found', { total: previewResult.total, name: previewResult.legacy_username }) }}
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
                <v-alert v-else type="info" variant="tonal" density="compact" class="mb-3">
                    {{ $t('account.legacyClaim.notFound', { name: previewResult.legacy_username }) }}
                </v-alert>
            </template>

            <template v-if="previewResult?.found">
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
import { ref } from 'vue';
import axios from 'axios';

const legacyUsername = ref('');
const message = ref('');
const previewing = ref(false);
const submitting = ref(false);
const previewResult = ref(null);
const result = ref('');
const resultType = ref('success');

const preview = async () => {
    previewing.value = true;
    result.value = '';
    previewResult.value = null;
    try {
        const { data } = await axios.post('/api/account/legacy-claim/preview', {
            legacy_username: legacyUsername.value,
        });
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
            legacy_username: legacyUsername.value,
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
