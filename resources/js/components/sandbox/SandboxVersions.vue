<template>
  <v-navigation-drawer
    :model-value="true"
    location="right"
    temporary
    width="460"
    class="versions-drawer"
    @update:model-value="$emit('close')"
  >
    <!-- Header -->
    <template #prepend>
      <div class="drawer-header">
        <div class="d-flex align-center ga-2">
          <v-icon>mdi-history</v-icon>
          <span class="text-subtitle-1 font-weight-bold">History</span>
        </div>
        <v-btn icon variant="text" size="small" @click="$emit('close')">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>

      <v-tabs v-model="activeTab" density="compact" color="primary" grow>
        <v-tab value="versions">
          <v-icon start size="18">mdi-content-save-outline</v-icon>
          Versions
        </v-tab>
        <v-tab value="activity">
          <v-icon start size="18">mdi-timeline-text-outline</v-icon>
          Activity
        </v-tab>
      </v-tabs>
    </template>

    <!-- Tab Content -->
    <v-tabs-window v-model="activeTab" class="fill-height">
      <!-- Versions Tab -->
      <v-tabs-window-item value="versions" class="fill-height">
        <div class="tab-content">
          <div v-if="versionsLoading" class="d-flex justify-center pa-8">
            <v-progress-circular indeterminate color="primary" size="32" />
          </div>

          <div v-else-if="versions.length === 0" class="empty-state">
            <v-icon size="48" color="medium-emphasis" class="mb-3">mdi-content-save-off-outline</v-icon>
            <p class="text-body-2 text-medium-emphasis mb-1">No saved versions</p>
            <p class="text-caption text-disabled">Click "Save Version" in the editor to create a restore point</p>
          </div>

          <div v-else class="version-list">
            <v-card
              v-for="version in versions"
              :key="version.id"
              variant="outlined"
              class="version-card mb-2"
            >
              <v-card-text class="pa-3">
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-body-2 font-weight-medium">
                    {{ version.title || 'Untitled version' }}
                  </span>
                  <v-chip size="x-small" variant="tonal" color="primary">
                    v{{ version.id }}
                  </v-chip>
                </div>
                <div class="d-flex align-center ga-2 text-caption text-disabled mb-2">
                  <UserAvatar v-if="version.user" :user="version.user" class="mini-avatar" />
                  <span>{{ version.user?.username || 'Unknown' }}</span>
                  <span>&middot;</span>
                  <span>{{ formatDate(version.created_at) }}</span>
                </div>
                <div class="d-flex ga-2">
                  <v-btn
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-eye-outline"
                    @click="previewVersion(version)"
                  >
                    View
                  </v-btn>
                  <v-btn
                    size="x-small"
                    variant="tonal"
                    color="warning"
                    prepend-icon="mdi-file-compare"
                    @click="diffVersion(version)"
                  >
                    Diff
                  </v-btn>
                  <v-btn
                    size="x-small"
                    variant="tonal"
                    prepend-icon="mdi-restore"
                    :loading="restoring === version.id"
                    @click="restoreVersion(version)"
                  >
                    Restore
                  </v-btn>
                </div>
              </v-card-text>
            </v-card>

            <div v-if="versionsHasMore" class="text-center py-3">
              <v-btn variant="text" size="small" :loading="versionsLoadingMore" @click="loadMoreVersions">
                Load more
              </v-btn>
            </div>
          </div>
        </div>
      </v-tabs-window-item>

      <!-- Activity Tab -->
      <v-tabs-window-item value="activity" class="fill-height">
        <div class="tab-content">
          <div v-if="activityLoading" class="d-flex justify-center pa-8">
            <v-progress-circular indeterminate color="primary" size="32" />
          </div>

          <div v-else-if="revisions.length === 0" class="empty-state">
            <v-icon size="48" color="medium-emphasis" class="mb-3">mdi-timeline-text-outline</v-icon>
            <p class="text-body-2 text-medium-emphasis mb-1">No activity yet</p>
            <p class="text-caption text-disabled">Changes to the sandbox will appear here</p>
          </div>

          <v-timeline v-else density="compact" side="end" class="revision-timeline">
            <v-timeline-item
              v-for="revision in revisions"
              :key="revision.id"
              :dot-color="getActionColor(revision.action)"
              :icon="getActionIcon(revision.action)"
              size="small"
            >
              <v-card variant="text" class="pa-0">
                <div class="d-flex align-center ga-1 mb-1">
                  <v-chip :color="getActionColor(revision.action)" variant="tonal" size="x-small">
                    {{ revision.action }}
                  </v-chip>
                  <span class="text-caption text-disabled">
                    {{ formatDate(revision.created_at) }}
                  </span>
                </div>

                <div v-if="revision.executor" class="d-flex align-center ga-1 mb-1">
                  <UserAvatar :user="revision.executor" class="mini-avatar" />
                  <span class="text-caption">{{ revision.executor.username }}</span>
                </div>

                <div v-if="revision.diff && Object.keys(revision.diff).length > 0" class="diff-list mt-1">
                  <div v-for="(change, field) in revision.diff" :key="field" class="diff-item">
                    <span class="text-caption font-weight-medium diff-field">{{ formatFieldName(field) }}</span>
                    <div class="diff-values">
                      <span v-if="change.old_value" class="diff-old text-caption">
                        {{ truncateStr(String(change.old_value), 60) }}
                      </span>
                      <v-icon size="12" class="mx-1">mdi-arrow-right</v-icon>
                      <span class="diff-new text-caption">
                        {{ truncateStr(String(change.new_value), 60) }}
                      </span>
                    </div>
                  </div>
                </div>
              </v-card>
            </v-timeline-item>
          </v-timeline>

          <div v-if="activityHasMore" class="text-center py-3">
            <v-btn variant="text" size="small" :loading="activityLoadingMore" @click="loadMoreActivity">
              Load more
            </v-btn>
          </div>
        </div>
      </v-tabs-window-item>
    </v-tabs-window>

    <!-- Version Preview / Diff Dialog -->
    <v-dialog v-model="showPreview" max-width="900" scrollable>
      <v-card v-if="previewData">
        <v-card-title class="d-flex align-center justify-space-between pa-4">
          <div>
            <span>{{ previewData.title || 'Untitled version' }}</span>
            <div class="text-caption text-disabled">
              {{ formatDate(previewData.created_at) }} by {{ previewData.user?.username }}
            </div>
          </div>
          <div class="d-flex ga-2 align-center">
            <v-btn
              variant="tonal"
              size="small"
              prepend-icon="mdi-restore"
              :loading="restoring === previewData.id"
              @click="restoreVersion(previewData)"
            >
              Restore
            </v-btn>
            <v-btn icon variant="text" size="small" @click="showPreview = false">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-tabs v-model="previewTab" density="compact" color="primary">
          <v-tab value="preview">
            <v-icon start size="18">mdi-eye-outline</v-icon>
            Preview
          </v-tab>
          <v-tab value="diff">
            <v-icon start size="18">mdi-file-compare</v-icon>
            Changes
          </v-tab>
        </v-tabs>

        <v-divider />

        <v-card-text class="preview-body pa-0">
          <div v-if="previewLoading" class="d-flex justify-center pa-8">
            <v-progress-circular indeterminate color="primary" />
          </div>

          <template v-else>
            <!-- Preview tab -->
            <div v-show="previewTab === 'preview'" class="tiptap-preview pa-6" v-html="previewData.content"></div>

            <!-- Diff tab -->
            <div v-show="previewTab === 'diff'" class="diff-view pa-4">
              <div class="diff-legend d-flex ga-4 mb-4">
                <span class="d-flex align-center ga-1 text-caption">
                  <span class="legend-swatch legend-removed"></span> Removed
                </span>
                <span class="d-flex align-center ga-1 text-caption">
                  <span class="legend-swatch legend-added"></span> Added
                </span>
              </div>
              <div class="diff-content" v-html="diffHtml"></div>
            </div>
          </template>
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-navigation-drawer>
</template>

<script>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'
import UserAvatar from '../common/UserAvatar.vue'

/**
 * Convert HTML to plain text, preserving line breaks from block elements.
 */
function htmlToText(html) {
  if (!html) return ''
  return html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|h[1-6]|li|tr|blockquote|pre)>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&nbsp;/g, ' ')
    .replace(/&#39;/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/[ \t]+/g, ' ')
    .replace(/\n[ \t]+/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

/**
 * Tokenize text into words, keeping newlines as separate tokens.
 */
function tokenize(text) {
  const result = []
  for (const part of text.split(/(\n)/)) {
    if (part === '\n') {
      result.push('\n')
    } else {
      for (const word of part.split(/\s+/)) {
        if (word.length > 0) result.push(word)
      }
    }
  }
  return result
}

function escapeHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

/**
 * LCS-based diff for two arrays of strings/chars.
 */
function diffArrays(oldArr, newArr) {
  const m = oldArr.length, n = newArr.length
  const dp = Array.from({ length: m + 1 }, () => new Uint16Array(n + 1))
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      dp[i][j] = oldArr[i - 1] === newArr[j - 1]
        ? dp[i - 1][j - 1] + 1
        : Math.max(dp[i - 1][j], dp[i][j - 1])
    }
  }
  const result = []
  let i = m, j = n
  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && oldArr[i - 1] === newArr[j - 1]) {
      result.unshift({ type: 'eq', val: oldArr[i - 1] })
      i--; j--
    } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
      result.unshift({ type: 'ins', val: newArr[j - 1] })
      j--
    } else {
      result.unshift({ type: 'del', val: oldArr[i - 1] })
      i--
    }
  }
  return result
}

/**
 * Character-level diff between two words. Returns HTML with <del>/<ins>.
 */
function charLevelDiff(oldWord, newWord) {
  const diff = diffArrays([...oldWord], [...newWord])
  let html = '', buf = '', curType = null
  const flush = () => {
    if (!buf) return
    if (curType === 'del') html += `<del>${escapeHtml(buf)}</del>`
    else if (curType === 'ins') html += `<ins>${escapeHtml(buf)}</ins>`
    else html += escapeHtml(buf)
    buf = ''
  }
  for (const d of diff) {
    if (d.type !== curType) { flush(); curType = d.type }
    buf += d.val
  }
  flush()
  return html
}

/**
 * For adjacent del+ins groups, pair words and show character-level changes.
 */
function renderPairedDiff(delVals, insVals) {
  const delWords = delVals.filter(v => v !== '\n')
  const insWords = insVals.filter(v => v !== '\n')
  const newlines = Math.max(
    delVals.filter(v => v === '\n').length,
    insVals.filter(v => v === '\n').length
  )

  let html = ''
  const pairs = Math.min(delWords.length, insWords.length)

  for (let i = 0; i < pairs; i++) {
    html += charLevelDiff(delWords[i], insWords[i]) + ' '
  }
  for (let i = pairs; i < delWords.length; i++) {
    html += `<del>${escapeHtml(delWords[i])}</del> `
  }
  for (let i = pairs; i < insWords.length; i++) {
    html += `<ins>${escapeHtml(insWords[i])}</ins> `
  }
  for (let i = 0; i < newlines; i++) html += '<br>'

  return html
}

/**
 * Word-level diff with character-level refinement for changed words.
 * Accepts raw HTML, converts to text preserving line breaks.
 */
function computeWordDiff(oldHtml, newHtml) {
  const oldTokens = tokenize(htmlToText(oldHtml))
  const newTokens = tokenize(htmlToText(newHtml))
  const diff = diffArrays(oldTokens, newTokens)

  // Group consecutive same-type tokens
  const groups = []
  for (const d of diff) {
    if (groups.length && groups[groups.length - 1].type === d.type) {
      groups[groups.length - 1].vals.push(d.val)
    } else {
      groups.push({ type: d.type, vals: [d.val] })
    }
  }

  let html = ''
  for (let i = 0; i < groups.length; i++) {
    const g = groups[i]
    if (g.type === 'eq') {
      for (const v of g.vals) html += v === '\n' ? '<br>' : escapeHtml(v) + ' '
    } else if (g.type === 'del') {
      const next = groups[i + 1]
      if (next && next.type === 'ins') {
        // Paired change — show character-level diff
        html += renderPairedDiff(g.vals, next.vals)
        i++
      } else {
        for (const v of g.vals) html += v === '\n' ? '<br>' : `<del>${escapeHtml(v)}</del> `
      }
    } else {
      for (const v of g.vals) html += v === '\n' ? '<br>' : `<ins>${escapeHtml(v)}</ins> `
    }
  }

  return html.trim()
}

export default {
  name: 'SandboxVersions',

  components: {
    UserAvatar,
  },

  props: {
    sandbox: {
      type: Object,
      required: true,
    },
    currentContent: {
      type: String,
      default: '',
    },
  },

  emits: ['close', 'restore'],

  setup(props, { emit }) {
    const activeTab = ref('versions')
    const previewTab = ref('preview')

    // Versions state
    const versions = ref([])
    const versionsLoading = ref(true)
    const versionsLoadingMore = ref(false)
    const versionsHasMore = ref(false)
    const versionsPage = ref(1)
    const restoring = ref(null)

    // Activity state
    const revisions = ref([])
    const activityLoading = ref(true)
    const activityLoadingMore = ref(false)
    const activityHasMore = ref(false)
    const activityPage = ref(1)

    // Preview / diff state
    const showPreview = ref(false)
    const previewData = ref(null)
    const previewLoading = ref(false)
    const diffHtml = ref('')

    // --- Versions ---
    const loadVersions = async () => {
      versionsLoading.value = true
      try {
        const response = await axios.get(`/api/sandbox/${props.sandbox.uuid}/versions`, {
          params: { page: versionsPage.value },
        })
        versions.value = response.data.data
        versionsHasMore.value = response.data.current_page < response.data.last_page
      } catch (error) {
        console.error('Failed to load versions:', error)
      } finally {
        versionsLoading.value = false
      }
    }

    const loadMoreVersions = async () => {
      versionsLoadingMore.value = true
      versionsPage.value++
      try {
        const response = await axios.get(`/api/sandbox/${props.sandbox.uuid}/versions`, {
          params: { page: versionsPage.value },
        })
        versions.value = [...versions.value, ...response.data.data]
        versionsHasMore.value = response.data.current_page < response.data.last_page
      } catch (error) {
        console.error('Failed to load more versions:', error)
      } finally {
        versionsLoadingMore.value = false
      }
    }

    const restoreVersion = async (version) => {
      if (!confirm('Restore this version? Current state will be saved first.')) return
      restoring.value = version.id
      try {
        const response = await axios.post(
          `/api/sandbox/${props.sandbox.uuid}/versions/${version.id}/restore`
        )
        showPreview.value = false
        emit('restore', response.data.content)
      } catch (error) {
        console.error('Failed to restore version:', error)
        alert('Failed to restore version')
      } finally {
        restoring.value = null
      }
    }

    const openPreview = async (version, tab = 'preview') => {
      previewData.value = version
      previewLoading.value = true
      previewTab.value = tab
      showPreview.value = true
      diffHtml.value = ''

      try {
        const response = await axios.get(
          `/api/sandbox/${props.sandbox.uuid}/versions/${version.id}`
        )
        previewData.value = response.data.version

        // Compute diff (handles HTML → text conversion internally)
        diffHtml.value = computeWordDiff(
          response.data.version.content || '',
          props.currentContent || ''
        )
      } catch (error) {
        console.error('Failed to load version:', error)
      } finally {
        previewLoading.value = false
      }
    }

    const previewVersion = (version) => openPreview(version, 'preview')
    const diffVersion = (version) => openPreview(version, 'diff')

    // --- Activity ---
    const loadActivity = async () => {
      activityLoading.value = true
      try {
        const response = await axios.get(`/api/sandbox/${props.sandbox.uuid}/history`, {
          params: { page: activityPage.value },
        })
        revisions.value = response.data.data
        activityHasMore.value = response.data.current_page < response.data.last_page
      } catch (error) {
        console.error('Failed to load activity:', error)
      } finally {
        activityLoading.value = false
      }
    }

    const loadMoreActivity = async () => {
      activityLoadingMore.value = true
      activityPage.value++
      try {
        const response = await axios.get(`/api/sandbox/${props.sandbox.uuid}/history`, {
          params: { page: activityPage.value },
        })
        revisions.value = [...revisions.value, ...response.data.data]
        activityHasMore.value = response.data.current_page < response.data.last_page
      } catch (error) {
        console.error('Failed to load more activity:', error)
      } finally {
        activityLoadingMore.value = false
      }
    }

    // --- Helpers ---
    const getActionColor = (action) => {
      const map = { created: 'success', updated: 'info', deleted: 'error', restored: 'warning' }
      return map[action] || 'default'
    }

    const getActionIcon = (action) => {
      const map = { created: 'mdi-plus', updated: 'mdi-pencil', deleted: 'mdi-delete', restored: 'mdi-restore' }
      return map[action] || 'mdi-circle-small'
    }

    const formatFieldName = (field) => field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())

    const truncateStr = (str, len) => {
      if (!str) return ''
      return str.length > len ? str.substring(0, len) + '...' : str
    }

    const formatDate = (dateStr) => {
      if (!dateStr) return ''
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
      return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
    }

    watch(activeTab, (tab) => {
      if (tab === 'versions' && versions.value.length === 0) loadVersions()
      if (tab === 'activity' && revisions.value.length === 0) loadActivity()
    })

    onMounted(() => loadVersions())

    return {
      activeTab,
      previewTab,
      versions,
      versionsLoading,
      versionsLoadingMore,
      versionsHasMore,
      restoring,
      revisions,
      activityLoading,
      activityLoadingMore,
      activityHasMore,
      showPreview,
      previewData,
      previewLoading,
      diffHtml,
      loadMoreVersions,
      restoreVersion,
      previewVersion,
      diffVersion,
      loadMoreActivity,
      getActionColor,
      getActionIcon,
      formatFieldName,
      truncateStr,
      formatDate,
    }
  },
}
</script>

<style lang="scss" scoped>
.drawer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid rgb(var(--v-border-color));
}

.tab-content {
  padding: 0.75rem;
  overflow-y: auto;
  height: 100%;
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
}

.version-card {
  transition: border-color 0.15s;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
  }
}

.mini-avatar {
  :deep(.v-avatar) {
    width: 20px !important;
    height: 20px !important;
    font-size: 0.5rem;

    .text-h4, .text-h5 {
      font-size: 0.5rem !important;
    }
  }
}

.revision-timeline {
  padding: 0 0.5rem;

  :deep(.v-timeline-item__body) {
    padding-top: 0;
  }
}

.diff-list {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-radius: 6px;
  padding: 0.5rem;
}

.diff-item {
  margin-bottom: 0.25rem;

  &:last-child {
    margin-bottom: 0;
  }
}

.diff-field {
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.diff-values {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.125rem;
}

.diff-old {
  color: rgb(var(--v-theme-error));
  text-decoration: line-through;
  opacity: 0.7;
}

.diff-new {
  color: rgb(var(--v-theme-success));
  font-weight: 500;
}

// Preview dialog
.preview-body {
  max-height: 70vh;
  overflow-y: auto;
}

.tiptap-preview {
  :deep(h1), :deep(h2), :deep(h3) {
    line-height: 1.2;
    margin-bottom: 0.5em;
  }

  :deep(p) {
    margin-bottom: 0.75em;
  }

  :deep(pre) {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    overflow-x: auto;
    margin-bottom: 0.75em;
  }

  :deep(blockquote) {
    border-left: 3px solid rgb(var(--v-theme-primary));
    padding-left: 1rem;
    color: rgba(var(--v-theme-on-surface), 0.7);
    margin-bottom: 0.75em;
  }

  :deep(code) {
    background: rgba(var(--v-theme-on-surface), 0.08);
    padding: 0.15em 0.3em;
    border-radius: 3px;
    font-size: 0.9em;
  }

  :deep(ul), :deep(ol) {
    padding-left: 1.5em;
    margin-bottom: 0.75em;
  }
}

// Diff view
.diff-legend {
  padding-bottom: 0.75rem;
  border-bottom: 1px solid rgb(var(--v-border-color));
}

.legend-swatch {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 3px;

  &.legend-removed {
    background: rgba(var(--v-theme-error), 0.15);
    border: 1px solid rgb(var(--v-theme-error));
  }

  &.legend-added {
    background: rgba(var(--v-theme-success), 0.15);
    border: 1px solid rgb(var(--v-theme-success));
  }
}

.diff-content {
  font-family: inherit;
  line-height: 1.8;
  word-wrap: break-word;

  :deep(del) {
    background: rgba(var(--v-theme-error), 0.15);
    color: rgb(var(--v-theme-error));
    text-decoration: line-through;
    padding: 1px 2px;
    border-radius: 2px;
  }

  :deep(ins) {
    background: rgba(var(--v-theme-success), 0.15);
    color: rgb(var(--v-theme-success));
    text-decoration: none;
    font-weight: 600;
    padding: 1px 2px;
    border-radius: 2px;
  }
}
</style>
