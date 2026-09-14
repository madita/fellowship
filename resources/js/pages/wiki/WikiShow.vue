<template>
    <div class="wiki-page-container">
        <!-- Loading State -->
        <loading-state v-if="loading" :text="$t('wiki.loadingPage')" />

        <!-- Content -->
        <div v-else class="wiki-content">
            <page-header
                :title="wikipage.title || $t('wiki.untitledPage')"
                :subtitle="pageSubtitle"
                icon="mdi-book-open-page-variant-outline"
                :back-to="{ name: 'wiki-index' }"
            >
                <template v-if="isAdmin || (authStore.isLoggedIn && mode)" #actions>
                    <v-chip
                        v-if="isAdmin"
                        :color="isApproved ? 'success' : 'warning'"
                        size="small"
                        variant="tonal"
                    >
                        {{ isApproved ? $t('wiki.approved') : $t('wiki.pendingApproval') }}
                    </v-chip>
                    <v-btn
                        v-if="authStore.isLoggedIn && mode"
                        color="primary"
                        variant="elevated"
                        :prepend-icon="mode === 'edit' ? 'mdi-pencil' : 'mdi-plus'"
                        :to="`/wiki/${slug}/${mode}`"
                    >
                        {{ mode === 'edit' ? $t('wiki.editPage') : $t('wiki.createPage') }}
                    </v-btn>
                </template>

                <template v-if="(redirect.length > 0 && redirect !== 'no') || (isAdmin && !isApproved) || message">
                    <!-- Redirect Notice -->
                    <v-alert
                        v-if="redirect.length > 0 && redirect !== 'no'"
                        type="info"
                        density="compact"
                        class="mb-3"
                    >
                        <template v-slot:prepend>
                            <v-icon>mdi-arrow-right</v-icon>
                        </template>
                        {{ $t('wiki.redirectedFrom') }}
                        <v-btn
                            :href="`/wiki/${redirect}?redirect=no`"
                            variant="text"
                            size="small"
                            class="text-decoration-underline ml-1"
                        >
                            {{ redirect }}
                        </v-btn>
                    </v-alert>

                    <!-- Pending Approval Banner (admin only) -->
                    <v-alert
                        v-if="isAdmin && !isApproved"
                        type="warning"
                        class="mb-3"
                    >
                        <template v-slot:prepend>
                            <v-icon>mdi-clock-alert-outline</v-icon>
                        </template>
                        <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                            <span>{{ $t('wiki.pendingApproval') }}</span>
                            <v-btn
                                color="success"
                                variant="flat"
                                size="small"
                                :loading="approving"
                                prepend-icon="mdi-check"
                                @click="approveWiki"
                            >
                                {{ $t('wiki.approve') }}
                            </v-btn>
                        </div>
                    </v-alert>

                    <!-- Status Message -->
                    <v-alert
                        v-if="message"
                        :type="mode === 'create' ? 'warning' : 'info'"
                        class="mb-0"
                    >
                        <template v-slot:prepend>
                            <v-icon>{{ mode === 'create' ? 'mdi-plus-circle' : 'mdi-information' }}</v-icon>
                        </template>
                        {{ message }}
                        <template v-if="mode === 'create'">
                            <v-btn
                                color="warning"
                                variant="flat"
                                size="small"
                                class="ml-3"
                                :to="`/wiki/${slug}/create`"
                            >
                                {{ $t('wiki.createPage') }}
                            </v-btn>
                        </template>
                    </v-alert>
                </template>
            </page-header>

            <v-container fluid>
                <!-- Main Content Area -->
                <v-row>
                    <!-- Article Content -->
                    <v-col cols="12" lg="9">
                        <v-card class="content-card" elevation="2" rounded="lg">
                            <v-card-text class="pa-8">
                                <!-- Main Content -->
                                <div
                                    class="wiki-content-body"
                                    v-html="wikipage.content"
                                />

                                <!-- Description -->
                                <div
                                    v-if="wikipage.description"
                                    class="wiki-description mt-6 pt-6"
                                    v-html="wikipage.description"
                                />
                            </v-card-text>
                        </v-card>

                        <related-content-list
                            v-if="wiki?.id && mode === 'edit'"
                            class="mt-6"
                            type="App\Models\Wiki"
                            :id="wiki.id"
                            :title="wikipage.title || ''"
                            :can-edit="canLinkContent"
                        />
                    </v-col>

                    <!-- Sidebar -->
                    <v-col cols="12" lg="3">
                        <div class="sidebar-content">
                            <!-- Categories Section -->
                            <v-card v-if="terms && terms.length > 0" class="mb-4" elevation="1" rounded="lg">
                                <v-card-title class="text-subtitle-1 font-weight-medium pb-2">
                                    <v-icon class="mr-2" color="primary">mdi-folder-outline</v-icon>
                                    {{ $t('wiki.categories') }}
                                </v-card-title>
                                <v-card-text class="pt-0">
                                    <div class="d-flex flex-wrap ga-2">
                                        <v-chip
                                            v-for="term in terms"
                                            :key="`term-${term.id}`"
                                            variant="tonal"
                                            color="primary"
                                            size="small"
                                            clickable
                                            @click="goTo(term.slug, 'wiki-category')"
                                            class="category-chip"
                                        >
                                            <template v-slot:prepend>
                                                <v-icon size="16">mdi-folder</v-icon>
                                            </template>
                                            {{ term.title }}
                                        </v-chip>
                                    </div>
                                </v-card-text>
                            </v-card>

                            <!-- Tags Section -->
                            <v-card v-if="tags && tags.length > 0" class="mb-4" elevation="1" rounded="lg">
                                <v-card-title class="text-subtitle-1 font-weight-medium pb-2">
                                    <v-icon class="mr-2" color="secondary">mdi-tag-outline</v-icon>
                                    {{ $t('wiki.tags') }}
                                </v-card-title>
                                <v-card-text class="pt-0">
                                    <div class="d-flex flex-wrap ga-2">
                                        <v-chip
                                            v-for="tag in tags"
                                            :key="`tag-${tag.id}`"
                                            variant="tonal"
                                            color="secondary"
                                            size="small"
                                            class="tag-chip"
                                        >
                                            <template v-slot:prepend>
                                                <v-icon size="16">mdi-pound</v-icon>
                                            </template>
                                            {{ tag.title }}
                                        </v-chip>
                                    </div>
                                </v-card-text>
                            </v-card>

                            <!-- Page Info -->
                            <v-card elevation="1" rounded="lg">
                                <v-card-title class="text-subtitle-1 font-weight-medium pb-2">
                                    <v-icon class="mr-2" color="success">mdi-information-outline</v-icon>
                                    {{ $t('wiki.pageInformation') }}
                                </v-card-title>
                                <v-card-text class="pt-0">
                                    <div class="page-info">
                                        <div class="info-item mb-2">
                                            <v-icon size="16" class="mr-2">mdi-calendar</v-icon>
                                            <span class="text-caption">{{ $t('wiki.created') }}: {{ $formatDate(wikipage.created_at) }}</span>
                                        </div>
                                        <div class="info-item mb-2">
                                            <v-icon size="16" class="mr-2">mdi-calendar</v-icon>
                                            <span class="text-caption">{{ $t('wiki.lastModified') }}: {{ $formatDate(wikipage.updated_at) }}{{ formatDate(wikipage.updated_at, 'dd.MM.yyyy HH:ii') }}</span>
                                        </div>
                                        <div class="info-item mb-2">
                                            <v-icon size="16" class="mr-2">mdi-account</v-icon>
                                            <span class="text-caption">{{ $t('wiki.author') }}: {{ wikiuser?.username || $t('wiki.anonymous') }}</span>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/store/authStore.js'
import { useUserStore } from '@/store/userStore.js'
import { useDateFormat } from '@/plugins/formatDate.js' // Adjust path as needed
import axios from 'axios'
import { useDialog } from '@/composables/useDialog.js'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import RelatedContentList from '@/components/common/RelatedContentList.vue'

const { t } = useI18n()
const dialog = useDialog()

// Props (if any would be passed to this component)
const props = defineProps({
    // Add any props if needed
})

// Composables
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const userStore = useUserStore()
const { formatDate } = useDateFormat()

// Reactive state
const loading = ref(false)
const wikipage = ref({})
const wiki = ref({})
const redirect = ref('')
const mode = ref('')
const message = ref('')
const parents = ref([])
const terms = ref([])
const tags = ref([])
const wikiuser = ref({})
const slug = ref('')
const isApproved = ref(true)
const approving = ref(false)

// Computed properties
const authenticated = computed(() => authStore.isLoggedIn)
const user = computed(() => authStore.user)
const isAdmin = computed(() => userStore.user?.isAdmin || false)
// Same right as the wiki-edit route (manage-page); the API has the final say
const canLinkContent = computed(() => {
    if (!authStore.isLoggedIn || mode.value !== 'edit') return false
    const permissions = userStore.permissions || []
    return isAdmin.value || permissions.some(permission => (permission?.name ?? permission) === 'manage-page')
})
const pageSubtitle = computed(() => {
    if (mode.value !== 'edit') return ''
    const parts = [`${t('wiki.author')}: ${wikiuser.value?.username || t('wiki.anonymous')}`]
    if (wikipage.value?.updated_at) {
        parts.push(`${t('wiki.lastModified')}: ${formatDate(wikipage.value.updated_at)}`)
    }
    return parts.join(' · ')
})

// Methods
const getWikiPage = async () => {
    try {
        const response = await axios.get(`/api/wiki/${slug.value}`)

        wikipage.value = response.data.page
        wiki.value = response.data.wiki

        if (wiki.value.status === 'redirect' && redirect.value !== 'no') {
            const pattern = /href="([^"]+)"/
            const match = wikipage.value.content.match(pattern)
            const link = match ? match[1] : ''
            router.push(`${link}?redirect=${slug.value}`)
            return
        }

        parents.value = response.data.parents
        terms.value = response.data.terms || []
        tags.value = response.data.tags || []
        wikiuser.value = response.data.user || {}
        isApproved.value = response.data.is_approved ?? true
        mode.value = 'edit'
        loading.value = false

    } catch (error) {
        if (error.response?.status === 404) {
            wikipage.value = error.response.data.page
            loading.value = false
            message.value = t('wiki.pageNotExistsCreate')
            mode.value = 'create'
        }
        if (error.response?.status === 401) {
            router.push('/auth/signin')
        }
    }
}

const goTo = (slugParam, type) => {
    router.push({ name: type, params: { slug: slugParam } })
}

const approveWiki = async () => {
    if (approving.value) return
    try {
        approving.value = true
        await axios.post(`/api/wiki/${slug.value}/approve`)
        isApproved.value = true
        approving.value = false
        await dialog.success(t('wiki.approveSuccess'))
    } catch (error) {
        await dialog.requestError(error, t('wiki.approveError'))
    } finally {
        approving.value = false
    }
}

const unapproveWiki = async () => {
    if (approving.value) return
    try {
        approving.value = true
        await axios.post(`/api/wiki/${slug.value}/unapprove`)
        isApproved.value = false
        approving.value = false
        await dialog.success(t('wiki.unapproveSuccess'))
    } catch (error) {
        await dialog.requestError(error, t('wiki.approveError'))
    } finally {
        approving.value = false
    }
}

// Locale change handler
const onLocaleChange = () => {
    if (slug.value) {
        loading.value = true
        getWikiPage()
    }
}

// Lifecycle
onMounted(() => {
    loading.value = true

    if (route.params.slug) {
        slug.value = route.params.slug
        getWikiPage()
    }

    if (route.query.redirect) {
        redirect.value = route.query.redirect
    }

    // Listen for locale changes to refetch content in new language
    window.addEventListener('locale-changed', onLocaleChange)
})

onUnmounted(() => {
    // Clean up locale change listener
    window.removeEventListener('locale-changed', onLocaleChange)
})
</script>

<style scoped>
.content-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    backdrop-filter: blur(10px);
}

.sidebar-content {
    position: sticky;
    top: 24px;
}

.category-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2);
}

.tag-chip {
    transition: all 0.2s ease;
}

.tag-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--v-theme-secondary), 0.2);
}

.info-item {
    display: flex;
    align-items: center;
}

/* Responsive design */
@media (max-width: 768px) {
    .sidebar-content {
        position: static;
        margin-top: 24px;
    }
}
</style>

<style>
/* Global styles for wiki content rendering */
.wiki-content-body {
    line-height: 1.7;
    color: rgb(var(--v-theme-on-surface));
}

.wiki-content-body > *:first-child {
    margin-top: 0 !important;
}

.wiki-content-body > *:last-child {
    margin-bottom: 0 !important;
}

/* Headings */
.wiki-content-body h1,
.wiki-content-body h2,
.wiki-content-body h3,
.wiki-content-body h4,
.wiki-content-body h5,
.wiki-content-body h6 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-weight: 600;
    line-height: 1.3;
    color: rgb(var(--v-theme-on-surface));
}

.wiki-content-body h1 {
    font-size: 2.25rem;
    border-bottom: 2px solid rgb(var(--v-border-color));
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
}

.wiki-content-body h2 {
    font-size: 1.875rem;
    border-bottom: 1px solid rgb(var(--v-border-color));
    padding-bottom: 0.25rem;
}

.wiki-content-body h3 {
    font-size: 1.5rem;
}

.wiki-content-body h4 {
    font-size: 1.25rem;
}

.wiki-content-body h5 {
    font-size: 1.125rem;
}

.wiki-content-body h6 {
    font-size: 1rem;
    color: rgb(var(--v-theme-on-surface-variant));
}

/* Paragraphs */
.wiki-content-body p {
    margin: 1rem 0;
    line-height: 1.7;
}

/* Lists */
.wiki-content-body ul,
.wiki-content-body ol {
    margin: 1rem 0;
    padding-left: 2rem;
}

.wiki-content-body li {
    margin: 0.5rem 0;
    line-height: 1.6;
}

.wiki-content-body ul li {
    list-style-type: disc;
}

.wiki-content-body ol li {
    list-style-type: decimal;
}

/* Links */
.wiki-content-body a {
    color: rgb(var(--v-theme-primary));
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: all 0.2s ease;
}

.wiki-content-body a:hover {
    border-bottom-color: rgb(var(--v-theme-primary));
    padding: 0 2px;
    border-radius: 4px;
}

/* Code */
.wiki-content-body code {
    background: rgba(var(--v-theme-surface-variant), 0.8);
    color: rgb(var(--v-theme-primary));
    padding: 0.2rem 0.4rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    border: 1px solid rgba(var(--v-border-color), 0.5);
}

.wiki-content-body pre {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-on-surface));
    padding: 1.5rem;
    border-radius: 12px;
    overflow-x: auto;
    margin: 1.5rem 0;
    border: 1px solid rgb(var(--v-border-color));
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    line-height: 1.6;
}

.wiki-content-body pre code {
    background: none;
    border: none;
    padding: 0;
    color: inherit;
}

/* Blockquotes */
.wiki-content-body blockquote {
    margin: 1.5rem 0;
    padding: 1rem 1.5rem;
    background: rgba(var(--v-theme-primary), 0.05);
    border-left: 4px solid rgb(var(--v-theme-primary));
    border-radius: 0 12px 12px 0;
    font-style: italic;
    color: rgb(var(--v-theme-on-surface-variant));
}

.wiki-content-body blockquote p {
    margin: 0.5rem 0;
}

.wiki-content-body blockquote p:first-child {
    margin-top: 0;
}

.wiki-content-body blockquote p:last-child {
    margin-bottom: 0;
}

/* Images */
.wiki-content-body img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    margin: 1.5rem 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.wiki-content-body img:hover {
    transform: scale(1.02);
}

/* Tables - Enhanced Styling */
.wiki-content-body table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 2rem 0;
    background: rgb(var(--v-theme-surface));
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgb(var(--v-border-color));
}

.wiki-content-body table th {
    min-width: 1em;
    border: 1px solid rgb(var(--v-border-color));
    padding: 2px 4px;
    vertical-align: top;
    box-sizing: border-box;
    position: relative;
    background: rgb(var(--v-theme-surface));
}

.wiki-content-body table th:first-child {
    border-top-left-radius: 12px;
}

.wiki-content-body table th:last-child {
    border-top-right-radius: 12px;
}

.wiki-content-body table td {
    padding: 0.175rem 0.25rem;
    border-bottom: 1px solid rgba(var(--v-border-color), 0.5);
    vertical-align: top;
    transition: background-color 0.2s ease;
}

.wiki-content-body table tr:hover td {
    background: rgba(var(--v-theme-primary), 0.03);
}

.wiki-content-body table tr:last-child td {
    border-bottom: none;
}

.wiki-content-body table tr:last-child td:first-child {
    border-bottom-left-radius: 12px;
}

.wiki-content-body table tr:last-child td:last-child {
    border-bottom-right-radius: 12px;
}

/* Alternating row colors for better readability */
.wiki-content-body table tr:nth-child(even) td {
    background: rgba(var(--v-theme-surface-variant), 0.3);
}

.wiki-content-body table tr:nth-child(even):hover td {
    background: rgba(var(--v-theme-primary), 0.05);
}

/* Horizontal Rules */
.wiki-content-body hr {
    border: none;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgb(var(--v-border-color)), transparent);
    margin: 2rem 0;
}

/* Wiki Description Styling */
.wiki-description {
    border-top: 1px solid rgb(var(--v-border-color));
    color: rgb(var(--v-theme-on-surface-variant));
    font-style: italic;
}

/* Responsive table */
@media (max-width: 768px) {
    .wiki-content-body table {
        font-size: 0.875rem;
    }

    .wiki-content-body table th,
    .wiki-content-body table td {
        padding: 0.75rem 1rem;
    }

    .wiki-content-body table th {
        font-size: 0.8rem;
    }
}

/* Print styles */
@media print {
    .wiki-content-body table {
        box-shadow: none;
        border: 2px solid #000;
    }

    .wiki-content-body table th {
        background: #f0f0f0 !important;
        color: #000 !important;
    }

    .wiki-content-body table td {
        border: 1px solid #000;
    }
}
</style>
