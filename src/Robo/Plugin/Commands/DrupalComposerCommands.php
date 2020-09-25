<?php

namespace VerbruggenAlex\RoboDrupal\Robo\Plugin\Commands;

use Robo\Robo;
use Robo\Common\ResourceExistenceChecker;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DrupalComposerCommands extends \Robo\Tasks
{
    use ResourceExistenceChecker;

  /** @var array */
    private $gitConfig;

  /** @var array $tasks */
    protected $tasks = [];

  /** @var string $composer */
    protected $composer;

    public function __construct()
    {
        Robo::loadConfiguration([__DIR__ . '/../../../config/default.yml']);
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
    public function drupalInit(/** @scrutinizer ignore-unused */ array $options = [
      'name' =>  InputOption::VALUE_OPTIONAL,
      'description' => InputOption::VALUE_OPTIONAL,
      'author' => InputOption::VALUE_OPTIONAL,
      'drupal-root' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $this->createComposerJson();
        $this->setComposerExecutable();
      // $this->normalizeComposerJson();
        $this->composerRequireDrupal();
        if ($this->tasks !== []) {
            return $this
            ->collectionBuilder()
            ->addTaskList($this->tasks);
        }
    }

  /**
   * Extend the composer.json.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   Collection builder.
   *
   * @command drupal:extend
   *
   * @option name         The project name.
   * @option description  The description of the project.
   */
    public function drupalExtend(/** @scrutinizer ignore-unused */ array $options = [
      'name' =>  InputOption::VALUE_OPTIONAL,
      'description' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $list = Robo::Config()->get('list');
        $libDir = __DIR__ . '/../../../../lib';
        $this->setComposerExecutable();

        if (!file_exists(getcwd() . '/composer.json')) {
            // phpcs:ignore Generic.Files.LineLength.TooLong
            $question = new ConfirmationQuestion('No composer.json in current directory. Would you like to generate one? <comment>(y/n)</comment> ', false);
            if ($this->getDialog()->ask($this->input(), $this->output(), $question)) {
                $composer = [];
                $requirements = array_merge($list['core']['require'], $list['core']['require-dev']);
                foreach ($requirements as $requirement) {
                    $composerJson = $libDir . '/' . $requirement . '/composer.json';
                    if (file_exists($composerJson)) {
                        $composerJsonArray = json_decode(file_get_contents($composerJson), true);
                        $composer = $this->arrayMergeRecursiveDistinct($composer, $composerJsonArray);
                    }
                }
            }
            $this->createComposerJson($composer);
            $this->tasks[] = $this->taskExecStack()
            ->stopOnFail()
              ->executable($this->composer)
              ->exec('require ' . implode(' ', $list['core']['require']) . ' --no-update --ansi')
              ->exec('require ' . implode(' ', $list['core']['require-dev']) . ' --dev --no-update --ansi');
        }

        if ($this->tasks !== []) {
            return $this
            ->collectionBuilder()
            ->addTaskList($this->tasks);
        }

        // $question = new ChoiceQuestion(
        //   'Please select your favorite colors (defaults to red and blue)',
        //   ['red', 'blue', 'yellow'],
        //   '0,1'
        // );
        // $question->setMultiselect(true);

        // $colors = $this->getDialog()->ask($this->input, $this->output, $question);
        // $this->output->writeln('You have just selected: ' . implode(', ', $colors));
    }

    protected function setAuthor()
    {
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
        $question = new Question('Author [<comment>' . $author . '</comment>, n to skip]: ');
        $question->setValidator(function ($value) use ($self, $author) {
            if ($value === 'n' || $value === 'no') {
                return;
            }
            $value = $value ?: $author;
            $author = $self->parseAuthorString((string) $value);

            return sprintf('%s <%s>', $author['name'], $author['email']);
        });

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
            'Invalid author string.  Must be in the format: ' .
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
        $cmd = new Process(array($gitBin, 'config', '-l'));
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

    protected function setDescription()
    {
        $description = $this->input()->getOption('description') ?: false;
        $description = $this->getDialog()->ask(
            $this->input(),
            $this->output(),
            new Question(
                'Description [<comment>' . (string) $description . '</comment>]: ',
                $description
            )
        );
        $this->input()->setOption('description', $description);

        return $description;
    }

    protected function setName()
    {
        $cwd = realpath(".");
        $git = $this->getGitConfig();

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

            $question = new Question('Package name (<vendor>/<name>) [<comment>' . $name . '</comment>]: ');
            $question->setValidator(
                function ($value) use ($name) {
                    if (null === $value) {
                        return $name;
                    }

                    if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $value)) {
                        throw new \InvalidArgumentException(
                        // phpcs:ignore Generic.Files.LineLength.TooLong
                            'The package name ' . (string) $value . ' is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+'
                        );
                    }

                    return $value;
                }
            );
            $name = $this->getDialog()->ask($this->input(), $this->output(), $question);
        } else {
            if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $name)) {
                throw new \InvalidArgumentException(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                    'The package name ' . (string) $name . ' is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+'
                );
            }
        }
        $this->input()->setOption('name', $name);

        return $name;
    }


    public function createComposerJson($composer)
    {
        if (!array_key_exists('name', $composer)) {
            $composer['name'] = $this->setName();
        }

        if (!array_key_exists('description', $composer)) {
            $composer['description'] = $this->setDescription();
        }

        if (!file_exists('composer.json')) {
            $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents('composer.json', $json);
            return $composer;
        }

        return false;
    }

    protected function setComposerExecutable()
    {
      // Find Composer
        $composer = $this->taskExec('which composer')
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
        ->run()
        ->getMessage();

        $this->composer = trim($composer);
    }

    protected function composerRequireDrupal()
    {
      // Update the composer.json with drupal requirements and run update.
        $require = [
        'composer/installers',
        'drupal/core',
        'drupal/core-composer-scaffold',
        'drush/drush',
        ];
        $requireDev = [
        'drupal-composer/drupal-security-advisories:dev-8.x-v2',
        'drupal/core-dev',
        'ergebnis/composer-normalize',
        ];
        $this->tasks[] = $this->taskExecStack()
        ->stopOnFail()
        ->executable($this->composer)
        ->exec('require ' . implode(' ', $require) . ' --no-update --ansi')
        ->exec('require ' . implode(' ', $requireDev) . ' --dev --no-update --ansi');
      //   ->exec('normalize --no-update-lock')
      // ->exec('install --no-progress --no-suggest --ansi');
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

    protected function arrayMergeRecursiveDistinct()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);
        if (!is_array($base)) {
            $base = empty($base) ? array() : array($base);
        }
        foreach ($arrays as $append) {
            if (!is_array($append)) {
                $append = array($append);
            }
            foreach ($append as $key => $value) {
                if (!array_key_exists($key, $base) and !is_numeric($key)) {
                    $base[$key] = $append[$key];
                    continue;
                }
                if (is_array($value) or is_array($base[$key])) {
                    $base[$key] = $this->arrayMergeRecursiveDistinct($base[$key], $append[$key]);
                } elseif (is_numeric($key)) {
                    if (!in_array($value, $base)) {
                        $base[] = $value;
                    }
                } else {
                    $base[$key] = $value;
                }
            }
        }
        return $base;
    }
}
