// Instead of Vue, import { createApp } from Vue
import { createApp } from 'vue';

// Adjust your Vuetify imports
// import { Vuetify } from 'vuetify'; // Modify based on the correct path in Vuetify 3
import "vuetify/styles";
import { createVuetify } from 'vuetify';
// Import MDI font CSS
import '@mdi/font/css/materialdesignicons.css'
// Import mdi-svg as fallback
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import { mdi as mdiSvg } from 'vuetify/iconsets/mdi-svg'
import 'vuetify/dist/vuetify.min.css';
import * as labs from 'vuetify/labs/components';
import * as components from "vuetify/components";
import * as directives from "vuetify/directives";
import i18n from './vue-i18n';
import config from '../configs';

// Get colors and font from localStorage if available (settings may have been cached)
function getThemeColors() {
    const defaultLightColors = {
        primary: '#115571',
        secondary: '#a0b9c8',
        accent: '#048ba8',
        background: '#ffffff',
        surface: '#f2f5f8',
        error: '#ef476f',
        info: '#2196F3',
        success: '#06d6a0',
        warning: '#ffd166',
    }

    const defaultDarkColors = {
        primary: '#115571',
        secondary: '#a0b9c8',
        accent: '#26c4da',
        background: '#121212',
        surface: '#1e1e1e',
        error: '#ef476f',
        info: '#2196F3',
        success: '#06d6a0',
        warning: '#ffd166',
    }

    try {
        const cachedSettings = localStorage.getItem('app_settings')
        if (cachedSettings) {
            const settings = JSON.parse(cachedSettings)
            return {
                light: {
                    primary: settings.primary_color_light || settings.primary_color || defaultLightColors.primary,
                    secondary: settings.secondary_color_light || settings.secondary_color || defaultLightColors.secondary,
                    accent: settings.accent_color_light || defaultLightColors.accent,
                    background: settings.background_color_light || defaultLightColors.background,
                    surface: settings.surface_color_light || defaultLightColors.surface,
                    error: settings.error_color_light || defaultLightColors.error,
                    info: settings.info_color_light || defaultLightColors.info,
                    success: settings.success_color_light || defaultLightColors.success,
                    warning: settings.warning_color_light || defaultLightColors.warning,
                },
                dark: {
                    primary: settings.primary_color_dark || settings.primary_color || defaultDarkColors.primary,
                    secondary: settings.secondary_color_dark || settings.secondary_color || defaultDarkColors.secondary,
                    accent: settings.accent_color_dark || defaultDarkColors.accent,
                    background: settings.background_color_dark || defaultDarkColors.background,
                    surface: settings.surface_color_dark || defaultDarkColors.surface,
                    error: settings.error_color_dark || defaultDarkColors.error,
                    info: settings.info_color_dark || defaultDarkColors.info,
                    success: settings.success_color_dark || defaultDarkColors.success,
                    warning: settings.warning_color_dark || defaultDarkColors.warning,
                },
            }
        }
    } catch (e) {
        console.warn('Failed to load colors from localStorage:', e)
    }

    return {
        light: defaultLightColors,
        dark: defaultDarkColors,
    }
}

function getFontFamily() {
    try {
        const cachedSettings = localStorage.getItem('app_settings')
        if (cachedSettings) {
            const settings = JSON.parse(cachedSettings)
            return settings.font_family || 'Roboto, sans-serif'
        }
    } catch (e) {
        console.warn('Failed to load font family from localStorage:', e)
    }
    return 'Roboto, sans-serif'
}

const themeColors = getThemeColors()
const appFontFamily = getFontFamily()

// Apply font family to document on initial load
if (appFontFamily) {
    document.documentElement.style.setProperty('--app-font-family', appFontFamily)
    if (document.body) {
        document.body.style.fontFamily = appFontFamily
    } else {
        // If body isn't ready yet, wait for it
        document.addEventListener('DOMContentLoaded', () => {
            document.body.style.fontFamily = appFontFamily
        })
    }
}

const light = {
    dark: false,
    colors: themeColors.light,
}

const dark = {
    dark: true,
    colors: themeColors.dark,
}

// const componentsTemp = {...components, VCalendar}

// Determine initial theme from localStorage
function getInitialTheme() {
    const savedTheme = localStorage.getItem('theme_mode') || 'system'

    if (savedTheme === 'system') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
        return prefersDark ? 'dark' : 'light'
    }

    return savedTheme
}

// Create your Vuetify instance
const vuetify = createVuetify({
    components: {
        ...components,
        ...labs,
    },
    directives,
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: {
            mdi,
            // Uncomment below if font icons don't load:
            // 'mdi-svg': mdiSvg,
        },
    },
    theme: {
        defaultTheme: getInitialTheme(),
        themes: {
            light,
            dark,
        },
    },
    defaults: {
        global: {
            // Apply custom font family to all Vuetify components
            style: {
                fontFamily: appFontFamily,
            },
        },
    },
    lang: {
        current: config.locales.locale,
        t: (key, ...params) => i18n.t(key, params),
    },
});

// Method to update theme colors dynamically
export function updateThemeColors(lightColors, darkColors) {
    if (vuetify && vuetify.theme) {
        // Update light theme colors
        if (lightColors) {
            Object.keys(lightColors).forEach(colorKey => {
                if (lightColors[colorKey]) {
                    vuetify.theme.themes.value.light.colors[colorKey] = lightColors[colorKey]
                }
            })
        }

        // Update dark theme colors
        if (darkColors) {
            Object.keys(darkColors).forEach(colorKey => {
                if (darkColors[colorKey]) {
                    vuetify.theme.themes.value.dark.colors[colorKey] = darkColors[colorKey]
                }
            })
        }
    }
}

// Method to update font family dynamically
export function updateFontFamily(fontFamily) {
    console.log('updateFontFamily called with:', fontFamily);
    if (fontFamily) {
        // Set CSS variable
        document.documentElement.style.setProperty('--app-font-family', fontFamily)

        // Apply to body
        document.body.style.fontFamily = fontFamily

        // Add or update global style rule for font family
        let styleEl = document.getElementById('dynamic-font-style');
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'dynamic-font-style';
            document.head.appendChild(styleEl);
        }
        styleEl.textContent = `
            body, .v-application {
                font-family: ${fontFamily};
            }
        `;

        console.log('Applied font to body:', document.body.style.fontFamily);

        // Update Vuetify defaults for future components
        if (vuetify && vuetify.defaults) {
            // Ensure global exists
            if (!vuetify.defaults.global) {
                vuetify.defaults.global = {};
            }
            // Ensure style exists
            if (!vuetify.defaults.global.style) {
                vuetify.defaults.global.style = {};
            }
            vuetify.defaults.global.style = {
                ...vuetify.defaults.global.style,
                fontFamily: fontFamily,
            }
            console.log('Updated Vuetify defaults with font:', fontFamily);
        } else {
            console.warn('Vuetify instance not available for font update');
        }
    } else {
        console.warn('updateFontFamily called with empty/null fontFamily');
    }
}

export default vuetify;
