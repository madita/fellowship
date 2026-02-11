<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class TranslationController extends Controller
{
    protected string $jsTranslationsPath;
    protected string $phpTranslationsPath;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'admin']);

        $this->jsTranslationsPath = resource_path('js/translations');
        $this->phpTranslationsPath = lang_path();
    }

    /**
     * Get all available locales with their status.
     */
    public function locales(): JsonResponse
    {
        $jsLocales = $this->getJsLocales();
        $phpLocales = $this->getPhpLocales();

        $allLocales = array_unique(array_merge(array_keys($jsLocales), array_keys($phpLocales)));
        sort($allLocales);

        $locales = [];
        foreach ($allLocales as $locale) {
            $locales[] = [
                'code'      => $locale,
                'name'      => $this->getLocaleName($locale),
                'has_js'    => isset($jsLocales[$locale]),
                'has_php'   => isset($phpLocales[$locale]),
                'js_keys'   => $jsLocales[$locale]['count'] ?? 0,
                'php_files' => $phpLocales[$locale]['files'] ?? [],
            ];
        }

        return response()->json([
            'locales'        => $locales,
            'default_locale' => config('app.locale', 'en'),
        ]);
    }

    /**
     * Get JS translations for a specific locale.
     */
    public function getJsTranslations(string $locale): JsonResponse
    {
        $filePath = $this->jsTranslationsPath . '/' . $locale . '.js';

        if (!File::exists($filePath)) {
            return response()->json(['error' => __('messages.translations.locale_not_found')], 404);
        }

        $content = File::get($filePath);
        $translations = $this->parseJsTranslations($content);

        // Flatten for easier editing
        $flattened = $this->flattenArray($translations);

        return response()->json([
            'locale'       => $locale,
            'translations' => $flattened,
            'nested'       => $translations,
            'total_keys'   => count($flattened),
        ]);
    }

    /**
     * Update JS translations for a specific locale.
     */
    public function updateJsTranslations(Request $request, string $locale): JsonResponse
    {
        $request->validate([
            'translations' => 'required|array',
        ]);

        $filePath = $this->jsTranslationsPath . '/' . $locale . '.js';
        $translations = $request->input('translations');

        // Unflatten the array back to nested structure
        $nested = $this->unflattenArray($translations);

        // Generate JS file content
        $content = $this->generateJsContent($nested);

        try {
            File::put($filePath, $content);

            return response()->json([
                'message'    => __('messages.translations.updated'),
                'locale'     => $locale,
                'total_keys' => count($translations),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => __('messages.translations.save_failed'),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get PHP translations for a specific locale and file.
     */
    public function getPhpTranslations(string $locale, string $file = null): JsonResponse
    {
        $localePath = $this->phpTranslationsPath . '/' . $locale;

        if (!File::isDirectory($localePath)) {
            return response()->json(['error' => __('messages.translations.locale_not_found')], 404);
        }

        if ($file) {
            // Get specific file
            $filePath = $localePath . '/' . $file . '.php';
            if (!File::exists($filePath)) {
                return response()->json(['error' => __('messages.translations.file_not_found')], 404);
            }

            $translations = include $filePath;
            $flattened = $this->flattenArray($translations);

            return response()->json([
                'locale'       => $locale,
                'file'         => $file,
                'translations' => $flattened,
                'nested'       => $translations,
                'total_keys'   => count($flattened),
            ]);
        }

        // Get all files for the locale
        $files = [];
        foreach (File::files($localePath) as $f) {
            if ($f->getExtension() === 'php') {
                $fileName = $f->getFilenameWithoutExtension();
                $translations = include $f->getPathname();
                $flattened = $this->flattenArray($translations);

                $files[$fileName] = [
                    'translations' => $flattened,
                    'nested'       => $translations,
                    'total_keys'   => count($flattened),
                ];
            }
        }

        return response()->json([
            'locale' => $locale,
            'files'  => $files,
        ]);
    }

    /**
     * Update PHP translations for a specific locale and file.
     */
    public function updatePhpTranslations(Request $request, string $locale, string $file): JsonResponse
    {
        $request->validate([
            'translations' => 'required|array',
        ]);

        $localePath = $this->phpTranslationsPath . '/' . $locale;

        // Create locale directory if it doesn't exist
        if (!File::isDirectory($localePath)) {
            File::makeDirectory($localePath, 0755, true);
        }

        $filePath = $localePath . '/' . $file . '.php';
        $translations = $request->input('translations');

        // Unflatten the array back to nested structure
        $nested = $this->unflattenArray($translations);

        // Generate PHP file content
        $content = $this->generatePhpContent($nested);

        try {
            File::put($filePath, $content);

            return response()->json([
                'message'    => __('messages.translations.updated'),
                'locale'     => $locale,
                'file'       => $file,
                'total_keys' => count($translations),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => __('messages.translations.save_failed'),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new locale.
     */
    public function createLocale(Request $request): JsonResponse
    {
        $request->validate([
            'code'         => 'required|string|size:2|alpha',
            'copy_from'    => 'nullable|string|size:2',
            'create_js'    => 'boolean',
            'create_php'   => 'boolean',
        ]);

        $code = strtolower($request->input('code'));
        $copyFrom = $request->input('copy_from', 'en');
        $createJs = $request->input('create_js', true);
        $createPhp = $request->input('create_php', true);

        $created = [];

        // Create JS translation file
        if ($createJs) {
            $sourceJsPath = $this->jsTranslationsPath . '/' . $copyFrom . '.js';
            $targetJsPath = $this->jsTranslationsPath . '/' . $code . '.js';

            if (File::exists($sourceJsPath) && !File::exists($targetJsPath)) {
                File::copy($sourceJsPath, $targetJsPath);
                $created[] = 'js';
            }
        }

        // Create PHP translation directory and files
        if ($createPhp) {
            $sourcePhpPath = $this->phpTranslationsPath . '/' . $copyFrom;
            $targetPhpPath = $this->phpTranslationsPath . '/' . $code;

            if (File::isDirectory($sourcePhpPath) && !File::isDirectory($targetPhpPath)) {
                File::copyDirectory($sourcePhpPath, $targetPhpPath);
                $created[] = 'php';
            }
        }

        return response()->json([
            'message' => __('messages.translations.locale_created'),
            'code'    => $code,
            'created' => $created,
        ]);
    }

    /**
     * Add a new translation key.
     */
    public function addKey(Request $request): JsonResponse
    {
        $request->validate([
            'type'   => 'required|in:js,php',
            'key'    => 'required|string',
            'values' => 'required|array',
            'file'   => 'required_if:type,php|string',
        ]);

        $type = $request->input('type');
        $key = $request->input('key');
        $values = $request->input('values');
        $file = $request->input('file');

        $updated = [];

        foreach ($values as $locale => $value) {
            if ($type === 'js') {
                $filePath = $this->jsTranslationsPath . '/' . $locale . '.js';
                if (File::exists($filePath)) {
                    $content = File::get($filePath);
                    $translations = $this->parseJsTranslations($content);
                    Arr::set($translations, $key, $value);
                    File::put($filePath, $this->generateJsContent($translations));
                    $updated[] = $locale;
                }
            } else {
                $filePath = $this->phpTranslationsPath . '/' . $locale . '/' . $file . '.php';
                if (File::exists($filePath)) {
                    $translations = include $filePath;
                    Arr::set($translations, $key, $value);
                    File::put($filePath, $this->generatePhpContent($translations));
                    $updated[] = $locale;
                }
            }
        }

        return response()->json([
            'message' => __('messages.translations.key_added'),
            'key'     => $key,
            'updated' => $updated,
        ]);
    }

    /**
     * Delete a translation key.
     */
    public function deleteKey(Request $request): JsonResponse
    {
        $request->validate([
            'type'    => 'required|in:js,php',
            'key'     => 'required|string',
            'locales' => 'required|array',
            'file'    => 'required_if:type,php|string',
        ]);

        $type = $request->input('type');
        $key = $request->input('key');
        $locales = $request->input('locales');
        $file = $request->input('file');

        $deleted = [];

        foreach ($locales as $locale) {
            if ($type === 'js') {
                $filePath = $this->jsTranslationsPath . '/' . $locale . '.js';
                if (File::exists($filePath)) {
                    $content = File::get($filePath);
                    $translations = $this->parseJsTranslations($content);
                    Arr::forget($translations, $key);
                    File::put($filePath, $this->generateJsContent($translations));
                    $deleted[] = $locale;
                }
            } else {
                $filePath = $this->phpTranslationsPath . '/' . $locale . '/' . $file . '.php';
                if (File::exists($filePath)) {
                    $translations = include $filePath;
                    Arr::forget($translations, $key);
                    File::put($filePath, $this->generatePhpContent($translations));
                    $deleted[] = $locale;
                }
            }
        }

        return response()->json([
            'message' => __('messages.translations.key_deleted'),
            'key'     => $key,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Scan codebase for missing translations.
     */
    public function scanMissing(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all'); // js, php, all

        $missing = [];

        if ($type === 'js' || $type === 'all') {
            $missing['js'] = $this->scanMissingJsTranslations();
        }

        if ($type === 'php' || $type === 'all') {
            $missing['php'] = $this->scanMissingPhpTranslations();
        }

        return response()->json([
            'missing' => $missing,
            'summary' => [
                'js_hardcoded'   => count($missing['js']['hardcoded'] ?? []),
                'js_missing'     => count($missing['js']['missing_keys'] ?? []),
                'php_hardcoded'  => count($missing['php']['hardcoded'] ?? []),
                'php_missing'    => count($missing['php']['missing_keys'] ?? []),
            ],
        ]);
    }

    /**
     * Compare translations across locales to find missing keys.
     */
    public function compareLocales(Request $request): JsonResponse
    {
        $type = $request->input('type', 'js');
        $baseLocale = $request->input('base', 'en');

        $comparison = [];

        if ($type === 'js') {
            $baseFile = $this->jsTranslationsPath . '/' . $baseLocale . '.js';
            if (!File::exists($baseFile)) {
                return response()->json(['error' => __('messages.translations.base_locale_not_found')], 404);
            }

            $baseTranslations = $this->flattenArray(
                $this->parseJsTranslations(File::get($baseFile))
            );
            $baseKeys = array_keys($baseTranslations);

            foreach ($this->getJsLocales() as $locale => $info) {
                if ($locale === $baseLocale) continue;

                $localeFile = $this->jsTranslationsPath . '/' . $locale . '.js';
                $localeTranslations = $this->flattenArray(
                    $this->parseJsTranslations(File::get($localeFile))
                );
                $localeKeys = array_keys($localeTranslations);

                $missing = array_diff($baseKeys, $localeKeys);
                $extra = array_diff($localeKeys, $baseKeys);

                $comparison[$locale] = [
                    'missing_count' => count($missing),
                    'extra_count'   => count($extra),
                    'missing'       => array_values($missing),
                    'extra'         => array_values($extra),
                    'completion'    => round((count($localeKeys) / count($baseKeys)) * 100, 1),
                ];
            }
        } else {
            // PHP comparison
            $basePhpPath = $this->phpTranslationsPath . '/' . $baseLocale;
            if (!File::isDirectory($basePhpPath)) {
                return response()->json(['error' => __('messages.translations.base_locale_not_found')], 404);
            }

            foreach ($this->getPhpLocales() as $locale => $info) {
                if ($locale === $baseLocale) continue;

                $comparison[$locale] = [
                    'files' => [],
                ];

                foreach ($info['files'] as $file) {
                    $baseFilePath = $basePhpPath . '/' . $file . '.php';
                    $localeFilePath = $this->phpTranslationsPath . '/' . $locale . '/' . $file . '.php';

                    if (!File::exists($baseFilePath)) continue;

                    $baseTranslations = $this->flattenArray(include $baseFilePath);
                    $baseKeys = array_keys($baseTranslations);

                    if (File::exists($localeFilePath)) {
                        $localeTranslations = $this->flattenArray(include $localeFilePath);
                        $localeKeys = array_keys($localeTranslations);

                        $missing = array_diff($baseKeys, $localeKeys);
                        $extra = array_diff($localeKeys, $baseKeys);

                        $comparison[$locale]['files'][$file] = [
                            'missing_count' => count($missing),
                            'extra_count'   => count($extra),
                            'missing'       => array_values($missing),
                            'extra'         => array_values($extra),
                            'completion'    => round((count($localeKeys) / max(count($baseKeys), 1)) * 100, 1),
                        ];
                    } else {
                        $comparison[$locale]['files'][$file] = [
                            'missing_count' => count($baseKeys),
                            'missing'       => $baseKeys,
                            'completion'    => 0,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'base_locale' => $baseLocale,
            'type'        => $type,
            'comparison'  => $comparison,
        ]);
    }

    // ==================== Helper Methods ====================

    /**
     * Get all JS locale files.
     */
    protected function getJsLocales(): array
    {
        $locales = [];

        if (File::isDirectory($this->jsTranslationsPath)) {
            foreach (File::files($this->jsTranslationsPath) as $file) {
                if ($file->getExtension() === 'js') {
                    $locale = $file->getFilenameWithoutExtension();
                    $content = File::get($file->getPathname());
                    $translations = $this->parseJsTranslations($content);
                    $locales[$locale] = [
                        'count' => count($this->flattenArray($translations)),
                    ];
                }
            }
        }

        return $locales;
    }

    /**
     * Get all PHP locale directories.
     */
    protected function getPhpLocales(): array
    {
        $locales = [];

        if (File::isDirectory($this->phpTranslationsPath)) {
            foreach (File::directories($this->phpTranslationsPath) as $dir) {
                $locale = basename($dir);
                $files = [];

                foreach (File::files($dir) as $file) {
                    if ($file->getExtension() === 'php') {
                        $files[] = $file->getFilenameWithoutExtension();
                    }
                }

                $locales[$locale] = [
                    'files' => $files,
                ];
            }
        }

        return $locales;
    }

    /**
     * Parse JS translation file content.
     */
    protected function parseJsTranslations(string $content): array
    {
        // Remove export default and get the object content
        $content = preg_replace('/export\s+default\s*/', '', $content);
        $content = trim($content);

        // Remove trailing semicolon if present
        $content = rtrim($content, ';');

        // Remove single-line comments (// ...)
        $content = preg_replace('/\/\/[^\n]*/', '', $content);

        // Remove multi-line comments (/* ... */)
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);

        // Handle escaped apostrophes in strings before converting quotes
        // Convert \' to a placeholder, then back after quote conversion
        $content = str_replace("\\'", '___ESCAPED_APOSTROPHE___', $content);

        // Escape double quotes inside single-quoted strings before converting
        // This prevents issues like 'Type "DELETE"' becoming "Type "DELETE""
        $content = preg_replace_callback(
            "/'([^']*?)'/",
            function ($matches) {
                // Escape any double quotes inside the single-quoted string
                $inner = str_replace('"', '\\"', $matches[1]);
                return '"' . $inner . '"';
            },
            $content
        );

        // Convert placeholder back to escaped apostrophe (as literal apostrophe in JSON string)
        $content = str_replace('___ESCAPED_APOSTROPHE___', "'", $content);

        // Remove trailing commas before closing braces
        $content = preg_replace('/,(\s*[\}\]])/', '$1', $content);

        try {
            $result = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::warning('JSON parse error in translations: ' . json_last_error_msg() . ' - Content sample: ' . substr($content, 0, 500));
                return [];
            }
            return $result ?? [];
        } catch (\Exception $e) {
            \Log::warning('Exception parsing translations: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate JS file content from array.
     */
    protected function generateJsContent(array $translations): string
    {
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Convert to JS object syntax (unquoted keys)
        $js = preg_replace('/"([a-zA-Z_][a-zA-Z0-9_]*)"(\s*:)/', '$1$2', $json);

        return "export default " . $js . ";\n";
    }

    /**
     * Generate PHP file content from array.
     */
    protected function generatePhpContent(array $translations): string
    {
        $export = var_export($translations, true);

        // Clean up the var_export output
        $export = preg_replace('/array \(/', '[', $export);
        $export = preg_replace('/\)$/', ']', $export);
        $export = preg_replace('/\)(\s*,)/', ']$1', $export);
        $export = preg_replace('/=> \[/', '=> [', $export);

        return "<?php\n\nreturn " . $export . ";\n";
    }

    /**
     * Flatten nested array with dot notation.
     */
    protected function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value) && !empty($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Unflatten dot notation array back to nested.
     */
    protected function unflattenArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            Arr::set($result, $key, $value);
        }

        return $result;
    }

    /**
     * Get human-readable locale name.
     */
    protected function getLocaleName(string $code): string
    {
        $names = [
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'pl' => 'Polish',
            'it' => 'Italian',
            'nl' => 'Dutch',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'tr' => 'Turkish',
            'cs' => 'Czech',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'uk' => 'Ukrainian',
            'vi' => 'Vietnamese',
            'th' => 'Thai',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'hi' => 'Hindi',
            'bn' => 'Bengali',
            'he' => 'Hebrew',
            'el' => 'Greek',
        ];

        return $names[$code] ?? strtoupper($code);
    }

    /**
     * Generate a detailed report for AI/programmatic translation updates.
     */
    public function generateReport(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all'); // js, php, all
        $format = $request->input('format', 'detailed'); // detailed, summary, actionable

        $report = [
            'generated_at' => now()->toISOString(),
            'format'       => $format,
            'files'        => [],
            'summary'      => [
                'total_files_scanned'    => 0,
                'files_with_hardcoded'   => 0,
                'total_hardcoded_strings' => 0,
                'missing_translation_keys' => 0,
            ],
            'suggested_translations' => [],
            'actionable_changes'     => [],
        ];

        if ($type === 'js' || $type === 'all') {
            $jsReport = $this->generateDetailedJsReport();
            $report['files'] = array_merge($report['files'], $jsReport['files']);
            $report['summary']['total_files_scanned'] += $jsReport['summary']['total_files'];
            $report['summary']['files_with_hardcoded'] += $jsReport['summary']['files_with_hardcoded'];
            $report['summary']['total_hardcoded_strings'] += $jsReport['summary']['total_hardcoded'];
            $report['summary']['missing_translation_keys'] += $jsReport['summary']['missing_keys'];
            $report['suggested_translations'] = array_merge(
                $report['suggested_translations'],
                $jsReport['suggested_translations']
            );
            $report['actionable_changes'] = array_merge(
                $report['actionable_changes'],
                $jsReport['actionable_changes']
            );
        }

        if ($type === 'php' || $type === 'all') {
            $phpReport = $this->generateDetailedPhpReport();
            $report['files'] = array_merge($report['files'], $phpReport['files']);
            $report['summary']['total_files_scanned'] += $phpReport['summary']['total_files'];
            $report['summary']['files_with_hardcoded'] += $phpReport['summary']['files_with_hardcoded'];
            $report['summary']['total_hardcoded_strings'] += $phpReport['summary']['total_hardcoded'];
            $report['summary']['missing_translation_keys'] += $phpReport['summary']['missing_keys'];
            $report['suggested_translations'] = array_merge(
                $report['suggested_translations'],
                $phpReport['suggested_translations']
            );
            $report['actionable_changes'] = array_merge(
                $report['actionable_changes'],
                $phpReport['actionable_changes']
            );
        }

        // Generate markdown report for AI consumption
        if ($format === 'actionable') {
            $report['markdown_report'] = $this->generateMarkdownReport($report);
        }

        return response()->json($report);
    }

    /**
     * Generate detailed JS/Vue report with line numbers and context.
     */
    protected function generateDetailedJsReport(): array
    {
        $vueFiles = $this->getFilesWithExtension(resource_path('js'), ['vue', 'js']);
        $files = [];
        $suggestedTranslations = [];
        $actionableChanges = [];
        $usedKeys = [];
        $totalHardcoded = 0;
        $filesWithHardcoded = 0;

        // Enhanced patterns with named captures
        $hardcodedPatterns = [
            // Vue template attributes
            [
                'pattern' => '/<v-[a-z-]+[^>]*\s(label|title|text|placeholder|hint|message|prepend-inner-icon-text|append-inner-icon-text)="([^"]+)"/',
                'type'    => 'vue_attribute',
                'capture' => 2,
                'attr'    => 1,
            ],
            // Vue template content (text between tags)
            [
                'pattern' => '/>([A-Z][^<>{}\n]{2,50})</s',
                'type'    => 'vue_content',
                'capture' => 1,
            ],
            // JS object properties
            [
                'pattern' => '/(?:label|title|placeholder|hint|message|text|description|buttonText|headerText)\s*:\s*[\'"]([^\'"]{3,100})[\'"]/',
                'type'    => 'js_property',
                'capture' => 1,
            ],
            // Alert/snackbar messages
            [
                'pattern' => '/(?:showSnackbar|showMessage|showError|showSuccess|alert)\s*\(\s*[\'"]([^\'"]{3,100})[\'"]/',
                'type'    => 'message_call',
                'capture' => 1,
            ],
            // v-btn content
            [
                'pattern' => '/<v-btn[^>]*>([A-Za-z][^<>{}\n]{1,30})<\/v-btn>/',
                'type'    => 'button_text',
                'capture' => 1,
            ],
            // v-card-title content
            [
                'pattern' => '/<v-card-title[^>]*>([^<>{}\n]{3,50})<\/v-card-title>/',
                'type'    => 'card_title',
                'capture' => 1,
            ],
            // Heading content
            [
                'pattern' => '/<h[1-6][^>]*>([^<>{}\n]{3,100})<\/h[1-6]>/',
                'type'    => 'heading',
                'capture' => 1,
            ],
        ];

        // Translation key patterns
        $translationPatterns = [
            '/\$t\([\'"]([^\'"]+)[\'"]\)/',
            '/\bt\([\'"]([^\'"]+)[\'"]\)/',
            '/i18n\.t\([\'"]([^\'"]+)[\'"]\)/',
        ];

        foreach ($vueFiles as $filePath) {
            $content = File::get($filePath);
            $lines = explode("\n", $content);
            $relativePath = str_replace(resource_path('js') . DIRECTORY_SEPARATOR, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);

            $fileHardcoded = [];

            // Find used translation keys
            foreach ($translationPatterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                foreach ($matches[1] as $key) {
                    if (!isset($usedKeys[$key])) {
                        $usedKeys[$key] = [];
                    }
                    $usedKeys[$key][] = $relativePath;
                }
            }

            // Find hardcoded strings with line numbers
            foreach ($lines as $lineNum => $line) {
                $lineNumber = $lineNum + 1;

                foreach ($hardcodedPatterns as $patternInfo) {
                    $pattern = $patternInfo['pattern'];
                    $captureIndex = $patternInfo['capture'];

                    if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[$captureIndex] as $match) {
                            $text = $match[0];

                            // Skip if already using translation
                            if (preg_match('/\$t\(|\bt\(|i18n\.t\(/', $line)) {
                                continue;
                            }

                            // Skip common false positives
                            if ($this->shouldSkipString($text)) {
                                continue;
                            }

                            // Generate suggested translation key
                            $suggestedKey = $this->generateTranslationKey($relativePath, $text, $patternInfo['type']);

                            $fileHardcoded[] = [
                                'line'          => $lineNumber,
                                'column'        => $match[1] ?? 0,
                                'text'          => $text,
                                'type'          => $patternInfo['type'],
                                'context'       => trim($line),
                                'suggested_key' => $suggestedKey,
                                'suggested_fix' => $this->generateSuggestedFix($line, $text, $suggestedKey, $patternInfo['type']),
                            ];

                            $suggestedTranslations[$suggestedKey] = [
                                'en' => $text,
                                'de' => '', // To be filled
                            ];

                            $actionableChanges[] = [
                                'file'            => $relativePath,
                                'file_full_path'  => $filePath,
                                'line'            => $lineNumber,
                                'original'        => trim($line),
                                'text'            => $text,
                                'suggested_key'   => $suggestedKey,
                                'suggested_fix'   => $this->generateSuggestedFix($line, $text, $suggestedKey, $patternInfo['type']),
                                'type'            => 'js',
                            ];
                        }
                    }
                }
            }

            if (!empty($fileHardcoded)) {
                $filesWithHardcoded++;
                $totalHardcoded += count($fileHardcoded);

                $files[] = [
                    'path'      => $relativePath,
                    'full_path' => $filePath,
                    'type'      => 'vue/js',
                    'hardcoded' => $fileHardcoded,
                ];
            }
        }

        // Load English translations
        $enFile = $this->jsTranslationsPath . '/en.js';
        $enTranslations = [];
        if (File::exists($enFile)) {
            $enTranslations = $this->flattenArray(
                $this->parseJsTranslations(File::get($enFile))
            );
        }

        // Find missing keys
        $missingKeys = [];
        foreach ($usedKeys as $key => $keyFiles) {
            if (!isset($enTranslations[$key])) {
                $missingKeys[$key] = $keyFiles;
            }
        }

        return [
            'files'   => $files,
            'summary' => [
                'total_files'          => count($vueFiles),
                'files_with_hardcoded' => $filesWithHardcoded,
                'total_hardcoded'      => $totalHardcoded,
                'missing_keys'         => count($missingKeys),
            ],
            'missing_keys'            => $missingKeys,
            'suggested_translations'  => $suggestedTranslations,
            'actionable_changes'      => $actionableChanges,
        ];
    }

    /**
     * Generate detailed PHP report.
     */
    protected function generateDetailedPhpReport(): array
    {
        $phpFiles = $this->getFilesWithExtension(app_path(), ['php']);
        $bladeFiles = $this->getFilesWithExtension(resource_path('views'), ['php']);
        $allFiles = array_merge($phpFiles, $bladeFiles);

        $files = [];
        $suggestedTranslations = [];
        $actionableChanges = [];
        $usedKeys = [];
        $totalHardcoded = 0;
        $filesWithHardcoded = 0;

        $hardcodedPatterns = [
            // Response messages
            [
                'pattern' => '/return\s+response\(\)\s*->\s*json\(\s*\[\s*[\'"](?:message|error|success)[\'"]\s*=>\s*[\'"]([^\'"]{3,100})[\'"]/',
                'type'    => 'response_message',
                'capture' => 1,
            ],
            // Throw exceptions with messages
            [
                'pattern' => '/throw\s+new\s+\w+Exception\(\s*[\'"]([^\'"]{3,100})[\'"]/',
                'type'    => 'exception_message',
                'capture' => 1,
            ],
            // Validation messages
            [
                'pattern' => '/[\'"](?:required|min|max|email|unique)[\'"](?:\s*=>\s*|\s*,\s*)[\'"]([^\'"]{3,100})[\'"]/',
                'type'    => 'validation_message',
                'capture' => 1,
            ],
            // Flash messages
            [
                'pattern' => '/(?:flash|with)\([\'"](?:success|error|message|warning)[\'"]\s*,\s*[\'"]([^\'"]{3,100})[\'"]/',
                'type'    => 'flash_message',
                'capture' => 1,
            ],
            // Array messages
            [
                'pattern' => '/[\'"]message[\'"]\s*=>\s*[\'"]([^\'"]{3,100})[\'"]/',
                'type'    => 'array_message',
                'capture' => 1,
            ],
        ];

        $translationPatterns = [
            '/__\([\'"]([^\'"]+)[\'"]\)/',
            '/trans\([\'"]([^\'"]+)[\'"]\)/',
            '/trans_choice\([\'"]([^\'"]+)[\'"]/',
            '/@lang\([\'"]([^\'"]+)[\'"]\)/',
            '/Lang::get\([\'"]([^\'"]+)[\'"]\)/',
        ];

        foreach ($allFiles as $filePath) {
            $content = File::get($filePath);
            $lines = explode("\n", $content);
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);

            $fileHardcoded = [];

            // Find used translation keys
            foreach ($translationPatterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                foreach ($matches[1] as $key) {
                    if (!isset($usedKeys[$key])) {
                        $usedKeys[$key] = [];
                    }
                    $usedKeys[$key][] = $relativePath;
                }
            }

            // Find hardcoded strings
            foreach ($lines as $lineNum => $line) {
                $lineNumber = $lineNum + 1;

                // Skip if already using translation
                if (preg_match('/__\(|trans\(|@lang\(|Lang::get\(/', $line)) {
                    continue;
                }

                foreach ($hardcodedPatterns as $patternInfo) {
                    if (preg_match_all($patternInfo['pattern'], $line, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[$patternInfo['capture']] as $match) {
                            $text = $match[0];

                            if ($this->shouldSkipString($text)) {
                                continue;
                            }

                            $suggestedKey = $this->generatePhpTranslationKey($relativePath, $text, $patternInfo['type']);

                            $fileHardcoded[] = [
                                'line'          => $lineNumber,
                                'text'          => $text,
                                'type'          => $patternInfo['type'],
                                'context'       => trim($line),
                                'suggested_key' => $suggestedKey,
                                'suggested_fix' => str_replace("'{$text}'", "__('messages.{$suggestedKey}')", $line),
                            ];

                            $suggestedTranslations[$suggestedKey] = [
                                'en' => $text,
                                'de' => '',
                            ];

                            $actionableChanges[] = [
                                'file'           => $relativePath,
                                'file_full_path' => $filePath,
                                'line'           => $lineNumber,
                                'original'       => trim($line),
                                'text'           => $text,
                                'suggested_key'  => 'messages.' . $suggestedKey,
                                'suggested_fix'  => str_replace("'{$text}'", "__('messages.{$suggestedKey}')", trim($line)),
                                'type'           => 'php',
                            ];
                        }
                    }
                }
            }

            if (!empty($fileHardcoded)) {
                $filesWithHardcoded++;
                $totalHardcoded += count($fileHardcoded);

                $files[] = [
                    'path'      => $relativePath,
                    'full_path' => $filePath,
                    'type'      => 'php',
                    'hardcoded' => $fileHardcoded,
                ];
            }
        }

        // Load English translations
        $enPath = $this->phpTranslationsPath . '/en';
        $enTranslations = [];
        if (File::isDirectory($enPath)) {
            foreach (File::files($enPath) as $file) {
                if ($file->getExtension() === 'php') {
                    $fileName = $file->getFilenameWithoutExtension();
                    $translations = include $file->getPathname();
                    foreach ($this->flattenArray($translations) as $key => $value) {
                        $enTranslations[$fileName . '.' . $key] = $value;
                    }
                }
            }
        }

        $missingKeys = [];
        foreach ($usedKeys as $key => $keyFiles) {
            if (!isset($enTranslations[$key])) {
                $missingKeys[$key] = $keyFiles;
            }
        }

        return [
            'files'   => $files,
            'summary' => [
                'total_files'          => count($allFiles),
                'files_with_hardcoded' => $filesWithHardcoded,
                'total_hardcoded'      => $totalHardcoded,
                'missing_keys'         => count($missingKeys),
            ],
            'missing_keys'           => $missingKeys,
            'suggested_translations' => $suggestedTranslations,
            'actionable_changes'     => $actionableChanges,
        ];
    }

    /**
     * Check if a string should be skipped (false positive).
     */
    protected function shouldSkipString(string $text): bool
    {
        // Skip very short strings
        if (strlen($text) < 3) {
            return true;
        }

        // Skip if it looks like a translation key
        if (preg_match('/^[a-z_]+\.[a-z_]+/i', $text)) {
            return true;
        }

        // Skip template variables
        if (preg_match('/^\{|^\$|^{{/', $text)) {
            return true;
        }

        // Skip icons
        if (preg_match('/^mdi-/', $text)) {
            return true;
        }

        // Skip colors
        if (preg_match('/^#[a-fA-F0-9]{3,6}$/', $text)) {
            return true;
        }

        // Skip numbers
        if (preg_match('/^\d+$/', $text)) {
            return true;
        }

        // Skip URLs
        if (preg_match('/^https?:\/\/|^\/[a-z]/i', $text)) {
            return true;
        }

        // Skip CSS classes
        if (preg_match('/^[a-z-]+(-[a-z]+)+$/', $text)) {
            return true;
        }

        // Skip common technical strings
        $skipPatterns = [
            '/^(id|class|style|type|name|value|href|src)$/',
            '/^v-[a-z]/',
            '/^:[a-z]/',
            '/^@[a-z]/',
            '/^\d+px$/',
            '/^(GET|POST|PUT|DELETE|PATCH)$/',
            '/^(true|false|null)$/i',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a translation key based on context.
     */
    protected function generateTranslationKey(string $filePath, string $text, string $type): string
    {
        // Extract component/page name from path
        $pathParts = explode('/', $filePath);
        $fileName = pathinfo(end($pathParts), PATHINFO_FILENAME);

        // Determine prefix based on file location
        $prefix = 'common';
        if (str_contains($filePath, 'pages/admin/')) {
            $prefix = 'admin';
        } elseif (str_contains($filePath, 'pages/auth/')) {
            $prefix = 'auth';
        } elseif (str_contains($filePath, 'components/')) {
            $prefix = 'components';
        } elseif (str_contains($filePath, 'layouts/')) {
            $prefix = 'layout';
        }

        // Generate key from text
        $key = strtolower($text);
        $key = preg_replace('/[^a-z0-9\s]/', '', $key);
        $key = preg_replace('/\s+/', '_', $key);
        $key = substr($key, 0, 30);
        $key = rtrim($key, '_');

        // Add type suffix for context
        $typeSuffix = match ($type) {
            'button_text'   => '_btn',
            'card_title'    => '_title',
            'vue_attribute' => '_label',
            'heading'       => '_heading',
            default         => '',
        };

        return $prefix . '.' . $fileName . '.' . $key . $typeSuffix;
    }

    /**
     * Generate PHP translation key.
     */
    protected function generatePhpTranslationKey(string $filePath, string $text, string $type): string
    {
        // Extract controller/class name
        $pathParts = explode('/', $filePath);
        $fileName = pathinfo(end($pathParts), PATHINFO_FILENAME);

        $prefix = match ($type) {
            'response_message' => 'success',
            'exception_message' => 'error',
            'validation_message' => 'validation',
            'flash_message' => 'flash',
            default => 'general',
        };

        $key = strtolower($text);
        $key = preg_replace('/[^a-z0-9\s]/', '', $key);
        $key = preg_replace('/\s+/', '_', $key);
        $key = substr($key, 0, 30);
        $key = rtrim($key, '_');

        return $prefix . '.' . $key;
    }

    /**
     * Generate suggested fix for a hardcoded string.
     */
    protected function generateSuggestedFix(string $line, string $text, string $suggestedKey, string $type): string
    {
        $escapedText = preg_quote($text, '/');

        return match ($type) {
            'vue_attribute' => preg_replace(
                '/="' . $escapedText . '"/',
                '="\$t(\'' . $suggestedKey . '\')"',
                $line
            ),
            'button_text', 'card_title', 'heading', 'vue_content' => preg_replace(
                '/>' . $escapedText . '</',
                '>{{ $t(\'' . $suggestedKey . '\') }}<',
                $line
            ),
            'js_property' => preg_replace(
                '/:\s*[\'"]' . $escapedText . '[\'"]/',
                ': t(\'' . $suggestedKey . '\')',
                $line
            ),
            'message_call' => preg_replace(
                '/\([\'"]' . $escapedText . '[\'"]\)/',
                '(t(\'' . $suggestedKey . '\'))',
                $line
            ),
            default => $line,
        };
    }

    /**
     * Generate markdown report for AI consumption.
     */
    protected function generateMarkdownReport(array $report): string
    {
        $md = "# Translation Report\n\n";
        $md .= "Generated: {$report['generated_at']}\n\n";

        $md .= "## Summary\n\n";
        $md .= "| Metric | Count |\n";
        $md .= "|--------|-------|\n";
        $md .= "| Total Files Scanned | {$report['summary']['total_files_scanned']} |\n";
        $md .= "| Files with Hardcoded Strings | {$report['summary']['files_with_hardcoded']} |\n";
        $md .= "| Total Hardcoded Strings | {$report['summary']['total_hardcoded_strings']} |\n";
        $md .= "| Missing Translation Keys | {$report['summary']['missing_translation_keys']} |\n\n";

        $md .= "## Actionable Changes\n\n";
        $md .= "Below are the files that need translation updates:\n\n";

        $groupedChanges = [];
        foreach ($report['actionable_changes'] as $change) {
            $file = $change['file'];
            if (!isset($groupedChanges[$file])) {
                $groupedChanges[$file] = [];
            }
            $groupedChanges[$file][] = $change;
        }

        foreach ($groupedChanges as $file => $changes) {
            $md .= "### `{$file}`\n\n";

            foreach ($changes as $change) {
                $md .= "**Line {$change['line']}:**\n";
                $md .= "- Text: `{$change['text']}`\n";
                $md .= "- Suggested Key: `{$change['suggested_key']}`\n";
                $md .= "- Original:\n```\n{$change['original']}\n```\n";
                $md .= "- Suggested Fix:\n```\n{$change['suggested_fix']}\n```\n\n";
            }
        }

        $md .= "## Suggested Translations to Add\n\n";
        $md .= "Add these to your translation files:\n\n";
        $md .= "```javascript\n";
        $md .= "// For en.js\n";
        foreach ($report['suggested_translations'] as $key => $values) {
            $md .= "'{$key}': '{$values['en']}',\n";
        }
        $md .= "```\n";

        return $md;
    }

    /**
     * Scan for missing JS translations in Vue files.
     */
    protected function scanMissingJsTranslations(): array
    {
        $vueFiles = $this->getFilesWithExtension(resource_path('js'), ['vue', 'js']);
        $usedKeys = [];
        $hardcoded = [];

        // Patterns to match
        $translationPatterns = [
            '/\$t\([\'"]([^\'"]+)[\'"]\)/',           // $t('key')
            '/\bt\([\'"]([^\'"]+)[\'"]\)/',            // t('key')
            '/i18n\.t\([\'"]([^\'"]+)[\'"]\)/',        // i18n.t('key')
        ];

        // Pattern for potential hardcoded strings
        $hardcodedPatterns = [
            '/<v-[^>]*(?:label|title|text|placeholder|hint|message)="([^"]+)"/',
            '/(?:label|title|placeholder|hint|message)\s*:\s*[\'"]([^\'"]+)[\'"]/',
        ];

        foreach ($vueFiles as $file) {
            $content = File::get($file);
            $relativePath = str_replace(resource_path('js') . DIRECTORY_SEPARATOR, '', $file);

            // Find used translation keys
            foreach ($translationPatterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                foreach ($matches[1] as $key) {
                    if (!isset($usedKeys[$key])) {
                        $usedKeys[$key] = [];
                    }
                    $usedKeys[$key][] = $relativePath;
                }
            }

            // Find potential hardcoded strings (excluding very short ones and common values)
            foreach ($hardcodedPatterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                foreach ($matches[1] as $match) {
                    // Skip if it looks like a translation key, variable, or is too short
                    if ($this->shouldSkipString($match)) {
                        continue;
                    }

                    $hardcoded[] = [
                        'file'  => $relativePath,
                        'text'  => $match,
                    ];
                }
            }
        }

        // Load English translations as reference
        $enFile = $this->jsTranslationsPath . '/en.js';
        $enTranslations = [];
        if (File::exists($enFile)) {
            $enTranslations = $this->flattenArray(
                $this->parseJsTranslations(File::get($enFile))
            );
        }

        // Find keys used in code but not in translations
        $missingKeys = [];
        foreach ($usedKeys as $key => $files) {
            if (!isset($enTranslations[$key])) {
                $missingKeys[$key] = $files;
            }
        }

        return [
            'used_keys'    => array_keys($usedKeys),
            'missing_keys' => $missingKeys,
            'hardcoded'    => array_slice($hardcoded, 0, 100), // Limit to 100
        ];
    }

    /**
     * Scan for missing PHP translations in PHP/Blade files.
     */
    protected function scanMissingPhpTranslations(): array
    {
        $phpFiles = $this->getFilesWithExtension(app_path(), ['php']);
        $bladeFiles = $this->getFilesWithExtension(resource_path('views'), ['php']);
        $allFiles = array_merge($phpFiles, $bladeFiles);

        $usedKeys = [];
        $hardcoded = [];

        // Patterns to match Laravel translation helpers
        $translationPatterns = [
            '/__\([\'"]([^\'"]+)[\'"]\)/',              // __('key')
            '/trans\([\'"]([^\'"]+)[\'"]\)/',           // trans('key')
            '/trans_choice\([\'"]([^\'"]+)[\'"]/',      // trans_choice('key', ...)
            '/@lang\([\'"]([^\'"]+)[\'"]\)/',           // @lang('key')
            '/Lang::get\([\'"]([^\'"]+)[\'"]\)/',       // Lang::get('key')
        ];

        foreach ($allFiles as $file) {
            $content = File::get($file);
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);

            foreach ($translationPatterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                foreach ($matches[1] as $key) {
                    if (!isset($usedKeys[$key])) {
                        $usedKeys[$key] = [];
                    }
                    $usedKeys[$key][] = $relativePath;
                }
            }
        }

        // Load English translations as reference
        $enPath = $this->phpTranslationsPath . '/en';
        $enTranslations = [];
        if (File::isDirectory($enPath)) {
            foreach (File::files($enPath) as $file) {
                if ($file->getExtension() === 'php') {
                    $fileName = $file->getFilenameWithoutExtension();
                    $translations = include $file->getPathname();
                    foreach ($this->flattenArray($translations) as $key => $value) {
                        $enTranslations[$fileName . '.' . $key] = $value;
                    }
                }
            }
        }

        // Find keys used in code but not in translations
        $missingKeys = [];
        foreach ($usedKeys as $key => $files) {
            if (!isset($enTranslations[$key])) {
                $missingKeys[$key] = $files;
            }
        }

        return [
            'used_keys'    => array_keys($usedKeys),
            'missing_keys' => $missingKeys,
            'hardcoded'    => $hardcoded,
        ];
    }

    /**
     * Get all files with specific extensions recursively.
     */
    protected function getFilesWithExtension(string $directory, array $extensions): array
    {
        if (!File::isDirectory($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions)) {
                // Skip vendor and node_modules
                $path = $file->getPathname();
                if (strpos($path, 'vendor') !== false || strpos($path, 'node_modules') !== false) {
                    continue;
                }
                $files[] = $path;
            }
        }

        return $files;
    }
}
