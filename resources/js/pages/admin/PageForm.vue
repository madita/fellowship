<template>
  <div class="flex-grow-1">
      <v-alert v-if="message" type="success">
          {{ message }}
      </v-alert>

      <v-text-field
          v-model="page.title"
      ></v-text-field>

      <simple-editor  v-model="page.body" :value="page.body" id="text-body" name="content"></simple-editor>

      <v-checkbox
          v-model="page.published"
          label="Published"
      ></v-checkbox>

      <v-checkbox
          v-model="page.sign_in_only"
          label="Sign in only"
      ></v-checkbox>


      <v-btn @click="save">{{form}}</v-btn>
  </div>
</template>

<script>
import SimpleEditor from '../../components/common/SimpleEditor'

export default {
    components: {
        SimpleEditor
    },
    data() {
        return {
            loading: true,
            page: {title:"", body:""},
            endpoint:'/api/datatable/pages',
            form:"create"|"edit",
            id:null,
            message:""
        }
    },

    methods: {
        getPage(){
            this.loading = true
            return axios.get(`/api/pages/${this.id}/edit`).then((response) => {
                this.page = response.data.data

                this.loading = false
            });
        },
        save () {
            if (this.form === "edit") {
                this.update()
            } else {
                this.store()
            }
        },
        update () {
            axios.patch(`${this.endpoint}/${this.id}`, this.page).then(() => {
                this.message = "Page updated"
            }).catch((error) => {
                if (error.response.status === 422) {
                    this.editing.errors = error.response.data
                }
            })
        },
        store () {
            axios.post(`${this.endpoint}`, this.page).then(() => {
                this.page = {title:"", body:""};
                this.message = "Page saved ..link"
            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        },
    },

    created() {
        if(this.$route.params.form) {
            this.form = this.$route.params.form;
        }

        if(this.$route.params.id) {
            this.id = this.$route.params.id;
            this.getPage();
        }

    }
}
</script>
