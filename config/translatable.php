<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Locales
    |--------------------------------------------------------------------------
    |
    | Contains an array with the applications available locales.
    |
    | IMPORTANT: Keep this list in sync with resources/js/configs/locales.js
    | The JS config is the master source - this PHP config should mirror it.
    |
    | To add a new language:
    | 1. Add to resources/js/configs/locales.js (master)
    | 2. Add to this array
    | 3. Create translation files in resources/js/translations/
    |
    */
    'locales' => [
        'en',
        'de',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale separator
    |--------------------------------------------------------------------------
    |
    | This is a string used to glue the language and the country when defining
    | the available locales. Example: if set to '-', the locale for Brasil
    | portugueses would be defined as 'pt-BR'.
    |
    */
    'locale_separator' => '-',

    /*
    |--------------------------------------------------------------------------
    | Default locale
    |--------------------------------------------------------------------------
    |
    | As a default locale, Translatable takes the application's current locale.
    | If you want to define a different locale, you can set it here.
    |
    */
    'locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Use fallback
    |--------------------------------------------------------------------------
    |
    | Determine if fallback locales are returned by default or not. To add
    | more flexibility and control, this now can be a value of either
    | a boolean or 'empty'.
    |
    | if true: the fallback locale is returned if the current translation is missing.
    | if false: no fallback locale is returned.
    | if 'empty': the fallback locale is returned only if the current translation is null or empty string.
    |
    */
    'use_fallback' => true,

    /*
    |--------------------------------------------------------------------------
    | Use fallback per property
    |--------------------------------------------------------------------------
    |
    | This allows for flexible control over the use_fallback setting.
    | If set to true and use_fallback is set to false/empty, the
    | fallback will still be used for those properties that have it
    | enabled in the model via the `$useFallback` property.
    |
    */
    'use_property_fallback' => true,

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | A fallback locale is the locale being used to return a translation
    | when the requested translation is not existing. To disable it
    | set it to false.
    |
    */
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Translation Suffix
    |--------------------------------------------------------------------------
    |
    | Defines the default 'Translation' class suffix. For example, if
    | you want to use CountryTrans instead of CountryTranslation
    | application, set this to 'Trans'.
    |
    */
    'translation_suffix' => 'Translation',

    /*
    |--------------------------------------------------------------------------
    | Locale key
    |--------------------------------------------------------------------------
    |
    | Defines the 'locale' field name, which is used by the
    | temporary locale attribute.
    |
    */
    'locale_key' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Always load translations
    |--------------------------------------------------------------------------
    |
    | Setting this to true will make translatable always load translations
    | when fetching records. This can be useful in certain situations
    | but can also cause performance issues in others.
    |
    */
    'to_array_always_loads_translations' => true,

    /*
    |--------------------------------------------------------------------------
    | Configure the Translation Model namespace
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure the namespace for Translation
    | Models, which is useful if you want to place them in a different
    | folder structure.
    |
    */
    'translation_model_namespace' => 'App\Models\Translations',

    /*
    |--------------------------------------------------------------------------
    | Rule Factory
    |--------------------------------------------------------------------------
    |
    | This option allows you to define the rule factory class.
    |
    */
    'rule_factory' => \Astrotomic\Translatable\Validation\RuleFactory::class,
];
