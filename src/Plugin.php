<?php
/**
 * This file is part of the Composer Toolkit plugin.
 */

namespace VerbruggenAlex\ComposerDrupalPlugin;

use VerbruggenAlex\ComposerDrupalPlugin\Plugin\ExtraPackage;
use VerbruggenAlex\ComposerDrupalPlugin\Plugin\MissingFileException;
use VerbruggenAlex\ComposerDrupalPlugin\Plugin\PluginState;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\Event as BaseEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;

/**
 * Composer for extra functionality in Toolkit.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * Offical package name
     */
    const PACKAGE_NAME = 'ec-europa/toolkit';

    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var PluginState $state
     */
    protected $state;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->state = new PluginState($this->composer);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'init' =>
                ['onInit'],
            InstallerEvents::PRE_DEPENDENCIES_SOLVING =>
                ['onDependencySolve'],
            ScriptEvents::POST_INSTALL_CMD => 
                ['onPostInstallOrUpdate'],
            ScriptEvents::POST_UPDATE_CMD =>
                ['onPostInstallOrUpdate'],
            ScriptEvents::PRE_INSTALL_CMD =>
                ['onInstallUpdateOrDump'],
            ScriptEvents::PRE_UPDATE_CMD =>
                ['onInstallUpdateOrDump'],
        );
    }

    /**
     * Handle an event callback for initialization.
     *
     * @param \Composer\EventDispatcher\Event $event
     */
    public function onInit(BaseEvent $event)
    {
        // $this->state->loadSettings();
        // $authors = $this->state->authors;
        // if ($authors === null) {
        //     var_dump("No authors");
        // }
    }

    /**
     * Handle an event callback for pre-dependency solving phase of an install
     * or update.
     *
     * @param InstallerEvent $event
     */
    public function onDependencySolve(InstallerEvent $event)
    {
        // $request = $event->getRequest();
        // if ($event->getIO()->askConfirmation('<info>Do you want to generate a new Drupal project?</info> <comment>(y/n)</comment> ')) {
        //     $event->getIO()->write('Yes?');
        // }
        // foreach ($this->state->getRequires() as $require) {
        //     // var_dump($require);
        //     // $request->install($link->getTarget(), $link->getConstraint());
        // }
    }

    /**
     * Handle an event callback for an install, update or dump command.
     *
     * @param ScriptEvent $event
     */
    public function onInstallUpdateOrDump(ScriptEvent $event)
    {
        $this->state->loadSettings();
    }

    /**
     * Handle an event callback following an install or update command. If our
     * plugin was installed during the run then trigger an installation
     * assistant.
     *
     * @param ScriptEvent $event
     */
    public function onPostInstallOrUpdate(ScriptEvent $event)
    {
        // @codeCoverageIgnoreStart
        if ($this->state->isFirstInstall()) {
            $this->state->setFirstInstall(false);

            $config = $this->composer->getConfig();
        }
        // @codeCoverageIgnoreEnd
    }
}
// vim:sw=4:ts=4:sts=4:et: