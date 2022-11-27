<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <v-list-item-group
                    color="primary"
                    v-for="(category, capital) in categories.capital"
                    :key="`${capital}-${category}`"
                >{{capital.toUpperCase()}}
                    <v-list-item
                        v-for="(model) in category"
                        :key="`${model.data.slug}`"
                        @click="goTo(model.data.slug, model.taxonomy[0].taxonomy)"
                    >
                        <v-list-item-content>
                            <v-list-item-title v-text="model.taxable_title"></v-list-item-title>
                        </v-list-item-content>
                    </v-list-item>
                </v-list-item-group>
            </v-container>
        </v-sheet>
    </div>
</template>

<script>

import {mapGetters} from "vuex";

export default {
    components: {
    },
    data() {
        return {
            categories:[],
            slug:""
        }
    },
    methods: {
        getWikiCategory(){
            this.loading = true
            return axios.get(`/api/taxables?term=${this.slug}&taxonomy=&model=`).then((response) => {
                this.categories = response.data.data
                console.log('asdsadasd')
               console.log('test',this.categories)
            }).catch((error) => {
                // if (error.response.status === 404) {
                //     this.$router.push('/error/not-found')
                // }
                // if (error.response.status === 401) {
                //     this.$router.push('/auth/signin')
                // }
            });
        },
        goTo(slug, type) {
            this.$router.push({ name: type, params: { slug: slug } })
        },
    },
    computed: {
        ...mapGetters({
            user: 'auth/user',
        })
    },
    mounted() {

        if(this.$route.params.slug) {
            this.slug = this.$route.params.slug;
            this.getWikiCategory();
        }


    }
}
</script>

<style>
.v-item-group {
    column-count: 3
}

.card-outter {
    position: relative;
    padding-bottom: 50px;
}
.card-actions {
    position: absolute;
    bottom: 0;
}
</style>


