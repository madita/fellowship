const path = require('path')
const fs = require('fs-extra')
const mix = require('laravel-mix')

//const { VuetifyLoaderPlugin } = require('vuetify-loader')

/*
|---------------------------------------------------------------------
| Load the Vuetify Loader Plugin
|---------------------------------------------------------------------
*/
mix.extend('vuetify', new class {
    webpackConfig (config) {
        // config.module.rules.push(webpackConfig.module.rules)
        //config.plugins.push(new VuetifyLoaderPlugin())
    }
})

//mix.copy('node_modules/@mdi/font/fonts/', 'dist/fonts/')

mix.vuetify()

if (mix.inProduction()) {
    const dist = path.resolve(__dirname, './public/dist')

    // clean dist folder
    if (fs.existsSync(dist)) fs.removeSync(dist)
}

/*
|---------------------------------------------------------------------
| Build and copy Vue application assets to 'public/dist' folder
|---------------------------------------------------------------------
*/
// mix.js('resources/js/app.js', 'dist/js').vue()
//     .sass('resources/sass/app.scss', 'dist/css')
//     .webpackConfig({
//         resolve: {
//             extensions: [".*",".wasm",".mjs",".js",".jsx",".json",".vue",".*"],
//             alias: {
//                 'vue$': 'vue/dist/vue.esm.js',
//                 '@': path.join(__dirname, './resources/js'),
//                 '~': path.join(__dirname, './resources/js')
//             }
//         },
//         output: {
//             chunkFilename: mix.inProduction() ? 'dist/js/[chunkhash].js' : 'dist/js/[name].js',
//             path: path.resolve(__dirname, './public')
//         }
//     })


mix.js('resources/js/app.js', 'public/js')
    .vue({
        version: 3,
    })
    // .postCss('resources/css/app.css', 'public/css', [
    //     require('postcss-import'),
    // ])
    .webpackConfig({
        resolve: {
            extensions: [".*",".wasm",".mjs",".js",".jsx",".json",".vue"],
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
            fallback: {"querystring": false},
        },
        output: {
            chunkFilename: mix.inProduction() ? 'dist/js/[chunkhash].js' : 'dist/js/[name].js',
            path: path.resolve(__dirname, 'public')
        }
    });

if (mix.inProduction()) {
    mix.version()
} else {
    mix.sourceMaps()
}
