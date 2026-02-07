<template>
    <v-container fluid class="pa-4">
        <v-row>
            <v-col cols="12">
                <div class="d-flex align-center justify-space-between mb-4">
                    <div>
                        <h1 class="text-h4 font-weight-bold">Translation Manager</h1>
                        <p class="text-body-2 text-grey mt-1">
                            Manage JavaScript and PHP translation files
                        </p>
                    </div>
                    <div class="d-flex ga-2">
                        <v-btn
                            color="warning"
                            variant="tonal"
                            @click="scanMissing"
                            :loading="scanning"
                        >
                            <v-icon icon="mdi-magnify-scan" start />
                            Scan Missing
                        </v-btn>
                        <v-btn
                            color="primary"
                            @click="showCreateLocaleDialog = true"
                        >
                            <v-icon icon="mdi-plus" start />
                            Add Locale
                        </v-btn>
                    </div>
                </div>
            </v-col>
        </v-row>

        <!-- Tabs for JS and PHP -->
        <v-card>
            <v-tabs v-model="activeTab" color="primary">
                <v-tab value="js">
                    <v-icon icon="mdi-language-javascript" start />
                    JavaScript Translations
                </v-tab>
                <v-tab value="php">
                    <v-icon icon="mdi-language-php" start />
                    PHP Translations
                </v-tab>
                <v-tab value="missing">
                    <v-icon icon="mdi-alert-circle-outline" start />
                    Missing Translations
                    <v-chip
                        v-if="totalMissing > 0"
                        size="x-small"
                        color="error"
                        class="ml-2"
                    >
                        {{ totalMissing }}
                    </v-chip>
                </v-tab>
                <v-tab value="report">
                    <v-icon icon="mdi-file-document-outline" start />
                    AI Report
                </v-tab>
            </v-tabs>

            <v-divider />

            <v-window v-model="activeTab">
                <!-- JS Translations Tab -->
                <v-window-item value="js">
                    <v-row no-gutters>
                        <!-- Locale List -->
                        <v-col cols="12" md="3" class="border-e">
                            <v-list density="compact" nav>
                                <v-list-subheader>Available Locales</v-list-subheader>
                                <v-list-item
                                    v-for="locale in jsLocales"
                                    :key="locale.code"
                                    :active="selectedJsLocale === locale.code"
                                    @click="selectJsLocale(locale.code)"
                                >
                                    <template #prepend>
                                        <v-avatar size="24" class="mr-2">
                                            <span class="text-caption font-weight-bold">
                                                {{ locale.code.toUpperCase() }}
                                            </span>
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title>{{ locale.name }}</v-list-item-title>
                                    <template #append>
                                        <v-chip size="x-small">{{ locale.js_keys }}</v-chip>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-col>

                        <!-- Translation Editor -->
                        <v-col cols="12" md="9">
                            <div v-if="!selectedJsLocale" class="pa-8 text-center">
                                <v-icon icon="mdi-translate" size="64" color="grey" />
                                <div class="text-h6 text-grey mt-4">Select a locale to edit</div>
                            </div>

                            <div v-else class="pa-4">
                                <!-- Toolbar -->
                                <div class="d-flex align-center ga-2 mb-4">
                                    <v-text-field
                                        v-model="jsSearch"
                                        placeholder="Search keys or values..."
                                        prepend-inner-icon="mdi-magnify"
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                        clearable
                                        style="max-width: 300px;"
                                    />

                                    <v-select
                                        v-model="jsGroupFilter"
                                        :items="jsGroups"
                                        placeholder="All Groups"
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                        clearable
                                        style="max-width: 200px;"
                                    />

                                    <v-spacer />

                                    <v-btn
                                        color="primary"
                                        variant="tonal"
                                        @click="showAddKeyDialog('js')"
                                    >
                                        <v-icon icon="mdi-plus" start />
                                        Add Key
                                    </v-btn>

                                    <v-btn
                                        color="success"
                                        :loading="saving"
                                        :disabled="!hasJsChanges"
                                        @click="saveJsTranslations"
                                    >
                                        <v-icon icon="mdi-content-save" start />
                                        Save Changes
                                    </v-btn>
                                </div>

                                <!-- Compare with base locale -->
                                <v-alert
                                    v-if="selectedJsLocale !== 'en' && jsComparison"
                                    :type="jsComparison.missing_count > 0 ? 'warning' : 'success'"
                                    density="compact"
                                    class="mb-4"
                                >
                                    <template v-if="jsComparison.missing_count > 0">
                                        {{ jsComparison.missing_count }} keys missing compared to English
                                        ({{ jsComparison.completion }}% complete)
                                    </template>
                                    <template v-else>
                                        All keys translated (100% complete)
                                    </template>
                                </v-alert>

                                <!-- Translation Table -->
                                <v-data-table
                                    :headers="jsHeaders"
                                    :items="filteredJsTranslations"
                                    :loading="loadingJs"
                                    :search="jsSearch"
                                    density="compact"
                                    class="translation-table"
                                    items-per-page="25"
                                >
                                    <template #item.key="{ item }">
                                        <code class="text-caption">{{ item.key }}</code>
                                    </template>
                                    <template #item.value="{ item }">
                                        <v-text-field
                                            v-model="jsTranslations[item.key]"
                                            variant="outlined"
                                            density="compact"
                                            hide-details
                                            @update:model-value="markJsChanged"
                                        />
                                    </template>
                                    <template #item.base="{ item }">
                                        <span class="text-caption text-grey">
                                            {{ jsBaseTranslations[item.key] || '-' }}
                                        </span>
                                    </template>
                                    <template #item.actions="{ item }">
                                        <v-btn
                                            icon
                                            size="small"
                                            variant="text"
                                            color="error"
                                            @click="confirmDeleteKey('js', item.key)"
                                        >
                                            <v-icon icon="mdi-delete" size="small" />
                                        </v-btn>
                                    </template>
                                </v-data-table>
                            </div>
                        </v-col>
                    </v-row>
                </v-window-item>

                <!-- PHP Translations Tab -->
                <v-window-item value="php">
                    <v-row no-gutters>
                        <!-- Locale & File List -->
                        <v-col cols="12" md="3" class="border-e">
                            <v-list density="compact" nav>
                                <v-list-subheader>Locales & Files</v-list-subheader>
                                <template v-for="locale in phpLocales" :key="locale.code">
                                    <v-list-group :value="locale.code">
                                        <template #activator="{ props }">
                                            <v-list-item v-bind="props">
                                                <template #prepend>
                                                    <v-avatar size="24" class="mr-2">
                                                        <span class="text-caption font-weight-bold">
                                                            {{ locale.code.toUpperCase() }}
                                                        </span>
                                                    </v-avatar>
                                                </template>
                                                <v-list-item-title>{{ locale.name }}</v-list-item-title>
                                            </v-list-item>
                                        </template>

                                        <v-list-item
                                            v-for="file in locale.php_files"
                                            :key="`${locale.code}-${file}`"
                                            :active="selectedPhpLocale === locale.code && selectedPhpFile === file"
                                            @click="selectPhpFile(locale.code, file)"
                                            class="pl-8"
                                        >
                                            <template #prepend>
                                                <v-icon icon="mdi-file-document" size="small" />
                                            </template>
                                            <v-list-item-title class="text-body-2">
                                                {{ file }}.php
                                            </v-list-item-title>
                                        </v-list-item>
                                    </v-list-group>
                                </template>
                            </v-list>
                        </v-col>

                        <!-- Translation Editor -->
                        <v-col cols="12" md="9">
                            <div v-if="!selectedPhpFile" class="pa-8 text-center">
                                <v-icon icon="mdi-translate" size="64" color="grey" />
                                <div class="text-h6 text-grey mt-4">Select a file to edit</div>
                            </div>

                            <div v-else class="pa-4">
                                <!-- Toolbar -->
                                <div class="d-flex align-center ga-2 mb-4">
                                    <v-chip color="primary" variant="tonal">
                                        {{ selectedPhpLocale }}/{{ selectedPhpFile }}.php
                                    </v-chip>

                                    <v-text-field
                                        v-model="phpSearch"
                                        placeholder="Search keys or values..."
                                        prepend-inner-icon="mdi-magnify"
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                        clearable
                                        style="max-width: 300px;"
                                    />

                                    <v-spacer />

                                    <v-btn
                                        color="primary"
                                        variant="tonal"
                                        @click="showAddKeyDialog('php')"
                                    >
                                        <v-icon icon="mdi-plus" start />
                                        Add Key
                                    </v-btn>

                                    <v-btn
                                        color="success"
                                        :loading="saving"
                                        :disabled="!hasPhpChanges"
                                        @click="savePhpTranslations"
                                    >
                                        <v-icon icon="mdi-content-save" start />
                                        Save Changes
                                    </v-btn>
                                </div>

                                <!-- Translation Table -->
                                <v-data-table
                                    :headers="phpHeaders"
                                    :items="filteredPhpTranslations"
                                    :loading="loadingPhp"
                                    :search="phpSearch"
                                    density="compact"
                                    class="translation-table"
                                    items-per-page="25"
                                >
                                    <template #item.key="{ item }">
                                        <code class="text-caption">{{ item.key }}</code>
                                    </template>
                                    <template #item.value="{ item }">
                                        <v-textarea
                                            v-if="item.value && item.value.length > 50"
                                            v-model="phpTranslations[item.key]"
                                            variant="outlined"
                                            density="compact"
                                            hide-details
                                            rows="2"
                                            auto-grow
                                            @update:model-value="markPhpChanged"
                                        />
                                        <v-text-field
                                            v-else
                                            v-model="phpTranslations[item.key]"
                                            variant="outlined"
                                            density="compact"
                                            hide-details
                                            @update:model-value="markPhpChanged"
                                        />
                                    </template>
                                    <template #item.actions="{ item }">
                                        <v-btn
                                            icon
                                            size="small"
                                            variant="text"
                                            color="error"
                                            @click="confirmDeleteKey('php', item.key)"
                                        >
                                            <v-icon icon="mdi-delete" size="small" />
                                        </v-btn>
                                    </template>
                                </v-data-table>
                            </div>
                        </v-col>
                    </v-row>
                </v-window-item>

                <!-- Missing Translations Tab -->
                <v-window-item value="missing">
                    <div class="pa-4">
                        <v-alert
                            v-if="!missingData"
                            type="info"
                            class="mb-4"
                        >
                            Click "Scan Missing" to analyze your codebase for missing or hardcoded translations.
                        </v-alert>

                        <template v-else>
                            <!-- Summary Cards -->
                            <v-row class="mb-4">
                                <v-col cols="12" md="3">
                                    <v-card color="warning" variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ missingData.summary.js_missing }}</div>
                                            <div class="text-body-2">Missing JS Keys</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-card color="error" variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ missingData.summary.js_hardcoded }}</div>
                                            <div class="text-body-2">Hardcoded JS Strings</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-card color="warning" variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ missingData.summary.php_missing }}</div>
                                            <div class="text-body-2">Missing PHP Keys</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-card color="error" variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ missingData.summary.php_hardcoded }}</div>
                                            <div class="text-body-2">Hardcoded PHP Strings</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <!-- Missing Keys Tables -->
                            <v-expansion-panels>
                                <v-expansion-panel v-if="Object.keys(missingData.missing.js?.missing_keys || {}).length">
                                    <v-expansion-panel-title>
                                        <v-icon icon="mdi-language-javascript" class="mr-2" />
                                        Missing JS Translation Keys
                                        <v-chip size="small" color="warning" class="ml-2">
                                            {{ Object.keys(missingData.missing.js.missing_keys).length }}
                                        </v-chip>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <v-data-table
                                            :headers="missingKeyHeaders"
                                            :items="formatMissingKeys(missingData.missing.js.missing_keys)"
                                            density="compact"
                                            items-per-page="10"
                                        >
                                            <template #item.key="{ item }">
                                                <code>{{ item.key }}</code>
                                            </template>
                                            <template #item.files="{ item }">
                                                <div class="text-caption">
                                                    {{ item.files.slice(0, 3).join(', ') }}
                                                    <span v-if="item.files.length > 3">
                                                        +{{ item.files.length - 3 }} more
                                                    </span>
                                                </div>
                                            </template>
                                        </v-data-table>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>

                                <v-expansion-panel v-if="missingData.missing.js?.hardcoded?.length">
                                    <v-expansion-panel-title>
                                        <v-icon icon="mdi-code-string" class="mr-2" />
                                        Hardcoded JS Strings
                                        <v-chip size="small" color="error" class="ml-2">
                                            {{ missingData.missing.js.hardcoded.length }}
                                        </v-chip>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <v-data-table
                                            :headers="hardcodedHeaders"
                                            :items="missingData.missing.js.hardcoded"
                                            density="compact"
                                            items-per-page="10"
                                        >
                                            <template #item.text="{ item }">
                                                <span class="text-error">"{{ item.text }}"</span>
                                            </template>
                                        </v-data-table>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>

                                <v-expansion-panel v-if="Object.keys(missingData.missing.php?.missing_keys || {}).length">
                                    <v-expansion-panel-title>
                                        <v-icon icon="mdi-language-php" class="mr-2" />
                                        Missing PHP Translation Keys
                                        <v-chip size="small" color="warning" class="ml-2">
                                            {{ Object.keys(missingData.missing.php.missing_keys).length }}
                                        </v-chip>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <v-data-table
                                            :headers="missingKeyHeaders"
                                            :items="formatMissingKeys(missingData.missing.php.missing_keys)"
                                            density="compact"
                                            items-per-page="10"
                                        >
                                            <template #item.key="{ item }">
                                                <code>{{ item.key }}</code>
                                            </template>
                                        </v-data-table>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </template>
                    </div>
                </v-window-item>

                <!-- AI Report Tab -->
                <v-window-item value="report">
                    <div class="pa-4">
                        <div class="d-flex align-center justify-space-between mb-4">
                            <div>
                                <h3 class="text-h6">AI-Friendly Translation Report</h3>
                                <p class="text-body-2 text-grey">
                                    Generate a detailed report with actionable changes for updating translations
                                </p>
                            </div>
                            <div class="d-flex ga-2">
                                <v-btn-toggle v-model="reportType" mandatory density="compact">
                                    <v-btn value="all" size="small">All</v-btn>
                                    <v-btn value="js" size="small">JS Only</v-btn>
                                    <v-btn value="php" size="small">PHP Only</v-btn>
                                </v-btn-toggle>
                                <v-btn
                                    color="primary"
                                    @click="generateReport"
                                    :loading="generatingReport"
                                >
                                    <v-icon icon="mdi-file-document-edit" start />
                                    Generate Report
                                </v-btn>
                            </div>
                        </div>

                        <v-alert
                            v-if="!reportData"
                            type="info"
                            class="mb-4"
                        >
                            Click "Generate Report" to create a detailed translation report with line numbers, suggested fixes, and actionable changes.
                        </v-alert>

                        <template v-else>
                            <!-- Report Summary -->
                            <v-row class="mb-4">
                                <v-col cols="12" md="3">
                                    <v-card variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ reportData.summary.total_files_scanned }}</div>
                                            <div class="text-body-2">Files Scanned</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-card color="warning" variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ reportData.summary.files_with_hardcoded }}</div>
                                            <div class="text-body-2">Files with Issues</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-card color="error" variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ reportData.summary.total_hardcoded_strings }}</div>
                                            <div class="text-body-2">Hardcoded Strings</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-card color="info" variant="tonal">
                                        <v-card-text class="text-center">
                                            <div class="text-h4">{{ reportData.summary.missing_translation_keys }}</div>
                                            <div class="text-body-2">Missing Keys</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <!-- Action Buttons -->
                            <div class="d-flex ga-2 mb-4">
                                <v-btn
                                    variant="outlined"
                                    @click="copyMarkdownReport"
                                    :disabled="!reportData.markdown_report"
                                >
                                    <v-icon icon="mdi-content-copy" start />
                                    Copy Markdown Report
                                </v-btn>
                                <v-btn
                                    variant="outlined"
                                    @click="downloadJsonReport"
                                >
                                    <v-icon icon="mdi-download" start />
                                    Download JSON
                                </v-btn>
                                <v-btn
                                    variant="outlined"
                                    color="success"
                                    @click="showSuggestedTranslations = !showSuggestedTranslations"
                                >
                                    <v-icon :icon="showSuggestedTranslations ? 'mdi-chevron-up' : 'mdi-chevron-down'" start />
                                    Suggested Translations ({{ Object.keys(reportData.suggested_translations).length }})
                                </v-btn>
                            </div>

                            <!-- Suggested Translations Panel -->
                            <v-expand-transition>
                                <v-card v-if="showSuggestedTranslations" class="mb-4" variant="outlined">
                                    <v-card-title class="text-subtitle-1">
                                        Suggested Translations to Add
                                    </v-card-title>
                                    <v-card-text>
                                        <pre class="suggested-translations-code">{{ formatSuggestedTranslations() }}</pre>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-btn
                                            variant="text"
                                            size="small"
                                            @click="copySuggestedTranslations"
                                        >
                                            <v-icon icon="mdi-content-copy" start />
                                            Copy
                                        </v-btn>
                                    </v-card-actions>
                                </v-card>
                            </v-expand-transition>

                            <!-- Actionable Changes by File -->
                            <v-expansion-panels v-model="expandedReportPanels">
                                <v-expansion-panel
                                    v-for="(fileChanges, fileName) in groupedActionableChanges"
                                    :key="fileName"
                                >
                                    <v-expansion-panel-title>
                                        <v-icon
                                            :icon="fileName.endsWith('.vue') ? 'mdi-vuejs' : (fileName.endsWith('.php') ? 'mdi-language-php' : 'mdi-file-code')"
                                            class="mr-2"
                                            :color="fileName.endsWith('.vue') ? 'green' : 'blue'"
                                        />
                                        <span class="font-weight-medium">{{ fileName }}</span>
                                        <v-chip size="x-small" color="warning" class="ml-2">
                                            {{ fileChanges.length }} changes
                                        </v-chip>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <v-list density="compact">
                                            <v-list-item
                                                v-for="(change, index) in fileChanges"
                                                :key="index"
                                                class="mb-3 change-item"
                                            >
                                                <div class="d-flex align-start">
                                                    <v-chip
                                                        size="x-small"
                                                        color="grey"
                                                        class="mr-2 mt-1"
                                                        label
                                                    >
                                                        L{{ change.line }}
                                                    </v-chip>
                                                    <div class="flex-grow-1">
                                                        <div class="text-caption text-grey mb-1">
                                                            Text: <code class="text-error">"{{ change.text }}"</code>
                                                        </div>
                                                        <div class="text-caption mb-1">
                                                            Suggested Key: <code class="text-primary">{{ change.suggested_key }}</code>
                                                        </div>
                                                        <div class="code-block original mb-1">
                                                            <span class="label">Original:</span>
                                                            <code>{{ change.original }}</code>
                                                        </div>
                                                        <div class="code-block suggested">
                                                            <span class="label">Suggested:</span>
                                                            <code>{{ change.suggested_fix }}</code>
                                                        </div>
                                                    </div>
                                                    <v-btn
                                                        icon
                                                        size="small"
                                                        variant="text"
                                                        @click="copyChange(change)"
                                                    >
                                                        <v-icon icon="mdi-content-copy" size="small" />
                                                    </v-btn>
                                                </div>
                                            </v-list-item>
                                        </v-list>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>

                            <!-- Markdown Report Preview -->
                            <v-card v-if="reportData.markdown_report" class="mt-4" variant="outlined">
                                <v-card-title class="d-flex align-center justify-space-between">
                                    <span class="text-subtitle-1">Markdown Report Preview</span>
                                    <v-btn
                                        variant="text"
                                        size="small"
                                        @click="showMarkdownPreview = !showMarkdownPreview"
                                    >
                                        {{ showMarkdownPreview ? 'Hide' : 'Show' }}
                                    </v-btn>
                                </v-card-title>
                                <v-expand-transition>
                                    <v-card-text v-if="showMarkdownPreview">
                                        <pre class="markdown-preview">{{ reportData.markdown_report }}</pre>
                                    </v-card-text>
                                </v-expand-transition>
                            </v-card>
                        </template>
                    </div>
                </v-window-item>
            </v-window>
        </v-card>

        <!-- Create Locale Dialog -->
        <v-dialog v-model="showCreateLocaleDialog" max-width="500">
            <v-card>
                <v-card-title>Add New Locale</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="newLocale.code"
                        label="Locale Code"
                        placeholder="e.g., fr, es, de"
                        hint="Two-letter ISO 639-1 code"
                        persistent-hint
                        variant="outlined"
                        maxlength="2"
                        class="mb-4"
                    />

                    <v-select
                        v-model="newLocale.copyFrom"
                        :items="availableLocalesForCopy"
                        label="Copy translations from"
                        variant="outlined"
                        class="mb-4"
                    />

                    <v-checkbox
                        v-model="newLocale.createJs"
                        label="Create JavaScript translations"
                        hide-details
                    />
                    <v-checkbox
                        v-model="newLocale.createPhp"
                        label="Create PHP translations"
                        hide-details
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateLocaleDialog = false">Cancel</v-btn>
                    <v-btn
                        color="primary"
                        :loading="creatingLocale"
                        :disabled="!newLocale.code || newLocale.code.length !== 2"
                        @click="createLocale"
                    >
                        Create Locale
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Add Key Dialog -->
        <v-dialog v-model="showAddKeyDialogVisible" max-width="600">
            <v-card>
                <v-card-title>Add Translation Key</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="newKey.key"
                        label="Translation Key"
                        placeholder="e.g., common.buttons.submit"
                        hint="Use dot notation for nested keys"
                        persistent-hint
                        variant="outlined"
                        class="mb-4"
                    />

                    <v-select
                        v-if="newKey.type === 'php'"
                        v-model="newKey.file"
                        :items="phpFiles"
                        label="PHP File"
                        variant="outlined"
                        class="mb-4"
                    />

                    <v-divider class="mb-4" />

                    <div class="text-subtitle-2 mb-2">Values per Locale</div>

                    <v-row>
                        <v-col
                            v-for="locale in localesForNewKey"
                            :key="locale"
                            cols="12"
                            md="6"
                        >
                            <v-text-field
                                v-model="newKey.values[locale]"
                                :label="locale.toUpperCase()"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showAddKeyDialogVisible = false">Cancel</v-btn>
                    <v-btn
                        color="primary"
                        :loading="addingKey"
                        :disabled="!newKey.key"
                        @click="addKey"
                    >
                        Add Key
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Key Confirmation -->
        <v-dialog v-model="showDeleteKeyDialog" max-width="400">
            <v-card>
                <v-card-title>Delete Translation Key</v-card-title>
                <v-card-text>
                    Are you sure you want to delete the key
                    <code>{{ keyToDelete.key }}</code>
                    from all locales?
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteKeyDialog = false">Cancel</v-btn>
                    <v-btn
                        color="error"
                        :loading="deletingKey"
                        @click="deleteKey"
                    >
                        Delete
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Snackbar -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from 'axios';

// State
const activeTab = ref('js');
const locales = ref([]);
const loading = ref(false);
const saving = ref(false);
const scanning = ref(false);

// JS State
const selectedJsLocale = ref(null);
const jsTranslations = ref({});
const jsBaseTranslations = ref({});
const jsOriginal = ref({});
const loadingJs = ref(false);
const jsSearch = ref('');
const jsGroupFilter = ref(null);
const jsComparison = ref(null);
const hasJsChanges = ref(false);

// PHP State
const selectedPhpLocale = ref(null);
const selectedPhpFile = ref(null);
const phpTranslations = ref({});
const phpOriginal = ref({});
const loadingPhp = ref(false);
const phpSearch = ref('');
const hasPhpChanges = ref(false);

// Missing State
const missingData = ref(null);

// Report State
const reportData = ref(null);
const reportType = ref('all');
const generatingReport = ref(false);
const showSuggestedTranslations = ref(false);
const showMarkdownPreview = ref(false);
const expandedReportPanels = ref([]);

// Dialogs
const showCreateLocaleDialog = ref(false);
const showAddKeyDialogVisible = ref(false);
const showDeleteKeyDialog = ref(false);
const creatingLocale = ref(false);
const addingKey = ref(false);
const deletingKey = ref(false);

const newLocale = reactive({
    code: '',
    copyFrom: 'en',
    createJs: true,
    createPhp: true,
});

const newKey = reactive({
    type: 'js',
    key: '',
    file: '',
    values: {},
});

const keyToDelete = reactive({
    type: '',
    key: '',
});

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
});

// Computed
const jsLocales = computed(() => locales.value.filter(l => l.has_js));
const phpLocales = computed(() => locales.value.filter(l => l.has_php).map(l => ({
    ...l,
    php_files: l.php_files || [],
})));

const jsHeaders = computed(() => {
    const headers = [
        { title: 'Key', key: 'key', width: '40%' },
        { title: 'Value', key: 'value', width: '45%' },
    ];

    if (selectedJsLocale.value !== 'en') {
        headers.splice(2, 0, { title: 'English', key: 'base', width: '30%' });
        headers[0].width = '30%';
        headers[1].width = '30%';
    }

    headers.push({ title: '', key: 'actions', width: '5%', sortable: false });

    return headers;
});

const phpHeaders = [
    { title: 'Key', key: 'key', width: '40%' },
    { title: 'Value', key: 'value', width: '55%' },
    { title: '', key: 'actions', width: '5%', sortable: false },
];

const missingKeyHeaders = [
    { title: 'Key', key: 'key' },
    { title: 'Used In', key: 'files' },
];

const hardcodedHeaders = [
    { title: 'File', key: 'file' },
    { title: 'Text', key: 'text' },
];

const jsGroups = computed(() => {
    const groups = new Set();
    Object.keys(jsTranslations.value).forEach(key => {
        const parts = key.split('.');
        if (parts.length > 1) {
            groups.add(parts[0]);
        }
    });
    return Array.from(groups).sort();
});

const filteredJsTranslations = computed(() => {
    let items = Object.entries(jsTranslations.value).map(([key, value]) => ({
        key,
        value,
    }));

    if (jsGroupFilter.value) {
        items = items.filter(item => item.key.startsWith(jsGroupFilter.value + '.'));
    }

    return items;
});

const filteredPhpTranslations = computed(() => {
    return Object.entries(phpTranslations.value).map(([key, value]) => ({
        key,
        value,
    }));
});

const availableLocalesForCopy = computed(() => {
    return locales.value.map(l => l.code);
});

const phpFiles = computed(() => {
    const files = new Set();
    phpLocales.value.forEach(l => {
        l.php_files.forEach(f => files.add(f));
    });
    return Array.from(files);
});

const localesForNewKey = computed(() => {
    if (newKey.type === 'js') {
        return jsLocales.value.map(l => l.code);
    }
    return phpLocales.value.map(l => l.code);
});

const totalMissing = computed(() => {
    if (!missingData.value) return 0;
    return (
        missingData.value.summary.js_missing +
        missingData.value.summary.js_hardcoded +
        missingData.value.summary.php_missing +
        missingData.value.summary.php_hardcoded
    );
});

const groupedActionableChanges = computed(() => {
    if (!reportData.value?.actionable_changes) return {};
    const grouped = {};
    reportData.value.actionable_changes.forEach(change => {
        const file = change.file;
        if (!grouped[file]) {
            grouped[file] = [];
        }
        grouped[file].push(change);
    });
    return grouped;
});

// Methods
const fetchLocales = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/admin/translations/locales');
        locales.value = response.data.locales;
    } catch (error) {
        console.error('Failed to fetch locales:', error);
        showSnackbar('Failed to load locales', 'error');
    } finally {
        loading.value = false;
    }
};

const selectJsLocale = async (locale) => {
    selectedJsLocale.value = locale;
    loadingJs.value = true;
    hasJsChanges.value = false;

    try {
        const response = await axios.get(`/api/admin/translations/js/${locale}`);
        jsTranslations.value = { ...response.data.translations };
        jsOriginal.value = { ...response.data.translations };

        // Load English for comparison if not English
        if (locale !== 'en') {
            const enResponse = await axios.get('/api/admin/translations/js/en');
            jsBaseTranslations.value = enResponse.data.translations;

            // Get comparison
            const compareResponse = await axios.get('/api/admin/translations/compare', {
                params: { type: 'js', base: 'en' },
            });
            jsComparison.value = compareResponse.data.comparison[locale] || null;
        } else {
            jsBaseTranslations.value = {};
            jsComparison.value = null;
        }
    } catch (error) {
        console.error('Failed to load translations:', error);
        showSnackbar('Failed to load translations', 'error');
    } finally {
        loadingJs.value = false;
    }
};

const selectPhpFile = async (locale, file) => {
    selectedPhpLocale.value = locale;
    selectedPhpFile.value = file;
    loadingPhp.value = true;
    hasPhpChanges.value = false;

    try {
        const response = await axios.get(`/api/admin/translations/php/${locale}/${file}`);
        phpTranslations.value = { ...response.data.translations };
        phpOriginal.value = { ...response.data.translations };
    } catch (error) {
        console.error('Failed to load translations:', error);
        showSnackbar('Failed to load translations', 'error');
    } finally {
        loadingPhp.value = false;
    }
};

const markJsChanged = () => {
    hasJsChanges.value = true;
};

const markPhpChanged = () => {
    hasPhpChanges.value = true;
};

const saveJsTranslations = async () => {
    saving.value = true;
    try {
        await axios.put(`/api/admin/translations/js/${selectedJsLocale.value}`, {
            translations: jsTranslations.value,
        });
        jsOriginal.value = { ...jsTranslations.value };
        hasJsChanges.value = false;
        showSnackbar('Translations saved successfully');
    } catch (error) {
        console.error('Failed to save translations:', error);
        showSnackbar('Failed to save translations', 'error');
    } finally {
        saving.value = false;
    }
};

const savePhpTranslations = async () => {
    saving.value = true;
    try {
        await axios.put(`/api/admin/translations/php/${selectedPhpLocale.value}/${selectedPhpFile.value}`, {
            translations: phpTranslations.value,
        });
        phpOriginal.value = { ...phpTranslations.value };
        hasPhpChanges.value = false;
        showSnackbar('Translations saved successfully');
    } catch (error) {
        console.error('Failed to save translations:', error);
        showSnackbar('Failed to save translations', 'error');
    } finally {
        saving.value = false;
    }
};

const scanMissing = async () => {
    scanning.value = true;
    try {
        const response = await axios.get('/api/admin/translations/scan');
        missingData.value = response.data;
        activeTab.value = 'missing';
        showSnackbar('Scan completed');
    } catch (error) {
        console.error('Failed to scan:', error);
        showSnackbar('Failed to scan for missing translations', 'error');
    } finally {
        scanning.value = false;
    }
};

const generateReport = async () => {
    generatingReport.value = true;
    try {
        const response = await axios.get('/api/admin/translations/report', {
            params: {
                type: reportType.value,
                format: 'actionable',
            },
        });
        reportData.value = response.data;
        showSnackbar('Report generated successfully');
    } catch (error) {
        console.error('Failed to generate report:', error);
        showSnackbar('Failed to generate report', 'error');
    } finally {
        generatingReport.value = false;
    }
};

const copyMarkdownReport = () => {
    if (reportData.value?.markdown_report) {
        navigator.clipboard.writeText(reportData.value.markdown_report);
        showSnackbar('Markdown report copied to clipboard');
    }
};

const downloadJsonReport = () => {
    if (!reportData.value) return;
    const blob = new Blob([JSON.stringify(reportData.value, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `translation-report-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
    showSnackbar('Report downloaded');
};

const formatSuggestedTranslations = () => {
    if (!reportData.value?.suggested_translations) return '';
    const lines = ['// Add these translations to en.js:'];
    Object.entries(reportData.value.suggested_translations).forEach(([key, values]) => {
        lines.push(`'${key}': '${values.en}',`);
    });
    return lines.join('\n');
};

const copySuggestedTranslations = () => {
    const text = formatSuggestedTranslations();
    navigator.clipboard.writeText(text);
    showSnackbar('Suggested translations copied to clipboard');
};

const copyChange = (change) => {
    const text = `File: ${change.file}:${change.line}\nKey: ${change.suggested_key}\nOriginal: ${change.original}\nSuggested: ${change.suggested_fix}`;
    navigator.clipboard.writeText(text);
    showSnackbar('Change copied to clipboard');
};

const createLocale = async () => {
    creatingLocale.value = true;
    try {
        await axios.post('/api/admin/translations/locales', {
            code: newLocale.code.toLowerCase(),
            copy_from: newLocale.copyFrom,
            create_js: newLocale.createJs,
            create_php: newLocale.createPhp,
        });
        showSnackbar('Locale created successfully');
        showCreateLocaleDialog.value = false;
        newLocale.code = '';
        await fetchLocales();
    } catch (error) {
        console.error('Failed to create locale:', error);
        showSnackbar('Failed to create locale', 'error');
    } finally {
        creatingLocale.value = false;
    }
};

const showAddKeyDialog = (type) => {
    newKey.type = type;
    newKey.key = '';
    newKey.file = phpFiles.value[0] || '';
    newKey.values = {};
    localesForNewKey.value.forEach(l => {
        newKey.values[l] = '';
    });
    showAddKeyDialogVisible.value = true;
};

const addKey = async () => {
    addingKey.value = true;
    try {
        await axios.post('/api/admin/translations/keys', {
            type: newKey.type,
            key: newKey.key,
            values: newKey.values,
            file: newKey.file,
        });
        showSnackbar('Key added successfully');
        showAddKeyDialogVisible.value = false;

        // Refresh current view
        if (newKey.type === 'js' && selectedJsLocale.value) {
            await selectJsLocale(selectedJsLocale.value);
        } else if (newKey.type === 'php' && selectedPhpFile.value) {
            await selectPhpFile(selectedPhpLocale.value, selectedPhpFile.value);
        }
    } catch (error) {
        console.error('Failed to add key:', error);
        showSnackbar('Failed to add key', 'error');
    } finally {
        addingKey.value = false;
    }
};

const confirmDeleteKey = (type, key) => {
    keyToDelete.type = type;
    keyToDelete.key = key;
    showDeleteKeyDialog.value = true;
};

const deleteKey = async () => {
    deletingKey.value = true;
    try {
        const localesList = keyToDelete.type === 'js'
            ? jsLocales.value.map(l => l.code)
            : phpLocales.value.map(l => l.code);

        await axios.delete('/api/admin/translations/keys', {
            data: {
                type: keyToDelete.type,
                key: keyToDelete.key,
                locales: localesList,
                file: selectedPhpFile.value,
            },
        });
        showSnackbar('Key deleted successfully');
        showDeleteKeyDialog.value = false;

        // Refresh current view
        if (keyToDelete.type === 'js' && selectedJsLocale.value) {
            await selectJsLocale(selectedJsLocale.value);
        } else if (keyToDelete.type === 'php' && selectedPhpFile.value) {
            await selectPhpFile(selectedPhpLocale.value, selectedPhpFile.value);
        }
    } catch (error) {
        console.error('Failed to delete key:', error);
        showSnackbar('Failed to delete key', 'error');
    } finally {
        deletingKey.value = false;
    }
};

const formatMissingKeys = (keys) => {
    return Object.entries(keys).map(([key, files]) => ({
        key,
        files: Array.isArray(files) ? files : [files],
    }));
};

const showSnackbar = (text, color = 'success') => {
    snackbar.text = text;
    snackbar.color = color;
    snackbar.show = true;
};

// Lifecycle
onMounted(() => {
    fetchLocales();
});
</script>

<style scoped>
.ga-2 {
    gap: 8px;
}

.translation-table :deep(.v-text-field) {
    font-size: 14px;
}

.translation-table :deep(code) {
    background: rgba(var(--v-theme-primary), 0.1);
    padding: 2px 6px;
    border-radius: 4px;
}

.border-e {
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.code-block {
    font-family: monospace;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

.code-block.original {
    background: rgba(var(--v-theme-error), 0.1);
    border-left: 3px solid rgb(var(--v-theme-error));
}

.code-block.suggested {
    background: rgba(var(--v-theme-success), 0.1);
    border-left: 3px solid rgb(var(--v-theme-success));
}

.code-block .label {
    font-weight: bold;
    margin-right: 8px;
    color: rgba(var(--v-theme-on-surface), 0.6);
}

.change-item {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding: 12px 0 !important;
}

.change-item:last-child {
    border-bottom: none;
}

.suggested-translations-code {
    background: rgba(var(--v-theme-surface-variant), 0.5);
    padding: 16px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 12px;
    overflow-x: auto;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}

.markdown-preview {
    background: rgba(var(--v-theme-surface-variant), 0.5);
    padding: 16px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 11px;
    overflow-x: auto;
    white-space: pre-wrap;
    max-height: 500px;
    overflow-y: auto;
}
</style>
