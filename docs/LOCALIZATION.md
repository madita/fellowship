# Localization Guide

This guide explains how to implement and extend the localization (i18n) system in the Fellowship application.

## Overview

The application uses **vue-i18n** for frontend internationalization. The system supports:
- Multiple languages (currently English and German are active)
- Browser language auto-detection
- Runtime language switching
- Vuetify component translations
- Pluralization support

## File Structure

```
resources/js/
├── plugins/
│   └── vue-i18n.js          # i18n plugin initialization
├── configs/
│   └── locales.js           # Locale configuration and available languages
├── translations/
│   ├── en.js                # English translations (active)
│   ├── de.js                # German translations (active)
│   ├── ar.js                # Arabic (available, not active)
│   ├── es.js                # Spanish (available, not active)
│   ├── fr.js                # French (available, not active)
│   ├── ja.js                # Japanese (available, not active)
│   ├── ko.js                # Korean (available, not active)
│   ├── pl.js                # Polish (available, not active)
│   ├── pt.js                # Portuguese (available, not active)
│   ├── ru.js                # Russian (available, not active)
│   └── zh.js                # Chinese (available, not active)
└── components/toolbar/
    └── ToolbarLanguage.vue  # Language switcher component
```

## How It Works

### 1. Plugin Initialization (`plugins/vue-i18n.js`)

```javascript
import { createI18n } from 'vue-i18n'
import locales from '../configs/locales.js'

const { locale, availableLocales, fallbackLocale } = locales

const messages = {}
availableLocales.forEach((l) => { messages[l.code] = l.messages })

export const i18n = new createI18n({
  locale: locale,
  fallbackLocale,
  messages,
  legacy: false   // Use Composition API mode
})
```

### 2. Locale Configuration (`configs/locales.js`)

```javascript
import en from '../translations/en'
import de from '../translations/de'

const supported = ['en', 'de']
let locale = 'en'

// Auto-detect browser language
try {
  const { 0: browserLang } = navigator.language.split('-')
  if (supported.includes(browserLang)) locale = browserLang
} catch (e) {
  console.log(e)
}

const availableLocales = [
    {
        code: 'en',
        flag: 'us',
        label: 'EN',
        messages: en,
    },
    {
        code: 'de',
        flag: 'de',
        label: 'DE',
        messages: de,
    },
];

export default { locale, availableLocales };
```

---

## Adding a New Language

### Step 1: Create Translation File

Create a new file in `resources/js/translations/` (e.g., `fr.js` for French):

```javascript
export default {
    common: {
        add: 'Ajouter',
        cancel: 'Annuler',
        description: 'Description',
        delete: 'Supprimer',
        title: 'Titre',
        save: 'Sauvegarder',
        faq: 'FAQ',
        contact: 'Contactez-nous',
        tos: 'Conditions d\'utilisation',
        policy: 'Politique de confidentialité'
    },
    // ... continue with all other sections from en.js
}
```

**Important:** Copy the structure from `en.js` and translate all keys. The structure must match exactly.

### Step 2: Register the Language

Update `resources/js/configs/locales.js`:

```javascript
import en from '../translations/en'
import de from '../translations/de'
import fr from '../translations/fr'  // Add import

const supported = ['en', 'de', 'fr']  // Add to supported array

const availableLocales = [
    {
        code: 'en',
        flag: 'us',
        label: 'EN',
        messages: en,
    },
    {
        code: 'de',
        flag: 'de',
        label: 'DE',
        messages: de,
    },
    {
        code: 'fr',           // Add new locale
        flag: 'fr',
        label: 'FR',
        messages: fr,
    },
];
```

---

## Using Translations in Components

### In Templates (Options API or Composition API)

```vue
<template>
  <!-- Simple translation -->
  <span>{{ $t('common.save') }}</span>

  <!-- With interpolation -->
  <span>{{ $t('chat.online', { count: userCount }) }}</span>

  <!-- Pluralization -->
  <span>{{ $t('chat.channel', 2) }}</span>

  <!-- In attributes -->
  <v-btn :label="$t('common.cancel')" />
</template>
```

### In Script (Composition API)

```vue
<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const saveLabel = computed(() => t('common.save'))

function showMessage() {
  alert(t('common.delete'))
}
</script>
```

### In Script (Options API)

```vue
<script>
export default {
  methods: {
    showMessage() {
      alert(this.$t('common.delete'))
    }
  },
  computed: {
    saveLabel() {
      return this.$t('common.save')
    }
  }
}
</script>
```

---

## Translation Key Structure

The translation files are organized by feature/section:

| Section | Description | Example Keys |
|---------|-------------|--------------|
| `common` | Common UI elements | `common.save`, `common.cancel`, `common.delete` |
| `login` | Login page | `login.title`, `login.email`, `login.password` |
| `register` | Registration page | `register.title`, `register.button` |
| `menu` | Navigation menu items | `menu.dashboard`, `menu.settings` |
| `dashboard` | Dashboard page | `dashboard.activity`, `dashboard.sales` |
| `usermenu` | User dropdown menu | `usermenu.profile`, `usermenu.signout` |
| `error` | Error pages | `error.notfound`, `error.other` |
| `$vuetify` | Vuetify component translations | `$vuetify.dataTable.sortBy` |

---

## Interpolation and Pluralization

### Interpolation (Dynamic Values)

Translation:
```javascript
chat: {
    online: 'Users Online ({count})'
}
```

Usage:
```vue
<span>{{ $t('chat.online', { count: 5 }) }}</span>
<!-- Output: "Users Online (5)" -->
```

### Pluralization

Translation:
```javascript
chat: {
    channel: 'Channel | Channels'
}
```

Usage:
```vue
<span>{{ $t('chat.channel', 1) }}</span>  <!-- Output: "Channel" -->
<span>{{ $t('chat.channel', 2) }}</span>  <!-- Output: "Channels" -->
```

### Advanced Pluralization

```javascript
pluralize: {
    zero: 'No users',
    one: '1 user',
    two: '2 users',
    few: '{n} users',
    many: '{n} users',
    other: 'no one | {n} other | {n} others'
}
```

---

## Language Switcher Component

The `ToolbarLanguage.vue` component provides a dropdown menu for switching languages:

```vue
<template>
  <ToolbarLanguage />

  <!-- With options -->
  <ToolbarLanguage :show-arrow="true" :show-label="false" />
</template>
```

**Props:**
- `showArrow` (Boolean, default: false) - Show dropdown arrow icon
- `showLabel` (Boolean, default: true) - Show language label text

---

## Changing Language Programmatically

```javascript
import { i18n } from '@/plugins/vue-i18n.js'

// Set language
i18n.global.locale.value = 'de'

// Get current language
const currentLang = i18n.global.locale.value
```

---

## RTL (Right-to-Left) Support

For RTL languages like Arabic, uncomment and modify the RTL handling in `ToolbarLanguage.vue`:

```javascript
setLocale(locale) {
  i18n.global.locale.value = locale

  // Enable RTL for Arabic
  if (locale === 'ar') {
    this.$vuetify.rtl = true
  } else {
    this.$vuetify.rtl = false
  }
}
```

---

## Backend Localization (Laravel)

Laravel translations are stored in `resources/lang/`:

```
resources/lang/
├── en/
│   ├── auth.php
│   ├── pagination.php
│   ├── passwords.php
│   └── validation.php
└── de/           # Create this folder for German
    ├── auth.php
    ├── pagination.php
    ├── passwords.php
    └── validation.php
```

### Using Laravel Translations

In Blade templates:
```blade
{{ __('auth.failed') }}
{{ trans('validation.required', ['attribute' => 'email']) }}
```

In PHP:
```php
__('auth.failed');
trans('validation.required', ['attribute' => 'email']);
```

---

## Admin Settings

The following settings control language behavior (stored in database):

| Setting | Description |
|---------|-------------|
| `default_language` | Default language for new users (e.g., 'en') |
| `language_change_enabled` | Whether users can change their language |

---

## Best Practices

1. **Always use translation keys** instead of hardcoded strings
2. **Keep keys organized** by feature/section
3. **Use interpolation** for dynamic content instead of string concatenation
4. **Test all languages** after adding new keys
5. **Maintain consistency** - if you add a key in one language, add it in all
6. **Use the English file as the source** - translate from `en.js` to other languages

---

## Checklist for Adding Translations

- [ ] Add key to `en.js` (source of truth)
- [ ] Add translated key to all other active language files (`de.js`, etc.)
- [ ] Test the component in all languages
- [ ] Test pluralization if applicable
- [ ] Test interpolation with edge cases (0, 1, many)

---

## Troubleshooting

### Translation key showing instead of text

**Problem:** You see `common.save` instead of "Save"

**Solutions:**
1. Check if the key exists in the translation file
2. Check for typos in the key path
3. Ensure the language file is imported in `locales.js`
4. Check if i18n is properly initialized

### Language not changing

**Problem:** Clicking language switcher doesn't change the language

**Solutions:**
1. Check browser console for errors
2. Verify `i18n.global.locale.value` is being set correctly
3. Ensure the locale code matches exactly (case-sensitive)

### Missing Vuetify translations

**Problem:** Vuetify components show default English

**Solution:** Add `$vuetify` section to your translation file with all Vuetify strings.

---

# Timezone & Date Format Guide

This section explains how to implement and use timezone and date/time formatting in the Fellowship application.

## Overview

The system uses **date-fns** with **date-fns-tz** for date formatting and timezone conversion. It supports:
- Global default settings (admin-configurable)
- Per-user preferences (user-configurable)
- Automatic fallback chain: User Setting → Global Setting → Default
- PHP to date-fns format conversion
- Localized date formatting

## File Structure

```
resources/js/
├── plugins/
│   └── formatDate.js          # Main date formatting plugin & composable
├── utils/
│   └── formatConverter.js     # PHP to date-fns format converter
├── store/
│   ├── settingStore.js        # Global settings (default_timezone, date_format, time_format)
│   └── userStore.js           # User preferences (timezone, date_format, time_format)
└── pages/users/EditUser/
    └── AccountTab.vue         # User preferences UI

app/
├── Models/
│   └── User.php               # User model with timezone/format accessors
└── Http/Controllers/
    └── UserController.php     # Preferences update endpoint
```

---

## Settings Hierarchy

The system uses a fallback chain for timezone and format settings:

```
User Preference (if set)
    ↓ (fallback)
Global Setting (admin-configured)
    ↓ (fallback)
Default Value (hardcoded)
```

### Global Settings (Admin)

| Setting | Description | Default |
|---------|-------------|---------|
| `default_timezone` | Default timezone for all users | `UTC` |
| `date_format` | Default date format (PHP format) | `Y-m-d` |
| `time_format` | Default time format (PHP format) | `H:i:s` |

### User Settings (Per-User)

| Setting | Description | Default |
|---------|-------------|---------|
| `timezone` | User's preferred timezone | `null` (uses global) |
| `date_format` | User's preferred date format | `null` (uses global) |
| `time_format` | User's preferred time format | `null` (uses global) |

---

## Available Formats

### Date Formats

| PHP Format | Example Output | Description |
|------------|----------------|-------------|
| `Y-m-d` | 2025-12-17 | ISO format (default) |
| `d/m/Y` | 17/12/2025 | European format |
| `m/d/Y` | 12/17/2025 | US format |
| `d.m.Y` | 17.12.2025 | German format |

### Time Formats

| PHP Format | Example Output | Description |
|------------|----------------|-------------|
| `H:i:s` | 14:30:45 | 24-hour with seconds |
| `h:i:s A` | 02:30:45 PM | 12-hour with seconds |
| `H:i` | 14:30 | 24-hour without seconds |
| `h:i A` | 02:30 PM | 12-hour without seconds |

---

## Using Date Formatting in Components

### Method 1: Global Properties (Options API)

The plugin registers global properties accessible via `this.$formatDate`:

```vue
<template>
  <div>
    <!-- Basic usage (uses user's preferred format) -->
    <span>{{ $formatDate(event.created_at) }}</span>

    <!-- With custom format -->
    <span>{{ $formatDate(event.created_at, 'd LLL yyyy') }}</span>

    <!-- Relative time -->
    <span>{{ $formatDistanceToNow(event.created_at) }}</span>
  </div>
</template>

<script>
export default {
  methods: {
    formatEventDate(date) {
      return this.$formatDate(date, 'Y-m-d H:i:s')
    }
  }
}
</script>
```

### Method 2: Composable (Composition API)

```vue
<script setup>
import { useDateFormat } from '@/plugins/formatDate.js'

const { formatDate, formatDateInTimezone, formatDistanceToNow } = useDateFormat()

// Basic usage
const formattedDate = computed(() => formatDate(event.value.created_at))

// With custom format
const customFormatted = computed(() => formatDate(event.value.created_at, 'd LLL yyyy'))

// In a specific timezone
const tokyoTime = computed(() => formatDateInTimezone(event.value.created_at, null, 'Asia/Tokyo'))
</script>
```

### Method 3: Direct Import

```javascript
import { formatDate, formatDateInTimezone, formatDateDistanceToNow } from '@/plugins/formatDate.js'

// In any JavaScript file
const formatted = formatDate('2025-12-17T10:30:00Z')
const inTimezone = formatDateInTimezone('2025-12-17T10:30:00Z', 'Y-m-d H:i:s', 'Europe/Berlin')
const relative = formatDateDistanceToNow('2025-12-17T10:30:00Z')
```

---

## Available Functions

### `formatDate(dateString, formatString?, options?)`

Formats a date using user preferences.

```javascript
// Uses user's preferred date format
formatDate('2025-12-17T10:30:00Z')
// Output: "2025-12-17" (or user's format)

// With custom format (PHP format)
formatDate('2025-12-17T10:30:00Z', 'Y-m-d H:i:s')
// Output: "2025-12-17 10:30:00"

// With date-fns format
formatDate('2025-12-17T10:30:00Z', 'dd MMM yyyy HH:mm')
// Output: "17 Dec 2025 10:30"

// Disable timezone conversion
formatDate('2025-12-17T10:30:00Z', null, { useTimezone: false })
```

### `formatDateInTimezone(dateString, formatString?, timezone?)`

Formats a date in a specific timezone.

```javascript
// In user's timezone with user's format
formatDateInTimezone('2025-12-17T10:30:00Z')

// In a specific timezone
formatDateInTimezone('2025-12-17T10:30:00Z', 'Y-m-d H:i:s', 'Asia/Tokyo')
// Output: "2025-12-17 19:30:00"

formatDateInTimezone('2025-12-17T10:30:00Z', 'Y-m-d H:i:s', 'America/New_York')
// Output: "2025-12-17 05:30:00"
```

### `formatDateDistance(date1, date2?)`

Formats the distance between two dates.

```javascript
formatDateDistance('2025-12-15T10:00:00Z', '2025-12-17T10:00:00Z')
// Output: "in 2 days"

formatDateDistance('2025-12-17T10:00:00Z', '2025-12-15T10:00:00Z')
// Output: "2 days ago"
```

### `formatDateDistanceToNow(dateString)`

Formats the distance from a date to now.

```javascript
formatDateDistanceToNow('2025-12-15T10:00:00Z')
// Output: "2 days ago"

formatDateDistanceToNow('2025-12-20T10:00:00Z')
// Output: "in 3 days"
```

---

## PHP to date-fns Format Conversion

The system automatically converts PHP date formats to date-fns formats.

### Common Conversions

| PHP | date-fns | Description |
|-----|----------|-------------|
| `Y` | `yyyy` | 4-digit year |
| `y` | `yy` | 2-digit year |
| `m` | `MM` | Month (01-12) |
| `n` | `M` | Month (1-12) |
| `F` | `MMMM` | Full month name |
| `M` | `MMM` | Short month name |
| `d` | `dd` | Day (01-31) |
| `j` | `d` | Day (1-31) |
| `H` | `HH` | Hour 24h (00-23) |
| `h` | `hh` | Hour 12h (01-12) |
| `i` | `mm` | Minutes |
| `s` | `ss` | Seconds |
| `A` | `a` | AM/PM |

### Using Custom Formats

```javascript
// PHP format - automatically converted
formatDate(date, 'Y-m-d H:i:s')

// date-fns format - used directly
formatDate(date, 'yyyy-MM-dd HH:mm:ss')

// Both produce the same output
```

---

## Backend Implementation

### User Model (`app/Models/User.php`)

The User model has accessor methods that implement the fallback chain:

```php
/**
 * Get the user's timezone preference with fallback to global setting.
 */
public function getTimezoneAttribute()
{
    // Get the raw database value
    $value = $this->getAttributeFromArray('timezone');

    // Return user's preference if set
    if ($value !== null && $value !== '') {
        return $value;
    }

    // Try to get from global settings, with fallback to UTC
    try {
        return Setting::get('default_timezone', 'UTC');
    } catch (\Exception $e) {
        return 'UTC';
    }
}

public function getDateFormatAttribute()
{
    $value = $this->getAttributeFromArray('date_format');

    if ($value !== null && $value !== '') {
        return $value;
    }

    try {
        return Setting::get('date_format', 'Y-m-d');
    } catch (\Exception $e) {
        return 'Y-m-d';
    }
}

public function getTimeFormatAttribute()
{
    $value = $this->getAttributeFromArray('time_format');

    if ($value !== null && $value !== '') {
        return $value;
    }

    try {
        return Setting::get('time_format', 'H:i:s');
    } catch (\Exception $e) {
        return 'H:i:s';
    }
}
```

### User Controller (`app/Http/Controllers/UserController.php`)

```php
public function updatePreferences(Request $request)
{
    $validated = $request->validate([
        'timezone'    => 'nullable|string|timezone',
        'date_format' => 'nullable|string|in:Y-m-d,d/m/Y,m/d/Y,d.m.Y',
        'time_format' => 'nullable|string|in:H:i:s,h:i:s A,H:i,h:i A',
        'theme_mode'  => 'nullable|string|in:light,dark,system',
        'language'    => 'nullable|string|in:en,de,es,fr,it,pt,ja,zh',
    ]);

    $user = auth()->user();
    $user->update($validated);

    return response()->json([
        'message' => 'Preferences updated successfully',
        'user'    => $user,
    ]);
}
```

### API Endpoint

```
PATCH /api/account/preferences
```

**Request Body:**
```json
{
    "timezone": "Europe/Berlin",
    "date_format": "d.m.Y",
    "time_format": "H:i"
}
```

---

## Frontend Store Integration

### Settings Store (`store/settingStore.js`)

```javascript
// Getters for global defaults
defaultTimezone: (state) => state.appSettings.default_timezone || 'UTC',
defaultDateFormat: (state) => state.appSettings.date_format || 'Y-m-d',
defaultTimeFormat: (state) => state.appSettings.time_format || 'H:i:s',
```

### User Store (`store/userStore.js`)

```javascript
// Getters for user preferences
userTimezone: (state) => state.user?.timezone || null,
userDateFormat: (state) => state.user?.date_format || null,
userTimeFormat: (state) => state.user?.time_format || null,

// Action to update preferences
async updatePreferences(preferences) {
    try {
        const { data } = await api.patch('/account/preferences', preferences)
        await this.refreshUserInfo()
        return data
    } catch (error) {
        throw error
    }
}
```

---

## Adding a User Preferences UI

Here's an example of a preferences form component:

```vue
<template>
  <v-card>
    <v-card-title>Localization Preferences</v-card-title>
    <v-card-text>
      <!-- Timezone -->
      <v-autocomplete
        v-model="preferences.timezone"
        label="Timezone"
        :items="timezones"
        item-title="label"
        item-value="value"
        clearable
      />

      <!-- Date Format -->
      <v-select
        v-model="preferences.date_format"
        label="Date Format"
        :items="dateFormats"
        item-title="label"
        item-value="value"
        clearable
      />

      <!-- Time Format -->
      <v-select
        v-model="preferences.time_format"
        label="Time Format"
        :items="timeFormats"
        item-title="label"
        item-value="value"
        clearable
      />

      <!-- Preview -->
      <v-alert type="info" class="mt-4">
        Preview: {{ previewDateTime }}
      </v-alert>

      <v-btn color="primary" @click="savePreferences">
        Save Preferences
      </v-btn>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useUserStore } from '@/store/userStore.js'
import { useSettingsStore } from '@/store/settingStore.js'
import { formatDateInTimezone } from '@/plugins/formatDate.js'

const userStore = useUserStore()
const settingsStore = useSettingsStore()

const preferences = ref({
  timezone: userStore.user?.timezone || null,
  date_format: userStore.user?.date_format || null,
  time_format: userStore.user?.time_format || null,
})

const timezones = [
  { label: 'UTC', value: 'UTC' },
  { label: 'Europe/Berlin (CET)', value: 'Europe/Berlin' },
  { label: 'America/New_York (Eastern)', value: 'America/New_York' },
  // ... add more
]

const dateFormats = [
  { label: 'YYYY-MM-DD (2025-12-17)', value: 'Y-m-d' },
  { label: 'DD/MM/YYYY (17/12/2025)', value: 'd/m/Y' },
  { label: 'MM/DD/YYYY (12/17/2025)', value: 'm/d/Y' },
  { label: 'DD.MM.YYYY (17.12.2025)', value: 'd.m.Y' },
]

const timeFormats = [
  { label: '24-hour (14:30:45)', value: 'H:i:s' },
  { label: '12-hour (02:30:45 PM)', value: 'h:i:s A' },
  { label: '24-hour short (14:30)', value: 'H:i' },
  { label: '12-hour short (02:30 PM)', value: 'h:i A' },
]

const previewDateTime = computed(() => {
  const tz = preferences.value.timezone || settingsStore.defaultTimezone
  const dateFmt = preferences.value.date_format || settingsStore.defaultDateFormat
  const timeFmt = preferences.value.time_format || settingsStore.defaultTimeFormat
  return formatDateInTimezone(new Date().toISOString(), `${dateFmt} ${timeFmt}`, tz)
})

async function savePreferences() {
  await userStore.updatePreferences(preferences.value)
}
</script>
```

---

## Adding New Date/Time Formats

### Step 1: Update Backend Validation

In `app/Http/Controllers/UserController.php`:

```php
$validated = $request->validate([
    'date_format' => 'nullable|string|in:Y-m-d,d/m/Y,m/d/Y,d.m.Y,d-M-Y',  // Add new format
    // ...
]);
```

### Step 2: Update Format Converter (if needed)

In `resources/js/utils/formatConverter.js`, add to `COMMON_FORMATS`:

```javascript
export const COMMON_FORMATS = {
    // Existing formats...
    'd-M-Y': 'dd-MMM-yyyy',  // Add new format conversion
};
```

### Step 3: Update UI Options

In your preferences component:

```javascript
const dateFormats = [
  // Existing formats...
  { label: 'DD-Mon-YYYY (17-Dec-2025)', value: 'd-M-Y' },
]
```

---

## Using Timezones in Backend (Laravel)

### Convert to User's Timezone

```php
use Carbon\Carbon;

// Get user's timezone
$timezone = auth()->user()->timezone;

// Convert UTC time to user's timezone
$utcTime = Carbon::parse($event->starts_at);
$localTime = $utcTime->setTimezone($timezone);

// Format in user's preferred format
$formatted = $localTime->format(auth()->user()->date_format . ' ' . auth()->user()->time_format);
```

### Store Times in UTC

Always store times in UTC in the database:

```php
// When saving
$event->starts_at = Carbon::parse($request->starts_at, $request->timezone)->utc();

// When retrieving
$localTime = $event->starts_at->setTimezone(auth()->user()->timezone);
```

---

## Troubleshooting

### Date shows "Invalid Date"

**Cause:** Invalid date string passed to formatDate

**Solution:** Check that the date string is valid ISO format or Date object

```javascript
// Good
formatDate('2025-12-17T10:30:00Z')
formatDate(new Date())

// Bad
formatDate('invalid-date')
formatDate(null)
```

### Timezone not applying

**Cause:** User preference not loaded or API data structure mismatch

**Solution:**
1. Check that user data includes timezone in API response
2. Verify userStore.user.timezone is populated
3. Check the fallback chain in formatDate.js

### Wrong time displayed

**Cause:** Date string already includes timezone offset that gets applied twice

**Solution:** Ensure dates are stored/transmitted in UTC (ending with 'Z' or '+00:00')

```javascript
// Correct - UTC time
'2025-12-17T10:30:00Z'
'2025-12-17T10:30:00+00:00'

// May cause issues - local time without indicator
'2025-12-17T10:30:00'
```

---

## Best Practices

1. **Always store dates in UTC** in the database
2. **Let the frontend handle timezone conversion** for display
3. **Use the composable/plugin** instead of raw date-fns for consistency
4. **Provide a preview** when users change their preferences
5. **Test with multiple timezones** including edge cases (DST, date line)
6. **Use PHP formats** in settings for consistency with Laravel
7. **Clear null values** mean "use default" - don't store empty strings
