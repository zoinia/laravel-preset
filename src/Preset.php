<?php

namespace harmonic\LaravelPreset;

use sixlive\DotenvEditor\DotenvEditor;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Foundation\Console\Presets\Preset as BasePreset;
use Illuminate\Filesystem\Filesystem;

class Preset extends BasePreset {
    protected $command;
    protected $options = [];
    protected static $installTheme = false;
    protected $packages = [
        'bensampo/laravel-enum' => [
            'repo' => 'https://github.com/BenSampo/laravel-enum',
        ],
        'silber/bouncer' => [
            'repo' => 'https://github.com/JosephSilber/bouncer',
            'version' => 'v1.0.0-rc.5',
        ],
        'harmonic/laravel-envcoder' => [
            'repo' => 'https://github.com/Harmonic/laravel-envcoder',
            'dev' => true,
        ],
        'dyrynda/laravel-make-user' => [
            'repo' => 'https://github.com/michaeldyrynda/laravel-make-user',
            'dev' => true,
        ],
        'sempro/phpunit-pretty-print' => [
            'repo' => 'https://github.com/Sempro/phpunit-pretty-print',
            'dev' => true,
        ],
        'Jorijn/laravel-security-checker' => [
            'repo' => 'https://github.com/Jorijn/laravel-security-checker'
        ],
    ];
    protected $themePackages = [
        'tightenco/ziggy' => [
            'repo' => 'https://github.com/tightenco/ziggy',
        ],
        'reinink/remember-query-strings' => [
            'repo' => 'https://github.com/reinink/remember-query-strings',
        ]
    ];
    protected static $jsInclude = [
        '@babel/plugin-syntax-dynamic-import' => '^7.2.0',
        'browser-sync' => '^2.26.5',
        'browser-sync-webpack-plugin' => '2.0.1',
    ];
    protected static $jsExclude = [];

    public function __construct($command) {
        $this->command = $command;
    }

    public static function install($command) {
        (new static($command))->run();
    }

    public function run() {
        $this->command->info('=================');
        $this->command->info(' Harmonic Preset');
        $this->command->info('=================');
        $this->command->info('Before you run the preset please confirm you have:');
        $this->command->info('✔️  Set up and configured your database and URL in .env');
        $this->command->info('✔️  Run the intial Laravel migrations with php artisan migrate');
        $this->command->info('✔️  Have yarn installed globally');
        $continue = $this->command->confirm("Yes, I've done all this, lets get creating!", true);
        if (!$continue) {
            return;
        }

        $this->options = $this->gatherOptions();

        if ($this->options['theme']) {
            static::$installTheme = true;
            $this->command->task('Install harmonic theme', function () {
                return $this->installTheme();
            });
        }

        if ($this->options['install_inertia']) {
            $this->packages['inertiajs/inertia-laravel'] = [
                'repo' => 'https://github.com/inertiajs/inertia-laravel',
                'version' => '^0.1.0'
            ];
            $this->packages['harmonic/inertia-table'] = [
                'repo' => 'https://github.com/harmonic/inertia-table',
                'version' => '^1.0.0'
            ];
            $this->options['packages'][] = 'inertiajs/inertia-laravel';
            $this->options['packages'][] = 'harmonic/inertia-table';
            self::$jsInclude = array_merge(self::$jsInclude, [
                '@inertiajs/inertia' => '^0.1.0',
                '@inertiajs/inertia-vue' => '^0.1.0',
                'inertia-table' => '^1.0.8',
                'vue-template-compiler' => '^2.6.10',
            ]);
        }

        if (!empty($this->options['packages'])) {
            $this->command->task('Install composer dependencies', function () {
                return $this->updateComposerPackages();
            });
        }
        $this->command->task('Install composer dev-dependencies', function () {
            return $this->updateComposerDevPackages();
        });

        $this->command->task('Update ENV files', function () {
            $this->updateEnvFile();
        });

        if ($this->options['install_tailwind']) {
            self::$jsInclude = array_merge(self::$jsInclude, [
                'laravel-mix-purgecss' => '^4.1.0',
                'postcss-import' => '^12.0.1',
                'postcss-nesting' => '^7.0.0',
                'tailwindcss' => '>=1.0.0'
            ]);

            self::$jsExclude = array_merge(self::$jsExclude, [
                'bootstrap',
                'bootstrap-sass',
                'jquery',
                'sass',
                'sass-loader'
            ]);

            $this->command->task('Install Tailwindcss', function () {
                static::ensureComponentDirectoryExists();
                static::updatePackages();
                $this->tailwindTemplate();
                static::removeNodeModules();
            });
            $this->command->task('Install node dependencies with Yarn', function () {
                $this->runCommand('yarn install');
                $this->runCommand('yarn add -D eslint eslint-plugin-vue');
                copy(__DIR__ . '/stubs/.eslintrc.js', base_path('.eslintrc.js'));
            });
            $this->command->task('Setup Tailwindcss', function () {
                $this->runCommand('yarn tailwind init');
            });
            $this->command->task('Run node dev build with Yarn', function () {
                $this->runCommand('yarn dev');
            });
        }

        if ($this->options['cypress']) {
            copy(__DIR__ . '/stubs/cypress.json', base_path('cypress.json'));
            $this->command->task('Install Cypress', function () {
                $this->runCommand('yarn add cypress --dev');
            });
        }

        $this->command->task('Update .gitignore', function () {
            $this->updateGitignore();
        });

        if ($this->options['theme']) {
            $this->command->task('Run migrations', function () {
                $this->runCommand('php artisan migrate');
            });
        }

        tap(new Filesystem, function ($files) {
            $files->deleteDirectory(resource_path('js/components'));
        });

        if ($this->options['remove_after_install']) {
            $this->command->task('Remove harmonic/laravel-preset', function () {
                $this->runCommand('composer remove harmonic/laravel-preset --dev');
                $this->runCommand('composer dumpautoload');
            });
        }

        $this->outputSuccessMessage();
    }

    private function tailwindTemplate() {
        tap(new Filesystem, function ($files) {
            $files->deleteDirectory(resource_path('sass'));
            $files->delete(public_path('css/app.css'));
            if (!$files->isDirectory($directory = resource_path('css'))) {
                $files->makeDirectory($directory, 0755, true);
            }
        });
        if (!$this->options['theme']) { // theme has its own settings
            copy(__DIR__ . '/stubs/tailwind/resources/css/app.css', resource_path('css/app.css'));
            copy(__DIR__ . '/stubs/tailwind/webpack.mix.js', base_path('webpack.mix.js'));

            tap(new Filesystem, function ($files) {
                $files->delete(resource_path('views/home.blade.php'));
                $files->delete(resource_path('views/welcome.blade.php'));
                $files->copyDirectory(__DIR__ . '/stubs/tailwind/resources/views', resource_path('views'));
            });
        }
    }

    protected static function updatePackageArray(array $packages) {
        return array_merge(static::$jsInclude, Arr::except($packages, static::$jsExclude));
    }

    private function gatherOptions() {
        $options = [
            'cypress' => $this->command->confirm('Install cypress for front end testing?', true),
            'theme' => $this->command->confirm('Install harmonic theme?', true),
            'packages' => $this->promptForPackagesToInstall(),
            'remove_after_install' => $this->command->confirm('Remove harmonic/laravel-preset after install?', true),
        ];

        if (!$options['theme']) {
            $options['install_tailwind'] = $this->command->confirm('Install Tailwindcss?', true);
            $options['install_inertia'] = $this->command->confirm('Install Inertia JS?', true);
        }

        return $options;
    }

    private function promptForPackagesToInstall() {
        $possiblePackages = $this->packages();
        $choices = $this->command->choice(
            'Which optional packages should be installed? (e.x. 1,2)',
            array_merge(['all'], $possiblePackages, ['none']),
            '0',
            null,
            true
        );
        if (in_array('all', $choices)) {
            return $possiblePackages;
        }
        if (in_array('none', $choices)) {
            return [];
        }
        return $choices;
    }

    private function updateComposerPackages() {
        $this->runCommand(sprintf(
            'composer require %s',
            $this->resolveForComposer($this->options['packages'])
        ));
    }

    private function packages() {
        return Collection::make($this->packages)
            ->where('dev', false)
            ->keys()
            ->toArray();
    }

    private function devPackages() {
        return Collection::make($this->packages)
            ->where('dev', true)
            ->keys()
            ->toArray();
    }

    private function resolveForComposer($packages) {
        return Collection::make($packages)
            ->transform(function ($package) {
                return isset($this->packages[$package]['version'])
                    ? $package . ':' . $this->packages[$package]['version']
                    : $package;
            })
            ->implode(' ');
    }

    private function updateComposerDevPackages() {
        $this->runCommand(sprintf(
            'composer require --dev %s',
            $this->resolveForComposer($this->devPackages())
        ));
    }

    private function installTheme() {
        $this->options['install_tailwind'] = true;
        $this->options['install_inertia'] = true;

        self::$jsInclude = array_merge(self::$jsInclude, [
            'portal-vue' => '^2.1.4',
        ]);

        $themePackages = Collection::make($this->themePackages)->keys()->toArray();

        // Add all the composer theme related packages to the list to install
        $this->options['packages'] = array_merge($this->options['packages'], $themePackages);
        $this->packages = array_merge($this->packages, $this->themePackages);

        // Add correct url to webpack
        copy(__DIR__ . '/stubs/theme/webpack.mix.js', base_path('webpack.mix.js'));
        $oldWebpack = \file_get_contents(base_path('webpack.mix.js'));
        $newContent = str_replace('laravel-preset-test.test', parse_url(config('app.url'))['host'], $oldWebpack);
        \file_put_contents(base_path('webpack.mix.js'), $newContent);

        copy(__DIR__ . '/stubs/theme/tailwind.config.js', base_path('tailwind.config.js'));
        copy(__DIR__ . '/stubs/theme/Kernel.php', app_path('Http/Kernel.php'));

        copy(__DIR__ . '/stubs/theme/Model.php', app_path('Model.php'));
        copy(__DIR__ . '/stubs/theme/User.php', app_path('User.php'));
        copy(__DIR__ . '/stubs/theme/UsersController.php', app_path('Http/Controllers/UsersController.php'));
        copy(__DIR__ . '/stubs/theme/web.php', base_path('routes/web.php'));
        copy(__DIR__ . '/stubs/theme/AppServiceProvider.php', app_path('Providers/AppServiceProvider.php'));
        copy(__DIR__ . '/stubs/theme/2019_05_29_033345_add_soft_deletes_to_users_table.php', base_path('database/migrations/2019_05_29_033345_add_soft_deletes_to_users_table.php'));

        tap(new Filesystem, function ($files) {
            $files->delete(resource_path('views/home.blade.php'));
            $files->delete(resource_path('views/welcome.blade.php'));
            $files->delete(resource_path('js/components/ExampleComponent.vue'));
            $files->copyDirectory(__DIR__ . '/stubs/theme/views', resource_path('views'));
            $files->copyDirectory(__DIR__ . '/stubs/theme/js', resource_path('js'));
            $files->copyDirectory(__DIR__ . '/stubs/theme/css', resource_path('css'));
            $files->copyDirectory(__DIR__ . '/stubs/theme/Auth', app_path('Http/Controllers/Auth'));
            $files->copyDirectory(__DIR__ . '/stubs/theme/Traits', app_path('Traits'));
        });

        return true;
    }

    private function updateEnvFile() {
        tap(new DotenvEditor, function ($editor) {
            $editor->load(base_path('.env'));
            $editor->set('LCS_MAIL_TO', 'email@toreceiveupdates.com');
            $editor->save();
        });
        tap(new DotenvEditor, function ($editor) {
            $editor = new DotenvEditor;
            $editor->load(base_path('.env.example'));
            $editor->save();
        });
    }

    private function updateGitignore() {
        copy(__DIR__ . '/stubs/gitignore-stub', base_path('.gitignore'));
        copy(__DIR__ . '/stubs/disable.xdebug.ini', base_path('disable.xdebug.ini'));
        copy(__DIR__ . '/stubs/run-tests.sh', base_path('run-tests.sh'));
        chmod(base_path('run-tests.sh'), 0755);
    }

    private function runCommand($command) {
        return exec(sprintf('%s 2>&1', $command));
    }

    private function getInstalledPackages() {
        return Collection::make($this->packages)
            ->filter(function ($data, $package) {
                return in_array($package, $this->options['packages'])
                    || ($data['dev'] ?? false);
            })
            ->toArray();
    }

    private function outputSuccessMessage() {
        $this->command->line('');
        $this->command->info('🎉  Preset installation complete. The packages that were installed may require additional installation steps.');
        $this->command->line('');
        foreach ($this->getInstalledPackages() as $package => $packageData) {
            $this->command->getOutput()->writeln(vsprintf('- %s: <comment>%s</comment>', [
                $package,
                $packageData['repo'],
            ]));
        }
        $this->command->line('');
        $this->command->info('Finish set up by running the following commands:');
        $this->command->info('✅  yarn');
        $this->command->info('✅  Create a user with php artisan make:user');
        $this->command->info('✅  Update the LCS_MAIL_TO .env variable with a meaningful email address');
        $this->command->info('✅  (optional) Create an encrypted version of your .env with php artisan env:encrypt');
        $this->command->info('✅  (optional) Start the project with yarn dev/watch/hot');
        $this->command->info('✅  yarn run dev');
        $this->command->line('');
    }
}
