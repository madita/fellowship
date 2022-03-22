<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <h1>{{page.title}}</h1>
                <v-btn v-if="!showHistory && !showHistoryItem" @click="loadHistory">History</v-btn>
                <v-btn v-if="showHistory || showHistoryItem" @click="showPage">Show Page</v-btn>
                <div v-if="showHistory">
                    <v-list-item-group
                        color="primary">
                        <v-list-item v-for="(item, index) in history" :key="`history-${index}`" two-line
                                     @click="loadHistoryItem(index)">
                            <v-list-item-content>
                                <v-list-item-title>{{item.action}} by {{ item.user.username }}</v-list-item-title>
                                <v-list-item-subtitle>{{ item.created_at | humanDiff() }}</v-list-item-subtitle>
                            </v-list-item-content>
                        </v-list-item>
                    </v-list-item-group>
                </div>
                <div v-else-if="showHistoryItem">

                    <v-simple-table>
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
                    </v-simple-table>
                </div>
                <div v-else v-html="page.body"></div>
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
            history: [],
            historyItem: [],
            slug:""
        }
    },

    methods: {
        getPage(){
            this.loading = true
            return axios.get(`/api/pages/${this.slug}`).then((response) => {
                this.page = response.data

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
