<template>
  <div class="flex-grow-1">
      <page-header
          :title="$t('admin.announcements.title')"
          :subtitle="$t('admin.announcements.subtitle')"
          icon="mdi-bullhorn-outline"
      />

      <v-container>
          <v-card rounded="lg">
              <v-card-text>
                  <v-text-field
                      :label="$t('announcement.subject')"
                      v-model="form.subject"
                      :rules="[rules.required]"
                      :error-messages="errors.subject"
                      :disabled="isLoading"
                  ></v-text-field>
                  <v-textarea
                      :label="$t('announcement.message')"
                      v-model="form.body"
                      :rules="[rules.required]"
                      :error-messages="errors.body"
                      :disabled="isLoading"
                  ></v-textarea>
                  <v-text-field
                      :label="$t('announcement.actionButton')"
                      v-model="form.action"
                      :disabled="isLoading"
                  ></v-text-field>
                  <v-text-field
                      :label="$t('announcement.url')"
                      v-model="form.url"
                      :disabled="isLoading"
                  ></v-text-field>
                  <v-text-field
                      :label="$t('announcement.footer')"
                      v-model="form.thanks"
                      :rules="[rules.required]"
                      :error-messages="errors.thanks"
                      :disabled="isLoading"
                  ></v-text-field>
              </v-card-text>
              <v-card-actions>
                  <v-spacer />
                  <v-btn
                      :loading="isLoading"
                      :disabled="isLoading"
                      color="primary"
                      variant="elevated"
                      prepend-icon="mdi-send"
                      @click="save"
                  >{{ $t('common.save') }}
                  </v-btn>
              </v-card-actions>
          </v-card>
      </v-container>

  </div>
</template>

<script>
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/common/PageHeader.vue';

export default {
    components: {
        PageHeader,
    },
    setup() {
        const { t } = useI18n();
        return { t };
    },
    data () {
        return {
            isLoading: false,
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
                required: (value) => (value && Boolean(value)) || this.t('validation.required'),
            }
        }
    },
    methods: {

        resetError() {
            this.errors = {
                subject: '',
                body: '',
                action: '',
                url: '',
                thanks: '',
            }
        },

        async save () {
            if (this.isLoading) return;
            this.isLoading = true
            try {
                await axios.post('/api/admin/announcement', this.form)

                this.form = {
                    subject: '',
                    body: '',
                    action: '',
                    url: '',
                    thanks: ''
                }
                this.resetError();
                this.isLoading = false
                this.$dialog.success(this.t('announcement.sentSuccess'))
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = { ...this.errors, ...(error.response.data.errors || {}) }
                }
                this.isLoading = false
                this.$dialog.requestError(error)
            }
        }
    },
    mounted () {

    }
}
</script>
