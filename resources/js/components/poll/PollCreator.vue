<template>
  <v-dialog v-model="dialog" max-width="700" persistent>
    <template v-slot:activator="{ on, attrs }">
      <v-btn
        color="primary"
        v-bind="attrs"
        v-on="on"
      >
        <v-icon left>mdi-poll</v-icon>
        Create Poll
      </v-btn>
    </template>

    <v-card>
      <v-card-title>
        <span class="text-h5">{{ editMode ? 'Edit Poll' : 'Create Poll' }}</span>
      </v-card-title>

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <v-text-field
            v-model="form.title"
            label="Poll Title"
            :rules="[rules.required]"
            outlined
            dense
          ></v-text-field>

          <v-textarea
            v-model="form.description"
            label="Description (optional)"
            outlined
            dense
            rows="2"
          ></v-textarea>

          <v-radio-group
            v-model="form.type"
            label="Poll Type"
            row
          >
            <v-radio label="Single Choice" value="single"></v-radio>
            <v-radio label="Multiple Choice" value="multiple"></v-radio>
          </v-radio-group>

          <v-checkbox
            v-model="form.anonymous"
            label="Anonymous voting (hide vote counts until poll closes)"
            dense
          ></v-checkbox>

          <v-menu
            v-model="dateMenu"
            :close-on-content-click="false"
            :nudge-right="40"
            transition="scale-transition"
            offset-y
            min-width="auto"
          >
            <template v-slot:activator="{ on, attrs }">
              <v-text-field
                v-model="form.closes_at"
                label="Closing Date (optional)"
                prepend-icon="mdi-calendar"
                readonly
                clearable
                v-bind="attrs"
                v-on="on"
              ></v-text-field>
            </template>
            <v-date-picker
              v-model="form.closes_at"
              @input="dateMenu = false"
              :min="minDate"
            ></v-date-picker>
          </v-menu>

          <v-divider class="my-4"></v-divider>

          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-subtitle-1 font-weight-bold">Poll Options</span>
            <v-btn
              small
              color="primary"
              @click="addOption"
              :disabled="form.options.length >= 10"
            >
              <v-icon left small>mdi-plus</v-icon>
              Add Option
            </v-btn>
          </div>

          <v-list dense>
            <v-list-item
              v-for="(option, index) in form.options"
              :key="index"
              class="px-0"
            >
              <v-text-field
                v-model="option.option_text"
                :label="`Option ${index + 1}`"
                :rules="[rules.required]"
                outlined
                dense
                hide-details
              >
                <template v-slot:append>
                  <v-btn
                    icon
                    small
                    @click="removeOption(index)"
                    :disabled="form.options.length <= 2"
                  >
                    <v-icon small>mdi-close</v-icon>
                  </v-btn>
                </template>
              </v-text-field>
            </v-list-item>
          </v-list>

          <v-alert
            v-if="form.options.length < 2"
            type="warning"
            dense
            text
            class="mt-2"
          >
            A poll must have at least 2 options
          </v-alert>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          text
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          :disabled="!canSubmit"
          :loading="loading"
          @click="submit"
        >
          {{ editMode ? 'Update' : 'Create' }} Poll
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
export default {
  name: 'PollCreator',
  props: {
    pollableType: {
      type: String,
      required: true
    },
    pollableId: {
      type: Number,
      required: true
    },
    existingPoll: {
      type: Object,
      default: null
    }
  },
  data() {
    return {
      dialog: false,
      dateMenu: false,
      valid: false,
      loading: false,
      form: {
        title: '',
        description: '',
        type: 'single',
        anonymous: false,
        closes_at: null,
        options: [
          { option_text: '' },
          { option_text: '' }
        ]
      },
      rules: {
        required: value => !!value || 'Required.'
      }
    }
  },
  computed: {
    editMode() {
      return !!this.existingPoll
    },
    minDate() {
      return new Date().toISOString().substr(0, 10)
    },
    canSubmit() {
      return this.valid && 
             this.form.options.length >= 2 && 
             this.form.options.every(opt => opt.option_text.trim())
    }
  },
  methods: {
    addOption() {
      if (this.form.options.length < 10) {
        this.form.options.push({ option_text: '' })
      }
    },
    removeOption(index) {
      if (this.form.options.length > 2) {
        this.form.options.splice(index, 1)
      }
    },
    async submit() {
      if (!this.$refs.form.validate()) {
        return
      }

      this.loading = true
      try {
        const payload = {
          pollable_type: this.pollableType,
          pollable_id: this.pollableId,
          title: this.form.title,
          description: this.form.description || null,
          type: this.form.type,
          anonymous: this.form.anonymous,
          closes_at: this.form.closes_at ? `${this.form.closes_at}T23:59:59` : null,
          options: this.form.options.filter(opt => opt.option_text.trim())
        }

        let response
        if (this.editMode) {
          response = await this.$axios.put(`/polls/${this.existingPoll.id}`, payload)
        } else {
          response = await this.$axios.post('/polls', payload)
        }

        this.$emit('created', response.data.poll)
        this.$notify({
          type: 'success',
          title: 'Success',
          text: response.data.message
        })

        this.close()
      } catch (error) {
        this.$notify({
          type: 'error',
          title: 'Error',
          text: error.response?.data?.message || 'Failed to save poll'
        })
      } finally {
        this.loading = false
      }
    },
    close() {
      this.dialog = false
      this.resetForm()
    },
    resetForm() {
      this.form = {
        title: '',
        description: '',
        type: 'single',
        anonymous: false,
        closes_at: null,
        options: [
          { option_text: '' },
          { option_text: '' }
        ]
      }
      this.$refs.form?.resetValidation()
    }
  },
  watch: {
    existingPoll: {
      handler(poll) {
        if (poll) {
          this.form = {
            title: poll.title,
            description: poll.description || '',
            type: poll.type,
            anonymous: poll.anonymous,
            closes_at: poll.closes_at ? poll.closes_at.substr(0, 10) : null,
            options: poll.options.map(opt => ({ option_text: opt.option_text }))
          }
        }
      },
      immediate: true
    }
  }
}
</script>
