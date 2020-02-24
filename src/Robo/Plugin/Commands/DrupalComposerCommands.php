<?php

namespace VerbruggenAlex\ComposerDrupalPlugin\Robo\Plugin\Commands;

use PhpTaskman\Core\Robo\Plugin\Commands\AbstractCommands;
use PhpTaskman\Core\Taskman;
use PhpTaskman\CoreTasks\Plugin\Task\CollectionFactoryTask;
use Robo\Common\ResourceExistenceChecker;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;

class DrupalComposerCommands extends AbstractCommands
{
    use ResourceExistenceChecker;

	/** @var array $tasks */
    protected $tasks = [];

	/** @var string $composer */
    protected $composer;

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFile(): string
    {
        return __DIR__ . '/../../../config/commands/composer.yml';
    }

    public function getDefaultConfigurationFile(): string
    {
        return __DIR__ . '/../../../config/default.yml';
    }

    /**
     * Create a Drupal composer.json.
     *
     * @param array $options
     *   Command options.
     *
     * @return \Robo\Collection\CollectionBuilder
     *   Collection builder.
     *
     * @command composer:create-drupal
     *
     * @option drupal-root  The root directory you wish to have Drupal installed at.
     *
     * @aliases composer:cd,ccd
     */
    public function createComposer(array $options = [
        'drupal-root' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $this->setComposerExecutable();
        $this->transformComposerJson();
        $this->normalizeComposerJson();
        $this->composerRequireDrupal();
        if ($this->tasks !== []) {
            return $this
                ->collectionBuilder()
                ->addTaskList($this->tasks);
        }
    }

    protected function setComposerExecutable() {
        // Find Composer
        $composer = $this->taskExec('which composer')
         ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
         ->run()
         ->getMessage();

        $this->composer = trim($composer);
    }

    protected function composerRequireDrupal() {
        // Update the composer.json with drupal requirements and run update.
        $this->tasks[] = $this->taskExecStack()
          ->stopOnFail()
          ->executable($this->composer)
          ->exec('require composer/installers drupal/core drupal/core-composer-scaffold --no-update --quiet')
          ->exec('require drupal/core-dev --dev --no-update --quiet');
        //   ->exec('install');
    }

    protected function transformComposerJson() {
        $this->checkResource('composer.json', 'file');
        $composerFile = \realpath('composer.json');
        $composerArray = json_decode(file_get_contents($composerFile), TRUE);
        $config = $this->getConfig()->get('composer.drupal');
        $newComposerArray = $this->array_merge_recursive_distinct($composerArray, $config);
        file_put_contents($composerFile, json_encode($newComposerArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function normalizeComposerJson() {
        // First remove composer.lock silently because normalize will trip that
        // the lock file is out of date.
        $this->taskExec("rm -f composer.lock")
          ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
          ->run();
        // Install and execute composer normalize
        $this->tasks[] = $this->taskExecStack()
         ->stopOnFail()
         ->executable($this->composer)
         ->exec('global require ergebnis/composer-normalize --quiet')
         ->exec('normalize --quiet');
    }

    protected function array_merge_recursive_distinct () {
      $arrays = func_get_args();
      $base = array_shift($arrays);
      if(!is_array($base)) $base = empty($base) ? array() : array($base);
      foreach($arrays as $append) {
        if(!is_array($append)) $append = array($append);
        foreach($append as $key => $value) {
          if(!array_key_exists($key, $base) and !is_numeric($key)) {
            $base[$key] = $append[$key];
            continue;
          }
          if(is_array($value) or is_array($base[$key])) {
            $base[$key] = $this->array_merge_recursive_distinct($base[$key], $append[$key]);
          } else if(is_numeric($key)) {
            if(!in_array($value, $base)) $base[] = $value;
          } else {
            $base[$key] = $value;
          }
        }
      }
      return $base;
    }

}
