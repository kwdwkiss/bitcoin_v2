<?php
/**
 * Created by PhpStorm.
 * User: WY
 * Date: 2016/12/24
 * Time: 15:08
 */
namespace Modules\Core\Providers;

trait ModuleServiceProviderTrait
{
    protected function walkPathFiles($path, callable $callback)
    {
        $class = new \ReflectionClass(__CLASS__);
        $moduleRoot = dirname(dirname($class->getFileName()));
        $files = app('files')->allFiles($moduleRoot . $path);
        $moduleNamespace = str_replace('\\Providers', '', $class->getNamespaceName());
        $namespace = str_replace('/', '\\', $path);
        foreach ($files as $file) {
            $class = '\\' . $moduleNamespace . $namespace . '\\' . $file->getBasename('.php');
            $callback($class);
        }
    }

    protected function registerCommand()
    {
        if ($this->app->runningInConsole()) {
            $this->walkPathFiles('/Console', function ($class) {
                $this->commands([$class]);
            });
        }
    }

    protected function registerService()
    {
        $this->walkPathFiles('/Service', function ($class) {
            $this->app->singleton(lcfirst(class_basename($class)), $class);
        });
    }

    protected function registerRoutes()
    {
        $this->walkPathFiles('/Http/Controllers', function ($class) {
            $this->registerForRoutes([['class' => $class, 'prefix' => 'api', 'middlewareGroup' => ['api', 'authAgent']]]);
        });
    }

    protected function registerForRoutes($routes)
    {
//        $routes = [
//            ['class' => \Modules\DdosNgx\Http\Controllers\NgxCdnController::class, 'prefix' => 'api', 'middlewareGroup' => ['api', 'authAgent']],
//        ];
        foreach ($routes as $item) {
            $class = $item['class'];
            $prefix = $item['prefix'];
            $middlewareGroup = $item['middlewareGroup'];
            $reflectionClass = new \ReflectionClass($class);
            $methods = $reflectionClass->getMethods();
            $shortName = $reflectionClass->getShortName();
            $controllerName = str_replace('Controller', '', $shortName);
            $uri = snake_case($controllerName, '/');
            $module = snake_case(explode('\\', $reflectionClass)[1], '_');
            $routeName = $module . '.' . snake_case($controllerName, '');
            foreach ($methods as $method) {
                if ($method->class == $reflectionClass->getName()) {
                    $route = \Route::any("$uri/{$method->name}", ['as' => "$routeName.{$method->name}", 'prefix' => $prefix, 'uses' => "$class@{$method->name}"]);
                    foreach ($middlewareGroup as $middleware) {
                        $route->middleware($middleware);
                    }
                }
            }
        }
    }
}