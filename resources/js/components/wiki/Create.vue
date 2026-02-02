<template>
    <div class="wiki-create-container">
        <v-container class="py-6">
            <!-- Progress Indicator -->
<!--            <div class="progress-section mb-6">-->
<!--                <v-stepper-->
<!--                    v-model="currentStep"-->
<!--                    :items="steps"-->
<!--                    color="success"-->
<!--                    variant="horizontal"-->
<!--                    class="elevation-2"-->
<!--                >-->
<!--                    <template #item.1>-->
<!--                        <v-stepper-item-->
<!--                            :complete="!!wikipage.title"-->
<!--                            title="Basic Info"-->
<!--                            subtitle="Title and content"-->
<!--                            value="1"-->
<!--                        />-->
<!--                    </template>-->
<!--                    <template #item.2>-->
<!--                        <v-stepper-item-->
<!--                            :complete="categoryValue.length > 0 || termValue.length > 0"-->
<!--                            title="Organization"-->
<!--                            subtitle="Categories and tags"-->
<!--                            value="2"-->
<!--                        />-->
<!--                    </template>-->
<!--                    <template #item.3>-->
<!--                        <v-stepper-item-->
<!--                            title="Review"-->
<!--                            subtitle="Final check"-->
<!--                            value="3"-->
<!--                        />-->
<!--                    </template>-->
<!--                </v-stepper>-->
<!--            </div>-->

            <!-- Header Section -->
            <div class="create-header mb-6">
                <v-row align="center">
                    <v-col cols="12" md="8">
                        <div class="d-flex align-center mb-2">
                            <v-icon color="success" size="28" class="mr-3">mdi-file-document-plus</v-icon>
                            <h1 class="create-title text-h4 font-weight-bold">
                                Create New Wiki Page
                            </h1>
                        </div>
                        <p class="text-subtitle-1 text-medium-emphasis">
                            {{ slug ? `Creating page: "${slug}"` : 'Build your knowledge base with rich, organized content' }}
                        </p>
                    </v-col>
                    <v-col cols="12" md="4" class="text-right">
                        <div class="header-actions">
                            <v-btn
                                v-if="authenticated && slug"
                                variant="outlined"
                                color="secondary"
                                prepend-icon="mdi-arrow-left"
                                :to="`/wiki/${slug}`"
                                class="mr-2"
                            >
                                Cancel
                            </v-btn>
                            <v-btn
                                color="success"
                                variant="elevated"
                                prepend-icon="mdi-content-save"
                                @click="handleSubmit"
                                :loading="creating"
                                :disabled="!canCreate"
                                class="create-btn"
                            >
                                {{ creating ? 'Creating...' : 'Create Page' }}
                            </v-btn>
                        </div>
                    </v-col>
                </v-row>

                <!-- Smart Alerts -->
                <v-alert
                    v-if="slug && slug.length > 0"
                    type="info"
                    variant="tonal"
                    class="mt-4"
                    closable
                >
                    <template #prepend>
                        <v-icon>mdi-information</v-icon>
                    </template>
                    <div class="alert-content">
                        <h4 class="alert-title">Ready to Create</h4>
                        <p class="mb-0">The page "{{ slug }}" doesn't exist yet. Fill out the form below to create it and start building your knowledge base.</p>
                    </div>
                </v-alert>

                <!-- Success Message with Actions -->
                <v-alert
                    v-if="message"
                    type="success"
                    variant="tonal"
                    class="mt-4"
                    closable
                    @click:close="message = ''"
                >
                    <template #prepend>
                        <v-icon>mdi-check-circle</v-icon>
                    </template>
                    <div class="d-flex justify-space-between align-center">
                        <span>{{ message }}</span>
                        <div class="ml-4">
                            <v-btn
                                v-if="createdSlug"
                                color="success"
                                variant="elevated"
                                size="small"
                                :to="`/wiki/${createdSlug}`"
                                prepend-icon="mdi-eye"
                                class="mr-2"
                            >
                                View Page
                            </v-btn>
                            <v-btn
                                variant="outlined"
                                size="small"
                                @click="createAnother"
                                prepend-icon="mdi-plus"
                            >
                                Create Another
                            </v-btn>
                        </div>
                    </div>
                </v-alert>

                <!-- Enhanced Error Display -->
                <v-alert
                    v-if="editing.errors && editing.errors.length > 0"
                    type="error"
                    variant="tonal"
                    class="mt-4"
                    closable
                    @click:close="editing.errors = []"
                >
                    <template #prepend>
                        <v-icon>mdi-alert-circle</v-icon>
                    </template>
                    <div class="error-content">
                        <h4 class="mb-2">Please fix the following issues:</h4>
                        <ul class="mb-0">
                            <li v-for="(error, index) in editing.errors" :key="index">{{ error }}</li>
                        </ul>
                    </div>
                </v-alert>
            </div>

            <!-- Main Content with Responsive Layout -->
            <v-row>
                <!-- Editor Section -->
                <v-col cols="12" :lg="showPreview ? 8 : 12">
                    <v-card class="editor-card" elevation="2" rounded="lg">
                        <v-card-title class="editor-card-title d-flex justify-space-between align-center">
                            <div class="d-flex align-center">
                                <v-icon class="mr-2" color="success">mdi-file-document-edit</v-icon>
                                <span>Page Content</span>
                            </div>
                            <div class="editor-actions">
                                <v-btn
                                    variant="text"
                                    size="small"
                                    @click="showPreview = !showPreview"
                                    :prepend-icon="showPreview ? 'mdi-eye-off' : 'mdi-eye'"
                                >
                                    {{ showPreview ? 'Hide' : 'Show' }} Preview
                                </v-btn>
                                <v-btn
                                    variant="text"
                                    size="small"
                                    @click="autoSave"
                                    :loading="autoSaving"
                                    prepend-icon="mdi-content-save-outline"
                                >
                                    Auto Save
                                </v-btn>
                            </div>
                        </v-card-title>

                        <v-card-text class="pa-6">
                            <!-- Enhanced Title Field -->
                            <div class="title-section mb-6">
                                <v-text-field
                                    v-model="wikipage.title"
                                    label="Page Title"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-format-title"
                                    placeholder="Enter a clear, descriptive title..."
                                    class="title-field"
                                    :error="titleError"
                                    :error-messages="titleErrorMessage"
                                    @input="onTitleChange"
                                    @blur="validateTitle"
                                    clearable
                                    counter="100"
                                    maxlength="100"
                                >
                                    <template #append-inner>
                                        <v-tooltip text="A good title is descriptive and helps others find your content">
                                            <template #activator="{ props }">
                                                <v-icon v-bind="props" size="small" color="info">mdi-help-circle</v-icon>
                                            </template>
                                        </v-tooltip>
                                    </template>
                                </v-text-field>

                                <!-- URL Preview -->
                                <div v-if="wikipage.title" class="url-preview mt-2">
                                    <v-chip size="small" color="success" variant="tonal">
                                        <v-icon start size="16">mdi-link</v-icon>
                                        URL: /wiki/{{ generateSlug(wikipage.title) }}
                                    </v-chip>
                                </div>
                            </div>

                            <!-- Content Editor with Toolbar -->
                            <div class="content-section">
                                <div class="content-header mb-3 d-flex justify-space-between align-center">
                                    <div class="d-flex align-center">
                                        <v-icon class="mr-2" size="20" color="success">mdi-text</v-icon>
                                        <span class="text-subtitle-1 font-weight-medium">Page Content</span>
                                    </div>
                                    <div class="content-stats">
                                        <v-chip size="small" variant="tonal" color="info">
                                            {{ contentStats.words }} words, {{ contentStats.chars }} characters
                                        </v-chip>
                                    </div>
                                </div>

                                <!-- Content Toolbar -->
                                <div class="content-toolbar mb-3">
                                    <v-btn-group density="compact" variant="outlined">
                                        <v-btn size="small" @click="insertTemplate('heading')">
                                            <v-icon>mdi-format-header-1</v-icon>
                                        </v-btn>
                                        <v-btn size="small" @click="insertTemplate('list')">
                                            <v-icon>mdi-format-list-bulleted</v-icon>
                                        </v-btn>
                                        <v-btn size="small" @click="insertTemplate('table')">
                                            <v-icon>mdi-table</v-icon>
                                        </v-btn>
                                        <v-btn size="small" @click="insertTemplate('link')">
                                            <v-icon>mdi-link</v-icon>
                                        </v-btn>
                                    </v-btn-group>

                                    <v-spacer />

                                    <v-btn
                                        variant="text"
                                        size="small"
                                        @click="showTemplates = !showTemplates"
                                        :prepend-icon="showTemplates ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                    >
                                        Templates
                                    </v-btn>
                                </div>

                                <!-- Quick Templates -->
                                <v-expand-transition>
                                    <div v-if="showTemplates" class="templates-section mb-4">
                                        <v-card variant="outlined" class="pa-3">
                                            <div class="text-subtitle-2 mb-2">Quick Templates</div>
                                            <v-chip-group>
                                                <v-chip
                                                    v-for="template in contentTemplates"
                                                    :key="template.name"
                                                    size="small"
                                                    @click="insertContentTemplate(template)"
                                                    prepend-icon="mdi-plus"
                                                >
                                                    {{ template.name }}
                                                </v-chip>
                                            </v-chip-group>
                                        </v-card>
                                    </div>
                                </v-expand-transition>

                                <div class="editor-wrapper">
                                    <tiptap
                                        v-model="wikipage.content"
                                        :value="wikipage.content"
                                        id="text-content"
                                        name="content"
                                        type="full"
                                        placeholder="Start writing your wiki page content here... Use @ to link to other pages, # for headings, and * for lists."
                                        @update:modelValue="onContentChange"
                                    />
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Enhanced Sidebar -->
                <v-col v-if="showPreview" cols="12" lg="4">
                    <div class="sidebar-content">
                        <!-- Quick Actions -->
                        <v-card class="quick-actions-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="quick-actions-title">
                                <v-icon class="mr-2" color="primary">mdi-lightning-bolt</v-icon>
                                Quick Actions
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-row dense>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="focusTitle"
                                            prepend-icon="mdi-format-title"
                                        >
                                            Focus Title
                                        </v-btn>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="focusContent"
                                            prepend-icon="mdi-text"
                                        >
                                            Focus Content
                                        </v-btn>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="clearForm"
                                            prepend-icon="mdi-refresh"
                                        >
                                            Clear All
                                        </v-btn>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="saveAsDraft"
                                            prepend-icon="mdi-content-save-outline"
                                        >
                                            Save Draft
                                        </v-btn>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>

                        <!-- Parent Page Selection -->
                        <v-card class="settings-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="settings-title">
                                <v-icon class="mr-2" color="info">mdi-file-tree</v-icon>
                                Organization
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-select
                                    v-model="wikipage.parent"
                                    :items="pages"
                                    label="Parent Page"
                                    variant="outlined"
                                    density="compact"
                                    prepend-inner-icon="mdi-file-tree"
                                    item-title="title"
                                    item-value="id"
                                    clearable
                                    no-data-text="No parent pages available"
                                    hint="Choose a parent page to organize your content hierarchically"
                                    persistent-hint
                                >
                                    <template #selection="{ item }">
                                        <div class="d-flex align-center">
                                            <v-icon size="16" class="mr-2">mdi-file-document</v-icon>
                                            {{ item.title }}
                                        </div>
                                    </template>
                                    <template #item="{ props, item }">
                                        <v-list-item v-bind="props">
                                            <template #prepend>
                                                <v-icon>mdi-file-document</v-icon>
                                            </template>
                                            <v-list-item-title>{{ item.title }}</v-list-item-title>
                                            <v-list-item-subtitle>{{ item.slug }}</v-list-item-subtitle>
                                        </v-list-item>
                                    </template>
                                </v-select>
                            </v-card-text>
                        </v-card>

                        <!-- Enhanced Categories -->
                        <v-card class="categories-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="categories-title">
                                <v-icon class="mr-2" color="primary">mdi-folder-outline</v-icon>
                                Categories
                                <v-spacer />
                                <v-chip size="small" color="primary" variant="tonal">
                                    {{ categoryValue.length }}
                                </v-chip>
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-combobox
                                    v-model="categoryValue"
                                    :items="categories"
                                    :search-input.sync="searchTax"
                                    item-title="title"
                                    item-value="id"
                                    label="Select or create categories"
                                    variant="outlined"
                                    density="compact"
                                    multiple
                                    chips
                                    deletable-chips
                                    clearable
                                    prepend-inner-icon="mdi-folder-plus"
                                    placeholder="Type to search or create..."
                                    hint="Categories help organize and discover your content"
                                    persistent-hint
                                >
                                    <template #chip="{ props, item }">
                                        <v-chip
                                            v-bind="props"
                                            color="primary"
                                            variant="tonal"
                                            size="small"
                                            closable
                                        >
                                            <v-icon start size="16">mdi-folder</v-icon>
                                            {{ item.title || item }}
                                        </v-chip>
                                    </template>
                                </v-combobox>

                                <!-- Popular Categories -->
                                <div v-if="popularCategories.length" class="popular-categories mt-3">
                                    <div class="text-caption text-medium-emphasis mb-2">Popular Categories:</div>
                                    <v-chip-group>
                                        <v-chip
                                            v-for="category in popularCategories"
                                            :key="category.id"
                                            size="small"
                                            variant="outlined"
                                            @click="addPopularCategory(category)"
                                        >
                                            {{ category.title }}
                                        </v-chip>
                                    </v-chip-group>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Enhanced Tags -->
                        <v-card class="tags-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="tags-title">
                                <v-icon class="mr-2" color="secondary">mdi-tag-outline</v-icon>
                                Tags
                                <v-spacer />
                                <v-chip size="small" color="secondary" variant="tonal">
                                    {{ termValue.length }}
                                </v-chip>
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-combobox
                                    v-model="termValue"
                                    :items="terms"
                                    item-title="title"
                                    :search-input.sync="searchTerm"
                                    label="Add tags"
                                    variant="outlined"
                                    density="compact"
                                    multiple
                                    chips
                                    deletable-chips
                                    clearable
                                    prepend-inner-icon="mdi-tag-plus"
                                    placeholder="Type to search or create tags"
                                    hint="Tags make your content discoverable across topics"
                                    persistent-hint
                                >
                                    <template #no-data>
                                        <v-list-item>
                                            <v-list-item-title>
                                                No results for "<strong>{{ searchTerm }}</strong>".
                                                Press <kbd class="kbd">Enter</kbd> to create a new tag
                                            </v-list-item-title>
                                        </v-list-item>
                                    </template>

                                    <template #chip="{ props, item }">
                                        <v-chip
                                            v-bind="props"
                                            :color="item.color || 'secondary'"
                                            variant="tonal"
                                            size="small"
                                            closable
                                        >
                                            <v-icon start size="16">mdi-pound</v-icon>
                                            {{ item.title || item }}
                                        </v-chip>
                                    </template>
                                </v-combobox>

                                <!-- Suggested Tags -->
                                <div v-if="suggestedTags.length" class="suggested-tags mt-3">
                                    <div class="text-caption text-medium-emphasis mb-2">Suggested based on content:</div>
                                    <v-chip-group>
                                        <v-chip
                                            v-for="tag in suggestedTags"
                                            :key="tag"
                                            size="small"
                                            variant="outlined"
                                            @click="addSuggestedTag(tag)"
                                        >
                                            {{ tag }}
                                        </v-chip>
                                    </v-chip-group>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Enhanced Preview -->
                        <v-card class="preview-card" elevation="1" rounded="lg">
                            <v-card-title class="preview-title">
                                <v-icon class="mr-2" color="success">mdi-eye-outline</v-icon>
                                Live Preview
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <div class="preview-content">
                                    <div class="preview-header mb-3">
                                        <h4 class="preview-page-title">
                                            {{ wikipage.title || 'Untitled Page' }}
                                            <v-chip v-if="!wikipage.title" color="warning" size="x-small" class="ml-2">
                                                Title Required
                                            </v-chip>
                                        </h4>
                                        <div class="text-caption text-medium-emphasis">
                                            Created {{ $formatDate(new Date()) }}
                                        </div>
                                    </div>

                                    <div class="preview-meta mb-3">
                                        <div v-if="categoryValue.length" class="preview-categories mb-2">
                                            <span class="text-caption text-medium-emphasis">Categories: </span>
                                            <v-chip
                                                v-for="category in categoryValue.slice(0, 3)"
                                                :key="category.id || category"
                                                variant="tonal"
                                                color="primary"
                                                size="x-small"
                                                class="mr-1"
                                            >
                                                {{ category.title || category }}
                                            </v-chip>
                                            <span v-if="categoryValue.length > 3" class="text-caption">
                        +{{ categoryValue.length - 3 }} more
                      </span>
                                        </div>

                                        <div v-if="termValue.length" class="preview-tags mb-2">
                                            <span class="text-caption text-medium-emphasis">Tags: </span>
                                            <v-chip
                                                v-for="tag in termValue.slice(0, 3)"
                                                :key="tag.id || tag"
                                                variant="tonal"
                                                color="secondary"
                                                size="x-small"
                                                class="mr-1"
                                            >
                                                {{ tag.title || tag }}
                                            </v-chip>
                                            <span v-if="termValue.length > 3" class="text-caption">
                        +{{ termValue.length - 3 }} more
                      </span>
                                        </div>
                                    </div>

                                    <div class="preview-stats">
                                        <v-row dense>
                                            <v-col cols="6">
                                                <div class="text-caption text-medium-emphasis">Content</div>
                                                <div class="text-body-2">{{ contentStats.status }}</div>
                                            </v-col>
                                            <v-col cols="6">
                                                <div class="text-caption text-medium-emphasis">Readiness</div>
                                                <v-progress-linear
                                                    :model-value="completionPercentage"
                                                    :color="completionPercentage < 50 ? 'warning' : 'success'"
                                                    height="6"
                                                    rounded
                                                />
                                            </v-col>
                                        </v-row>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-col>
            </v-row>

            <!-- Floating Action Button for Mobile -->
            <v-fab
                v-if="$vuetify.display.mobile"
                location="bottom end"
                size="large"
                color="success"
                icon="mdi-content-save"
                @click="handleSubmit"
                :loading="creating"
                :disabled="!canCreate"
                app
            />
        </v-container>

        <!-- Confirmation Dialog -->
        <v-dialog v-model="showConfirmDialog" max-width="500">
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="warning" class="mr-2">mdi-alert</v-icon>
                    Confirm Action
                </v-card-title>
                <v-card-text>
                    {{ confirmMessage }}
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showConfirmDialog = false">Cancel</v-btn>
                    <v-btn color="primary" variant="elevated" @click="confirmAction">Confirm</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { useDisplay } from 'vuetify'
import Tiptap from '../common/tiptap/Tiptap.vue'
import { useAuthStore } from '@/store/authStore.js'
import { useRouter } from 'vue-router'

export default {
    name: 'WikiCreatePage',
    components: {
        Tiptap
    },
    setup() {
        // Reactive data
        const slug = ref('')
        const currentStep = ref(1)
        const showPreview = ref(true)
        const showTemplates = ref(false)
        const showConfirmDialog = ref(false)
        const confirmMessage = ref('')
        const confirmCallback = ref(null)
        const autoSaving = ref(false)

        const wikipage = ref({
            title: '',
            content: '',
            parent: null,
            terms: [],
            categories: []
        })

        const message = ref('')
        const createdSlug = ref('')
        const parents = reactive([])
        const termValue = ref([])
        const categories = ref([])
        const categoryValue = ref([])
        const pages = ref([])
        const searchTerm = ref('')
        const searchTax = ref('')
        const loading = ref(true)
        const creating = ref(false)
        const titleError = ref(false)
        const titleErrorMessage = ref('')

        const terms = ref([])
        const colors = ref(['green', 'purple', 'indigo', 'cyan', 'teal', 'orange'])
        const nonce = ref(1)

        const editing = reactive({
            errors: []
        })

        // Composables
        const { mobile } = useDisplay()
        const authStore = useAuthStore()
        const router = useRouter()

        // Computed properties
        const authenticated = computed(() => authStore.authenticated)
        const user = computed(() => authStore.user)

        const canCreate = computed(() => {
            return wikipage.value.title &&
                wikipage.value.title.trim().length > 0 &&
                wikipage.value.content &&
                wikipage.value.content.trim().length > 0
        })

        const contentStats = computed(() => {
            const content = wikipage.value.content || ''
            const words = content.split(/\s+/).filter(word => word.length > 0).length
            const chars = content.length
            const status = content ? `${words} words` : 'Empty'
            return { words, chars, status }
        })

        const completionPercentage = computed(() => {
            let score = 0
            if (wikipage.value.title) score += 30
            if (wikipage.value.content) score += 40
            if (categoryValue.value.length > 0) score += 15
            if (termValue.value.length > 0) score += 15
            return score
        })

        const popularCategories = computed(() => {
            return categories.value.filter(cat => cat.popular).slice(0, 5)
        })

        const suggestedTags = computed(() => {
            const content = (wikipage.value.title + ' ' + wikipage.value.content).toLowerCase()
            const suggestions = []

            // Simple keyword extraction for suggestions
            const keywords = ['tutorial', 'guide', 'api', 'documentation', 'howto', 'tips', 'best-practices']
            keywords.forEach(keyword => {
                if (content.includes(keyword) && !termValue.value.some(tag =>
                    (tag.title || tag).toLowerCase().includes(keyword)
                )) {
                    suggestions.push(keyword)
                }
            })

            return suggestions.slice(0, 3)
        })

        // Steps for stepper
        const steps = [
            { title: 'Basic Info', subtitle: 'Title and content', value: 1 },
            { title: 'Organization', subtitle: 'Categories and tags', value: 2 },
            { title: 'Review', subtitle: 'Final check', value: 3 }
        ]

        // Content templates
        const contentTemplates = ref([
            {
                name: 'Tutorial',
                content: `# Tutorial Title

## Overview
Brief description of what this tutorial covers.

## Prerequisites
- Item 1
- Item 2

## Steps
1. First step
2. Second step
3. Third step

## Conclusion
Summary and next steps.`
            },
            {
                name: 'API Reference',
                content: `# API Reference

## Endpoint
\`\`\`
GET /api/endpoint
\`\`\`

## Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| param1    | string | Description |

## Response
\`\`\`json
{
  "key": "value"
}
\`\`\``
            },
            {
                name: 'FAQ',
                content: `# Frequently Asked Questions

## Question 1?
Answer to the first question.

## Question 2?
Answer to the second question.

## Question 3?
Answer to the third question.`
            }
        ])

        // Methods
        const generateSlug = (title) => {
            return title
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-')
        }

        const onTitleChange = () => {
            titleError.value = false
            titleErrorMessage.value = ''
        }

        const validateTitle = () => {
            if (!wikipage.value.title || wikipage.value.title.trim().length === 0) {
                titleError.value = true
                titleErrorMessage.value = 'Page title is required'
                return false
            }
            if (wikipage.value.title.length > 100) {
                titleError.value = true
                titleErrorMessage.value = 'Title must be less than 100 characters'
                return false
            }
            return true
        }

        const onContentChange = (content) => {
            wikipage.value.content = content
        }

        const insertTemplate = (type) => {
            let template = ''
            switch (type) {
                case 'heading':
                    template = '\n## New Heading\n'
                    break
                case 'list':
                    template = '\n- Item 1\n- Item 2\n- Item 3\n'
                    break
                case 'table':
                    template = '\n| Column 1 | Column 2 | Column 3 |\n|----------|----------|----------|\n| Row 1    | Data     | Data     |\n| Row 2    | Data     | Data     |\n'
                    break
                case 'link':
                    template = '[Link text](https://example.com)'
                    break
            }
            wikipage.value.content += template
        }

        const insertContentTemplate = (template) => {
            wikipage.value.content = template.content
            showTemplates.value = false
        }

        const focusTitle = () => {
            nextTick(() => {
                const titleField = document.querySelector('.title-field input')
                if (titleField) titleField.focus()
            })
        }

        const focusContent = () => {
            nextTick(() => {
                const contentEditor = document.querySelector('#text-content')
                if (contentEditor) contentEditor.focus()
            })
        }

        const clearForm = () => {
            confirmMessage.value = 'Are you sure you want to clear all content? This action cannot be undone.'
            confirmCallback.value = () => {
                wikipage.value = {
                    title: '',
                    content: '',
                    parent: null,
                    terms: [],
                    categories: []
                }
                termValue.value = []
                categoryValue.value = []
                currentStep.value = 1
                showConfirmDialog.value = false
            }
            showConfirmDialog.value = true
        }

        const saveAsDraft = () => {
            // Implementation for saving as draft
            console.log('Saving as draft...')
        }

        const autoSave = async () => {
            if (!wikipage.value.title && !wikipage.value.content) return

            autoSaving.value = true
            try {
                // Auto-save implementation would go here
                await new Promise(resolve => setTimeout(resolve, 1000)) // Simulate API call
                console.log('Auto-saved successfully')
            } catch (error) {
                console.error('Auto-save failed:', error)
            } finally {
                autoSaving.value = false
            }
        }

        const addPopularCategory = (category) => {
            if (!categoryValue.value.find(cat => (cat.id || cat) === (category.id || category))) {
                categoryValue.value.push(category)
            }
        }

        const addSuggestedTag = (tag) => {
            if (!termValue.value.find(t => (t.title || t) === tag)) {
                termValue.value.push({ title: tag, color: colors.value[Math.floor(Math.random() * colors.value.length)] })
            }
        }

        const createAnother = () => {
            clearForm()
            message.value = ''
            createdSlug.value = ''
        }

        const confirmAction = () => {
            if (confirmCallback.value) {
                confirmCallback.value()
            }
        }

        const handleSubmit = () => {
            if (!validateTitle()) {
                currentStep.value = 1
                return
            }

            if (!wikipage.value.content || wikipage.value.content.trim().length === 0) {
                editing.errors = ['Page content is required']
                return
            }

            store()
        }

        // API Methods
        const getWikiPage = async () => {
            loading.value = true
            try {
                const response = await axios.get(`/api/wiki/${slug.value}`)
                wikipage.value = response.data.page
                parents.value = response.data.parents
                categoryValue.value = response.data.terms || []
            } catch (error) {
                if (error.response?.status === 404) {
                    wikipage.value = error.response.data.page || { title: '', content: '' }
                    parents.value = error.response.data.parents || []
                    categoryValue.value = error.response.data.terms || []
                }
                if (error.response?.status === 401) {
                    router.push('/auth/signin')
                }
            } finally {
                loading.value = false
            }
        }

        const getCategories = async () => {
            try {
                const response = await axios.get(`/api/tag/terms/wiki`)
                categories.value = response.data.terms.map(term => ({
                    ...term,
                    popular: Math.random() > 0.7 // Simulate popular categories
                }))
            } catch (error) {
                console.error('Failed to load categories:', error)
            }
        }

        const getTerms = async () => {
            try {
                const response = await axios.get(`/api/tag/terms/tags`)
                terms.value = response.data.terms.map(x => ({
                    title: x.title,
                    color: colors.value[Math.floor(Math.random() * colors.value.length)]
                }))
            } catch (error) {
                console.error('Failed to load terms:', error)
            }
        }

        const getPages = async () => {
            try {
                const response = await axios.get(`/api/wiki-pages`)
                pages.value = response.data
            } catch (error) {
                console.error('Failed to load pages:', error)
            }
        }

        const store = async () => {
            editing.errors = []
            creating.value = true

            wikipage.value.terms = termValue.value
            wikipage.value.categories = categoryValue.value

            try {
                const response = await axios.post(`/api/wiki`, wikipage.value)
                createdSlug.value = response.data.slug || slug.value

                // Reset form
                wikipage.value = { title: '', content: '', parent: null, terms: [], categories: [] }
                termValue.value = []
                categoryValue.value = []

                message.value = "Wiki page created successfully!"
                currentStep.value = 3

                setTimeout(() => {
                    message.value = ''
                }, 5000)

            } catch (error) {
                if (error.response?.status === 422) {
                    editing.errors = error.response.data.errors || [error.response.data.message || 'Validation failed']
                } else {
                    editing.errors = ['An error occurred while creating the page. Please try again.']
                }
            } finally {
                creating.value = false
            }
        }

        // Watchers
        watch(termValue, (val, prev) => {
            if (val.length === prev.length) return

            termValue.value = val.map(v => {
                if (typeof v === 'string') {
                    v = {
                        title: v,
                        color: colors.value[nonce.value % colors.value.length],
                    }
                    terms.value.push(v)
                    nonce.value++
                }
                return v
            })
        })

        // Auto-save functionality
        watch([() => wikipage.value.title, () => wikipage.value.content], () => {
            // Debounced auto-save could be implemented here
        })

        // Lifecycle
        onMounted(() => {
            if (router.currentRoute.value.params.slug) {
                slug.value = router.currentRoute.value.params.slug
                getWikiPage()
            }
            getCategories()
            getTerms()
            getPages()

            // Auto-save every 2 minutes
            setInterval(() => {
                if (wikipage.value.title || wikipage.value.content) {
                    autoSave()
                }
            }, 120000)
        })

        return {
            // Reactive data
            slug,
            currentStep,
            showPreview,
            showTemplates,
            showConfirmDialog,
            confirmMessage,
            autoSaving,
            wikipage,
            message,
            createdSlug,
            parents,
            termValue,
            categories,
            categoryValue,
            pages,
            searchTerm,
            searchTax,
            loading,
            creating,
            titleError,
            titleErrorMessage,
            terms,
            colors,
            nonce,
            editing,

            // Computed
            authenticated,
            user,
            canCreate,
            contentStats,
            completionPercentage,
            popularCategories,
            suggestedTags,
            steps,
            contentTemplates,

            // Methods
            generateSlug,
            onTitleChange,
            validateTitle,
            onContentChange,
            insertTemplate,
            insertContentTemplate,
            focusTitle,
            focusContent,
            clearForm,
            saveAsDraft,
            autoSave,
            addPopularCategory,
            addSuggestedTag,
            createAnother,
            confirmAction,
            handleSubmit,
            getWikiPage,
            getCategories,
            getTerms,
            getPages,
            store
        }
    }
}

</script>

<style scoped>
.wiki-create-container {
    min-height: 100vh;
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
}

.editor-card,
.quick-actions-card,
.settings-card,
.categories-card,
.tags-card,
.preview-card {
    border: 1px solid rgba(var(--v-border-color), 0.2);
    backdrop-filter: blur(10px);
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
    transition: all 0.3s ease;
}
</style>
