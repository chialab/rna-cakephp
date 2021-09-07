<?php
declare(strict_types=1);

namespace Chialab\Rna\View\Helper;

use Cake\Core\Plugin;
use Cake\Utility\Hash;
use Cake\View\Helper;

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
     * Load entrypoints for a frontend plugin.
     *
     * @param string|null $plugin The frontend plugin name.
     * @return array|null A list of entrypoints.
     */
    protected function loadEntrypoints(?string $plugin = null): ?array
    {
        if ($plugin === null) {
            $plugin = '_';
        }

        if (isset($this->cache[$plugin])) {
            return $this->cache[$plugin];
        }

        $path = ROOT . DS;
        if ($plugin !== '_') {
            $path = Plugin::path($plugin);
        }

        $path .= rtrim($this->getConfig('buildPath'), DS) . DS . $this->getConfig('entrypointFile');
        if (!file_exists($path) || !is_readable($path)) {
            return null;
        }

        return $this->cache[$plugin] = json_decode(file_get_contents($path), true);
    }

    /**
     * Get dev server data.
     *
     * @param string $pluginName The plugin name.
     * @param array $options Array of options and HTML attributes.
     * @return string Script to inject dev server functionality.
     */
    public function devServer(string $pluginName, array $options = []): string
    {
        [$plugin] = pluginSplit($pluginName);
        $map = $this->loadEntrypoints($plugin);
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

        return join('', array_filter(
            array_map(
                fn (string $path): ?string => $this->Html->css($path, $options),
                $assets
            )
        ));
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

        return join('', array_filter(
            array_map(
                fn (string $path): ?string => $this->Html->script($path, $options),
                $assets
            )
        ));
    }
}
