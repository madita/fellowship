<template>
    <div class="wiki-edit-container">
        <v-container class="py-6">
            <!-- Header Section -->
            <div class="edit-header mb-6">
                <v-row align="center">
                    <v-col cols="12" md="8">
                        <div class="d-flex align-center mb-2">
                            <v-icon color="primary" size="28" class="mr-3">mdi-pencil</v-icon>
                            <h1 class="edit-title text-h4 font-weight-bold">
                                {{ wikipage.title ? `Editing: ${wikipage.title}` : 'Create New Wiki Page' }}
                            </h1>
                        </div>
                        <p class="text-subtitle-1 text-medium-emphasis">
                            Make your changes and save to update the wiki page
                        </p>
                    </v-col>
                    <v-col cols="12" md="4" class="text-right">
                        <div class="header-actions">
                            <v-btn
                                v-if="authenticated"
                                variant="outlined"
                                color="secondary"
                                prepend-icon="mdi-arrow-left"
                                :to="`/wiki/${slug}`"
                                class="mr-2"
                            >
                                Cancel
                            </v-btn>
                            <v-btn
                                color="primary"
                                variant="elevated"
                                prepend-icon="mdi-content-save"
                                @click="update"
                                :loading="saving"
                                class="save-btn"
                            >
                                Save Changes
                            </v-btn>
                        </div>
                    </v-col>
                </v-row>

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
                </v-alert>
            </div>

            <!-- Main Content -->
            <v-row>
                <!-- Editor Section -->
                <v-col cols="12" lg="8">
                    <v-card class="editor-card" elevation="2" rounded="lg">
                        <v-card-title class="editor-card-title">
                            <v-icon class="mr-2" color="primary">mdi-file-document-edit</v-icon>
                            Content Editor
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
                                />
                            </div>

                            <!-- Content Editor -->
                            <div class="content-section">
                                <div class="content-label mb-3">
                                    <v-icon class="mr-2" size="20" color="primary">mdi-text</v-icon>
                                    <span class="text-subtitle-1 font-weight-medium">Page Content</span>
                                </div>
                                <div class="editor-wrapper">
                                    <tiptap
                                        v-model:modelValue="wikipage.content"
                                        :value="wikipage.content"
                                        id="text-content"
                                        name="content"
                                        type="full"
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
                                Page Settings
                            </v-card-title>

                            <v-card-text class="pa-4">
                                <!-- Parent Page -->
                                <div class="setting-section mb-4">
                                    <v-select
                                        v-model="wikiPageParent"
                                        :items="pages"
                                        item-title="title"
                                        label="Parent Page"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-file-tree"
                                        return-object
                                        clearable
                                        no-data-text="No parent pages available"
                                    >
                                        <template v-slot:selection="{ item }">
                                            <div class="d-flex align-center">
                                                <v-icon size="16" class="mr-2">mdi-file-document</v-icon>
                                                {{ item?.title }}
                                                <span class="text-caption text-medium-emphasis ml-1">({{
                                                        item?.slug
                                                    }})</span>
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
                                                    @click="addCategory = false"
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

                        <!-- Preview Card -->
                        <v-card class="preview-card" elevation="1" rounded="lg">
                            <v-card-title class="preview-title">
                                <v-icon class="mr-2" color="success">mdi-eye-outline</v-icon>
                                Quick Preview
                            </v-card-title>

                            <v-card-text class="pa-4">
                                <div class="preview-content">
                                    <h4 class="preview-page-title mb-2">{{ wikipage.title || 'Untitled Page' }}</h4>
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
                            variant="outlined"
                            color="secondary"
                            prepend-icon="mdi-arrow-left"
                            :to="`/wiki/${slug}`"
                            class="mr-3"
                        >
                            Cancel
                        </v-btn>
                        <v-btn
                            color="primary"
                            variant="elevated"
                            prepend-icon="mdi-content-save"
                            @click="update"
                            :loading="saving"
                            size="large"
                            class="save-btn-fixed"
                        >
                            Save Changes
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
    name: 'WikiEditPage',
    components: {
        Tiptap
    },
    setup(props, { emit }) {
        let slug = ref(null);
        let wikipage = ref({
            title: '',
            content: '',
            parent: null,
            terms: [],
            categories: []
        });
        let message = ref('');
        let parents = reactive([]);
        let taxonomyValue = ref([]);
        let taxonomies = ref([]);
        let termValue = ref([]);
        let categories = ref([]);
        let categoryValue = ref([]);
        let pages = ref([]);
        let wikiPageParent = ref();
        let searchTerm = ref('');
        let searchTax = ref('');
        let loading = ref(true);
        let saving = ref(false);
        let savingCategory = ref(false);

        let terms = ref([]);
        let colors = ref(['green', 'purple', 'indigo', 'cyan', 'teal', 'orange']);
        let nonce = ref(1);
        let addCategory = ref(false);
        let newCategory = ref('');
        let parentValue = ref(null);

        const authStore = useAuthStore();
        const authenticated = computed(() => authStore.authenticated);
        const user = computed(() => authStore.user);
        const router = useRouter();

        const rules = {
            required: value => !!value || 'This field is required.',
        };

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
                wikiPageParent.value = response.data.parent;
                parents.value = response.data.parents;
                taxonomies.value = response.data.terms;
                termValue.value = response.data.tags || [];
                categoryValue.value = taxonomies.value || [];
                loading.value = false;
            }).catch((error) => {
                loading.value = false;
                if (error.response?.status === 404) {
                    // Handle 404 - maybe initialize empty page
                }
                if (error.response?.status === 401) {
                    router.push('/auth/signin');
                }
            });
        }

        function getCategories() {
            return axios.get(`/api/tag/terms/wiki`).then((response) => {
                categories.value = parents.value = response.data.terms;
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

        // function getTags() {
        //     return axios.get(`/api/tag/terms/tags`).then((response) => {
        //         terms.value = response.data.terms;
        //     });
        // }

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

        function update() {
            if (!wikipage.value.title) {
                message.value = "Please enter a page title";
                return;
            }

            saving.value = true;
            wikipage.value.terms = termValue.value;
            wikipage.value.taxonomy = taxonomyValue.value;
            wikipage.value.categories = categoryValue.value;

            axios.patch(`/api/wiki/${slug.value}`, wikipage.value).then(() => {
                message.value = "Wiki page updated successfully!";
                saving.value = false;
                // Auto-hide message after 3 seconds
                setTimeout(() => {
                    message.value = '';
                }, 3000);
            }).catch((error) => {
                saving.value = false;
                if (error.response?.status === 422) {
                    message.value = "Please check your input and try again";
                }
            });
        }

        return {
            slug,
            wikipage,
            wikiPageParent,
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
            searchTerm,
            searchTax,
            pages,
            loading,
            saving,
            savingCategory,
            categories,
            rules,
            getWikiPage,
            getCategories,
            getTerms,
            saveCategory,
            update
        };
    }
};
</script>

<style scoped>
.wiki-edit-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding-bottom: 100px; /* Space for fixed bottom actions */
}

.edit-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 51, 234, 0.05) 100%);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.edit-title {
    background: linear-gradient(135deg, #3b82f6 0%, #9333ea 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}

.header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.save-btn {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3) !important;
}

.editor-card,
.settings-card,
.categories-card,
.tags-card,
.preview-card {
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.9) !important;
}

.editor-card-title,
.settings-title,
.categories-title,
.tags-title,
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

.preview-content {
    padding: 12px;
    background: rgba(var(--v-theme-surface-variant), 0.3);
    border-radius: 8px;
    border: 1px solid rgba(var(--v-border-color), 0.5);
}

.preview-page-title {
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
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

.save-btn-fixed {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3) !important;
}

/* Responsive design */
@media (max-width: 1024px) {
    .sidebar-content {
        position: static;
        max-height: none;
        margin-top: 24px;
    }

    .bottom-actions {
        position: static;
        background: transparent;
        backdrop-filter: none;
        border-top: none;
        margin-top: 32px;
    }

    .wiki-edit-container {
        padding-bottom: 0;
    }
}

@media (max-width: 768px) {
    .edit-header {
        padding: 16px;
    }

    .edit-title {
        font-size: 1.5rem !important;
    }

    .header-actions {
        justify-content: center;
        margin-top: 16px;
    }

    .header-actions .v-btn {
        width: 100%;
        margin: 0 0 8px 0 !important;
    }
}

/* Animation for expand transition */
.v-expand-transition-enter-active,
.v-expand-transition-leave-active {
    transition: all 0.3s ease;
}

.v-expand-transition-enter-from,
.v-expand-transition-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
