import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'
// import vuetify from '@vuetify/vite-plugin'
// import vuetify from 'vite-plugin-vuetify'

import path from 'path'


export default defineConfig({
    plugins: [
        vue(),
        vuetify({
            autoImport: true,
        }),
        laravel({
            input: [
                "resources/sass/app.scss",
                "resources/js/app.js"
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    optimizeDeps: {
        exclude: ['pinia']
    },
    define: {
        // Vue-i18n feature flags
        '__VUE_I18N_FULL_INSTALL__': false,
        '__VUE_I18N_LEGACY_API__': true,
        '__INTLIFY_PROD_DEVTOOLS__': false
    }
});
/*import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
// import vuetify from 'vite-plugin-vuetify';
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify';
import { resolve } from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/css/app.css'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify({ autoImport: true }),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, './resources/js'),
        },
    },
});*/
