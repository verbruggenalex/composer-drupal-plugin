<?php

namespace VerbruggenAlex\ComposerDrupalPlugin\Robo\Plugin\Commands;

use PhpTaskman\Core\Robo\Plugin\Commands\AbstractCommands;
use PhpTaskman\Core\Taskman;
use PhpTaskman\CoreTasks\Plugin\Task\CollectionFactoryTask;
use Robo\Common\ResourceExistenceChecker;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DrupalComposerCommands extends AbstractCommands
{
    use ResourceExistenceChecker;
    use \Boedah\Robo\Task\Drush\loadTasks;

    /** @var array */
    private $gitConfig;

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
     * Generate Drupal folders.
     *
     * @param array $options
     *   Command options.
     *
     * @return \Robo\Collection\CollectionBuilder
     *   Collection builder.
     *
     * @command drupal:generate-folders
     *
     * @option drupal-root  The root directory for Drupal.
     */
    public function drupalGenerateFolders(array $options = [
        'drupal-root' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $root = $this->getConfig()->get('drupal.root');
        $files = $this->getConfig()->get('drupal.files');
        $sites = $this->getConfig()->get('drupal.sites');

        $folders = ['public', 'private', 'temp', 'translations'];
        $filesystem = new Filesystem();
        foreach ($sites as $site) {
            foreach ($folders as $folder) {
                $path = 'sites/' . $site . '/files/' . $folder;
                $fullPath = $files . '/' . $path;
                $fullPathWeb = getcwd() . '/' . $root . '/' . $path;
                $filesystem->mkdir($fullPath, 0700);
                if ($folder === 'public') {
                    $filesystem->symlink($fullPath, $fullPathWeb);
                }
            }
        }
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
     * @command drupal:init
     *
     * @option name         The project name.
     * @option description  The description of the project.
     * @option author       The author of the project.
     * @option drupal-root  The root directory for Drupal.
     */
    public function drupalInit(array $options = [
        'name' =>  InputOption::VALUE_OPTIONAL,
        'description' => InputOption::VALUE_OPTIONAL,
        'author' => InputOption::VALUE_OPTIONAL,
        'drupal-root' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $this->createComposerJson();
        $this->setComposerExecutable();
        $this->transformComposerJson();
        // $this->normalizeComposerJson();
        $this->composerRequireDrupal();
        if ($this->tasks !== []) {
            return $this
                ->collectionBuilder()
                ->addTaskList($this->tasks);
        }
    }

    /**
     * Build a development codebase.
     *
     * @param array $options
     *   Command options.
     *
     * @return \Robo\Collection\CollectionBuilder
     *   Collection builder.
     *
     * @command drupal:build-dev
     *
     * @option branch       The branch name.
     */
    public function drupalBuildDev(array $options = [
        'branch' =>  InputOption::VALUE_OPTIONAL,
    ])
    {
        $branch = $this->taskExec('git rev-parse --abbrev-ref HEAD')
         ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
         ->run()
         ->getMessage();

        $branch = isset($options['branch']) ? $options['branch'] : trim($branch);
        $buildPath = "build/dev/$branch";
        $this->_exec("mkdir -p $buildPath");
        $this->taskRsync()
          ->fromPath('./')
          ->toPath($buildPath)
          ->filter(':- .gitignore')
          ->recursive()
          ->run();
        $this->taskComposerInstall()
          ->workingDir($buildPath)
          ->run();
        $this->taskExec("./vendor/bin/drush site-install standard -y -r web --account-pass=admin --db-url=mysql://root:@mysql:3306/dev_$branch")->dir($buildPath)->run();
    }

    protected function setAuthor() {
        $git = $this->getGitConfig();
        if (null === $author = $this->input()->getOption('author')) {
            if (!empty($_SERVER['COMPOSER_DEFAULT_AUTHOR'])) {
                $author_name = $_SERVER['COMPOSER_DEFAULT_AUTHOR'];
            } elseif (isset($git['user.name'])) {
                $author_name = $git['user.name'];
            }

            if (!empty($_SERVER['COMPOSER_DEFAULT_EMAIL'])) {
                $author_email = $_SERVER['COMPOSER_DEFAULT_EMAIL'];
            } elseif (isset($git['user.email'])) {
                $author_email = $git['user.email'];
            }

            if (isset($author_name) && isset($author_email)) {
                $author = sprintf('%s <%s>', $author_name, $author_email);
            }
        }

        $self = $this;
        $question = new Question('Author [<comment>'.$author.'</comment>, n to skip]: ');
        $question->setValidator(function ($value) use ($self, $author) {
                if ($value === 'n' || $value === 'no') {
                    return;
                }
                $value = $value ?: $author;
                $author = $self->parseAuthorString($value);

                return sprintf('%s <%s>', $author['name'], $author['email']);
            },
            null,
            $author
        
        );

        $author = $this->getDialog()->ask($this->input(), $this->output(), $question);
        $this->input()->setOption('author', $author);
    }

    /**
     * @private
     * @param  string $author
     * @return array
     */
    public function parseAuthorString($author)
    {
        if (preg_match('/^(?P<name>[- .,\p{L}\p{N}\p{Mn}\'’"()]+) <(?P<email>.+?)>$/u', $author, $match)) {
            if ($this->isValidEmail($match['email'])) {
                return array(
                    'name' => trim($match['name']),
                    'email' => $match['email'],
                );
            }
        }

        throw new \InvalidArgumentException(
            'Invalid author string.  Must be in the format: '.
            'John Smith <john@example.com>'
        );
    }

    protected function isValidEmail($email)
    {
        // assume it's valid if we can't validate it
        if (!function_exists('filter_var')) {
            return true;
        }

        // php <5.3.3 has a very broken email validator, so bypass checks
        if (PHP_VERSION_ID < 50303) {
            return true;
        }

        return false !== filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    protected function getGitConfig()
    {
        if (null !== $this->gitConfig) {
            return $this->gitConfig;
        }

        $finder = new ExecutableFinder();
        $gitBin = $finder->find('git');

        // TODO in v3 always call with an array
        if (method_exists('Symfony\Component\Process\Process', 'fromShellCommandline')) {
            $cmd = new Process(array($gitBin, 'config', '-l'));
        } else {
            $cmd = new Process(sprintf('%s config -l', ProcessExecutor::escape($gitBin)));
        }
        $cmd->run();

        if ($cmd->isSuccessful()) {
            $this->gitConfig = array();
            preg_match_all('{^([^=]+)=(.*)$}m', $cmd->getOutput(), $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $this->gitConfig[$match[1]] = $match[2];
            }

            return $this->gitConfig;
        }

        return $this->gitConfig = array();
    }

    protected function setDescription() {
        $description = $this->input()->getOption('description') ?: false;
        $description = $this->getDialog()->ask(
            $this->input(),
            $this->output(),
            new Question('Description [<comment>'.$description.'</comment>]: ',
            $description)
        );
        $this->input()->setOption('description', $description);
    }

    protected function setName() {
        $cwd = realpath(".");

        if (!$name = $this->input()->getOption('name')) {
            $name = basename($cwd);
            $name = preg_replace('{(?:([a-z])([A-Z])|([A-Z])([A-Z][a-z]))}', '\\1\\3-\\2\\4', $name);
            $name = strtolower($name);
            if (!empty($_SERVER['COMPOSER_DEFAULT_VENDOR'])) {
                $name = $_SERVER['COMPOSER_DEFAULT_VENDOR'] . '/' . $name;
            } elseif (isset($git['github.user'])) {
                $name = $git['github.user'] . '/' . $name;
            } elseif (!empty($_SERVER['USERNAME'])) {
                $name = $_SERVER['USERNAME'] . '/' . $name;
            } elseif (!empty($_SERVER['USER'])) {
                $name = $_SERVER['USER'] . '/' . $name;
            } elseif (get_current_user()) {
                $name = get_current_user() . '/' . $name;
            } else {
                // package names must be in the format foo/bar
                $name .= '/' . $name;
            }
            $name = strtolower($name);
        } else {
            if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $name)) {
                throw new \InvalidArgumentException(
                    'The package name '.$name.' is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+'
                );
            }
        }

        $question = new Question('Package name (<vendor>/<name>) [<comment>'.$name.'</comment>]: ');
        $question->setValidator(function ($value) use ($name) {
                if (null === $value) {
                    return $name;
                }

                if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $value)) {
                    throw new \InvalidArgumentException(
                        'The package name '.$value.' is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+'
                    );
                }

                return $value;
            },
            null,
            $name
        );
        $name = $this->getDialog()->ask($this->input(), $this->output(), $question);
        $this->input()->setOption('name', $name);
    }

    public function createComposerJson()
    {
        $this->setName();
        $this->setDescription();
        $this->setAuthor();

        $composer = [
            'name' => $this->input()->getOption('name'),
            'description' => $this->input()->getOption('description'),
        ];

        if (!file_exists('composer.json')) {
            $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents('composer.json', $json);
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
          ->exec('require composer/installers drupal/core drupal/core-composer-scaffold drush/drush --no-update')
          ->exec('require drupal-composer/drupal-security-advisories:dev-8.x-v2 drupal/core-dev ergebnis/composer-normalize --dev --no-update')
        //   ->exec('normalize --no-update-lock')
          ->exec('install');
    }

    protected function transformComposerJson() {
        $this->checkResource('composer.json', 'file');
        $composerFile = \realpath('composer.json');
        $composerArray = json_decode(file_get_contents($composerFile), TRUE);
        $config = $this->getConfig()->get('composer.drupal');
        $newComposerArray = $this->array_merge_recursive_distinct($composerArray, $config);
        file_put_contents($composerFile, json_encode($newComposerArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // protected function normalizeComposerJson() {
    //     // First remove composer.lock silently because normalize will trip that
    //     // the lock file is out of date.
    //     $this->taskExec("rm -f composer.lock")
    //       ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
    //       ->run();
    //     // Install and execute composer normalize
    //     $this->tasks[] = $this->taskExecStack()
    //      ->stopOnFail()
    //      ->executable($this->composer)
    //      ->exec('global require ergebnis/composer-normalize --quiet')
    //      ->exec('normalize --quiet');
    // }

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
