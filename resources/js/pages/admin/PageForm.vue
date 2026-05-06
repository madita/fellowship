<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Tiptap from '../../components/common/tiptap/Tiptap.vue';

const route = useRoute();
const { t } = useI18n();

// Reactive state
const loading = ref(true);
const dataReady = ref(false);
const addCategory = ref(false);
const page = ref({ title: '', content: '', parent: null, taxonomy: [], terms: [], categories: [] });
const pages = ref([]);
const endpoint = '/api/datatable/pages';
const form = ref('create');
const id = ref(null);
const message = ref('');
const searchTax = ref(null);
const searchTerm = ref(null);
const terms = ref([]);
const taxonomy = ref(null);
const taxonomyValue = ref('category');
const termValue = ref([]);
const newCategory = ref('');
const categories = ref([]);
const categoryValue = ref([]);
const parents = ref([]);
const parentValue = ref(null);
const colors = ['green', 'purple', 'indigo', 'cyan', 'teal', 'orange'];
let nonce = 1;

const rules = {
    required: value => !!value || t('validation.required'),
};

// Computed
const isDisabled = computed(() => {
    return id.value > 0 && categoryValue.value.length > 0;
});

// Watch
watch(termValue, (val, prev) => {
    if (!prev || val.length === prev.length) return;

    termValue.value = val.map(v => {
        if (typeof v === 'string') {
            v = {
                title: v,
                color: colors[nonce - 1],
            };
            terms.value.push(v);
            nonce++;
        }
        return v;
    });
});

// Methods
const getPage = () => {
    loading.value = true;
    return axios.get(`/api/pages/${id.value}/edit`).then((response) => {
        page.value = response.data.page;
        page.value.parent = response.data.parent;

        const taxonomies = { ...(response.data.taxonomies || {}) };

        if (taxonomies.tags) {
            termValue.value = taxonomies.tags;
            delete taxonomies.tags;
        }

        const remainingKeys = Object.keys(taxonomies);
        if (remainingKeys.length > 0) {
            taxonomyValue.value = remainingKeys[0];
            categoryValue.value = taxonomies[remainingKeys[0]];
        }

        getCategories(getTaxonomyName());
        loading.value = false;
    });
};

const getPages = () => {
    return axios.get(`/api/datatable/pages?page=1&itemsPerPage=100000&pageStart=-1&pageStop=100000000&pageCount=-1&itemsLength=-1`).then((response) => {
        pages.value = response.data.data.records.data;
        loading.value = false;
    });
};

const getTaxonomy = () => {
    loading.value = true;
    return axios.get(`/api/tag/taxonomies`).then((response) => {
        taxonomy.value = response.data;
        loading.value = false;
    });
};

const getTerms = () => {
    loading.value = true;
    return axios.get(`/api/tag/terms/tags`).then((response) => {
        terms.value = response.data.terms.map(x => x.title);
        loading.value = false;
    });
};

const getTaxonomyName = () => {
    const val = taxonomyValue.value;
    if (typeof val === 'object' && val !== null) return val.taxonomy;
    return val || 'category';
};

const setTaxonomy = () => {
    getCategories(getTaxonomyName());
};

const getCategories = (tax) => {
    loading.value = true;
    return axios.get(`/api/tag/terms/${tax}`).then((response) => {
        categories.value = response.data.terms;
        parents.value = response.data.terms;
        loading.value = false;
    });
};

const saveCategory = () => {
    const taxName = getTaxonomyName();
    let data = { term: newCategory.value, taxonomy: taxName, parent: parentValue.value };
    axios.post(`/api/tag/terms`, data).then(() => {
        getCategories(taxName);
        categoryValue.value.push(newCategory.value);
        newCategory.value = '';
        addCategory.value = false;
    });
};

const save = () => {
    if (form.value === 'edit') {
        update();
    } else {
        store();
    }
};

const update = () => {
    page.value.terms = termValue.value;
    page.value.taxonomy = getTaxonomyName();
    page.value.categories = categoryValue.value.map(x => x.title ?? x);

    axios.patch(`${endpoint}/${id.value}`, page.value).then(() => {
        message.value = t('pageForm.pageUpdated');
    }).catch((error) => {
        if (error.response?.status === 422) {
            console.error('Validation errors:', error.response.data);
        }
    });
};

const store = () => {
    page.value.terms = termValue.value;
    page.value.taxonomy = getTaxonomyName();
    page.value.categories = categoryValue.value.map(x => x.title ?? x);

    axios.post(`${endpoint}`, page.value).then(() => {
        page.value = { title: '', content: '' };
        message.value = t('pageForm.pageSaved');
    }).catch((error) => {
        if (error.response?.status === 422) {
            console.error('Validation errors:', error.response.data);
        }
    });
};

// Init (equivalent to created())
if (route.params.form) {
    form.value = route.params.form;
}

if (route.params.id) {
    id.value = route.params.id;
}

Promise.all([
    route.params.id ? getPage() : getCategories('category'),
    getTaxonomy(),
    getTerms(),
    getPages(),
]).finally(() => {
    dataReady.value = true;
    loading.value = false;
});
</script>

<template>
    <div class="flex-grow-1">
        <v-container>
            <v-row>
                <v-col cols="8">
                    <v-alert v-if="message" type="success">
                        {{ message }}
                    </v-alert>

                    <v-text-field
                        :label="$t('common.title')"
                        v-model="page.title"
                    />

                    <tiptap v-model="page.content" :value="page.content" id="text-content" name="content" />
                </v-col>
                <v-col cols="4" v-if="dataReady">
                    <!-- Parent Page -->
                    <v-card class="mb-4" elevation="1" rounded="lg">
                        <v-card-title class="text-subtitle-1">
                            <v-icon class="mr-2" color="info">mdi-file-tree</v-icon>
                            {{ $t('common.pages') }}
                        </v-card-title>
                        <v-card-text>
                            <v-select
                                v-model="page.parent"
                                :items="pages"
                                :label="$t('common.pages')"
                                variant="outlined"
                                density="compact"
                                prepend-inner-icon="mdi-file-tree"
                                item-title="title"
                                item-value="id"
                                return-object
                                clearable
                                :hint="$t('pageForm.parentPageHint')"
                                persistent-hint
                            >
                                <template #selection="{ item }">
                                    <div class="d-flex align-center">
                                        <v-icon size="16" class="mr-2">mdi-file-document</v-icon>
                                        {{ item.raw.title }} ({{ item.raw.slug }})
                                    </div>
                                </template>
                                <template #item="{ item, props }">
                                    <v-list-item v-bind="props">
                                        <template #prepend>
                                            <v-icon>mdi-file-document</v-icon>
                                        </template>
                                        <v-list-item-title>{{ item.raw.title }}</v-list-item-title>
                                        <v-list-item-subtitle>{{ item.raw.slug }}</v-list-item-subtitle>
                                    </v-list-item>
                                </template>
                            </v-select>
                        </v-card-text>
                    </v-card>

                    <!-- Taxonomy -->
                    <v-card class="mb-4" elevation="1" rounded="lg">
                        <v-card-title class="text-subtitle-1">
                            <v-icon class="mr-2" color="secondary">mdi-shape-outline</v-icon>
                            {{ $t('pageForm.taxonomy') }}
                        </v-card-title>
                        <v-card-text>
                            <v-combobox
                                v-model="taxonomyValue"
                                :items="taxonomy"
                                item-title="taxonomy"
                                @update:model-value="setTaxonomy"
                                v-model:search="searchTax"
                                hide-selected
                                :disabled="isDisabled"
                                :label="$t('pageForm.taxonomy')"
                                variant="outlined"
                                density="compact"
                                prepend-inner-icon="mdi-shape-outline"
                                chips
                                persistent-hint
                            />
                        </v-card-text>
                    </v-card>

                    <!-- Categories -->
                    <v-card class="mb-4" elevation="1" rounded="lg">
                        <v-card-title class="text-subtitle-1">
                            <v-icon class="mr-2" color="primary">mdi-folder-outline</v-icon>
                            {{ $t('pageForm.category') }}
                            <v-spacer />
                            <v-chip size="small" color="primary" variant="tonal">
                                {{ categoryValue.length }}
                            </v-chip>
                        </v-card-title>
                        <v-card-text>
                            <v-combobox
                                v-model="categoryValue"
                                :items="categories"
                                item-title="title"
                                item-value="id"
                                :label="$t('pageForm.category')"
                                variant="outlined"
                                density="compact"
                                multiple
                                chips
                                closable-chips
                                clearable
                                prepend-inner-icon="mdi-folder-plus"
                                :placeholder="$t('pageForm.typeToSearchCategories')"
                                :hint="$t('pageForm.categoriesHint')"
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

                            <!-- Add New Category -->
                            <div class="mt-3">
                                <v-btn
                                    variant="text"
                                    size="small"
                                    :prepend-icon="addCategory ? 'mdi-chevron-up' : 'mdi-plus'"
                                    @click="addCategory = !addCategory"
                                >
                                    {{ $t('pageForm.addNewCategory') }}
                                </v-btn>

                                <v-expand-transition>
                                    <div v-if="addCategory" class="mt-2">
                                        <v-text-field
                                            v-model="newCategory"
                                            :label="$t('pageForm.newCategoryName')"
                                            variant="outlined"
                                            density="compact"
                                            :rules="[rules.required]"
                                            prepend-inner-icon="mdi-folder-plus-outline"
                                        />
                                        <v-combobox
                                            v-model="parentValue"
                                            :items="parents"
                                            item-title="title"
                                            :label="$t('pageForm.parentCategory')"
                                            variant="outlined"
                                            density="compact"
                                            chips
                                            closable-chips
                                            clearable
                                            prepend-inner-icon="mdi-file-tree"
                                        />
                                        <v-btn
                                            color="primary"
                                            variant="elevated"
                                            size="small"
                                            prepend-icon="mdi-plus"
                                            @click="saveCategory"
                                        >
                                            {{ $t('pageForm.addNewCategoryBtn') }}
                                        </v-btn>
                                    </div>
                                </v-expand-transition>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- Tags -->
                    <v-card class="mb-4" elevation="1" rounded="lg">
                        <v-card-title class="text-subtitle-1">
                            <v-icon class="mr-2" color="secondary">mdi-tag-outline</v-icon>
                            {{ $t('pageForm.terms') }}
                            <v-spacer />
                            <v-chip size="small" color="secondary" variant="tonal">
                                {{ termValue.length }}
                            </v-chip>
                        </v-card-title>
                        <v-card-text>
                            <v-combobox
                                v-model="termValue"
                                :items="terms"
                                item-title="title"
                                v-model:search="searchTerm"
                                hide-selected
                                :label="$t('pageForm.terms')"
                                variant="outlined"
                                density="compact"
                                multiple
                                chips
                                closable-chips
                                clearable
                                prepend-inner-icon="mdi-tag-plus"
                                :placeholder="$t('pageForm.typeToSearchTags')"
                                :hint="$t('pageForm.tagsHint')"
                                persistent-hint
                            >
                                <template #no-data>
                                    <v-list-item>
                                        <v-list-item-title>
                                            {{ $t('pageForm.noResultsMatching', { term: searchTerm }) }}
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
                        </v-card-text>
                    </v-card>

                    <!-- Settings & Actions -->
                    <v-card class="mb-4" elevation="1" rounded="lg">
                        <v-card-title class="text-subtitle-1">
                            <v-icon class="mr-2" color="success">mdi-cog-outline</v-icon>
                            {{ $t('pageForm.settings') }}
                        </v-card-title>
                        <v-card-text>
                            <v-checkbox
                                v-model="page.published"
                                :label="$t('pageForm.published')"
                                density="compact"
                                hide-details
                            />
                            <v-checkbox
                                v-model="page.sign_in_only"
                                :label="$t('pageForm.signInOnly')"
                                density="compact"
                                hide-details
                            />
                        </v-card-text>
                        <v-card-actions class="pa-4 pt-0">
                            <v-btn
                                color="primary"
                                variant="elevated"
                                block
                                :prepend-icon="form === 'edit' ? 'mdi-content-save' : 'mdi-plus'"
                                @click="save"
                            >
                                {{ form === 'edit' ? $t('common.update') : $t('common.create') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>
