<template>
  <div class="publish-control">
    <div v-if="label" class="text-caption text-medium-emphasis mb-1">{{ label }}</div>

    <div class="d-flex flex-wrap align-center ga-2">
      <v-btn-toggle
        :model-value="mode"
        mandatory
        density="compact"
        variant="outlined"
        color="primary"
        :disabled="disabled"
        @update:model-value="setMode"
      >
        <v-btn value="draft" size="small" prepend-icon="mdi-file-outline">
          {{ $t('publish.draft') }}
        </v-btn>
        <v-btn value="published" size="small" prepend-icon="mdi-earth">
          {{ $t('publish.published') }}
        </v-btn>
      </v-btn-toggle>

      <v-spacer />

      <v-chip
        :color="chip.color"
        :prepend-icon="chip.icon"
        variant="tonal"
        size="small"
      >
        {{ chip.text }}
      </v-chip>
    </div>

    <v-expand-transition>
      <div v-if="mode === 'published'" class="mt-2">
        <v-checkbox
          :model-value="scheduled"
          :label="$t('publish.scheduleForLater')"
          :disabled="disabled"
          density="compact"
          hide-details
          @update:model-value="setScheduled"
        />

        <v-text-field
          v-if="scheduled"
          :model-value="scheduleInput"
          type="datetime-local"
          :label="$t('publish.publishAt')"
          :min="minDateTime"
          :disabled="disabled"
          :hint="isFuture ? $t('publish.goesLiveAt', { date: formattedValue }) : $t('publish.publishAtHint')"
          persistent-hint
          density="compact"
          prepend-inner-icon="mdi-calendar-clock"
          hide-details="auto"
          class="mt-2"
          @update:model-value="setScheduleInput"
        />
      </div>
    </v-expand-transition>
  </div>
</template>

<script>
import { formatDate } from '@/plugins/formatDate.js'

const HOUR = 60 * 60 * 1000

// Anything Date can parse -> Date, null when empty or unparsable
const toDate = (value) => {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return isNaN(date.getTime()) ? null : date
}

// Date/ISO -> "YYYY-MM-DDTHH:mm" in the browser's local time
const toLocalInput = (value) => {
  const date = toDate(value)
  if (!date) return null
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

// "YYYY-MM-DDTHH:mm" (local) -> ISO string, null when empty or unparsable
const toIso = (value) => {
  const date = toDate(value)
  return date ? date.toISOString() : null
}

/**
 * Publication state of a page or post. `v-model` is the raw `published_at`
 * the API accepts: null for a draft, an ISO 8601 string otherwise — a past
 * timestamp is live, a future one is scheduled.
 *
 * Self-contained: no store or parent wiring beyond the model, so both the
 * dedicated page form and the data table drawer can drop it in.
 */
export default {
  name: 'PublishControl',
  props: {
    modelValue: {
      type: [String, Date],
      default: null
    },
    label: {
      type: String,
      default: ''
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:modelValue'],
  data() {
    return {
      mode: 'draft',
      scheduled: false,
      scheduleInput: null,
      // Last timestamp we know of, so "Published" without a schedule keeps an
      // existing publication date instead of stamping it with "now"
      lastTimestamp: null,
      // undefined until the first emit, so a null modelValue still seeds
      lastEmitted: undefined
    }
  },
  computed: {
    // The value this control would emit for its current inputs
    resolvedValue() {
      if (this.mode !== 'published') return null

      if (this.scheduled) {
        const iso = toIso(this.scheduleInput)
        if (iso) return iso
      }

      const known = toDate(this.lastTimestamp)
      if (known && known.getTime() <= Date.now()) return known.toISOString()

      return new Date().toISOString()
    },
    isFuture() {
      const date = toDate(this.resolvedValue)
      return !!date && date.getTime() > Date.now()
    },
    formattedValue() {
      return this.formatValue(this.resolvedValue)
    },
    chip() {
      if (!this.resolvedValue) {
        return { color: 'grey', icon: 'mdi-file-outline', text: this.$t('publish.draft') }
      }
      const date = this.formattedValue
      return this.isFuture
        ? { color: 'info', icon: 'mdi-clock-outline', text: this.$t('publish.scheduledFor', { date }) }
        : { color: 'success', icon: 'mdi-check-circle-outline', text: this.$t('publish.publishedOn', { date }) }
    },
    minDateTime() {
      return toLocalInput(new Date())
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
    }
  },
  methods: {
    seed(value) {
      const date = toDate(value)
      if (!date) {
        this.mode = 'draft'
        this.scheduled = false
        this.scheduleInput = null
        return
      }
      this.lastTimestamp = date.toISOString()
      this.mode = 'published'
      this.scheduled = date.getTime() > Date.now()
      this.scheduleInput = toLocalInput(date)
    },
    setMode(value) {
      if (this.disabled) return
      this.mode = value === 'published' ? 'published' : 'draft'
      this.apply()
    },
    setScheduled(value) {
      if (this.disabled) return
      this.scheduled = !!value
      if (this.scheduled && !this.scheduleInput) {
        this.scheduleInput = toLocalInput(new Date(Date.now() + HOUR))
      }
      this.apply()
    },
    setScheduleInput(value) {
      if (this.disabled) return
      this.scheduleInput = value || null
      this.apply()
    },
    apply() {
      const value = this.resolvedValue
      if (value) this.lastTimestamp = value
      this.lastEmitted = value
      this.$emit('update:modelValue', value)
    },
    formatValue(value) {
      if (!value) return ''
      try {
        const formatted = formatDate(value, 'Y-m-d H:i')
        if (formatted && formatted !== 'Invalid Date') return formatted
      } catch {
        // Stores may not be ready (or absent outside the app) — fall through
      }
      const date = toDate(value)
      return date ? date.toLocaleString() : ''
    }
  }
}
</script>
