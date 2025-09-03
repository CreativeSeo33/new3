const Encore = require('@symfony/webpack-encore');
const path = require('path');
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
const selectedEntry = process.env.APP_ENTRY;

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build');
// only needed for CDN's or subdirectory deploy
//.setManifestKeyPrefix('build/')

/*
 * ENTRY CONFIG
 *
 * Each entry will result in one JavaScript file (e.g. app.js)
 * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
 */
// removed default app entry
if (!selectedEntry || selectedEntry === 'admin') {
    Encore.addEntry('admin', './assets/admin/admin.ts');
}
if (!selectedEntry || selectedEntry === 'catalog') {
    Encore.addEntry('catalog', './assets/catalog/catalog.ts');
}

Encore
    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()

    // Displays build status system notifications to the user
    // .enableBuildNotifications()

    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // Enable PostCSS (TailwindCSS, Autoprefixer)
    .enablePostCssLoader()

    // configure Babel
    // .configureBabel((config) => {
    //     config.plugins.push('@babel/a-babel-plugin');
    // })

    // enables and configure @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    })

    // enables Sass/SCSS support
    //.enableSassLoader()

    // Enable TypeScript with transpileOnly for faster builds
    .enableTypeScriptLoader((tsConfig) => {
        tsConfig.transpileOnly = true;
    })
    // Add ForkTsChecker for async type checking
    .addPlugin(new ForkTsCheckerWebpackPlugin());

// Включаем Vue loader только если собирается admin
if (!selectedEntry || selectedEntry === 'admin') {
    Encore.enableVueLoader(() => {}, { version: 3 });
}

// Общие алиасы для совместимости с TS и JS
Encore.addAliases({
    '@admin': path.resolve(__dirname, 'assets/admin'),
    // Алиасы для catalog модульной системы
    '@': path.resolve(__dirname, 'assets/catalog/src'),
    '@shared': path.resolve(__dirname, 'assets/catalog/src/shared'),
    '@features': path.resolve(__dirname, 'assets/catalog/src/features'),
    '@entities': path.resolve(__dirname, 'assets/catalog/src/entities'),
    '@widgets': path.resolve(__dirname, 'assets/catalog/src/widgets'),
    '@pages': path.resolve(__dirname, 'assets/catalog/src/pages'),
    '@app': path.resolve(__dirname, 'assets/catalog/src/app'),
});

module.exports = Encore.getWebpackConfig();
