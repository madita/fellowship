<template>
  <div :class="['sandbox-list', { 'sandbox-list--compact': compact }]">
    <page-header
      v-if="!compact"
      :title="$t('sandbox.title')"
      :subtitle="$t('sandbox.subtitle')"
      icon="mdi-file-document-edit-outline"
      fluid
    >
      <template #actions>
        <v-btn color="primary" variant="elevated" prepend-icon="mdi-plus" @click="showCreateModal = true">
          {{ $t('sandbox.newSandbox') }}
        </v-btn>
      </template>
    </page-header>

    <v-container fluid :class="{ 'pa-0': compact }">
      <!-- Filters -->
      <div :class="['d-flex flex-wrap ga-2', compact ? 'pa-3 sandbox-list__filters' : 'mb-6']">
        <v-chip
          v-for="filter in filters"
          :key="filter.value"
          :color="activeFilter === filter.value ? 'primary' : undefined"
          :variant="activeFilter === filter.value ? 'elevated' : 'tonal'"
          size="small"
          @click="activeFilter = filter.value"
        >
          {{ $t(filter.label) }}
        </v-chip>
      </div>

      <!-- Loading -->
      <loading-state v-if="loading" :compact="compact" :text="compact ? '' : $t('sandbox.loading')" />

      <!-- Empty State -->
      <empty-state
        v-else-if="filteredSandboxes.length === 0"
        :compact="compact"
        icon="mdi-file-document-outline"
        :title="$t('sandbox.noSandboxes')"
        :text="compact ? '' : $t('sandbox.noSandboxesText')"
      >
        <template #actions>
          <v-btn color="primary" variant="flat" size="small" prepend-icon="mdi-plus" @click="showCreateModal = true">
            {{ $t('sandbox.createSandbox') }}
          </v-btn>
        </template>
      </empty-state>

      <!-- Compact: Vertical list -->
      <div v-else-if="compact" class="sandbox-compact-list">
        <div
          v-for="sandbox in filteredSandboxes"
          :key="sandbox.id"
          :class="['sandbox-item', { selected: selectedUuid === sandbox.uuid }]"
          @click="selectSandbox(sandbox)"
        >
          <div class="d-flex justify-space-between align-center ga-2">
            <span class="item-title text-body-2 font-weight-medium">{{ sandbox.title }}</span>
            <div class="d-flex align-center ga-1">
              <v-chip
                v-if="sandbox.relationship === 'owner'"
                color="primary"
                variant="tonal"
                size="x-small"
                label
              >{{ $t('sandbox.mine') }}</v-chip>
              <v-chip
                v-else-if="sandbox.relationship === 'shared'"
                color="info"
                variant="tonal"
                size="x-small"
                label
              >{{ $t('sandbox.sharedWithMe') }}</v-chip>
              <v-icon :color="getVisibilityColor(sandbox.visibility)" size="14">
                {{ getVisibilityIcon(sandbox.visibility) }}
              </v-icon>
            </div>
          </div>
          <div class="d-flex justify-space-between mt-1">
            <span class="text-caption text-disabled">
              <template v-if="sandbox.relationship !== 'owner'">{{ sandbox.owner?.username }}</template>
              <template v-else>{{ $t('sandbox.byYou') }}</template>
            </span>
            <span v-if="sandbox.last_edited_at" class="text-caption text-disabled">{{ formatDate(sandbox.last_edited_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Full: Grid layout -->
      <div v-else class="sandbox-grid">
        <v-card
          v-for="sandbox in filteredSandboxes"
          :key="sandbox.id"
          variant="outlined"
          rounded="lg"
          class="sandbox-card"
          @click="openSandbox(sandbox)"
        >
          <v-card-text>
            <div class="d-flex align-center flex-wrap ga-2 mb-3">
              <v-chip
                :color="getVisibilityColor(sandbox.visibility)"
                variant="tonal"
                size="x-small"
                :prepend-icon="getVisibilityIcon(sandbox.visibility)"
              >
                {{ sandbox.visibility }}
              </v-chip>
              <v-chip
                v-if="sandbox.relationship === 'owner'"
                color="primary"
                variant="tonal"
                size="x-small"
                prepend-icon="mdi-account"
              >
                {{ $t('sandbox.mine') }}
              </v-chip>
              <v-chip
                v-else-if="sandbox.relationship === 'shared'"
                color="info"
                variant="tonal"
                size="x-small"
                prepend-icon="mdi-account-multiple"
              >
                {{ $t('sandbox.sharedWithMe') }}
              </v-chip>
            </div>

            <h3 class="text-subtitle-1 font-weight-medium mb-1">{{ sandbox.title }}</h3>
            <p class="text-body-2 text-medium-emphasis card-description mb-3">{{ sandbox.description || $t('sandbox.noDescription') }}</p>

            <div class="d-flex ga-4 mb-3">
              <span class="text-caption text-disabled d-flex align-center ga-1">
                <v-icon size="14">mdi-account-group-outline</v-icon>
                {{ sandbox.collaborators_count || 0 }}
              </span>
              <span v-if="sandbox.last_edited_at" class="text-caption text-disabled">
                {{ formatDate(sandbox.last_edited_at) }}
              </span>
            </div>

            <v-divider class="mb-3" />

            <div class="d-flex align-center ga-2">
              <UserAvatar v-if="sandbox.owner" :user="sandbox.owner" size="24" />
              <span class="text-body-2">{{ sandbox.owner?.username }}</span>
            </div>
          </v-card-text>
        </v-card>
      </div>

      <!-- Pagination (full mode only) -->
      <div v-if="!compact && totalPages > 1" class="d-flex justify-center align-center ga-4 mt-8">
        <v-btn
          icon="mdi-chevron-left"
          variant="text"
          size="small"
          :disabled="currentPage <= 1"
          @click="loadPage(currentPage - 1)"
        />
        <span class="text-body-2 text-medium-emphasis">
          {{ $t('sandbox.pageOf', { current: currentPage, total: totalPages }) }}
        </span>
        <v-btn
          icon="mdi-chevron-right"
          variant="text"
          size="small"
          :disabled="currentPage >= totalPages"
          @click="loadPage(currentPage + 1)"
        />
      </div>
    </v-container>

    <!-- Create Dialog -->
    <v-dialog v-model="showCreateModal" max-width="600" persistent>
      <v-card>
        <v-card-title class="text-h6">{{ $t('sandbox.create.title') }}</v-card-title>
        <v-divider />

        <v-card-text>
          <v-text-field
            v-model="newSandbox.title"
            :label="$t('common.title') + ' *'"
            autofocus
            :disabled="creating"
            class="mb-3"
          />

          <v-textarea
            v-model="newSandbox.description"
            :label="$t('common.description')"
            rows="3"
            :placeholder="$t('sandbox.create.descriptionPlaceholder')"
            :disabled="creating"
            class="mb-3"
          />

          <v-select
            v-model="newSandbox.visibility"
            :label="$t('sandbox.visibility.label')"
            :items="visibilityOptions"
            item-title="text"
            item-value="value"
            :disabled="creating"
          />
        </v-card-text>

        <v-divider />
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="creating" @click="showCreateModal = false">{{ $t('common.cancel') }}</v-btn>
          <v-btn color="primary" variant="flat" :loading="creating" :disabled="!newSandbox.title.trim()" @click="createSandbox">
            {{ $t('sandbox.createSandbox') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useSettingsStore } from '@/store/settingStore.js'
import { useDialog } from '@/composables/useDialog.js'
import UserAvatar from '../common/UserAvatar.vue'
import PageHeader from '../common/PageHeader.vue'
import EmptyState from '../common/EmptyState.vue'
import LoadingState from '../common/LoadingState.vue'

export default {
  name: 'SandboxList',

  components: {
    UserAvatar,
    PageHeader,
    EmptyState,
    LoadingState,
  },

  props: {
    compact: {
      type: Boolean,
      default: false,
    },
    selectedUuid: {
      type: String,
      default: null,
    },
  },

  emits: ['select', 'created'],

  setup(props, { emit }) {
    const router = useRouter()
    const { t } = useI18n()
    const dialog = useDialog()
    const sandboxes = ref([])
    const loading = ref(true)
    const creating = ref(false)
    const showCreateModal = ref(false)
    const currentPage = ref(1)
    const totalPages = ref(1)
    const activeFilter = ref('all')
    const currentUserId = ref(null)

    // Labels are translation keys, resolved in the template
    const filters = [
      { value: 'all', label: 'sandbox.filters.all' },
      { value: 'owned', label: 'sandbox.filters.owned' },
      { value: 'shared', label: 'sandbox.filters.shared' },
    ]

    const settingsStore = useSettingsStore()

    const visibilityOptions = computed(() => {
      const options = [
        { value: 'private', text: t('sandbox.visibility.private') },
        { value: 'members', text: t('sandbox.visibility.members') },
      ]
      if (settingsStore.sandboxPublicEnabled) {
        options.push({ value: 'public', text: t('sandbox.visibility.public') })
      }
      return options
    })

    const newSandbox = ref({
      title: '',
      description: '',
      visibility: 'private',
    })

    const filteredSandboxes = computed(() => sandboxes.value)

    const loadSandboxes = async (page = 1) => {
      loading.value = true
      try {
        const params = { page }
        if (activeFilter.value !== 'all') {
          params.filter = activeFilter.value
        }
        const response = await axios.get('/api/sandbox', { params })
        sandboxes.value = response.data.data
        currentPage.value = response.data.current_page
        totalPages.value = response.data.last_page
      } catch (error) {
        console.error('Failed to load sandboxes:', error)
      } finally {
        loading.value = false
      }
    }

    const loadPage = (page) => {
      if (page >= 1 && page <= totalPages.value) {
        loadSandboxes(page)
      }
    }

    const loadCurrentUser = async () => {
      // User ID is still useful for other logic if needed
      try {
        const response = await axios.get('/api/user')
        currentUserId.value = response.data.id
      } catch (error) {
        // Not logged in
      }
    }

    // Exposed so the parent page's CTA can open the create dialog
    const openCreate = () => {
      showCreateModal.value = true
    }

    const createSandbox = async () => {
      if (!newSandbox.value.title.trim() || creating.value) return

      creating.value = true
      try {
        const response = await axios.post('/api/sandbox', newSandbox.value)
        const sandbox = response.data.sandbox
        showCreateModal.value = false
        newSandbox.value = { title: '', description: '', visibility: 'private' }

        // Re-fetch list so it shows the new sandbox
        await loadSandboxes(1)

        if (props.compact) {
          emit('created', sandbox)
        } else {
          router.push(`/sandbox/${sandbox.uuid}`)
        }
      } catch (error) {
        console.error('Failed to create sandbox:', error)
        await dialog.requestError(error, t('sandbox.create.failed'))
      } finally {
        creating.value = false
      }
    }

    const selectSandbox = (sandbox) => {
      emit('select', sandbox.uuid)
    }

    const openSandbox = (sandbox) => {
      if (props.compact) {
        emit('select', sandbox.uuid)
      } else {
        router.push(`/sandbox/${sandbox.uuid}`)
      }
    }

    const getVisibilityIcon = (visibility) => {
      const icons = {
        private: 'mdi-lock',
        members: 'mdi-account-group',
        public: 'mdi-earth',
      }
      return icons[visibility] || 'mdi-file'
    }

    const getVisibilityColor = (visibility) => {
      const colors = {
        private: 'error',
        members: 'warning',
        public: 'success',
      }
      return colors[visibility] || 'default'
    }

    const formatDate = (dateStr) => {
      const date = new Date(dateStr)
      const now = new Date()
      const diffMs = now - date
      const diffMins = Math.floor(diffMs / 60000)
      const diffHours = Math.floor(diffMs / 3600000)
      const diffDays = Math.floor(diffMs / 86400000)

      if (diffMins < 1) return 'Just now'
      if (diffMins < 60) return `${diffMins}m ago`
      if (diffHours < 24) return `${diffHours}h ago`
      if (diffDays < 7) return `${diffDays}d ago`

      return date.toLocaleDateString()
    }

    watch(activeFilter, () => {
      loadSandboxes(1)
    })

    onMounted(async () => {
      await loadCurrentUser()
      await loadSandboxes()
    })

    return {
      sandboxes,
      loading,
      creating,
      showCreateModal,
      currentPage,
      totalPages,
      activeFilter,
      filters,
      visibilityOptions,
      filteredSandboxes,
      newSandbox,
      currentUserId,
      loadPage,
      openCreate,
      createSandbox,
      selectSandbox,
      openSandbox,
      getVisibilityIcon,
      getVisibilityColor,
      formatDate,
    }
  },
}
</script>

<style lang="scss" scoped>
.sandbox-list--compact .sandbox-list__filters {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

// Compact list styles
.sandbox-compact-list {
  overflow-y: auto;
}

.sandbox-item {
  padding: 0.75rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: background 0.15s;

  &:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
  }

  &.selected {
    background: rgba(var(--v-theme-primary), 0.08);
    border-left: 3px solid rgb(var(--v-theme-primary));
    padding-left: calc(1rem - 3px);
  }
}

.item-title {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

// Grid styles (full mode)
.sandbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 24px;
}

.sandbox-card {
  cursor: pointer;
  transition: border-color 0.15s;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
  }
}

.card-description {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
