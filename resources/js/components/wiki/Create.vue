<template>
    <div class="wiki-create-container">
        <v-container class="py-6">
            <!-- Header Section -->
            <div class="create-header mb-6">
                <v-row align="center">
                    <v-col cols="12" md="8">
                        <div class="d-flex align-center mb-2">
                            <v-icon color="success" size="28" class="mr-3">mdi-plus-circle</v-icon>
                            <h1 class="create-title text-h4 font-weight-bold">
                                Create New Wiki Page
                            </h1>
                        </div>
                        <p class="text-subtitle-1 text-medium-emphasis">
                            {{ slug ? `Creating page: "${slug}"` : 'Start building your knowledge base with a new wiki page' }}
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
                                @click="store"
                                :loading="creating"
                                :disabled="!canCreate"
                                class="create-btn"
                            >
                                Create Page
                            </v-btn>
                        </div>
                    </v-col>
                </v-row>

                <!-- Page Exists Notice -->
                <v-alert
                    v-if="slug.length > 0"
                    type="info"
                    variant="tonal"
                    class="mt-4"
                    prominent
                >
                    <template v-slot:prepend>
                        <v-icon>mdi-information</v-icon>
                    </template>
                    <div class="alert-content">
                        <h4 class="alert-title">Page doesn't exist yet</h4>
                        <p class="mb-0">The page "{{ slug }}" doesn't exist. Fill out the form below to create it and start contributing to the knowledge base.</p>
                    </div>
                </v-alert>

                <!-- Success Message -->
                <v-alert
                    v-if="message"
                    type="success"
                    variant="tonal"
                    class="mt-4"
                    dismissible
                    @click:close="message = ''"
                >
                    <template v-slot:prepend>
                        <v-icon>mdi-check-circle</v-icon>
                    </template>
                    {{ message }}
                    <template v-slot:append>
                        <v-btn
                            v-if="createdSlug"
                            color="success"
                            variant="text"
                            size="small"
                            :to="`/wiki/${createdSlug}`"
                            class="ml-2"
                        >
                            View Page
                        </v-btn>
                    </template>
                </v-alert>

                <!-- Error Messages -->
                <v-alert
                    v-if="editing.errors.length > 0"
                    type="error"
                    variant="tonal"
                    class="mt-4"
                    dismissible
                    @click:close="editing.errors = []"
                >
                    <template v-slot:prepend>
                        <v-icon>mdi-alert-circle</v-icon>
                    </template>
                    <div v-if="typeof editing.errors === 'string'">
                        {{ editing.errors }}
                    </div>
                    <ul v-else class="mb-0">
                        <li v-for="error in editing.errors" :key="error">{{ error }}</li>
                    </ul>
                </v-alert>
            </div>

            <!-- Main Content -->
            <v-row>
                <!-- Editor Section -->
                <v-col cols="12" lg="8">
                    <v-card class="editor-card" elevation="2" rounded="lg">
                        <v-card-title class="editor-card-title">
                            <v-icon class="mr-2" color="success">mdi-file-document-plus</v-icon>
                            Page Content
                        </v-card-title>

                        <v-card-text class="pa-6">
                            <!-- Title Field -->
                            <div class="title-section mb-6">
                                <v-text-field
                                    v-model="wikipage.title"
                                    label="Page Title"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-format-title"
                                    placeholder="Enter a descriptive title for your wiki page"
                                    class="title-field"
                                    :rules="[rules.required]"
                                    :error="titleError"
                                    :error-messages="titleError ? 'Page title is required' : ''"
                                    @input="titleError = false"
                                />
                                <div class="text-caption text-medium-emphasis mt-1">
                                    This will be the main heading and URL slug for your page
                                </div>
                            </div>

                            <!-- Content Editor -->
                            <div class="content-section">
                                <div class="content-label mb-3">
                                    <v-icon class="mr-2" size="20" color="success">mdi-text</v-icon>
                                    <span class="text-subtitle-1 font-weight-medium">Page Content</span>
                                    <span class="text-caption text-medium-emphasis ml-2">(Supports rich text, tables, links, and more)</span>
                                </div>
                                <div class="editor-wrapper">
                                    <tiptap
                                        v-model:modelValue="wikipage.content"
                                        :value="wikipage.content"
                                        id="text-content"
                                        name="content"
                                        type="full"
                                        placeholder="Start writing your wiki page content here..."
                                    />
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Sidebar -->
                <v-col cols="12" lg="4">
                    <div class="sidebar-content">
                        <!-- Page Settings -->
                        <v-card class="settings-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="settings-title">
                                <v-icon class="mr-2" color="info">mdi-cog</v-icon>
                                Parent
                            </v-card-title>

                            <v-card-text class="pa-4">
                                <!-- Parent Page -->
                                <div class="setting-section mb-4">
                                    <v-select
                                        v-model="wikipage.parent"
                                        :items="pages"
                                        label="Parent Page"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-file-tree"
                                        return-object
                                        clearable
                                        no-data-text="No parent pages available"
                                        hint="Choose a parent page to organize your content"
                                        persistent-hint
                                    >
                                        <template v-slot:selection="{ item }">
                                            <div class="d-flex align-center">
                                                <v-icon size="16" class="mr-2">mdi-file-document</v-icon>
                                                {{ item?.title }}
                                                <span class="text-caption text-medium-emphasis ml-1">({{ item?.slug }})</span>
                                            </div>
                                        </template>
<!--                                        <template v-slot:item="{ props, item }">-->
<!--                                            <v-list-item v-bind="props">-->
<!--                                                <template v-slot:prepend>-->
<!--                                                    <v-icon>mdi-file-document</v-icon>-->
<!--                                                </template>-->
<!--                                                <v-list-item-title>{{ item?.title }}</v-list-item-title>-->
<!--                                                <v-list-item-subtitle>{{ item?.slug }}</v-list-item-subtitle>-->
<!--                                            </v-list-item>-->
<!--                                        </template>-->
                                    </v-select>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Categories -->
                        <v-card class="categories-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="categories-title">
                                <v-icon class="mr-2" color="primary">mdi-folder-outline</v-icon>
                                Categories
                            </v-card-title>

                            <v-card-text class="pa-4">
                                <v-combobox
                                    v-model="categoryValue"
                                    :items="categories"
                                    :search-input.sync="searchTax"
                                    item-title="title"
                                    label="Select categories"
                                    variant="outlined"
                                    density="compact"
                                    multiple
                                    chips
                                    deletable-chips
                                    clearable
                                    prepend-inner-icon="mdi-folder-plus"
                                    placeholder="Type to search or add categories"
                                    hint="Categories help organize and discover your content"
                                    persistent-hint
                                >
                                    <template v-slot:chip="{ props, item }">
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

                                <!-- Add New Category -->
                                <div class="add-category-section mt-3">
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        prepend-icon="mdi-plus"
                                        @click="addCategory = !addCategory"
                                        class="add-category-btn"
                                    >
                                        Add new category
                                    </v-btn>

                                    <v-expand-transition>
                                        <div v-if="addCategory" class="new-category-form mt-3 pa-3 rounded" style="background: rgba(var(--v-theme-surface-variant), 0.5);">
                                            <v-text-field
                                                v-model="newCategory"
                                                label="Category name"
                                                variant="outlined"
                                                density="compact"
                                                :rules="[rules.required]"
                                                class="mb-3"
                                                placeholder="Enter category name"
                                            />

                                            <v-combobox
                                                v-model="parentValue"
                                                :items="parents"
                                                item-title="title"
                                                label="Parent Category (optional)"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                                class="mb-3"
                                                placeholder="Choose parent category"
                                            />

                                            <div class="d-flex gap-2">
                                                <v-btn
                                                    color="primary"
                                                    variant="elevated"
                                                    size="small"
                                                    @click="saveCategory"
                                                    :loading="savingCategory"
                                                >
                                                    Add Category
                                                </v-btn>
                                                <v-btn
                                                    variant="outlined"
                                                    size="small"
                                                    @click="addCategory = false; newCategory = ''; parentValue = null;"
                                                >
                                                    Cancel
                                                </v-btn>
                                            </div>
                                        </div>
                                    </v-expand-transition>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Tags -->
                        <v-card class="tags-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="tags-title">
                                <v-icon class="mr-2" color="secondary">mdi-tag-outline</v-icon>
                                Tags
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
                                    hint="Tags make your content easier to find and relate to other pages"
                                    persistent-hint
                                >
                                    <template v-slot:no-data>
                                        <v-list-item>
                                            <v-list-item-title>
                                                No results matching "<strong>{{ searchTerm }}</strong>".
                                                Press <kbd class="kbd">Enter</kbd> to create a new tag
                                            </v-list-item-title>
                                        </v-list-item>
                                    </template>

                                    <template v-slot:chip="{ props, item }">
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

                                    <template v-slot:item="{ props, item }">
                                        <v-list-item v-bind="props">
                                            <template v-slot:prepend>
                                                <v-chip
                                                    :color="item.color || 'secondary'"
                                                    variant="tonal"
                                                    size="small"
                                                >
                                                    {{ item.title }}
                                                </v-chip>
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-combobox>
                            </v-card-text>
                        </v-card>

                        <!-- Quick Tips -->
                        <v-card class="tips-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="tips-title">
                                <v-icon class="mr-2" color="warning">mdi-lightbulb-outline</v-icon>
                                Quick Tips
                            </v-card-title>

                            <v-card-text class="pa-4">
                                <v-list density="compact" class="tips-list">
                                    <v-list-item>
                                        <template v-slot:prepend>
                                            <v-icon size="16" color="success">mdi-check</v-icon>
                                        </template>
                                        <v-list-item-title class="text-caption">Use descriptive titles for better discoverability</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item>
                                        <template v-slot:prepend>
                                            <v-icon size="16" color="success">mdi-check</v-icon>
                                        </template>
                                        <v-list-item-title class="text-caption">Add relevant categories and tags</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item>
                                        <template v-slot:prepend>
                                            <v-icon size="16" color="success">mdi-check</v-icon>
                                        </template>
                                        <v-list-item-title class="text-caption">Use headings to structure your content</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item>
                                        <template v-slot:prepend>
                                            <v-icon size="16" color="success">mdi-check</v-icon>
                                        </template>
                                        <v-list-item-title class="text-caption">Link to related pages using @ mentions</v-list-item-title>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>
                        </v-card>

                        <!-- Preview Card -->
                        <v-card class="preview-card" elevation="1" rounded="lg">
                            <v-card-title class="preview-title">
                                <v-icon class="mr-2" color="success">mdi-eye-outline</v-icon>
                                Quick Preview
                            </v-card-title>

                            <v-card-text class="pa-4">
                                <div class="preview-content">
                                    <h4 class="preview-page-title mb-2">
                                        {{ wikipage.title || 'Untitled Page' }}
                                        <v-chip v-if="!wikipage.title" color="warning" size="x-small" class="ml-2">
                                            Title Required
                                        </v-chip>
                                    </h4>
                                    <div class="preview-categories mb-2" v-if="categoryValue.length">
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
                                    <div class="preview-tags" v-if="termValue.length">
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
                                    <div class="text-caption text-medium-emphasis mt-2">
                                        Content: {{ wikipage.content ? 'Ready' : 'Empty' }}
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-col>
            </v-row>

            <!-- Fixed Bottom Actions -->
            <div class="bottom-actions">
                <v-container>
                    <div class="d-flex justify-end align-center">
                        <v-btn
                            v-if="authenticated && slug"
                            variant="outlined"
                            color="secondary"
                            prepend-icon="mdi-arrow-left"
                            :to="`/wiki/${slug}`"
                            class="mr-3"
                        >
                            Cancel
                        </v-btn>
                        <v-btn
                            color="success"
                            variant="elevated"
                            prepend-icon="mdi-content-save"
                            @click="store"
                            :loading="creating"
                            :disabled="!canCreate"
                            size="large"
                            class="create-btn-fixed"
                        >
                            Create Page
                        </v-btn>
                    </div>
                </v-container>
            </div>
        </v-container>
    </div>
</template>

<script>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import Tiptap from '../common/tiptap/Tiptap.vue';
import { useAuthStore } from '@/store/authStore.js';
import { useUserStore } from '@/store/userStore.js';
import { useRouter } from "vue-router";

export default {
    name: 'WikiCreatePage',
    components: {
        Tiptap
    },
    setup(props, { emit }) {
        let slug = ref('');
        let wikipage = ref({
            title: '',
            content: '',
            parent: null,
            terms: [],
            categories: []
        });
        let message = ref('');
        let createdSlug = ref('');
        let parents = reactive([]);
        let taxonomyValue = ref('wiki');
        let taxonomies = ref([]);
        let termValue = ref([]);
        let categories = ref([]);
        let categoryValue = ref([]);
        let pages = ref([]);
        let searchTerm = ref('');
        let searchTax = ref('');
        let loading = ref(true);
        let creating = ref(false);
        let savingCategory = ref(false);
        let titleError = ref(false);

        let terms = ref([]);
        let colors = ref(['green', 'purple', 'indigo', 'cyan', 'teal', 'orange']);
        let nonce = ref(1);
        let addCategory = ref(false);
        let newCategory = ref('');
        let parentValue = ref(null);

        const editing = reactive({
            id: null,
            form: {},
            errors: []
        });

        const authStore = useAuthStore();
        const authenticated = computed(() => authStore.authenticated);
        const user = computed(() => authStore.user);
        const router = useRouter();

        const rules = {
            required: value => !!value || 'This field is required.',
        };

        // Check if page can be created
        const canCreate = computed(() => {
            return wikipage.value.title && wikipage.value.title.trim().length > 0;
        });

        onMounted(() => {
            if (router.currentRoute.value.params.slug) {
                slug.value = router.currentRoute.value.params.slug;
                getWikiPage();
            }
            getCategories();
            getTerms();
            getPages();
        });

        watch(termValue, (val, prev) => {
            if (val.length === prev.length) return;

            termValue.value = val.map(v => {
                if (typeof v === 'string') {
                    v = {
                        title: v,
                        color: colors.value[nonce.value - 1],
                    };
                    terms.value.push(v);
                    nonce.value++;
                }
                return v;
            });
        });

        function getWikiPage() {
            loading.value = true;
            return axios.get(`/api/wiki/${slug.value}`).then((response) => {
                wikipage.value = response.data.page;
                parents.value = response.data.parents;
                taxonomies.value = response.data.terms;
                categoryValue.value = taxonomies.value || [];
                loading.value = false;
            }).catch((error) => {
                if (error.response?.status === 404) {
                    // Page doesn't exist - this is expected for create
                    wikipage.value = error.response.data.page || { title: '', content: '' };
                    parents.value = error.response.data.parents || [];
                    taxonomies.value = error.response.data.terms || [];
                    categoryValue.value = taxonomies.value || [];
                }
                if (error.response?.status === 401) {
                    router.push('/auth/signin');
                }
                loading.value = false;
            });
        }

        function getCategories() {
            return axios.get(`/api/tag/terms/wiki`).then((response) => {
                categories.value = response.data.terms;
            });
        }

        function getTerms() {
            return axios.get(`/api/tag/terms/tags`).then((response) => {
                terms.value = response.data.terms.map(x => ({
                    title: x.title,
                    color: colors.value[Math.floor(Math.random() * colors.value.length)]
                }));
            });
        }

        function getPages() {
            return axios.get(`/api/wiki-pages`).then((response) => {
                pages.value =  response.data;
            });

        }


        function saveCategory() {
            if (!newCategory.value) return;

            savingCategory.value = true;
            let data = {
                term: newCategory.value,
                taxonomy: taxonomyValue.value,
                parent: parentValue.value
            };

            axios.post(`/api/tag/terms`, data).then(() => {
                getCategories();
                categoryValue.value.push({ title: newCategory.value });
                newCategory.value = '';
                parentValue.value = null;
                addCategory.value = false;
                savingCategory.value = false;
            }).catch(() => {
                savingCategory.value = false;
            });
        }

        function store() {
            // Reset errors
            editing.errors = [];
            titleError.value = false;

            // Validate required fields
            if (!wikipage.value.title || wikipage.value.title.trim().length === 0) {
                titleError.value = true;
                editing.errors = ['Page title is required'];
                return;
            }

            creating.value = true;
            wikipage.value.terms = termValue.value;
            wikipage.value.taxonomy = taxonomyValue.value;
            wikipage.value.categories = categoryValue.value;

            axios.post(`/api/wiki`, wikipage.value).then((response) => {
                // Store the slug of the created page
                createdSlug.value = response.data.slug || slug.value;

                // Reset form
                wikipage.value = { title: '', content: '', parent: null, terms: [], categories: [] };
                termValue.value = [];
                categoryValue.value = [];

                message.value = "Wiki page created successfully!";
                creating.value = false;

                // Auto-hide success message after 5 seconds
                setTimeout(() => {
                    message.value = '';
                }, 5000);

            }).catch((error) => {
                creating.value = false;
                if (error.response?.status === 422) {
                    editing.errors = error.response.data.errors || error.response.data;
                } else {
                    editing.errors = ['An error occurred while creating the page. Please try again.'];
                }
            });
        }

        return {
            slug,
            wikipage,
            parents,
            taxonomies,
            termValue,
            categoryValue,
            terms,
            colors,
            nonce,
            authenticated,
            user,
            addCategory,
            newCategory,
            parentValue,
            message,
            createdSlug,
            searchTerm,
            searchTax,
            pages,
            loading,
            creating,
            savingCategory,
            titleError,
            categories,
            editing,
            rules,
            canCreate,
            getWikiPage,
            getCategories,
            saveCategory,
            store
        };
    }
};
</script>

<style scoped>
.wiki-create-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    padding-bottom: 100px; /* Space for fixed bottom actions */
}

.create-header {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid rgba(34, 197, 94, 0.1);
}

.create-title {
    background: linear-gradient(135deg, #22c55e 0%, #10b981 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}

.alert-content {
    color: rgb(var(--v-theme-on-surface));
}

.alert-title {
    font-weight: 600;
    margin-bottom: 8px;
    color: rgb(var(--v-theme-info));
}

.header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.create-btn {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 16px rgba(34, 197, 94, 0.3) !important;
}

.editor-card,
.settings-card,
.categories-card,
.tags-card,
.tips-card,
.preview-card {
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.9) !important;
}

.editor-card-title,
.settings-title,
.categories-title,
.tags-title,
.tips-title,
.preview-title {
    background: rgb(var(--v-theme-surface-variant));
    border-bottom: 1px solid rgb(var(--v-border-color));
    font-size: 1.1rem !important;
    font-weight: 600 !important;
    padding: 16px 20px !important;
}

.sidebar-content {
    position: sticky;
    top: 24px;
    max-height: calc(100vh - 48px);
    overflow-y: auto;
}

.title-field {
    background: rgba(255, 255, 255, 0.8);
    border-radius: 12px;
}

.editor-wrapper {
    border: 1px solid rgb(var(--v-border-color));
    border-radius: 12px;
    overflow: hidden;
    background: white;
}

.content-label {
    display: flex;
    align-items: center;
    color: rgb(var(--v-theme-on-surface));
}

.setting-section {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.3);
    padding-bottom: 16px;
}

.setting-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.add-category-btn {
    text-transform: none !important;
    color: rgb(var(--v-theme-primary)) !important;
}

.new-category-form {
    border: 1px solid rgba(var(--v-border-color), 0.5);
}

.kbd {
    background: rgb(var(--v-theme-surface-variant));
    border: 1px solid rgb(var(--v-border-color));
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 0.75rem;
    font-family: monospace;
}

.tips-list {
    background: transparent;
}

.preview-content {
    padding: 12px;
    background: rgba(var(--v-theme-surface-variant), 0.3);
    border-radius: 8px;
    border: 1px solid rgba(var(--v-border-color), 0.5);
}

.preview-page-title {
    color: rgb(var(--v-theme-success));
    font-weight: 600;
    display: flex;
    align-items: center;
}

.bottom-actions {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-top: 1px solid rgb(var(--v-border-color));
    padding: 16px 0;
    z-index: 1000;
}

.create-btn-fixed {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 16px rgba(34, 197, 94, 0.3) !important;
}

/* Success theme variations */
.create-btn:disabled {
    opacity: 0.6
}
</style>
