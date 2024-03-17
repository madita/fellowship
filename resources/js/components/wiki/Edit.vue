<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">
        <v-container class="mb-15">
            <template v-if="authenticated">
                <v-btn class="mx-1" :to="`/wiki/${slug}`">
                    Cancel
                </v-btn>
            </template>

            <v-row>
                <v-col
                    cols="8">
                    <v-alert v-if="message" type="success">
                        {{ message }}
                    </v-alert>

                    <v-text-field
                        label="Title"
                        v-model="wikipage.title"
                    ></v-text-field>

                    <!--                    <simple-editor v-model="page.body" :value="page.body" id="text-body" name="content"></simple-editor>-->
                    <tiptap v-model:modelValue="wikipage.content" :value="wikipage.content" id="text-content" name="content"/>

                </v-col>
                <v-col
                    cols="4">
                    <v-select
                        v-model="wikipage.parent"
                        :items="pages"
                        label="Pages"
                        persistent-hint
                        return-object
                        single-line
                    >
                        <template v-slot:selection="{ attrs, item, parent, selected }">
                            {{ item?.title }} ({{ item.slug }})
                        </template>
                        <template v-slot:item="{ index, item }">
                            {{ item?.title }} ({{ item.slug }})
                        </template>
                    </v-select>

                    <!--                    <v-combobox-->
                    <!--                        v-model="taxonomyValue"-->
                    <!--                        :items="taxonomies"-->
                    <!--                        item-text="taxonomy"-->
                    <!--                        @change="setTaxonomy"-->
                    <!--                        :search-input.sync="searchTax"-->
                    <!--                        hide-selected-->
                    <!--                        :disabled="isDisabled"-->
                    <!--                        label="Taxonomy"-->
                    <!--                        persistent-hint-->
                    <!--                        small-chips-->
                    <!--                    >-->
                    <!--                    </v-combobox>-->

                    <v-combobox
                        v-model="categoryValue"
                        :items="categories"
                        :search-input.sync="searchTax"
                        item-title="title"
                        label="Category"
                        multiple
                        small-chips
                        deletable-chips
                        clearable
                    ></v-combobox>

                    <a href="#" @click="addCategory=!addCategory">Add new category</a>


                    <template v-if="addCategory">
                        <v-text-field
                            required
                            v-model="newCategory"
                            :rules="[rules.required]"
                        ></v-text-field>
                        <v-combobox
                            v-model="parentValue"
                            :items="parents"
                            item-title="title"
                            label="Parent Category"
                            chips
                            clearable
                        ></v-combobox>

                        <v-btn @click="saveCategory">Add New Category</v-btn>

                    </template>

                    <v-combobox
                        v-model="termValue"
                        :items="terms"
                        item-title="title"
                        :search-input.sync="searchTerm"
                        hide-selected
                        label="Terms"
                        multiple
                        persistent-hint
                        small-chips
                        clearable
                        deletable-chips
                    >
                        <template v-slot:no-data>
                            <v-list-item>

                                    <v-list-item-title>
                                        No results matching "<strong>{{ searchTerm }}</strong>". Press <kbd>enter</kbd> to create a new one
                                    </v-list-item-title>

                            </v-list-item>
                        </template>
                        <template v-slot:selection="{ attrs, item, parent, selected }">
                            <v-chip
                                v-if="item === Object(item)"
                                v-bind="attrs"
                                :color="`${item.color} lighten-3`"
                                :input-value="selected"
                                label
                                small
                            >
          <span class="pr-2">
            {{ item.title }}
          </span>
                                <v-icon
                                    small
                                    :color="`${item.color} lighten`"
                                    @click="parent.selectItem(item)"
                                >
                                    $delete
                                </v-icon>
                            </v-chip>
                        </template>
                        <template v-slot:item="{ index, item }">
                            <v-chip
                                :color="`${item.color} lighten-3`"
                                dark
                                label
                                small
                            >
                                {{ item.title }}
                            </v-chip>

                        </template>
                    </v-combobox>


                    <v-btn @click="update">Save</v-btn>
                </v-col>
            </v-row>

        </v-container>

    </div>
</template>

<script>

// import {mapGetters} from "vuex";
import { ref, reactive, computed, watch, onMounted } from 'vue';
import Tiptap from '../common/tiptap/Tiptap.vue'
import {useAuthStore} from '@/store/authStore.js'
import {useUserStore} from '@/store/userStore.js'
import {useRouter} from "vue-router";

export default {
    components: {
        Tiptap
    },
    setup(props, {emit}) {
        let slug = ref(null);
        let wikipage = ref({});
        let message = ref('');
        let parents = reactive([]);
        let taxonomyValue = ref([]);
        let taxonomies = ref([]);
        let termValue = ref([]);
        let categories = ref([]);
        let categoryValue = ref([]);
        let pages = ref([]);
        let searchTerm = ref();
        let searchTax = ref();
        let loading = ref(true);

        let terms = ref([]);
        let colors = ref(['green', 'purple', 'indigo', 'cyan', 'teal', 'orange']);
        let nonce = ref(1);
        let addCategory = ref(false);
        const authStore = useAuthStore();
        const authenticated = computed(() => authStore.authenticated);
        const user = computed(() => authStore.user);
        const router = useRouter();

        onMounted(() => {
            if (router.currentRoute.value.params.slug) {
                slug.value = router.currentRoute.value.params.slug;
                getWikiPage();
            }

            getCategories()
            getTerms()
        });

        watch(termValue, (val, prev) => {
            if (val.length === prev.length) return

            this.termValue = val.map(v => {
                if (typeof v === 'string') {
                    v = {
                        title: v,
                        color: this.colors[this.nonce - 1],
                    }

                    this.search = null
                    this.terms.push(v)

                    this.nonce++
                }

                return v
            })
        });

        // watch(() => wikipage.value.content, content => {
        //     // console.log('updatedtcontent', content)
        //     //const isSame = editor.value.getHTML() === value;
        //
        //     // if (isSame) {
        //     //     return;
        //     // }
        //
        //     // editor.value.commands.setContent(value);
        // });


        // watch(
        //     () => wikipage.value.content,
        //     (content) => {
        //         console.log('content change', content)
        //         wikipage.value.content = content
        //     },
        //     { deep: true }
        // );

        // Your methods go here, but they're just regular functions now:
        function getWikiPage() {
            loading.value = true
            console.log('test', slug)
            return axios.get(`/api/wiki/${slug.value}`).then((response) => {
                wikipage.value = response.data.page

                parents = response.data.parents
                taxonomies.value = response.data.terms

                termValue.value = response.data.tags

                categoryValue.value = taxonomies.value;
            }).catch((error) => {
                if (error.response.status === 404) {
                    // this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    //this.$router.push('/auth/signin')
                }
            });
        }

        function goTo(slug, type) {
            //this.$router.push({name: type, params: {slug: slug}})
        }

        function getCategories() {
            loading.value = true
            return axios.get(`/api/tag/terms/wiki`).then((response) => {

                categories.value = parents = response.data.terms

                loading.value = false
            });
        }

        function getTerms() {
            loading.value = true
            return axios.get(`/api/tag/terms/tags`).then((response) => {
                terms.value = response.data.terms.map(x => {
                    return x.title
                })

                loading.value = false
            });
        }

        function saveCategory() {
            // this.loading.value = true
            let data = {term: newCategory, taxonomy: taxonomyValue, parent: parentValue}

            axios.post(`/api/tag/terms`, data).then(() => {

                getCategories(this.taxonomyValue.taxonomy)
                categoryValue.push(this.newCategory);
                // this.categories = this.parents = response.data

                // this.loading.value = false
            });
        }

        function updateContent(content) {
            wikipage.value.content = content
        }

        function update() {
            wikipage.value.terms = termValue.value;
            wikipage.value.taxonomy = taxonomyValue.value
            wikipage.value.categories = categoryValue.value

            axios.patch(`/api/wiki/${slug.value}`, wikipage.value).then(() => {
                message.value = "Wiki Page updated"
            }).catch((error) => {
                if (error.response.status === 422) {
                    editing.errors = error.response.data
                }
            })
        }

        // ... and so on for your other methods

        // Return anything that should be available in the template:
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
            message,
            searchTerm,
            searchTax,
            pages,
            loading,
            categories,
            getWikiPage,
            goTo,
            getCategories,
            getTerms,
            saveCategory,
            update,
            updateContent
        };
    }
}
</script>

<style>
.card-outter {
    position: relative;
    padding-bottom: 50px;
}

.card-actions {
    position: absolute;
    bottom: 0;
}
</style>


