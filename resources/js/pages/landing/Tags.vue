<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15">
                <v-list-group
                    color="bg-primary"
                    v-for="(taxable, type) in taxables.type"
                    :key="`${type}-${$taxable}`"
                >{{type}}
                    <v-list-item
                        v-for="(model) in taxable"
                        :key="`${model.data.slug}`"
                        @click="goTo(model.data.slug, model.taxonomy[0].taxonomy)"
                    >
                        <v-list-item-content>
                            <v-list-item-title v-text="model.taxable_title"></v-list-item-title>
                        </v-list-item-content>
                    </v-list-item>
                </v-list-group>
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
            taxables: [],
            taxonomy:"",
            term:"",
            model:""
        }
    },

    methods: {
        getTaxables(){
            this.loading = true
            return axios.get(`/api/taxables?term=${this.term}&taxonomy=${this.taxonomy}&model=${this.model}`).then((response) => {
                this.taxables = response.data.data

            }).catch((error) => {
                if (error.response.status === 404) {
                    this.$router.push('/error/not-found')
                }
                if (error.response.status === 401) {
                    this.$router.push('/auth/signin')
                }
            });
        },
        goTo(slug, type) {
            this.$router.push({ name: type, params: { slug: slug } })
        }

    },

    created() {

        if(this.$route.params.model) {
            this.model = this.$route.params.model;
        }

        if(this.$route.params.term) {
            let term = this.$route.params.term;
            term = term.split(":")
            if(term.length > 1) {
                this.term = term[1]
                this.taxonomy = term[0]
            } else {
                this.term = term[0]
            }

            this.getTaxables()
        }



    }

}
</script>
