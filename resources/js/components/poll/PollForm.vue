<template>
  <div class="poll-form">
    <v-text-field
      v-model="form.title"
      :label="$t('poll.pollTitle')"
      :error-messages="errors.title"
      :disabled="disabled"
      maxlength="255"
      counter
      class="mb-2"
      @update:model-value="errors.title = []"
    />

    <v-textarea
      v-model="form.description"
      :label="$t('poll.description')"
      :error-messages="errors.description"
      :disabled="disabled"
      rows="2"
      auto-grow
      maxlength="1000"
      counter
      class="mb-2"
      @update:model-value="errors.description = []"
    />

    <div class="d-flex flex-wrap align-center ga-4 mb-2">
      <div>
        <div class="text-caption text-medium-emphasis mb-1">{{ $t('poll.type') }}</div>
        <v-btn-toggle
          v-model="form.type"
          mandatory
          density="compact"
          variant="outlined"
          color="primary"
          :disabled="disabled"
        >
          <v-btn value="single" size="small" prepend-icon="mdi-radiobox-marked">
            {{ $t('poll.singleChoice') }}
          </v-btn>
          <v-btn value="multiple" size="small" prepend-icon="mdi-checkbox-marked-outline">
            {{ $t('poll.multipleChoice') }}
          </v-btn>
        </v-btn-toggle>
      </div>

      <v-switch
        v-model="form.anonymous"
        :label="$t('poll.anonymous')"
        :disabled="disabled"
        color="primary"
        density="compact"
        hide-details
        inset
      />
    </div>

    <v-text-field
      v-model="form.closes_at"
      type="datetime-local"
      :label="$t('poll.closingDate')"
      :hint="$t('poll.closingDateHint')"
      :error-messages="errors.closes_at"
      :disabled="disabled"
      :min="minDateTime"
      prepend-inner-icon="mdi-calendar-clock"
      persistent-hint
      clearable
      class="mb-4"
      @update:model-value="errors.closes_at = []"
    />

    <div class="d-flex justify-space-between align-center mb-2">
      <span class="text-subtitle-2 font-weight-medium">
        {{ $t('poll.options') }}
        <span class="text-caption text-medium-emphasis ml-1">({{ form.options.length }}/{{ maxOptions }})</span>
      </span>
      <v-btn
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="mdi-plus"
        :disabled="disabled || form.options.length >= maxOptions"
        @click="addOption"
      >
        {{ $t('poll.addOption') }}
      </v-btn>
    </div>

    <div
      v-for="(option, index) in form.options"
      :key="option.key"
      class="d-flex align-start ga-2 mb-2"
    >
      <v-text-field
        v-model="option.text"
        :label="$t('poll.option', { n: index + 1 })"
        :error-messages="errors.options[index] || []"
        :disabled="disabled"
        maxlength="255"
        density="compact"
        hide-details="auto"
        @update:model-value="clearOptionError(index)"
      />
      <v-btn
        icon="mdi-close"
        variant="text"
        size="small"
        :aria-label="$t('poll.removeOption')"
        :disabled="disabled || form.options.length <= minOptions"
        @click="removeOption(index)"
      />
    </div>

    <v-alert
      v-if="errors.optionsCount.length"
      type="warning"
      variant="tonal"
      density="compact"
      class="mt-2"
    >
      {{ errors.optionsCount[0] }}
    </v-alert>
  </div>
</template>

<script>
const MIN_OPTIONS = 2
const MAX_OPTIONS = 10

let optionKey = 0
const newOption = (text = '') => ({ key: ++optionKey, text })

const emptyForm = () => ({
  title: '',
  description: '',
  type: 'single',
  anonymous: false,
  closes_at: null,
  options: [newOption(), newOption()]
})

const emptyErrors = () => ({
  title: [],
  description: [],
  closes_at: [],
  options: {},
  optionsCount: []
})

// ISO (or anything Date can parse) -> "YYYY-MM-DDTHH:mm" in the browser's local time
const toLocalInput = (value) => {
  if (!value) return null
  const date = new Date(value)
  if (isNaN(date)) return null
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

// "YYYY-MM-DDTHH:mm" (local) -> ISO string, null when empty or unparsable
const toIso = (value) => {
  if (!value) return null
  const date = new Date(value)
  return isNaN(date) ? null : date.toISOString()
}

/**
 * Inline poll editor. `v-model` is the poll object the API accepts
 * ({ title, description, type, anonymous, closes_at, options: [string] })
 * or null. Call `validate()` before submitting the surrounding form.
 */
export default {
  name: 'PollForm',
  props: {
    modelValue: {
      type: Object,
      default: null
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:modelValue'],
  data() {
    return {
      form: emptyForm(),
      errors: emptyErrors(),
      minOptions: MIN_OPTIONS,
      maxOptions: MAX_OPTIONS,
      // undefined until the first emit, so a null modelValue still seeds the form
      lastEmitted: undefined
    }
  },
  computed: {
    minDateTime() {
      return toLocalInput(new Date())
    },
    payload() {
      return {
        title: (this.form.title || '').trim(),
        description: (this.form.description || '').trim() || null,
        type: this.form.type === 'multiple' ? 'multiple' : 'single',
        anonymous: !!this.form.anonymous,
        closes_at: toIso(this.form.closes_at),
        options: this.form.options
          .map(option => (option.text || '').trim())
          .filter(text => text.length > 0)
      }
    }
  },
  watch: {
    modelValue: {
      handler(value) {
        // Ignore the echo of our own emit; only re-seed on an outside change
        if (value === this.lastEmitted) return
        this.seed(value)
      },
      immediate: true
    },
    form: {
      handler() {
        this.emitValue()
      },
      deep: true
    }
  },
  mounted() {
    // The initial seed runs before the form watcher exists, so hand the parent
    // a poll object explicitly as soon as the form is shown
    if (this.lastEmitted === undefined) {
      this.emitValue()
    }
  },
  methods: {
    seed(value) {
      if (!value) {
        this.form = emptyForm()
      } else {
        const options = Array.isArray(value.options) ? value.options : []
        const texts = options.map(option => (typeof option === 'string' ? option : option?.option_text || ''))
        while (texts.length < MIN_OPTIONS) texts.push('')
        this.form = {
          title: value.title || '',
          description: value.description || '',
          type: value.type === 'multiple' ? 'multiple' : 'single',
          anonymous: !!value.anonymous,
          closes_at: toLocalInput(value.closes_at),
          options: texts.slice(0, MAX_OPTIONS).map(text => newOption(text))
        }
      }
      this.errors = emptyErrors()
    },
    emitValue() {
      this.lastEmitted = this.payload
      this.$emit('update:modelValue', this.lastEmitted)
    },
    addOption() {
      if (this.form.options.length < MAX_OPTIONS) {
        this.form.options.push(newOption())
      }
    },
    removeOption(index) {
      if (this.form.options.length > MIN_OPTIONS) {
        this.form.options.splice(index, 1)
        this.errors.options = {}
      }
    },
    clearOptionError(index) {
      if (this.errors.options[index]) {
        delete this.errors.options[index]
      }
      this.errors.optionsCount = []
    },
    /**
     * Validates the current fields, fills the inline error messages and
     * returns true when the poll can be submitted.
     */
    validate() {
      const errors = emptyErrors()
      const t = (key, params) => this.$t(key, params)

      if (!this.payload.title) {
        errors.title.push(t('poll.titleRequired'))
      } else if (this.payload.title.length > 255) {
        errors.title.push(t('poll.tooLong', { max: 255 }))
      }

      if (this.payload.description && this.payload.description.length > 1000) {
        errors.description.push(t('poll.tooLong', { max: 1000 }))
      }

      if (this.form.closes_at) {
        const closesAt = new Date(this.form.closes_at)
        if (isNaN(closesAt)) {
          errors.closes_at.push(t('poll.invalidDate'))
        } else if (closesAt <= new Date()) {
          errors.closes_at.push(t('poll.closingDateFuture'))
        }
      }

      this.form.options.forEach((option, index) => {
        const text = (option.text || '').trim()
        if (!text) {
          errors.options[index] = [t('poll.optionRequired')]
        } else if (text.length > 255) {
          errors.options[index] = [t('poll.tooLong', { max: 255 })]
        }
      })

      if (this.payload.options.length < MIN_OPTIONS) {
        errors.optionsCount.push(t('poll.minOptions'))
      } else if (this.payload.options.length > MAX_OPTIONS) {
        errors.optionsCount.push(t('poll.maxOptions', { max: MAX_OPTIONS }))
      }

      this.errors = errors

      return !errors.title.length &&
        !errors.description.length &&
        !errors.closes_at.length &&
        !Object.keys(errors.options).length &&
        !errors.optionsCount.length
    },
    reset() {
      this.seed(null)
      this.emitValue()
    }
  }
}
</script>
