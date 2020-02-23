<?php
/**
 * This file is part of the Composer Toolkit plugin.
 */

namespace VerbruggenAlex\ComposerDrupalPlugin\Plugin;

use Composer\Composer;

/**
 * Mutable plugin state
 */
class PluginState
{
    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var bool $firstInstall
     */
    protected $firstInstall = false;

    /**
     * @var array $requires
     */
    protected $requires = array();

    /**
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * Load plugin settings
     */
    public function loadSettings()
    {
        $this->authors = $this->composer->getPackage()->getAuthors();
        $this->requires = $this->composer->getPackage()->getRequires();
    }

    /**
     * Set the first install flag
     *
     * @param bool $flag
     */
    public function setFirstInstall($flag)
    {
        $this->firstInstall = (bool)$flag;
    }

    /**
     * Is this the first time that the plugin has been installed?
     *
     * @return bool
     */
    public function isFirstInstall()
    {
        return $this->firstInstall;
    }

    /**
     * Get the require section
     *
     * @return array
     */
    public function getRequires()
    {
        return $this->requires;
    }
}
// vim:sw=4:ts=4:sts=4:et: