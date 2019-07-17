<?php

namespace FileTools;

use Illuminate\Support\ServiceProvider;

class FileToolsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(\Illuminate\Routing\Router $router)
    {
        if(config('filetools.router.includeRoutes')) {
            $router->prefix(config('filetools.router.prefix'))
                ->namespace('FileTools\Http\Controllers')
                ->middleware(config('filetools.router.guestMiddleware'))
                ->group(__DIR__.'/Http/routes.php');
        }

        $argv = $this->app->request->server->get('argv');
        if(isset($argv[1]) and $argv[1]=='vendor:publish') {
            $this->publishes([
                __DIR__.'/../config/filetools.php' => config_path('filetools.php'),
            ], 'config');
            $this->publishes([
                __DIR__.'/File.stub.php' => app_path('File.php'),
            ], 'model');

            $existing = glob(database_path('migrations/*_create_files_table.php'));
            if(empty($existing)) {
                $this->publishes([
                    __DIR__.'/../database/migrations/create_files_table.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_files_table.php'),
                    __DIR__.'/../database/migrations/create_file_associations_pivot.stub.php' => database_path('migrations/'.date('Y_m_d_His', time()+1).'_create_file_associations_pivot.php'),
                ], 'migrations');
            }
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

        $this->mergeConfigFrom(__DIR__.'/../config/filetools.php', 'filetools');


        $this->app->bind('command.filetools:stats', Commands\StatsCommand::class);
        $this->app->bind('command.filetools:config', Commands\ConfigCommand::class);
        $this->app->bind('command.filetools:setup', Commands\SetupCommand::class);
        $this->app->bind('command.filetools:clean', Commands\CleanCommand::class);

        $this->commands([
            'command.filetools:stats',
            'command.filetools:config',
            'command.filetools:setup',
            'command.filetools:clean',
        ]);

    }

}
