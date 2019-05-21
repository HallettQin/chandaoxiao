<?php
//cgxlm
namespace Illuminate\Support;

abstract class ServiceProvider
{
	/**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
	protected $app;
	/**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
	protected $defer = false;
	/**
     * The paths that should be published.
     *
     * @var array
     */
	static protected $publishes = array();
	/**
     * The paths that should be published by group.
     *
     * @var array
     */
	static protected $publishGroups = array();

	public function __construct($app)
	{
		$this->app = $app;
	}

	protected function mergeConfigFrom($path, $key)
	{
		$config = $this->app['config']->get($key, array());
		$this->app['config']->set($key, array_merge(require $path, $config));
	}

	protected function loadRoutesFrom($path)
	{
		if (!$this->app->routesAreCached()) {
			require $path;
		}
	}

	protected function loadViewsFrom($path, $namespace)
	{
		if (is_dir($appPath = $this->app->resourcePath() . '/views/vendor/' . $namespace)) {
			$this->app['view']->addNamespace($namespace, $appPath);
		}

		$this->app['view']->addNamespace($namespace, $path);
	}

	protected function loadTranslationsFrom($path, $namespace)
	{
		$this->app['translator']->addNamespace($namespace, $path);
	}

	protected function loadMigrationsFrom($paths)
	{
		$this->app->afterResolving('migrator', function($migrator) use($paths) {
			foreach ((array) $paths as $path) {
				$migrator->path($path);
			}
		});
	}

	protected function publishes(array $paths, $group = NULL)
	{
		$this->ensurePublishArrayInitialized($class = static::class);
		static::$publishes[$class] = array_merge(static::$publishes[$class], $paths);

		if ($group) {
			$this->addPublishGroup($group, $paths);
		}
	}

	protected function ensurePublishArrayInitialized($class)
	{
		if (!array_key_exists($class, static::$publishes)) {
			static::$publishes[$class] = array();
		}
	}

	protected function addPublishGroup($group, $paths)
	{
		if (!array_key_exists($group, static::$publishGroups)) {
			static::$publishGroups[$group] = array();
		}

		static::$publishGroups[$group] = array_merge(static::$publishGroups[$group], $paths);
	}

	static public function pathsToPublish($provider = NULL, $group = NULL)
	{
		if (!is_null($paths = static::pathsForProviderOrGroup($provider, $group))) {
			return $paths;
		}

		return collect(static::$publishes)->reduce(function($paths, $p) {
			return array_merge($paths, $p);
		}, array());
	}

	static protected function pathsForProviderOrGroup($provider, $group)
	{
		if ($provider && $group) {
			return static::pathsForProviderAndGroup($provider, $group);
		}
		else {
			if ($group && array_key_exists($group, static::$publishGroups)) {
				return static::$publishGroups[$group];
			}
			else {
				if ($provider && array_key_exists($provider, static::$publishes)) {
					return static::$publishes[$provider];
				}
				else {
					if ($group || $provider) {
						return array();
					}
				}
			}
		}
	}

	static protected function pathsForProviderAndGroup($provider, $group)
	{
		if (!empty(static::$publishes[$provider]) && !empty(static::$publishGroups[$group])) {
			return array_intersect_key(static::$publishes[$provider], static::$publishGroups[$group]);
		}

		return array();
	}

	public function commands($commands)
	{
		$commands = (is_array($commands) ? $commands : func_get_args());
		\Illuminate\Console\Application::starting(function($artisan) use($commands) {
			$artisan->resolveCommands($commands);
		});
	}

	public function provides()
	{
		return array();
	}

	public function when()
	{
		return array();
	}

	public function isDeferred()
	{
		return $this->defer;
	}

	static public function compiles()
	{
		return array();
	}
}


?>
