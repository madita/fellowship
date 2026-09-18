<template>
    <div>
        <page-header
            :title="$t(`feedback.${form.type}.create`)"
            :subtitle="$t('feedback.editor.subtitle')"
            :icon="form.type === 'bug' ? 'mdi-bug-outline' : 'mdi-lightbulb-on-outline'"
            :back-to="backTo"
        >
            <template #actions>
                <v-btn variant="tonal" :to="backTo" :disabled="submitting">
                    {{ $t('feedback.cancel') }}
                </v-btn>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-send"
                    :loading="submitting"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{ $t('feedback.submit') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <v-form ref="form" @submit.prevent="submit">
                <v-row>
                    <!-- Content -->
                    <v-col cols="12" lg="8">
                        <v-card class="editor-card" elevation="2" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-file-document-edit</v-icon>
                                {{ $t('feedback.editor.content') }}
                            </v-card-title>
                            <v-card-text class="pa-6">
                                <div class="type-choice d-flex flex-wrap ga-3 mb-6" role="radiogroup" :aria-label="$t('feedback.fields.type')">
                                    <v-card
                                        v-for="option in typeOptions"
                                        :key="option.value"
                                        role="radio"
                                        :aria-checked="form.type === option.value"
                                        :variant="form.type === option.value ? 'tonal' : 'outlined'"
                                        :color="form.type === option.value ? 'primary' : undefined"
                                        rounded="lg"
                                        class="type-option flex-grow-1"
                                        @click="form.type = option.value"
                                    >
                                        <v-card-text class="d-flex align-center ga-3">
                                            <v-icon :icon="option.icon" size="28" />
                                            <div>
                                                <div class="text-subtitle-1 font-weight-medium">{{ $t(`feedback.tabs.${option.value}`) }}</div>
                                                <div class="text-caption text-medium-emphasis">{{ $t(`feedback.editor.typeHint.${option.value}`) }}</div>
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </div>

                                <v-text-field
                                    v-model="form.title"
                                    :label="$t('feedback.fields.title')"
                                    :placeholder="$t(`feedback.editor.titlePlaceholder.${form.type}`)"
                                    :rules="[v => !!v?.trim() || $t('feedback.validation.titleRequired')]"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-format-title"
                                    counter="255"
                                    maxlength="255"
                                    autofocus
                                    class="mb-4"
                                />

                                <div class="d-flex align-center mb-3">
                                    <v-icon class="mr-2" size="20" color="primary">mdi-text</v-icon>
                                    <span class="text-subtitle-1 font-weight-medium">{{ $t('feedback.fields.description') }}</span>
                                </div>
                                <simple-editor
                                    v-model="form.description"
                                    :placeholder="$t(`feedback.${form.type}.descriptionHint`)"
                                    :limit="10000"
                                    :disabled="submitting"
                                    min-height="280px"
                                    @submit="submit"
                                />
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <!-- Settings and tips -->
                    <v-col cols="12" lg="4">
                        <v-card v-if="tags.length" class="settings-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-tag-multiple-outline</v-icon>
                                {{ $t('feedback.fields.tags') }}
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-autocomplete
                                    v-model="form.tag_ids"
                                    :items="tags"
                                    item-title="name"
                                    item-value="id"
                                    :label="$t('feedback.fields.tags')"
                                    variant="outlined"
                                    density="compact"
                                    multiple
                                    chips
                                    closable-chips
                                    hide-details
                                />
                            </v-card-text>
                        </v-card>

                        <v-card class="settings-card" elevation="1" rounded="lg">
                            <v-card-title class="text-subtitle-1 font-weight-medium d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-lightbulb-outline</v-icon>
                                {{ $t('feedback.editor.tipsTitle') }}
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <ul class="tips text-body-2">
                                    <li v-for="tip in tips" :key="tip">{{ tip }}</li>
                                </ul>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-form>
        </v-container>
    </div>
</template>

<script>
import axios from 'axios'
import PageHeader from '@/components/common/PageHeader.vue'
import SimpleEditor from '@/components/common/tiptap/SimpleEditor.vue'
import { hasRichText } from '@/utils/richText.js'

const TYPES = ['bug', 'feature']

/**
 * File a bug report or feature request (/feedback/new?type=bug|feature),
 * laid out like the wiki editor: type, title and description with
 * @mentions on the left, tags and writing tips on the right.
 */
export default {
    name: 'FeedbackEditor',
    components: { PageHeader, SimpleEditor },
    data() {
        return {
            form: {
                type: TYPES.includes(this.$route.query.type) ? this.$route.query.type : 'bug',
                title: '',
                description: '',
                tag_ids: [],
            },
            tags: [],
            submitting: false,
            submitted: false,
            typeOptions: [
                { value: 'bug', icon: 'mdi-bug-outline' },
                { value: 'feature', icon: 'mdi-lightbulb-on-outline' },
            ],
        }
    },
    computed: {
        backTo() {
            return { name: 'feedback', query: TYPES.includes(this.$route.query.type) ? { type: this.$route.query.type } : {} }
        },
        canSubmit() {
            return !!this.form.title.trim() && hasRichText(this.form.description) && !this.submitting
        },
        dirty() {
            return !!this.form.title.trim() || hasRichText(this.form.description)
        },
        tips() {
            return [1, 2, 3].map(n => this.$t(`feedback.editor.tips.${this.form.type}${n}`))
        },
    },
    mounted() {
        this.loadTags()
        window.addEventListener('beforeunload', this.warnBeforeUnload)
    },
    beforeUnmount() {
        window.removeEventListener('beforeunload', this.warnBeforeUnload)
    },
    async beforeRouteLeave() {
        if (this.submitted || !this.dirty) return true
        return this.$dialog.confirm({
            title: this.$t('feedback.editor.leaveTitle'),
            content: this.$t('feedback.editor.leaveMessage'),
            confirmationText: this.$t('feedback.editor.leave'),
            color: 'warning',
        })
    },
    methods: {
        async loadTags() {
            try {
                const { data } = await axios.get('/api/feedback/tags')
                this.tags = data
            } catch (error) {
                console.warn('Failed to load feedback tags:', error)
            }
        },
        warnBeforeUnload(event) {
            if (this.dirty && !this.submitted) event.preventDefault()
        },
        async submit() {
            if (!this.canSubmit) return
            const { valid } = await this.$refs.form.validate()
            if (!valid) return

            this.submitting = true
            try {
                const { data } = await axios.post('/api/feedback/tickets', this.form)
                this.submitted = true
                await this.$dialog.success(this.$t(`feedback.${this.form.type}.created`))
                this.$router.replace({ name: 'feedback-ticket', params: { id: data.id } })
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('feedback.messages.createFailed'))
            } finally {
                this.submitting = false
            }
        },
    },
}
</script>

<style scoped>
.editor-card,
.settings-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.type-option {
    min-width: 220px;
    cursor: pointer;
}

.tips {
    padding-left: 18px;
    line-height: 1.6;
}

.tips li + li {
    margin-top: 6px;
}
</style>
