// Instead of Vue, import { createApp } from Vue
import { createApp } from 'vue';

// Adjust your Vuetify imports
// import { Vuetify } from 'vuetify'; // Modify based on the correct path in Vuetify 3
import "vuetify/styles";
import { createVuetify } from 'vuetify';
import '@mdi/font/css/materialdesignicons.css'
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import 'vuetify/dist/vuetify.min.css';
import * as labs from 'vuetify/labs/components';
import * as components from "vuetify/components";
import * as directives from "vuetify/directives";
import i18n from './vue-i18n';
import config from '../configs';

// import {lightTheme}, {darkTheme} from '../configs/theme.js'

const light = {
    dark: false,
        colors: {
        background: '#ffffff',
            surface: '#f2f5f8',
            // primary: '#0096c7',
            primary: '#115571',
            secondary: '#a0b9c8',
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
        primary: '#4da6c7',
        secondary: '#7ca9ba',
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
    rtl: config.theme.isRTL,
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: {
            mdi,
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


export default vuetify;
