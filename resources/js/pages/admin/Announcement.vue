<template>
  <div class="flex-grow-1">
      <v-container>
          <v-alert v-if="message" :type="type">
              {{ message }}
          </v-alert>
          <v-text-field
              label="Subject"
              v-model="form.subject"
              :rules="[rules.required]"
              :error-messages="errors.subject"
          ></v-text-field>
          <v-textarea
              label="Message"
              v-model="form.body"
              :rules="[rules.required]"
              :error-messages="errors.body"
          ></v-textarea>
          <v-text-field
              label="Action Button"
              v-model="form.action"
          ></v-text-field>
          <v-text-field
              label="URL"
              v-model="form.url"
          ></v-text-field>
          <v-text-field
              label="Footer (say thanks)"
              v-model="form.thanks"
              :rules="[rules.required]"
              :error-messages="errors.thanks"
          ></v-text-field>
          <v-btn
              :loading="isLoading"
              block
              x-large
              color="primary"
              @click="save"
          >{{ $t('save') }}
          </v-btn>
      </v-container>

  </div>
</template>

<script>

export default {
    data () {
        return {
            isLoading: false,
            message:'',
            type:'error',
            form: {
                subject: '',
                body: '',
                action: '',
                url: '',
                thanks: '',
            },
            errors: {
                subject: '',
                body: '',
                action: '',
                url: '',
                thanks: '',
            },
            rules: {
                required: (value) => (value && Boolean(value)) || 'Required',
            }
        }
    },
    methods: {

        resetError() {
            this.message = '';
            this.errors = {
                subject: '',
                body: '',
                action: '',
                url: '',
                thanks: '',
            }
        },

        save () {
            this.isLoading = true
            axios.post('/api/admin/announcement', this.form).then((response) => {

                this.message = 'Tadaaa'
                this.type = 'success'

                this.form = {
                    subject: '',
                    body: '',
                    action: '',
                    url: '',
                    footer: ''
                }
                this.resetError();
                this.isLoading = false

            }).catch((error) => {
                this.isLoading = false
                this.type = 'error'
                this.message = error
                if (error.response.status === 422) {
                    this.message = error.response.data.message
                    this.errors = error.response.data.errors
                }
            })
        }
    },
    mounted () {

    }
}
</script>
