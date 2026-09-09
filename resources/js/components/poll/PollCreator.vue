<template>
  <v-dialog v-model="dialog" max-width="600" persistent>
    <template v-slot:activator="{ props: activatorProps }">
      <v-btn
        color="primary"
        variant="elevated"
        prepend-icon="mdi-poll"
        v-bind="activatorProps"
      >
        {{ $t('poll.createPoll') }}
      </v-btn>
    </template>

    <v-card>
      <v-card-title class="text-h6">
        {{ editMode ? $t('poll.editPoll') : $t('poll.createPoll') }}
      </v-card-title>
      <v-divider />

      <v-card-text>
        <v-form ref="form" v-model="valid">
          <v-text-field
            v-model="form.title"
            :label="$t('poll.pollTitle')"
            :rules="[rules.required]"
            class="mb-2"
          />

          <v-textarea
            v-model="form.description"
            :label="$t('poll.description')"
            rows="2"
            class="mb-2"
          />

          <v-radio-group
            v-model="form.type"
            :label="$t('poll.type')"
            inline
          >
            <v-radio :label="$t('poll.singleChoice')" value="single" />
            <v-radio :label="$t('poll.multipleChoice')" value="multiple" />
          </v-radio-group>

          <v-checkbox
            v-model="form.anonymous"
            :label="$t('poll.anonymous')"
            density="compact"
          />

          <v-menu
            v-model="dateMenu"
            :close-on-content-click="false"
            transition="scale-transition"
            min-width="auto"
          >
            <template v-slot:activator="{ props: menuProps }">
              <v-text-field
                v-model="form.closes_at"
                :label="$t('poll.closingDate')"
                prepend-inner-icon="mdi-calendar"
                readonly
                clearable
                v-bind="menuProps"
              />
            </template>
            <v-date-picker
              :min="minDate"
              @update:model-value="onDateSelected"
            />
          </v-menu>

          <v-divider class="my-4" />

          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-subtitle-1 font-weight-medium">{{ $t('poll.options') }}</span>
            <v-btn
              size="small"
              color="primary"
              variant="tonal"
              prepend-icon="mdi-plus"
              :disabled="form.options.length >= 10"
              @click="addOption"
            >
              {{ $t('poll.addOption') }}
            </v-btn>
          </div>

          <div
            v-for="(option, index) in form.options"
            :key="index"
            class="d-flex align-center ga-2 mb-2"
          >
            <v-text-field
              v-model="option.option_text"
              :label="$t('poll.option', { n: index + 1 })"
              :rules="[rules.required]"
              density="compact"
              hide-details
            />
            <v-btn
              icon="mdi-close"
              variant="text"
              size="small"
              :disabled="form.options.length <= 2"
              @click="removeOption(index)"
            />
          </div>

          <v-alert
            v-if="form.options.length < 2"
            type="warning"
            density="compact"
            class="mt-2"
          >
            {{ $t('poll.minOptions') }}
          </v-alert>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer />
        <v-btn
          variant="text"
          :disabled="loading"
          @click="close"
        >
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :disabled="!canSubmit"
          :loading="loading"
          @click="submit"
        >
          {{ editMode ? $t('poll.updatePoll') : $t('poll.createPoll') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import axios from 'axios'

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
        required: value => !!value || this.$t('poll.required')
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
    // The date picker hands back a Date; the form keeps the YYYY-MM-DD string the API expects
    onDateSelected(value) {
      if (value instanceof Date && !isNaN(value)) {
        const pad = (n) => String(n).padStart(2, '0')
        this.form.closes_at = `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`
      } else if (typeof value === 'string') {
        this.form.closes_at = value.substr(0, 10)
      }
      this.dateMenu = false
    },
    async submit() {
      if (this.loading) return
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
          response = await axios.put(`/api/polls/${this.existingPoll.id}`, payload)
        } else {
          response = await axios.post('/api/polls', payload)
        }

        this.$emit('created', response.data.poll)
        this.close()

        await this.$dialog.success(response.data.message || this.$t('poll.saved'))
      } catch (error) {
        await this.$dialog.requestError(error, this.$t('poll.saveFailed'))
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
