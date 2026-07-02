<template>
  <v-container v-show="!isFullscreen" fluid class="sandbox-layout pa-0">
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
        <div v-if="!selectedUuid" class="empty-state d-flex align-center justify-center fill-height">
          <div class="text-center">
            <v-icon size="64" color="medium-emphasis" class="mb-4">
              mdi-file-document-edit-outline
            </v-icon>
            <h3 class="text-h5 mb-2">Select a sandbox</h3>
            <p class="text-body-1 text-medium-emphasis">
              Choose a sandbox from the list or create a new one to start collaborating
            </p>
          </div>
        </div>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import { ref, watch, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SandboxList from './SandboxList.vue'
import SandboxEditor from './SandboxEditor.vue'

export default {
  name: 'SandboxDashboard',

  components: {
    SandboxList,
    SandboxEditor,
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

    const onSelect = (uuid) => {
      selectedUuid.value = uuid
      router.push(`/sandbox/${uuid}`)
    }

    const onCreated = (sandbox) => {
      selectedUuid.value = sandbox.uuid
      router.push(`/sandbox/${sandbox.uuid}`)
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
      onSelect,
      onCreated,
      toggleFullscreen,
    }
  },
}
</script>

<style lang="scss" scoped>
.sandbox-layout {
  height: calc(100vh - 64px);
  min-height: 400px;
}

.left-panel {
  border-right: 1px solid rgb(var(--v-border-color));
  background: rgb(var(--v-theme-surface));
  overflow-y: auto;
  height: 100%;
}

.right-panel {
  background: rgb(var(--v-theme-background));
  height: 100%;
  overflow-y: auto;
}

.empty-state {
  height: 100%;
  min-height: 400px;
}

@media (max-width: 600px) {
  .left-panel {
    border-right: none;
    border-bottom: 1px solid rgb(var(--v-border-color));
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
