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

// Get colors from localStorage if available (settings may have been cached)
function getThemeColors() {
    const defaultColors = {
        primary: '#115571',
        secondary: '#a0b9c8',
    }

    try {
        const cachedSettings = localStorage.getItem('app_settings')
        if (cachedSettings) {
            const settings = JSON.parse(cachedSettings)
            return {
                primary: settings.primary_color || defaultColors.primary,
                secondary: settings.secondary_color || defaultColors.secondary,
            }
        }
    } catch (e) {
        console.warn('Failed to load colors from localStorage:', e)
    }

    return defaultColors
}

const themeColors = getThemeColors()

const light = {
    dark: false,
    colors: {
        background: '#ffffff',
        surface: '#f2f5f8',
        primary: themeColors.primary,
        secondary: themeColors.secondary,
        accent: '#048ba8',
        error: '#ef476f',
        info: '#2196F3',
        success: '#06d6a0',
        warning: '#ffd166'
    },
}

const dark = {
    dark: true,
    colors: {
        background: '#121212',
        surface: '#1e1e1e',
        primary: themeColors.primary,
        secondary: themeColors.secondary,
        accent: '#26c4da',
        error: '#ef476f',
        info: '#2196F3',
        success: '#06d6a0',
        warning: '#ffd166'
    },
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
    lang: {
        current: config.locales.locale,
        t: (key, ...params) => i18n.t(key, params),
    },
});

// Method to update theme colors dynamically
export function updateThemeColors(primaryColor, secondaryColor) {
    if (vuetify && vuetify.theme) {
        // Update both light and dark themes
        vuetify.theme.themes.value.light.colors.primary = primaryColor
        vuetify.theme.themes.value.light.colors.secondary = secondaryColor
        vuetify.theme.themes.value.dark.colors.primary = primaryColor
        vuetify.theme.themes.value.dark.colors.secondary = secondaryColor
    }
}

export default vuetify;
