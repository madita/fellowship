<template>
    <v-dialog
        :model-value="modelValue"
        max-width="700"
        :persistent="submitting"
        @update:model-value="close"
    >
        <v-card>
            <v-card-title class="d-flex align-center ga-2 pt-4 px-6">
                <v-icon :icon="form.type === 'bug' ? 'mdi-bug-outline' : 'mdi-lightbulb-on-outline'" color="primary" />
                {{ $t(`feedback.${form.type}.create`) }}
            </v-card-title>

            <v-card-text class="px-6">
                <v-form ref="form" @submit.prevent="submit">
                    <v-btn-toggle
                        v-model="form.type"
                        mandatory
                        divided
                        variant="outlined"
                        color="primary"
                        density="comfortable"
                        class="mb-4"
                        :aria-label="$t('feedback.fields.type')"
                    >
                        <v-btn value="bug" prepend-icon="mdi-bug-outline">{{ $t('feedback.tabs.bug') }}</v-btn>
                        <v-btn value="feature" prepend-icon="mdi-lightbulb-on-outline">{{ $t('feedback.tabs.feature') }}</v-btn>
                    </v-btn-toggle>
                    <v-text-field
                        v-model="form.title"
                        :label="$t('feedback.fields.title')"
                        :rules="[v => !!v?.trim() || $t('feedback.validation.titleRequired')]"
                        counter="255"
                        maxlength="255"
                        class="mb-2"
                    />
                    <v-textarea
                        v-model="form.description"
                        :label="$t('feedback.fields.description')"
                        :rules="[v => !!v?.trim() || $t('feedback.validation.descriptionRequired')]"
                        :hint="$t(`feedback.${form.type}.descriptionHint`)"
                        persistent-hint
                        rows="8"
                        auto-grow
                        class="mb-4"
                    />
                    <v-autocomplete
                        v-if="tags.length"
                        v-model="form.tag_ids"
                        :items="tags"
                        item-title="name"
                        item-value="id"
                        :label="$t('feedback.fields.tags')"
                        multiple
                        chips
                        closable-chips
                        hide-details
                    />
                </v-form>
            </v-card-text>

            <v-card-actions class="px-6 pb-4">
                <v-spacer />
                <v-btn variant="text" :disabled="submitting" @click="close(false)">
                    {{ $t('feedback.cancel') }}
                </v-btn>
                <v-btn color="primary" variant="flat" :loading="submitting" @click="submit">
                    {{ $t('feedback.submit') }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios'

const emptyForm = (type) => ({ type: type || 'bug', title: '', description: '', tag_ids: [] })

export default {
    name: 'FeedbackComposer',
    props: {
        modelValue: { type: Boolean, default: false },
        // Preselected type (the open tab); the member can still switch
        type: {
            type: String,
            default: null,
            validator: (value) => value === null || ['bug', 'feature'].includes(value),
        },
        // Active tags, loaded once by the list page
        tags: { type: Array, default: () => [] },
    },
    emits: ['update:modelValue', 'submitted'],
    data() {
        return {
            submitting: false,
            form: emptyForm(this.type),
        }
    },
    watch: {
        modelValue(open) {
            if (open && this.type) this.form.type = this.type
        },
    },
    methods: {
        close(open = false) {
            if (open || this.submitting) return
            this.$emit('update:modelValue', false)
        },
        async submit() {
            if (this.submitting) return
            const { valid } = await this.$refs.form.validate()
            if (!valid) return

            this.submitting = true
            try {
                const { data } = await axios.post('/api/feedback/tickets', this.form)
                const type = this.form.type
                this.form = emptyForm(this.type)
                this.$refs.form.resetValidation()
                this.$emit('update:modelValue', false)
                await this.$dialog.success(this.$t(`feedback.${type}.created`))
                this.$emit('submitted', data)
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.createFailed'))
            } finally {
                this.submitting = false
            }
        },
    },
}
</script>
