<template>
    <div class="wiki-edit-container">
        <v-container class="py-6">
            <!-- Progress Indicator -->
<!--            <div class="progress-section mb-6">-->
<!--                <v-stepper-->
<!--                    v-model="currentStep"-->
<!--                    :items="steps"-->
<!--                    color="primary"-->
<!--                    variant="horizontal"-->
<!--                    class="elevation-2"-->
<!--                >-->
<!--                    <template #item.1>-->
<!--                        <v-stepper-item-->
<!--                            :complete="!!wikipage.title && !!wikipage.content"-->
<!--                            title="Content"-->
<!--                            subtitle="Title and body"-->
<!--                            value="1"-->
<!--                        />-->
<!--                    </template>-->
<!--                    <template #item.2>-->
<!--                        <v-stepper-item-->
<!--                            :complete="hasChanges"-->
<!--                            title="Organization"-->
<!--                            subtitle="Categories and tags"-->
<!--                            value="2"-->
<!--                        />-->
<!--                    </template>-->
<!--                    <template #item.3>-->
<!--                        <v-stepper-item-->
<!--                            title="Review"-->
<!--                            subtitle="Save changes"-->
<!--                            value="3"-->
<!--                        />-->
<!--                    </template>-->
<!--                </v-stepper>-->
<!--            </div>-->

            <!-- Header Section -->
            <div class="edit-header mb-6">
                <v-row align="center">
                    <v-col cols="12" md="8">
                        <div class="d-flex align-center mb-2">
                            <v-icon color="primary" size="28" class="mr-3">mdi-pencil</v-icon>
                            <h1 class="edit-title text-h4 font-weight-bold">
                                {{ wikipage.title ? $t('wiki.editing', { title: wikipage.title }) : $t('wiki.editPage') }}
                            </h1>
                        </div>
                        <p class="text-subtitle-1 text-medium-emphasis">
                            {{ lastModified ? $t('wiki.lastModifiedAt', { date: lastModified }) : $t('wiki.makeChangesAndSave') }}
                        </p>
                    </v-col>
                    <v-col cols="12" md="4" class="text-right">
                        <div class="header-actions">
                            <v-btn
                                v-if="authenticated"
                                variant="outlined"
                                color="secondary"
                                prepend-icon="mdi-arrow-left"
                                :to="`/wiki/${slug}`"
                                class="mr-2"
                                :disabled="saving"
                            >
                                {{ $t('common.cancel') }}
                            </v-btn>
                            <v-btn
                                color="primary"
                                variant="elevated"
                                prepend-icon="mdi-content-save"
                                @click="handleSave"
                                :loading="saving"
                                :disabled="!hasChanges || !canSave"
                                class="save-btn"
                            >
                                {{ saving ? $t('wiki.saving') : $t('wiki.saveChanges') }}
                            </v-btn>
                        </div>
                    </v-col>
                </v-row>

                <!-- Change Indicator -->
                <v-alert
                    v-if="hasChanges"
                    type="warning"
                    variant="tonal"
                    class="mt-4"
                    closable
                    @click:close="dismissChangeAlert = true"
                >
                    <template #prepend>
                        <v-icon>mdi-pencil-circle</v-icon>
                    </template>
                    <div class="d-flex justify-space-between align-center">
                        <span>{{ $t('wiki.unsavedChanges') }}</span>
                        <div class="ml-4">
                            <v-btn
                                variant="text"
                                size="small"
                                @click="autoSave"
                                :loading="autoSaving"
                                prepend-icon="mdi-content-save-outline"
                            >
                                {{ $t('wiki.autoSave') }}
                            </v-btn>
                            <v-btn
                                variant="text"
                                size="small"
                                @click="discardChanges"
                                prepend-icon="mdi-undo"
                            >
                                {{ $t('wiki.discard') }}
                            </v-btn>
                        </div>
                    </div>
                </v-alert>

                <!-- Success Message with Actions -->
                <v-alert
                    v-if="message"
                    type="success"
                    variant="tonal"
                    class="mt-4"
                    closable
                    @click:close="message = ''"
                >
                    <template #prepend>
                        <v-icon>mdi-check-circle</v-icon>
                    </template>
                    <div class="d-flex justify-space-between align-center">
                        <span>{{ message }}</span>
                        <div class="ml-4">
                            <v-btn
                                color="success"
                                variant="elevated"
                                size="small"
                                :to="`/wiki/${slug}`"
                                prepend-icon="mdi-eye"
                                class="mr-2"
                            >
                                {{ $t('wiki.viewPage') }}
                            </v-btn>
                            <v-btn
                                variant="outlined"
                                size="small"
                                @click="continueEditing"
                                prepend-icon="mdi-pencil"
                            >
                                {{ $t('wiki.keepEditing') }}
                            </v-btn>
                        </div>
                    </div>
                </v-alert>

                <!-- Error Display -->
                <v-alert
                    v-if="editing.errors && editing.errors.length > 0"
                    type="error"
                    variant="tonal"
                    class="mt-4"
                    closable
                    @click:close="editing.errors = []"
                >
                    <template #prepend>
                        <v-icon>mdi-alert-circle</v-icon>
                    </template>
                    <div class="error-content">
                        <h4 class="mb-2">{{ $t('wiki.pleaseFixIssues') }}</h4>
                        <ul class="mb-0">
                            <li v-for="(error, index) in editing.errors" :key="index">{{ error }}</li>
                        </ul>
                    </div>
                </v-alert>
            </div>

            <!-- Main Content with Responsive Layout -->
            <v-row>
                <!-- Editor Section -->
                <v-col cols="12" :lg="showPreview ? 8 : 12">
                    <v-card class="editor-card" elevation="2" rounded="lg">
                        <v-card-title class="editor-card-title d-flex justify-space-between align-center">
                            <div class="d-flex align-center">
                                <v-icon class="mr-2" color="primary">mdi-file-document-edit</v-icon>
                                <span>{{ $t('wiki.contentEditor') }}</span>
                            </div>
                            <div class="editor-actions">
                                <v-btn
                                    variant="text"
                                    size="small"
                                    @click="showPreview = !showPreview"
                                    :prepend-icon="showPreview ? 'mdi-eye-off' : 'mdi-eye'"
                                >
                                    {{ showPreview ? $t('wiki.hidePreview') : $t('wiki.showPreview') }}
                                </v-btn>
                                <v-btn
                                    variant="text"
                                    size="small"
                                    @click="showHistory = true"
                                    prepend-icon="mdi-history"
                                >
                                    {{ $t('wiki.history') }}
                                </v-btn>
                            </div>
                        </v-card-title>

                        <v-card-text class="pa-6">
                            <!-- Enhanced Title Field -->
                            <div class="title-section mb-6">
                                <v-text-field
                                    v-model="wikipage.title"
                                    :label="$t('wiki.pageTitle')"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-inner-icon="mdi-format-title"
                                    :placeholder="$t('wiki.enterTitle')"
                                    class="title-field"
                                    :error="titleError"
                                    :error-messages="titleErrorMessage"
                                    @input="onTitleChange"
                                    @blur="validateTitle"
                                    clearable
                                    counter="100"
                                    maxlength="100"
                                >
                                    <template #append-inner>
                                        <v-tooltip :text="$t('wiki.titleChangeTooltip')">
                                            <template #activator="{ props }">
                                                <v-icon v-bind="props" size="small" color="info">mdi-help-circle</v-icon>
                                            </template>
                                        </v-tooltip>
                                    </template>
                                </v-text-field>

                                <!-- Change Detection -->
                                <div v-if="originalTitle !== wikipage.title" class="change-indicator mt-2">
                                    <v-chip size="small" color="warning" variant="tonal">
                                        <v-icon start size="16">mdi-pencil</v-icon>
                                        {{ $t('wiki.titleChangedFrom', { title: originalTitle }) }}
                                    </v-chip>
                                </div>
                            </div>

                            <!-- Content Editor with Enhanced Features -->
                            <div class="content-section">
                                <div class="content-header mb-3 d-flex justify-space-between align-center">
                                    <div class="d-flex align-center">
                                        <v-icon class="mr-2" size="20" color="primary">mdi-text</v-icon>
                                        <span class="text-subtitle-1 font-weight-medium">{{ $t('wiki.pageContent') }}</span>
                                    </div>
                                    <div class="content-stats">
                                        <v-chip size="small" variant="tonal" color="info">
                                            {{ $t('wiki.wordsChars', { words: contentStats.words, chars: contentStats.chars }) }}
                                        </v-chip>
                                        <v-chip v-if="contentChanged" size="small" variant="tonal" color="warning" class="ml-2">
                                            {{ $t('wiki.modified') }}
                                        </v-chip>
                                    </div>
                                </div>

                                <!-- Content Toolbar -->
                                <div class="content-toolbar mb-3">
                                    <v-btn-group density="compact" variant="outlined">
                                        <v-btn size="small" @click="insertTemplate('heading')">
                                            <v-icon>mdi-format-header-1</v-icon>
                                        </v-btn>
                                        <v-btn size="small" @click="insertTemplate('list')">
                                            <v-icon>mdi-format-list-bulleted</v-icon>
                                        </v-btn>
                                        <v-btn size="small" @click="insertTemplate('table')">
                                            <v-icon>mdi-table</v-icon>
                                        </v-btn>
                                        <v-btn size="small" @click="insertTemplate('link')">
                                            <v-icon>mdi-link</v-icon>
                                        </v-btn>
                                        <v-btn size="small" @click="showDiff = !showDiff">
                                            <v-icon>mdi-file-compare</v-icon>
                                        </v-btn>
                                    </v-btn-group>

                                    <v-spacer />

                                    <div class="auto-save-status">
                                        <v-chip
                                            :color="autoSaveStatus.color"
                                            size="small"
                                            variant="tonal"
                                            :prepend-icon="autoSaveStatus.icon"
                                        >
                                            {{ autoSaveStatus.text }}
                                        </v-chip>
                                    </div>
                                </div>

                                <!-- Diff View -->
                                <v-expand-transition>
                                    <div v-if="showDiff" class="diff-section mb-4">
                                        <v-card variant="outlined" class="pa-3">
                                            <div class="text-subtitle-2 mb-2">{{ $t('wiki.contentChanges') }}</div>
                                            <div class="diff-content">
                                                <div class="text-caption text-medium-emphasis">
                                                    {{ $t('wiki.showingChanges') }}
                                                </div>
                                                <!-- Simplified diff display -->
                                                <div class="mt-2 pa-2 rounded" style="background: rgba(var(--v-theme-warning), 0.1);">
                                                    {{ $t('wiki.wordsChanged', { count: contentStats.words - originalContentStats.words }) }}
                                                </div>
                                            </div>
                                        </v-card>
                                    </div>
                                </v-expand-transition>

                                <div class="editor-wrapper">
                                    <tiptap
                                        v-model="wikipage.content"
                                        :value="wikipage.content"
                                        id="text-content"
                                        name="content"
                                        type="full"
                                        :placeholder="$t('wiki.editPlaceholder')"
                                        ref="editorRef"
                                        @update:modelValue="onContentChange"
                                    />
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Enhanced Sidebar -->
                <v-col v-if="showPreview" cols="12" lg="4">
                    <div class="sidebar-content">
                        <!-- Quick Actions -->
                        <v-card class="quick-actions-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="quick-actions-title">
                                <v-icon class="mr-2" color="primary">mdi-lightning-bolt</v-icon>
                                {{ $t('wiki.quickActions') }}
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-row dense>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="focusTitle"
                                            prepend-icon="mdi-format-title"
                                        >
                                            {{ $t('wiki.focusTitle') }}
                                        </v-btn>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="focusContent"
                                            prepend-icon="mdi-text"
                                        >
                                            {{ $t('wiki.focusContent') }}
                                        </v-btn>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="discardChanges"
                                            prepend-icon="mdi-undo"
                                            :disabled="!hasChanges"
                                        >
                                            {{ $t('wiki.discard') }}
                                        </v-btn>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-btn
                                            block
                                            variant="outlined"
                                            size="small"
                                            @click="duplicatePage"
                                            prepend-icon="mdi-content-copy"
                                        >
                                            {{ $t('wiki.duplicate') }}
                                        </v-btn>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>

                        <!-- Page Settings -->
                        <v-card class="settings-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="settings-title">
                                <v-icon class="mr-2" color="info">mdi-cog</v-icon>
                                {{ $t('wiki.pageSettings') }}
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-select
                                    v-model="wikiPageParent"
                                    :items="pages"
                                    item-title="title"
                                    item-value="id"
                                    :label="$t('wiki.parentPage')"
                                    variant="outlined"
                                    density="compact"
                                    prepend-inner-icon="mdi-file-tree"
                                    clearable
                                    :no-data-text="$t('wiki.noParentPages')"
                                    :hint="$t('wiki.parentPageHint')"
                                    persistent-hint
                                >
                                    <template #selection="{ item }">
                                        <div class="d-flex align-center">
                                            <v-icon size="16" class="mr-2">mdi-file-document</v-icon>
                                            {{ item.title }}
                                        </div>
                                    </template>
                                    <template #item="{ props, item }">
                                        <v-list-item v-bind="props">
                                            <template #prepend>
                                                <v-icon>mdi-file-document</v-icon>
                                            </template>
                                            <v-list-item-title>{{ item.title }}</v-list-item-title>
                                            <v-list-item-subtitle>{{ item.slug }}</v-list-item-subtitle>
                                        </v-list-item>
                                    </template>
                                </v-select>
                            </v-card-text>
                        </v-card>

                        <!-- Enhanced Categories -->
                        <v-card class="categories-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="categories-title">
                                <v-icon class="mr-2" color="primary">mdi-folder-outline</v-icon>
                                {{ $t('wiki.categories') }}
                                <v-spacer />
                                <v-chip size="small" color="primary" variant="tonal">
                                    {{ categoryValue.length }}
                                </v-chip>
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-combobox
                                    v-model="categoryValue"
                                    :items="categories"
                                    :search-input.sync="searchTax"
                                    item-title="title"
                                    item-value="id"
                                    :label="$t('wiki.selectOrCreateCategories')"
                                    variant="outlined"
                                    density="compact"
                                    multiple
                                    chips
                                    deletable-chips
                                    clearable
                                    prepend-inner-icon="mdi-folder-plus"
                                    :placeholder="$t('wiki.typeToSearchOrCreate')"
                                    :hint="$t('wiki.categoriesHint')"
                                    persistent-hint
                                >
                                    <template #chip="{ props, item }">
                                        <v-chip
                                            v-bind="props"
                                            color="primary"
                                            variant="tonal"
                                            size="small"
                                            closable
                                        >
                                            <v-icon start size="16">mdi-folder</v-icon>
                                            {{ item.title || item }}
                                        </v-chip>
                                    </template>
                                </v-combobox>

                                <!-- Category Change Indicator -->
                                <div v-if="categoriesChanged" class="change-indicator mt-3">
                                    <v-chip size="small" color="warning" variant="tonal">
                                        <v-icon start size="16">mdi-pencil</v-icon>
                                        {{ $t('wiki.categoriesModified') }}
                                    </v-chip>
                                </div>

                                <!-- Popular Categories -->
                                <div v-if="popularCategories.length" class="popular-categories mt-3">
                                    <div class="text-caption text-medium-emphasis mb-2">{{ $t('wiki.popularCategories') }}:</div>
                                    <v-chip-group>
                                        <v-chip
                                            v-for="category in popularCategories"
                                            :key="category.id"
                                            size="small"
                                            variant="outlined"
                                            @click="addPopularCategory(category)"
                                        >
                                            {{ category.title }}
                                        </v-chip>
                                    </v-chip-group>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Enhanced Tags -->
                        <v-card class="tags-card mb-4" elevation="1" rounded="lg">
                            <v-card-title class="tags-title">
                                <v-icon class="mr-2" color="secondary">mdi-tag-outline</v-icon>
                                {{ $t('wiki.tags') }}
                                <v-spacer />
                                <v-chip size="small" color="secondary" variant="tonal">
                                    {{ termValue.length }}
                                </v-chip>
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-combobox
                                    v-model="termValue"
                                    :items="terms"
                                    item-title="title"
                                    :search-input.sync="searchTerm"
                                    :label="$t('wiki.addTags')"
                                    variant="outlined"
                                    density="compact"
                                    multiple
                                    chips
                                    deletable-chips
                                    clearable
                                    prepend-inner-icon="mdi-tag-plus"
                                    :placeholder="$t('wiki.typeToSearchOrCreateTags')"
                                    :hint="$t('wiki.tagsHint')"
                                    persistent-hint
                                >
                                    <template #no-data>
                                        <v-list-item>
                                            <v-list-item-title>
                                                {{ $t('wiki.noResultsFor', { term: searchTerm }) }}
                                                {{ $t('wiki.pressEnterToCreate') }}
                                            </v-list-item-title>
                                        </v-list-item>
                                    </template>

                                    <template #chip="{ props, item }">
                                        <v-chip
                                            v-bind="props"
                                            :color="item.color || 'secondary'"
                                            variant="tonal"
                                            size="small"
                                            closable
                                        >
                                            <v-icon start size="16">mdi-pound</v-icon>
                                            {{ item.title || item }}
                                        </v-chip>
                                    </template>
                                </v-combobox>

                                <!-- Tag Change Indicator -->
                                <div v-if="tagsChanged" class="change-indicator mt-3">
                                    <v-chip size="small" color="warning" variant="tonal">
                                        <v-icon start size="16">mdi-pencil</v-icon>
                                        {{ $t('wiki.tagsModified') }}
                                    </v-chip>
                                </div>

                                <!-- Suggested Tags -->
                                <div v-if="suggestedTags.length" class="suggested-tags mt-3">
                                    <div class="text-caption text-medium-emphasis mb-2">{{ $t('wiki.suggestedTags') }}:</div>
                                    <v-chip-group>
                                        <v-chip
                                            v-for="tag in suggestedTags"
                                            :key="tag"
                                            size="small"
                                            variant="outlined"
                                            @click="addSuggestedTag(tag)"
                                        >
                                            {{ tag }}
                                        </v-chip>
                                    </v-chip-group>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Enhanced Preview -->
                        <v-card class="preview-card" elevation="1" rounded="lg">
                            <v-card-title class="preview-title">
                                <v-icon class="mr-2" color="success">mdi-eye-outline</v-icon>
                                {{ $t('wiki.livePreview') }}
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <div class="preview-content">
                                    <div class="preview-header mb-3">
                                        <h4 class="preview-page-title">
                                            {{ wikipage.title || $t('wiki.untitledPage') }}
                                            <v-chip v-if="hasChanges" color="warning" size="x-small" class="ml-2">
                                                {{ $t('wiki.modified') }}
                                            </v-chip>
                                        </h4>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ $t('wiki.lastSaved') }} {{ lastSaved || $t('wiki.never') }}
                                        </div>
                                    </div>

                                    <div class="preview-meta mb-3">
                                        <div v-if="categoryValue.length" class="preview-categories mb-2">
                                            <span class="text-caption text-medium-emphasis">{{ $t('wiki.categories') }}: </span>
                                            <v-chip
                                                v-for="category in categoryValue.slice(0, 3)"
                                                :key="category.id || category"
                                                variant="tonal"
                                                color="primary"
                                                size="x-small"
                                                class="mr-1"
                                            >
                                                {{ category.title || category }}
                                            </v-chip>
                                            <span v-if="categoryValue.length > 3" class="text-caption">
                        +{{ categoryValue.length - 3 }} more
                      </span>
                                        </div>

                                        <div v-if="termValue.length" class="preview-tags mb-2">
                                            <span class="text-caption text-medium-emphasis">{{ $t('wiki.tags') }}: </span>
                                            <v-chip
                                                v-for="tag in termValue.slice(0, 3)"
                                                :key="tag.id || tag"
                                                variant="tonal"
                                                color="secondary"
                                                size="x-small"
                                                class="mr-1"
                                            >
                                                {{ tag.title || tag }}
                                            </v-chip>
                                            <span v-if="termValue.length > 3" class="text-caption">
                        +{{ termValue.length - 3 }} more
                      </span>
                                        </div>
                                    </div>

                                    <div class="preview-stats">
                                        <v-row dense>
                                            <v-col cols="6">
                                                <div class="text-caption text-medium-emphasis">{{ $t('wiki.content') }}</div>
                                                <div class="text-body-2">{{ contentStats.status }}</div>
                                            </v-col>
                                            <v-col cols="6">
                                                <div class="text-caption text-medium-emphasis">{{ $t('wiki.changes') }}</div>
                                                <v-progress-linear
                                                    :model-value="changePercentage"
                                                    :color="changePercentage > 50 ? 'warning' : 'success'"
                                                    height="6"
                                                    rounded
                                                />
                                            </v-col>
                                        </v-row>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-col>
            </v-row>

            <!-- Floating Action Button for Mobile -->
            <v-fab
                v-if="$vuetify.display.mobile"
                location="bottom end"
                size="large"
                color="primary"
                icon="mdi-content-save"
                @click="handleSave"
                :loading="saving"
                :disabled="!hasChanges || !canSave"
                app
            />
        </v-container>

        <!-- History Dialog -->
        <v-dialog v-model="showHistory" max-width="800">
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="info" class="mr-2">mdi-history</v-icon>
                    {{ $t('wiki.pageHistory') }}
                </v-card-title>
                <v-card-text>
                    <div class="text-body-2 text-medium-emphasis">
                        {{ $t('wiki.pageHistoryPlaceholder') }}
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showHistory = false">{{ $t('common.close') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Confirmation Dialog -->
        <v-dialog v-model="showConfirmDialog" max-width="500">
            <v-card>
                <v-card-title class="d-flex align-center">
                    <v-icon color="warning" class="mr-2">mdi-alert</v-icon>
                    {{ $t('common.confirmAction') }}
                </v-card-title>
                <v-card-text>
                    {{ confirmMessage }}
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showConfirmDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="elevated" @click="confirmAction">{{ $t('common.confirm') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { ref, reactive, computed, watch, onMounted, nextTick, onBeforeUnmount } from 'vue'
import { useDisplay } from 'vuetify'
import { useI18n } from 'vue-i18n'
import Tiptap from '../common/tiptap/Tiptap.vue'
import { useAuthStore } from '@/store/authStore.js'
import { useRouter } from 'vue-router'
import { formatDate } from '@/plugins/formatDate.js'

export default {
    name: 'WikiEditPage',
    components: {
        Tiptap
    },
    setup() {
        const { t } = useI18n()

        // Reactive data
        const editorRef = ref(null)
        const slug = ref(null)
        const currentStep = ref(1)
        const showPreview = ref(true)
        const showHistory = ref(false)
        const showDiff = ref(false)
        const showConfirmDialog = ref(false)
        const confirmMessage = ref('')
        const confirmCallback = ref(null)
        const dismissChangeAlert = ref(false)
        const autoSaving = ref(false)

        const wikipage = ref({
            title: '',
            content: '',
            parent: null,
            terms: [],
            categories: []
        })

        // Original data for comparison
        const originalData = ref({
            title: '',
            content: '',
            parent: null,
            terms: [],
            categories: []
        })

        const message = ref('')
        const parents = reactive([])
        const termValue = ref([])
        const categories = ref([])
        const categoryValue = ref([])
        const pages = ref([])
        const wikiPageParent = ref()
        const searchTerm = ref('')
        const searchTax = ref('')
        const loading = ref(true)
        const saving = ref(false)
        const savingCategory = ref(false)
        const titleError = ref(false)
        const titleErrorMessage = ref('')
        const lastSaved = ref('')
        const lastModified = ref('')

        const terms = ref([])
        const colors = ref(['green', 'purple', 'indigo', 'cyan', 'teal', 'orange'])
        const nonce = ref(1)
        const addCategory = ref(false)
        const newCategory = ref('')
        const parentValue = ref(null)

        const editing = reactive({
            errors: []
        })

        // Composables
        const { mobile } = useDisplay()
        const authStore = useAuthStore()
        const router = useRouter()

        // Computed properties
        const authenticated = computed(() => authStore.authenticated)
        const user = computed(() => authStore.user)

        const originalTitle = computed(() => originalData.value.title)
        const originalContent = computed(() => originalData.value.content)

        const hasChanges = computed(() => {
            if (dismissChangeAlert.value) return false
            return wikipage.value.title !== originalData.value.title ||
                wikipage.value.content !== originalData.value.content ||
                JSON.stringify(categoryValue.value) !== JSON.stringify(originalData.value.categories) ||
                JSON.stringify(termValue.value) !== JSON.stringify(originalData.value.terms)
        })

        const contentChanged = computed(() => {
            return wikipage.value.content !== originalData.value.content
        })

        const categoriesChanged = computed(() => {
            return JSON.stringify(categoryValue.value) !== JSON.stringify(originalData.value.categories)
        })

        const tagsChanged = computed(() => {
            return JSON.stringify(termValue.value) !== JSON.stringify(originalData.value.terms)
        })

        const canSave = computed(() => {
            return wikipage.value.title &&
                wikipage.value.title.trim().length > 0 &&
                wikipage.value.content &&
                wikipage.value.content.trim().length > 0
        })

        const contentStats = computed(() => {
            const content = wikipage.value.content || ''
            const words = content.split(/\s+/).filter(word => word.length > 0).length
            const chars = content.length
            const status = content ? `${words} words` : 'Empty'
            return { words, chars, status }
        })

        const originalContentStats = computed(() => {
            const content = originalData.value.content || ''
            const words = content.split(/\s+/).filter(word => word.length > 0).length
            const chars = content.length
            return { words, chars }
        })

        const changePercentage = computed(() => {
            const changes = [
                wikipage.value.title !== originalData.value.title,
                wikipage.value.content !== originalData.value.content,
                categoriesChanged.value,
                tagsChanged.value
            ].filter(Boolean).length
            return (changes / 4) * 100
        })

        const popularCategories = computed(() => {
            return categories.value.filter(cat => cat.popular).slice(0, 5)
        })

        const suggestedTags = computed(() => {
            const content = (wikipage.value.title + ' ' + wikipage.value.content).toLowerCase()
            const suggestions = []

            // Simple keyword extraction for suggestions
            const keywords = ['tutorial', 'guide', 'api', 'documentation', 'howto', 'tips', 'best-practices']
            keywords.forEach(keyword => {
                if (content.includes(keyword) && !termValue.value.some(tag =>
                    (tag.title || tag).toLowerCase().includes(keyword)
                )) {
                    suggestions.push(keyword)
                }
            })

            return suggestions.slice(0, 3)
        })

        const autoSaveStatus = computed(() => {
            if (autoSaving.value) {
                return { color: 'info', icon: 'mdi-loading', text: 'Saving...' }
            }
            if (hasChanges.value) {
                return { color: 'warning', icon: 'mdi-pencil', text: 'Unsaved' }
            }
            return { color: 'success', icon: 'mdi-check', text: 'Saved' }
        })

        // Steps for stepper
        const steps = [
            { title: 'Content', subtitle: 'Title and body', value: 1 },
            { title: 'Organization', subtitle: 'Categories and tags', value: 2 },
            { title: 'Review', subtitle: 'Save changes', value: 3 }
        ]

        // Methods
        const validateTitle = () => {
            if (!wikipage.value.title || wikipage.value.title.trim().length === 0) {
                titleError.value = true
                titleErrorMessage.value = t('wiki.pageTitleRequired')
                return false
            }
            if (wikipage.value.title.length > 100) {
                titleError.value = true
                titleErrorMessage.value = t('wiki.titleTooLong')
                return false
            }
            return true
        }

        const onTitleChange = () => {
            titleError.value = false
            titleErrorMessage.value = ''
        }

        const onContentChange = (content) => {
            wikipage.value.content = content
        }

        const insertTemplate = (type) => {
            let template = ''
            switch (type) {
                case 'heading':
                    template = '\n## New Heading\n'
                    break
                case 'list':
                    template = '\n- Item 1\n- Item 2\n- Item 3\n'
                    break
                case 'table':
                    template = '\n| Column 1 | Column 2 | Column 3 |\n|----------|----------|----------|\n| Row 1    | Data     | Data     |\n| Row 2    | Data     | Data     |\n'
                    break
                case 'link':
                    template = '[Link text](https://example.com)'
                    break
            }
            wikipage.value.content += template
        }

        const focusTitle = () => {
            nextTick(() => {
                const titleField = document.querySelector('.title-field input')
                if (titleField) titleField.focus()
            })
        };


        const focusContent = () => {
            editorRef.value?.focusEditor()
        }

        const discardChanges = () => {
            confirmMessage.value = t('wiki.confirmDiscardChanges')
            confirmCallback.value = () => {
                wikipage.value = { ...originalData.value }
                termValue.value = [...originalData.value.terms]
                categoryValue.value = [...originalData.value.categories]
                wikiPageParent.value = originalData.value.parent
                editing.errors = []
                showConfirmDialog.value = false
                dismissChangeAlert.value = true
            }
            showConfirmDialog.value = true
        }

        const duplicatePage = () => {
            const duplicateData = {
                title: `${wikipage.value.title} (Copy)`,
                content: wikipage.value.content,
                parent: wikipage.value.parent,
                terms: termValue.value,
                categories: categoryValue.value
            }

            // Navigate to create page with prefilled data
            router.push({
                name: 'wiki-create',
                query: { duplicate: JSON.stringify(duplicateData) }
            })
        }

        const continueEditing = () => {
            message.value = ''
        }

        const autoSave = async () => {
            if (!hasChanges.value || !canSave.value) return

            autoSaving.value = true
            try {
                // Auto-save implementation
                await new Promise(resolve => setTimeout(resolve, 1000)) // Simulate API call
                lastSaved.value = formatDate(new Date(), 'H:i:s')
                console.log('Auto-saved successfully')
            } catch (error) {
                console.error('Auto-save failed:', error)
            } finally {
                autoSaving.value = false
            }
        }

        const addPopularCategory = (category) => {
            if (!categoryValue.value.find(cat => (cat.id || cat) === (category.id || category))) {
                categoryValue.value.push(category)
            }
        }

        const addSuggestedTag = (tag) => {
            if (!termValue.value.find(t => (t.title || t) === tag)) {
                termValue.value.push({ title: tag, color: colors.value[Math.floor(Math.random() * colors.value.length)] })
            }
        }

        const confirmAction = () => {
            if (confirmCallback.value) {
                confirmCallback.value()
            }
        }

        const handleSave = () => {
            if (!validateTitle()) {
                currentStep.value = 1
                return
            }

            if (!wikipage.value.content || wikipage.value.content.trim().length === 0) {
                editing.errors = [t('wiki.pageContentRequired')]
                return
            }

            update()
        }

        // API Methods
        const getWikiPage = async () => {
            loading.value = true
            try {
                const response = await axios.get(`/api/wiki/${slug.value}`)
                wikipage.value = response.data.page
                wikiPageParent.value = response.data.parent
                parents.value = response.data.parents
                termValue.value = response.data.tags || []
                categoryValue.value = response.data.terms || []

                // Store original data for comparison
                originalData.value = {
                    title: response.data.page.title,
                    content: response.data.page.content,
                    parent: response.data.parent,
                    terms: [...(response.data.tags || [])],
                    categories: [...(response.data.terms || [])]
                }

                lastModified.value = response.data.page.updated_at ?
                    formatDate(response.data.page.updated_at) : null

            } catch (error) {
                if (error.response?.status === 404) {
                    editing.errors = [t('wiki.pageNotFound')]
                }
                if (error.response?.status === 401) {
                    router.push('/auth/signin')
                }
            } finally {
                loading.value = false
            }
        }

        const getCategories = async () => {
            try {
                const response = await axios.get(`/api/tag/terms/wiki`)
                categories.value = response.data.terms.map(term => ({
                    ...term,
                    popular: Math.random() > 0.7 // Simulate popular categories
                }))
                parents.value = response.data.terms
            } catch (error) {
                console.error('Failed to load categories:', error)
            }
        }

        const getTerms = async () => {
            try {
                const response = await axios.get(`/api/tag/terms/tags`)
                terms.value = response.data.terms.map(x => ({
                    title: x.title,
                    color: colors.value[Math.floor(Math.random() * colors.value.length)]
                }))
            } catch (error) {
                console.error('Failed to load terms:', error)
            }
        }

        const getPages = async () => {
            try {
                const response = await axios.get(`/api/wiki-pages`)
                pages.value = response.data
            } catch (error) {
                console.error('Failed to load pages:', error)
            }
        }

        function updateContent(content) {
            console.log('updatecontent',content)
            wikipage.value.content = content
        }

        const saveCategory = async () => {
            if (!newCategory.value) return

            savingCategory.value = true
            const data = {
                term: newCategory.value,
                taxonomy: 'wiki',
                parent: parentValue.value
            }

            try {
                await axios.post(`/api/tag/terms`, data)
                await getCategories()
                categoryValue.value.push({ title: newCategory.value })
                newCategory.value = ''
                parentValue.value = null
                addCategory.value = false
            } catch (error) {
                console.error('Failed to save category:', error)
            } finally {
                savingCategory.value = false
            }
        }

        const update = async () => {
            editing.errors = []
            saving.value = true

            wikipage.value.terms = termValue.value
            wikipage.value.categories = categoryValue.value
            wikipage.value.parent = wikiPageParent.value

            try {
                const response = await axios.patch(`/api/wiki/${slug.value}`, wikipage.value)

                // Update original data
                originalData.value = {
                    title: wikipage.value.title,
                    content: wikipage.value.content,
                    parent: wikiPageParent.value,
                    terms: [...termValue.value],
                    categories: [...categoryValue.value]
                }

                message.value = t('wiki.pageUpdatedSuccessfully')
                lastSaved.value = formatDate(new Date(), 'H:i:s')
                currentStep.value = 3
                dismissChangeAlert.value = true

                setTimeout(() => {
                    message.value = ''
                }, 5000)

            } catch (error) {
                if (error.response?.status === 422) {
                    editing.errors = error.response.data.errors || [error.response.data.message || t('wiki.validationFailed')]
                } else {
                    editing.errors = [t('wiki.errorUpdatingPage')]
                }
            } finally {
                saving.value = false
            }
        }

        // Watchers
        watch(termValue, (val, prev) => {
            if (val.length === prev.length) return

            termValue.value = val.map(v => {
                if (typeof v === 'string') {
                    v = {
                        title: v,
                        color: colors.value[nonce.value % colors.value.length],
                    }
                    terms.value.push(v)
                    nonce.value++
                }
                return v
            })
        })

        // Auto-save functionality
        watch([() => wikipage.value.title, () => wikipage.value.content], () => {
            // Debounced auto-save could be implemented here
        })

        // Before unload warning
        const beforeUnloadHandler = (event) => {
            if (hasChanges.value) {
                event.preventDefault()
                event.returnValue = ''
            }
        }

        // Lifecycle
        onMounted(() => {
            if (router.currentRoute.value.params.slug) {
                slug.value = router.currentRoute.value.params.slug
                getWikiPage()
            }
            getCategories()
            getTerms()
            getPages()

            // Auto-save every 2 minutes
            const autoSaveInterval = setInterval(() => {
                if (hasChanges.value && canSave.value) {
                    autoSave()
                }
            }, 120000)

            // Add beforeunload listener
            window.addEventListener('beforeunload', beforeUnloadHandler)

            onBeforeUnmount(() => {
                clearInterval(autoSaveInterval)
                window.removeEventListener('beforeunload', beforeUnloadHandler)
            })
        })

        return {
            // Reactive data
            slug,
            currentStep,
            showPreview,
            showHistory,
            showDiff,
            showConfirmDialog,
            confirmMessage,
            dismissChangeAlert,
            autoSaving,
            wikipage,
            originalData,
            message,
            parents,
            termValue,
            categories,
            categoryValue,
            pages,
            wikiPageParent,
            searchTerm,
            searchTax,
            loading,
            saving,
            savingCategory,
            titleError,
            titleErrorMessage,
            lastSaved,
            lastModified,
            terms,
            colors,
            nonce,
            addCategory,
            newCategory,
            parentValue,
            editing,

            // Computed
            authenticated,
            user,
            originalTitle,
            originalContent,
            hasChanges,
            contentChanged,
            categoriesChanged,
            tagsChanged,
            canSave,
            contentStats,
            originalContentStats,
            changePercentage,
            popularCategories,
            suggestedTags,
            autoSaveStatus,
            steps,

            // Methods
            validateTitle,
            onTitleChange,
            onContentChange,
            insertTemplate,
            focusTitle,
            focusContent,
            discardChanges,
            duplicatePage,
            continueEditing,
            autoSave,
            addPopularCategory,
            addSuggestedTag,
            confirmAction,
            handleSave,
            getWikiPage,
            getCategories,
            getTerms,
            getPages,
            saveCategory,
            update
        }
    }
}
</script>

<style scoped>
.wiki-edit-container {
    min-height: 100vh;
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
    position: relative;
}

.progress-section {
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity));
    border-radius: 16px;
    padding: 16px;
    backdrop-filter: blur(10px);
}

.edit-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(147, 51, 234, 0.08) 100%);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid rgba(59, 130, 246, 0.15);
    backdrop-filter: blur(10px);
}

.edit-title {
    background: linear-gradient(135deg, #3b82f6 0%, #9333ea 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}

.error-content {
    color: rgb(var(--v-theme-on-surface));
}

.header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.save-btn {
    border-radius: 12px !important;
    text-transform: none !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3) !important;
    transition: all 0.3s ease !important;
}

.save-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4) !important;
}

.editor-card,
.quick-actions-card,
.settings-card,
.categories-card,
.tags-card,
.preview-card {
    border: 1px solid rgba(var(--v-border-color), 0.2);
    backdrop-filter: blur(10px);
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
    transition: all 0.3s ease;
}

.editor-card:hover,
.quick-actions-card:hover,
.settings-card:hover,
.categories-card:hover,
.tags-card:hover,
.preview-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

.editor-card-title,
.quick-actions-title,
.settings-title,
.categories-title,
.tags-title,
.preview-title {
    background: linear-gradient(135deg, rgb(var(--v-theme-surface-variant)) 0%, rgba(var(--v-theme-surface-variant), 0.8) 100%);
    border-bottom: 1px solid rgb(var(--v-border-color));
    font-size: 1.1rem !important;
    font-weight: 600 !important;
    padding: 16px 20px !important;
}

.editor-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-content {
    position: sticky;
    top: 24px;
    max-height: calc(100vh - 48px);
    overflow-y: auto;
    padding-right: 8px;
}

.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.3);
    border-radius: 3px;
}

.title-field {
    background: rgba(var(--v-theme-surface), var(--app-surface-opacity));
    border-radius: 12px;
    transition: all 0.3s ease;
}

.title-field:focus-within {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.change-indicator {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.content-header {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.3);
    padding-bottom: 8px;
}

.content-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(var(--v-theme-surface-variant), 0.3);
    border-radius: 8px;
    border: 1px solid rgba(var(--v-border-color), 0.5);
}

.auto-save-status {
    display: flex;
    align-items: center;
}

.diff-section {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 200px;
    }
}

.editor-wrapper {
    border: 2px solid rgb(var(--v-border-color));
    border-radius: 12px;
    overflow: hidden;
    background: rgb(var(--v-theme-surface));
    transition: border-color 0.3s ease;
}

.editor-wrapper:focus-within {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.quick-actions-card .v-btn {
    text-transform: none !important;
    font-size: 0.875rem !important;
}

.popular-categories,
.suggested-tags {
    padding: 12px;
    background: rgba(var(--v-theme-surface-variant), 0.2);
    border-radius: 8px;
    border: 1px dashed rgba(var(--v-border-color), 0.6);
}

.kbd {
    background: rgb(var(--v-theme-surface-variant));
    border: 1px solid rgb(var(--v-border-color));
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 0.75rem;
    font-family: monospace;
}

.preview-content {
    padding: 16px;
    background: linear-gradient(135deg, rgba(var(--v-theme-surface-variant), 0.2) 0%, rgba(var(--v-theme-surface-variant), 0.1) 100%);
    border-radius: 12px;
    border: 1px solid rgba(var(--v-border-color), 0.5);
}

.preview-header {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.3);
    padding-bottom: 8px;
}

.preview-page-title {
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
    display: flex;
    align-items: center;
    font-size: 1.1rem;
}

.preview-meta {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.2);
    padding-bottom: 8px;
}

.preview-stats .v-progress-linear {
    border-radius: 3px;
}

/* Mobile optimizations */
@media (max-width: 960px) {
    .edit-header {
        padding: 16px;
    }

    .header-actions {
        justify-content: center;
        margin-top: 16px;
    }

    .sidebar-content {
        position: static;
        max-height: none;
    }

    .content-toolbar {
        flex-wrap: wrap;
    }

    .editor-actions {
        flex-direction: column;
        align-items: stretch;
    }
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .wiki-edit-container {
        background: linear-gradient(135deg, #0f1419 0%, #1a1a1a 100%);
    }

    .edit-header {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 51, 234, 0.1) 100%);
        border-color: rgba(59, 130, 246, 0.2);
    }

    .editor-card,
    .quick-actions-card,
    .settings-card,
    .categories-card,
    .tags-card,
    .preview-card {
        background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
    }
}

/* Animation for chips */
.v-chip {
    transition: all 0.3s ease !important;
}

.v-chip:hover {
    transform: translateY(-1px) !important;
}

/* Focus styles */
.v-text-field:focus-within,
.v-select:focus-within,
.v-combobox:focus-within {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}

/* Warning states for unsaved changes */
.save-btn:not(:disabled) {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
    }
    50% {
        box-shadow: 0 4px 16px rgba(59, 130, 246, 0.5);
    }
    100% {
        box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
    }
}
</style>
