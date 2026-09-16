<template>
  <div v-show="!isFullscreen" class="sandbox-page d-flex flex-column">
    <page-header
      :title="$t('sandbox.title')"
      :subtitle="$t('sandbox.subtitle')"
      icon="mdi-file-document-edit-outline"
      fluid
      class="mb-0 flex-shrink-0"
    >
      <template #actions>
        <v-btn color="primary" variant="elevated" prepend-icon="mdi-plus" @click="openCreate">
          {{ $t('sandbox.newSandbox') }}
        </v-btn>
      </template>
    </page-header>

    <v-container fluid class="sandbox-layout pa-0 flex-grow-1">
      <v-row no-gutters class="fill-height">
        <!-- Left Panel: Sandbox List -->
        <v-col
          cols="12"
          sm="5"
          md="4"
          lg="3"
          class="left-panel"
        >
          <SandboxList
            ref="listRef"
            compact
            :selected-uuid="selectedUuid"
            @select="onSelect"
            @created="onCreated"
          />
        </v-col>

        <!-- Right Panel: Editor or Empty State -->
        <v-col
          cols="12"
          sm="7"
          md="8"
          lg="9"
          class="right-panel"
        >
          <!--
            Single editor instance. When not fullscreen, Teleport is disabled so
            the editor renders here inside the right panel. When fullscreen,
            it teleports to body — escaping all layout chrome (nav, toolbar, footer).
          -->
          <Teleport to="body" :disabled="!isFullscreen">
            <div v-if="selectedUuid" :class="{ 'sandbox-fullscreen': isFullscreen }">
              <SandboxEditor
                :uuid="selectedUuid"
                :key="selectedUuid"
                :is-fullscreen="isFullscreen"
                @toggle-fullscreen="toggleFullscreen"
              />
            </div>
          </Teleport>

          <!-- Empty State -->
          <div v-if="!selectedUuid" class="d-flex align-center justify-center fill-height">
            <empty-state
              icon="mdi-file-document-edit-outline"
              :title="$t('sandbox.selectSandbox')"
              :text="$t('sandbox.selectSandboxText')"
            />
          </div>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script>
import { ref, watch, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SandboxList from './SandboxList.vue'
import SandboxEditor from './SandboxEditor.vue'
import PageHeader from '../common/PageHeader.vue'
import EmptyState from '../common/EmptyState.vue'

export default {
  name: 'SandboxDashboard',

  components: {
    SandboxList,
    SandboxEditor,
    PageHeader,
    EmptyState,
  },

  props: {
    uuid: {
      type: String,
      default: null,
    },
  },

  setup(props) {
    const route = useRoute()
    const router = useRouter()
    const selectedUuid = ref(props.uuid || route.params.uuid || null)
    const isFullscreen = ref(false)
    const listRef = ref(null)

    const onSelect = (uuid) => {
      selectedUuid.value = uuid
      router.push(`/sandbox/${uuid}`)
    }

    const onCreated = (sandbox) => {
      selectedUuid.value = sandbox.uuid
      router.push(`/sandbox/${sandbox.uuid}`)
    }

    // The create dialog lives in the list; the page CTA just opens it
    const openCreate = () => {
      listRef.value?.openCreate()
    }

    const toggleFullscreen = () => {
      isFullscreen.value = !isFullscreen.value
      document.body.style.overflow = isFullscreen.value ? 'hidden' : ''
    }

    // Clean up body overflow on unmount
    onUnmounted(() => {
      document.body.style.overflow = ''
    })

    // Also exit fullscreen via Escape key
    const onKeydown = (e) => {
      if (e.key === 'Escape' && isFullscreen.value) {
        toggleFullscreen()
      }
    }
    document.addEventListener('keydown', onKeydown)
    onUnmounted(() => {
      document.removeEventListener('keydown', onKeydown)
    })

    // Sync selectedUuid with route param changes
    watch(
      () => route.params.uuid,
      (newUuid) => {
        selectedUuid.value = newUuid || null
      }
    )

    return {
      selectedUuid,
      isFullscreen,
      listRef,
      onSelect,
      onCreated,
      openCreate,
      toggleFullscreen,
    }
  },
}
</script>

<style lang="scss" scoped>
.sandbox-page {
  height: calc(100vh - 64px);
  min-height: 400px;
}

.sandbox-layout {
  min-height: 0;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.left-panel {
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  overflow-y: auto;
  height: 100%;
}

.right-panel {
  background: rgb(var(--v-theme-background));
  height: 100%;
  overflow-y: auto;
}

@media (max-width: 600px) {
  .left-panel {
    border-right: none;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    height: auto;
    max-height: 40vh;
  }

  .right-panel {
    height: 60vh;
  }
}
</style>

<!-- Unscoped for the teleported fullscreen overlay -->
<style lang="scss">
.sandbox-fullscreen {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgb(var(--v-theme-background));
  display: flex;
  flex-direction: column;
}
</style>
