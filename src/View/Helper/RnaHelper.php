<?php
declare(strict_types=1);

namespace Chialab\Rna\View\Helper;

use Cake\Core\Plugin;
use Cake\Utility\Hash;
use Cake\View\Helper;
use Chialab\Rna\RnaPluginInterface;

/**
 * Rna helper
 *
 * @property-read \Cake\View\Helper\HtmlHelper $Html
 */
class RnaHelper extends Helper
{
    /**
     * @inheritDoc
     */
    public $helpers = ['Html'];

    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'buildPath' => 'webroot' . DS . 'build',
        'entrypointFile' => 'entrypoints.json',
    ];

    /**
     * Cache entrypoints.
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Dev servers data.
     *
     * @var array
     */
    protected array $devServers = [];

    /**
     * Original view request.
     *
     * @var \Cake\Http\ServerRequest|null
     */
    protected $originalRequest = null;

    /**
     * Get the path to the `entrypoints.json` file.
     *
     * @param string|null $plugin Plugin name.
     * @return string
     */
    protected function getEntrypointPath(?string $plugin = null): string
    {
        if ($plugin === null) {
            return ROOT . DS . rtrim($this->getConfig('buildPath'), DS) . DS . $this->getConfig('entrypointFile');
        }

        $pluginInstance = Plugin::getCollection()->get($plugin);
        if ($pluginInstance instanceof RnaPluginInterface) {
            return $pluginInstance->getEntrypointsPath();
        }

        return Plugin::path($plugin) . rtrim($this->getConfig('buildPath'), DS) . DS . $this->getConfig('entrypointFile');
    }

    /**
     * Load entrypoints for a frontend plugin.
     *
     * @param string|null $plugin The frontend plugin name.
     * @return array|null A list of entrypoints.
     */
    protected function loadEntrypoints(?string $plugin = null): ?array
    {
        $cacheKey = $plugin ?? '_';
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $path = $this->getEntrypointPath($plugin);
        if (!file_exists($path) || !is_readable($path)) {
            return null;
        }

        return $this->cache[$cacheKey] = json_decode(file_get_contents($path), true);
    }

    /**
     * Patch current request with an empty webroot attribute.
     * RNA entrypoints already includes the full webroot path.
     * We are removing the `webroot` attribute in order to prevent additional prefix
     * when using Html::script and Html::css methods.
     *
     * @return void
     */
    protected function patchViewRequest(): void
    {
        $view = $this->getView();
        $this->originalRequest = $view->getRequest();
        $view->setRequest($this->originalRequest->withAttribute('webroot', '/'));
    }

    /**
     * Restore the original request in view.
     *
     * @return void
     */
    protected function restoreViewRequest(): void
    {
        if (!empty($this->originalRequest)) {
            $this->getView()->setRequest($this->originalRequest);
            $this->originalRequest = null;
        }
    }

    /**
     * Get dev server data.
     *
     * @param string $pluginName The plugin name.
     * @param array $options Array of options and HTML attributes.
     * @return string Script to inject dev server functionality.
     */
    public function devServer(?string $pluginName = null, ?array $options = []): string
    {
        $map = $this->loadEntrypoints($pluginName);
        if ($map === null) {
            return '';
        }

        $scripts = Hash::get($map, 'server.inject', []);
        $options['type'] = 'module';

        return join('', array_filter(
            array_map(
                fn (string $path): ?string => $this->Html->script($path, $options),
                $scripts
            )
        ));
    }

    /**
     * Get assets by type.
     *
     * @param string $asset The assets name.
     * @param string $type The asset type.
     * @return array Tuple: format and list of entries.
     */
    public function getAssets(string $asset, string $type): array
    {
        [$plugin, $resource] = pluginSplit($asset);
        $map = $this->loadEntrypoints($plugin);
        if ($map === null) {
            return [null, []];
        }

        $format = Hash::get($map, sprintf('entrypoints.%s.format', $resource), 'umd');
        $entries = Hash::get($map, sprintf('entrypoints.%s.%s', $resource, $type), []);

        return [$format, $entries];
    }

    /**
     * Get css assets.
     *
     * @param string $asset The assets name.
     * @param array $options Array of options and HTML attributes.
     * @return string HTML to load CSS resources.
     */
    public function css(string $asset, array $options = []): string
    {
        [, $assets] = $this->getAssets($asset, 'css');

        if (empty($assets)) {
            return '';
        }

        $this->patchViewRequest();
        $out = join('', array_filter(
            array_map(
                fn (string $path): ?string => $this->Html->css($path, ['fullBase' => true] + $options),
                $assets
            )
        ));
        $this->restoreViewRequest();

        return $out;
    }

    /**
     * Get js assets.
     *
     * @param string $asset The assets name.
     * @param array $options Array of options and HTML attributes.
     * @return string HTML to load JS resources.
     */
    public function script(string $asset, array $options = []): string
    {
        [$format, $assets] = $this->getAssets($asset, 'js');
        if (empty($assets)) {
            return '';
        }

        if ($format === 'esm') {
            $options['type'] = 'module';
        }

        $this->patchViewRequest();
        $out = join('', array_filter(
            array_map(
                fn (string $path): ?string => $this->Html->script($path, ['fullBase' => true] + $options),
                $assets
            )
        ));
        $this->restoreViewRequest();

        return $out;
    }
}
