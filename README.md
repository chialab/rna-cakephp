# RNA plugin for CakePHP

A [CakePHP](https://cakephp.org/) plugin to seamlessly integrate with
[@chialab/rna](https://github.com/chialab/rna) build artifacts.

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org).

The recommended way to install composer packages is:

```
composer require chialab/rna-cakephp
```

You must load the plugin into your CakePHP application by loading it in your application class `bootstrap` method:

```php
class Application extends \Cake\Http\BaseApplication
{
    public function bootstrap(): void
    {
        parent::bootstrap();

        // ...
        $this->addPlugin('Chialab/Rna');
    }
}
```

## Usage

### RNA Helper

Load the helper in your view class:

```php
class View extends \Cake\View\View
{
    public function initialize(): void
    {
        parent::initialize();
        
        // ...
        $this->loadHelper('Chialab/Rna.Rna');
    }
}
```

Then use it in your templates:

* To inject the code generated by the dev server (only when `debug` is on):

    ```twig
    {{ Rna.devServer()|raw }}
    {{ Rna.devServer('YourPlugin')|raw }}
    ```

* To load resources:

    ```twig
    {{ Rna.script('index')|raw }}
    {{ Rna.css('YourPlugin.main')|raw }}
    {{ Rna.script('YourPlugin.main', { type: 'module' })|raw }}
    ```
### Migrate from Symfony Encore

Follow this instructions to migrate from a Webpack Encore based configuration:

* Create a `rna.config.js` file in the root of yuor project.
* Define entrypoints
```js
/**
 * @param {import('@chialab/rna-config-loader').Config} config
 * @param {import('@chialab/rna-config-loader').Mode} mode
 */
export default function(config, mode) {
    return {
        ...config,
        entrypoints: [
            // Encore.addEntry('section-filters', `./resources/js/section-filters.js`)
            {
                input: [
                    './resources/js/section-filters.js',
                ],
                output: '/webroot/build',
            },
            // Encore.addEntry('app', './resources/js/app.js').addStyleEntry('app-style'', './resources/css/app.css')
            // Encore.script('app') -> Rna.script('app')
            // Encore.css('app-style') -> Rna.css('app')
            {
                input: [
                    './resources/js/app.js',
                    './resources/js/app.css',
                ],
                output: '/webroot/build',
            },
        ],
    };
}
```
* Extend the RNA configuration
```js
export default function(config, mode) {
    return {
        ...config,
        entrypoints: [...],
        // Encore.cleanupOutputBeforeBuild()
        clean: true,
        // Encore.enableSourceMaps(!Encore.isProduction())
        minify: mode === 'build',
        sourcemap: mode !== 'build',
        // Encore.enableVersioning(Encore.isProduction())
        entryNames: mode === 'build' ? '[name]-[hash]' : '[name]',
        chunkNames: mode === 'build' ? '[name]-[hash]' : '[name]',
        assetNames: mode === 'build' ? 'assets/[name]-[hash]' : '[name]',
        // Encore.setPublicPath('/webroot/build')
        manifestPath: 'webroot/build/manifest.json',
        entrypointsPath: 'webroot/build/entrypoints.json',
    };
}
```
* Remove `@symfony/webpack-encore`, `webpack` and webpack loaders `
