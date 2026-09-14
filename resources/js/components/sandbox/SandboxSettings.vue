<template>
  <v-dialog :model-value="true" max-width="600" scrollable @update:model-value="onDialogToggle">
    <v-card>
      <v-card-title class="text-h6 d-flex align-center">
        <v-icon start>mdi-cog-outline</v-icon>
        {{ $t('sandbox.settings.title') }}
      </v-card-title>
      <v-divider />

      <v-card-text>
        <v-form @submit.prevent="saveSettings">
          <v-text-field
            v-model="form.title"
            :label="$t('common.title')"
            required
            :disabled="busy"
            class="mb-3"
          />

          <v-textarea
            v-model="form.description"
            :label="$t('common.description')"
            rows="3"
            :disabled="busy"
            class="mb-3"
          />

          <v-select
            v-model="form.visibility"
            :label="$t('sandbox.visibility.label')"
            :items="visibilityOptions"
            item-title="text"
            item-value="value"
            :disabled="busy"
          />

          <div class="text-subtitle-1 font-weight-medium mt-4 mb-1">
            {{ $t('sandbox.settings.editorSettings') }}
          </div>
          <v-checkbox
            v-model="form.settings.showCursors"
            :label="$t('sandbox.settings.showCursors')"
            density="compact"
            hide-details
            :disabled="busy"
          />
          <v-checkbox
            v-model="form.settings.autoSave"
            :label="$t('sandbox.settings.autoSave')"
            density="compact"
            hide-details
            :disabled="busy"
          />
          <v-checkbox
            v-model="form.settings.allowComments"
            :label="$t('sandbox.settings.allowComments')"
            density="compact"
            hide-details
            :disabled="busy"
          />

          <v-divider class="my-4" />

          <div class="text-subtitle-1 font-weight-medium text-error mb-2">
            {{ $t('sandbox.settings.dangerZone') }}
          </div>
          <v-btn
            color="error"
            variant="tonal"
            prepend-icon="mdi-delete-outline"
            :loading="deleting"
            :disabled="busy"
            @click="confirmDelete"
          >
            {{ $t('sandbox.settings.deleteSandbox') }}
          </v-btn>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" :disabled="busy" @click="saveSettings">
          {{ $t('common.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useSettingsStore } from '@/store/settingStore.js'
import { useDialog } from '@/composables/useDialog.js'

export default {
  name: 'SandboxSettings',

  props: {
    sandbox: {
      type: Object,
      required: true,
    },
  },

  emits: ['close', 'updated'],

  setup(props, { emit }) {
    const { t } = useI18n()
    const dialog = useDialog()
    const settingsStore = useSettingsStore()
    const publicEnabled = computed(() => settingsStore.sandboxPublicEnabled)
    const saving = ref(false)
    const deleting = ref(false)
    const busy = computed(() => saving.value || deleting.value)
    const form = reactive({
      title: '',
      description: '',
      visibility: 'private',
      settings: {
        showCursors: true,
        autoSave: true,
        allowComments: true,
      },
    })

    const visibilityOptions = computed(() => {
      const options = [
        { value: 'private', text: t('sandbox.visibility.private') },
        { value: 'members', text: t('sandbox.visibility.members') },
      ]
      if (publicEnabled.value) {
        options.push({ value: 'public', text: t('sandbox.visibility.public') })
      }
      return options
    })

    onMounted(() => {
      form.title = props.sandbox.title
      form.description = props.sandbox.description || ''
      form.visibility = props.sandbox.visibility
      form.settings = { ...form.settings, ...(props.sandbox.settings || {}) }
    })

    // Outside click / Esc closes the dialog unless a request is running
    const onDialogToggle = (open) => {
      if (!open && !busy.value) emit('close')
    }

    const saveSettings = async () => {
      if (busy.value) return

      saving.value = true
      try {
        const response = await axios.put(`/api/sandbox/${props.sandbox.uuid}`, form)
        emit('updated', response.data.sandbox)
        await dialog.success(t('sandbox.settings.saved'))
      } catch (error) {
        console.error('Failed to save settings:', error)
        await dialog.requestError(error, t('sandbox.settings.saveFailed'))
      } finally {
        saving.value = false
      }
    }

    const confirmDelete = async () => {
      if (busy.value) return

      const confirmed = await dialog.confirmDelete(t('sandbox.settings.deleteConfirm'), {
        title: t('sandbox.settings.deleteTitle'),
      })
      if (!confirmed) return

      deleting.value = true
      try {
        await axios.delete(`/api/sandbox/${props.sandbox.uuid}`)
        window.location.href = '/sandbox'
      } catch (error) {
        console.error('Failed to delete:', error)
        await dialog.requestError(error, t('sandbox.settings.deleteFailed'))
      } finally {
        deleting.value = false
      }
    }

    return {
      form,
      saving,
      deleting,
      busy,
      publicEnabled,
      visibilityOptions,
      onDialogToggle,
      saveSettings,
      confirmDelete,
    }
  },
}
</script>
