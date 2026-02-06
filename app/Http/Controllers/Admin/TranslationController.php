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
            return response()->json(['error' => 'Locale not found'], 404);
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
                'message'    => 'Translations updated successfully',
                'locale'     => $locale,
                'total_keys' => count($translations),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to save translations',
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
            return response()->json(['error' => 'Locale not found'], 404);
        }

        if ($file) {
            // Get specific file
            $filePath = $localePath . '/' . $file . '.php';
            if (!File::exists($filePath)) {
                return response()->json(['error' => 'Translation file not found'], 404);
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
                'message'    => 'Translations updated successfully',
                'locale'     => $locale,
                'file'       => $file,
                'total_keys' => count($translations),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to save translations',
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
            'message' => 'Locale created successfully',
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
            'message' => 'Translation key added successfully',
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
            'message' => 'Translation key deleted successfully',
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
                return response()->json(['error' => 'Base locale not found'], 404);
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
                return response()->json(['error' => 'Base locale not found'], 404);
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

        // Convert JS object to JSON
        // Replace single quotes with double quotes
        $content = preg_replace("/'/", '"', $content);

        // Handle unquoted keys
        $content = preg_replace('/(\s*)(\w+)(\s*):/', '$1"$2"$3:', $content);

        // Remove trailing commas before closing braces
        $content = preg_replace('/,(\s*[\}\]])/', '$1', $content);

        try {
            return json_decode($content, true) ?? [];
        } catch (\Exception $e) {
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
                    if (strlen($match) < 3) continue;
                    if (preg_match('/^[a-z_]+\.[a-z_]+/', $match)) continue; // Looks like a key
                    if (preg_match('/^\{/', $match)) continue; // Template variable
                    if (preg_match('/^mdi-/', $match)) continue; // Icon
                    if (preg_match('/^#[a-fA-F0-9]{3,6}$/', $match)) continue; // Color
                    if (preg_match('/^\d+$/', $match)) continue; // Number

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
