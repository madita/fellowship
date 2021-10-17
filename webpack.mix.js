const path = require('path')
const fs = require('fs-extra')
const mix = require('laravel-mix')
const { VuetifyLoaderPlugin } = require('vuetify-loader')

/*
|---------------------------------------------------------------------
| Inject Vuetify variables in SASS
|---------------------------------------------------------------------
*/
Mix.listen('configReady', (config) => {
    const scssRule = config.module.rules.find((r) => r.test.toString() === /\.scss$/.toString())

    scssRule.oneOf.forEach((ruleset) => {
        const scssOptions = ruleset.use.find((l) => l.loader === 'sass-loader').options

        scssOptions.additionalData = '@import \'./resources/sass/vuetify/variables\';'
    })

    const sassRule = config.module.rules.find((r) => r.test.toString() === /\.sass$/.toString())

    sassRule.oneOf.forEach((ruleset) => {
        const sassOptions = ruleset.use.find((l) => l.loader === 'sass-loader').options

        sassOptions.additionalData = '@import \'./resources/sass/vuetify/variables\''
    })
})

/*
|---------------------------------------------------------------------
| Load the Vuetify Loader Plugin
|---------------------------------------------------------------------
*/
mix.extend('vuetify', new class {
    webpackConfig (config) {
        config.plugins.push(new VuetifyLoaderPlugin())
    }
})

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
mix.js('resources/js/app.js', 'dist/js').vue()
    .sass('resources/sass/app.scss', 'dist/css')
    .webpackConfig({
        resolve: {
            extensions: ['.js', '.vue', '.json'],
            alias: {
                'vue$': 'vue/dist/vue.esm.js',
                '@': path.join(__dirname, './resources/js'),
                '~': path.join(__dirname, './resources/js')
            }
        },
        output: {
            chunkFilename: mix.inProduction() ? 'dist/js/[chunkhash].js' : 'dist/js/[name].js',
            path: path.resolve(__dirname, './public')
        }
    })

if (mix.inProduction()) {
    mix.version()
} else {
    mix.sourceMaps()
}
