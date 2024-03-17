<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-5">

                <v-breadcrumbs :items="breadcrumbs" class="pa-0 py-2"></v-breadcrumbs>

                <h1>{{page.title}}</h1>
                <v-btn v-if="!showHistory && !showHistoryItem" @click="loadHistory">History</v-btn>
                <v-btn v-if="showHistory || showHistoryItem" @click="showPage">Show Page</v-btn>
                <div v-if="showHistory">
                    <v-list-group
                        color="primary">
                        <v-list-item v-for="(item, index) in history" :key="`history-${index}`" two-line
                                     @click="loadHistoryItem(index)">

                                <v-list-item-title>{{item.action}} by {{ item.user.username }}</v-list-item-title>
                                <v-list-item-subtitle>{{ $formatDistanceToNow(item.created_at) }}</v-list-item-subtitle>

                        </v-list-item>
                    </v-list-group>
                </div>
                <div v-else-if="showHistoryItem">

                    <v-table>
                        <template v-slot:default>
                            <thead>
                            <tr>
                                <th class="text-left">
                                    Field
                                </th>
                                <th class="text-left">
                                    Old
                                </th>
                                <th class="text-left">
                                    New
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr
                                v-for="(item, key) in historyItem.diff"
                                :key="key"
                            >
                                <td>{{ key }}</td>
                                <td v-html="item.old_value"></td>
                                <td v-html="item.new_value"></td>
                            </tr>
                            </tbody>
                        </template>
                    </v-table>
                </div>
                <div v-else v-html="page.body"></div>
                <div v-for="child in page.children"><router-link :to="`/p/${child.slug}`" class="font-weight-bold">
                    {{child.title}}
                </router-link></div>
                <v-chip :color="tag.color" v-for="tag in tags" :key="`tag-${tag.id}`" @click="goToTerm(tag.slug)">{{ tag.name }}</v-chip>

                <div v-for="(taxonomy, key) in taxonomies">
                    {{key}}:  <v-chip :color="tag.color" v-for="tag in taxonomy" :key="`tax-${tag.id}`" @click="goToCategory(tag.slug, key)">{{ tag.name }}</v-chip>
                </div>

            </v-container>
        </v-sheet>
    </div>
</template>

<script>

export default {
    components: {
    },
    data() {
        return {
            loading: true,
            showHistory: false,
            showHistoryItem: false,
            page: [],
            tags: [],
            taxonomies: [],
            history: [],
            historyItem: [],
            slug:"",
            parents:[],
            breadcrumbs: [],
        }
    },

    methods: {
        getPage(){
            this.loading = true
            return axios.get(`/api/pages/${this.slug}`).then((response) => {
                this.page = response.data.page
                this.parents = Object.values(response.data.parents)

                let taxonomies = response.data.taxonomies
                this.tags = taxonomies.tags;

                this.breadcrumbs = this.parents.map(x =>  ({
                    text:x.title, to:'/p/'+x.slug
                })).reverse();

                this.breadcrumbs.push({text:this.page.title})

                delete taxonomies.tags;
                this.taxonomies = taxonomies;

                this.loading = false
            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },
        showPage(){
            this.showHistory = false
            this.showHistoryItem = false
        },
        loadHistory() {
            if(!this.page) {
                this.getPage();
            }

            this.loading = true
            return axios.get(`/api/pages/${this.page.id}/history`).then((response) => {
                this.history = response.data

                this.loading = false
                this.showHistory = true
            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 403) {
                    this.$router.push('/auth/signin')
                }
            });

        },
        loadHistoryItem(index) {
            this.showHistory = false
            this.showHistoryItem = true
            this.historyItem = this.history[index]
        },
        goToTerm(item) {
            this.$router.push(`/pages/tags/${item}`)
        },
        goToCategory(category, taxonomy) {
            this.$router.push(`/pages/${taxonomy}/${category}`)
        }
    },

    created() {
        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getPage();
        }

    }

}
</script>
