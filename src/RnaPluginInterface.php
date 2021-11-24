<?php
declare(strict_types=1);

namespace Chialab\Rna;

/**
 * Interface for plugins to set a custom path for their `entrypoints.json` file.
 */
interface RnaPluginInterface
{
    /**
     * Get path to `entrypoints.json` file.
     *
     * @return string
     */
    public function getEntrypointsPath(): string;
}
