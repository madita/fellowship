<template>
  <v-dialog :model-value="true" max-width="600" scrollable @update:model-value="onDialogToggle">
    <v-card>
      <v-card-title class="text-h6 d-flex align-center">
        <v-icon start>mdi-share-variant-outline</v-icon>
        {{ $t('sandbox.collaborators.title') }}
      </v-card-title>
      <v-divider />

      <v-card-text>
        <!-- Share link -->
        <v-text-field
          ref="linkInput"
          :model-value="shareLink"
          :label="$t('sandbox.collaborators.shareLink')"
          :hint="sandbox.visibility === 'public'
            ? $t('sandbox.collaborators.linkPublicHint')
            : $t('sandbox.collaborators.linkPrivateHint')"
          persistent-hint
          readonly
          class="mb-4"
        >
          <template #append-inner>
            <v-btn
              :icon="copied ? 'mdi-check' : 'mdi-content-copy'"
              :color="copied ? 'success' : undefined"
              variant="text"
              size="small"
              :title="copied ? $t('sandbox.collaborators.copied') : $t('common.copy')"
              @click="copyLink"
            />
          </template>
        </v-text-field>

        <!-- Add collaborator -->
        <div class="text-subtitle-1 font-weight-medium mb-2">
          {{ $t('sandbox.collaborators.addPeople') }}
        </div>
        <v-text-field
          v-model="searchQuery"
          :placeholder="$t('sandbox.collaborators.searchPlaceholder')"
          prepend-inner-icon="mdi-magnify"
          hide-details
          :disabled="adding"
          @input="searchUsers"
        />
        <v-card v-if="searchResults.length > 0" variant="outlined" class="mt-2">
          <v-list density="compact" class="py-0">
            <v-list-item
              v-for="user in searchResults"
              :key="user.id"
              :title="user.username"
              :subtitle="user.email"
              :disabled="adding"
              @click="addCollaborator(user)"
            >
              <template #prepend>
                <v-progress-circular v-if="adding" indeterminate size="20" width="2" class="mr-3" />
                <v-icon v-else>mdi-account-plus-outline</v-icon>
              </template>
            </v-list-item>
          </v-list>
        </v-card>

        <!-- Current collaborators -->
        <div class="text-subtitle-1 font-weight-medium mt-6 mb-1">
          {{ $t('sandbox.collaborators.peopleWithAccess') }}
        </div>
        <v-list class="py-0">
          <!-- Owner -->
          <v-list-item :title="sandbox.owner?.username">
            <template #prepend>
              <UserAvatar v-if="sandbox.owner" :user="sandbox.owner" size="36" class="mr-3" />
            </template>
            <template #subtitle>
              <span class="text-success">{{ $t('sandbox.collaborators.owner') }}</span>
            </template>
          </v-list-item>

          <!-- Collaborators -->
          <v-list-item
            v-for="collab in collaborators"
            :key="collab.id"
            :title="collab.username"
          >
            <template #prepend>
              <UserAvatar :user="collab" size="36" class="mr-3" />
            </template>
            <template v-if="!collab.pivot?.accepted_at" #subtitle>
              <span class="text-warning">{{ $t('sandbox.collaborators.pending') }}</span>
            </template>
            <template #append>
              <div class="d-flex align-center ga-1">
                <v-select
                  v-model="collab.pivot.role"
                  :items="roleOptions"
                  item-title="text"
                  item-value="value"
                  density="compact"
                  hide-details
                  class="role-select"
                  :loading="isBusy(collab.id)"
                  :disabled="isBusy(collab.id)"
                  @update:model-value="updateRole(collab)"
                />
                <v-btn
                  icon="mdi-close"
                  variant="text"
                  size="small"
                  color="error"
                  :loading="isBusy(collab.id)"
                  :disabled="isBusy(collab.id)"
                  :title="$t('dialogs.confirm.remove')"
                  @click="removeCollaborator(collab)"
                />
              </div>
            </template>
          </v-list-item>
        </v-list>

        <empty-state
          v-if="collaborators.length === 0"
          compact
          icon="mdi-account-multiple-plus-outline"
          :title="$t('sandbox.collaborators.noCollaborators')"
          :text="$t('sandbox.collaborators.noCollaboratorsText')"
        />
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="flat" @click="$emit('close')">
          {{ $t('common.done') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useDialog } from '@/composables/useDialog.js'
import UserAvatar from '../common/UserAvatar.vue'
import EmptyState from '../common/EmptyState.vue'

export default {
  name: 'SandboxCollaborators',

  components: {
    UserAvatar,
    EmptyState,
  },

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
    const searchQuery = ref('')
    const searchResults = ref([])
    const collaborators = ref([])
    const copied = ref(false)
    const linkInput = ref(null)
    const adding = ref(false)
    // Collaborator ids with a role change or removal in flight
    const busyIds = ref([])
    // Last role the server accepted per collaborator, to revert a failed change
    const lastRoles = {}
    let searchTimeout = null

    const roleOptions = computed(() => [
      { value: 'viewer', text: t('sandbox.collaborators.roles.viewer') },
      { value: 'editor', text: t('sandbox.collaborators.roles.editor') },
      { value: 'admin', text: t('sandbox.collaborators.roles.admin') },
    ])

    const isBusy = (id) => busyIds.value.includes(id)
    const setBusy = (id, busy) => {
      busyIds.value = busy
        ? [...busyIds.value, id]
        : busyIds.value.filter((i) => i !== id)
    }
    const rememberRoles = () => {
      collaborators.value.forEach((c) => { lastRoles[c.id] = c.pivot?.role })
    }

    const shareLink = computed(() => {
      return `${window.location.origin}/sandbox/${props.sandbox.slug}`
    })

    onMounted(() => {
      collaborators.value = props.sandbox.collaborators || []
      rememberRoles()
    })

    const onDialogToggle = (open) => {
      if (!open) emit('close')
    }

    const copyLink = async () => {
      try {
        await navigator.clipboard.writeText(shareLink.value)
        copied.value = true
        setTimeout(() => copied.value = false, 2000)
      } catch (error) {
        // Fallback for older browsers
        linkInput.value?.$el?.querySelector('input')?.select()
        document.execCommand('copy')
        copied.value = true
        setTimeout(() => copied.value = false, 2000)
      }
    }

    const searchUsers = () => {
      clearTimeout(searchTimeout)
      if (searchQuery.value.length < 2) {
        searchResults.value = []
        return
      }

      searchTimeout = setTimeout(async () => {
        try {
          const response = await axios.post('/api/users/search', {
            query: searchQuery.value,
          })
          // Filter out existing collaborators and owner
          const existingIds = [
            props.sandbox.user_id,
            ...collaborators.value.map(c => c.id)
          ]
          searchResults.value = response.data.filter(
            user => !existingIds.includes(user.id)
          )
        } catch (error) {
          console.error('Search failed:', error)
        }
      }, 300)
    }

    const addCollaborator = async (user) => {
      if (adding.value) return

      adding.value = true
      try {
        const response = await axios.post(
          `/api/sandbox/${props.sandbox.uuid}/collaborators`,
          { user_id: user.id, role: 'editor' }
        )
        collaborators.value = response.data.collaborators
        rememberRoles()
        searchQuery.value = ''
        searchResults.value = []
        emit('updated')
      } catch (error) {
        console.error('Failed to add collaborator:', error)
        await dialog.requestError(error, t('sandbox.collaborators.addFailed'))
      } finally {
        adding.value = false
      }
    }

    const updateRole = async (collab) => {
      if (isBusy(collab.id)) return

      setBusy(collab.id, true)
      try {
        await axios.post(`/api/sandbox/${props.sandbox.uuid}/collaborators`, {
          user_id: collab.id,
          role: collab.pivot.role,
        })
        lastRoles[collab.id] = collab.pivot.role
      } catch (error) {
        console.error('Failed to update role:', error)
        if (lastRoles[collab.id]) collab.pivot.role = lastRoles[collab.id]
        await dialog.requestError(error, t('sandbox.collaborators.roleUpdateFailed'))
      } finally {
        setBusy(collab.id, false)
      }
    }

    const removeCollaborator = async (collab) => {
      if (isBusy(collab.id)) return

      const confirmed = await dialog.confirmDelete(
        t('sandbox.collaborators.removeConfirm', { name: collab.username }),
        {
          title: t('sandbox.collaborators.removeTitle'),
          confirmationText: t('dialogs.confirm.remove'),
        }
      )
      if (!confirmed) return

      setBusy(collab.id, true)
      try {
        await axios.delete(
          `/api/sandbox/${props.sandbox.uuid}/collaborators/${collab.id}`
        )
        collaborators.value = collaborators.value.filter(c => c.id !== collab.id)
        delete lastRoles[collab.id]
        emit('updated')
      } catch (error) {
        console.error('Failed to remove collaborator:', error)
        await dialog.requestError(error, t('sandbox.collaborators.removeFailed'))
      } finally {
        setBusy(collab.id, false)
      }
    }

    return {
      searchQuery,
      searchResults,
      collaborators,
      copied,
      linkInput,
      adding,
      roleOptions,
      isBusy,
      shareLink,
      onDialogToggle,
      copyLink,
      searchUsers,
      addCollaborator,
      updateRole,
      removeCollaborator,
    }
  },
}
</script>

<style lang="scss" scoped>
.role-select {
  width: 150px;
}
</style>
