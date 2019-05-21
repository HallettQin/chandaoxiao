<?php
//cgxlm
namespace Illuminate\Database;

class DatabaseServiceProvider extends \Illuminate\Support\ServiceProvider
{
	public function boot()
	{
		Eloquent\Model::setConnectionResolver($this->app['db']);
		Eloquent\Model::setEventDispatcher($this->app['events']);
	}

	public function register()
	{
		Eloquent\Model::clearBootedModels();
		$this->registerConnectionServices();
		$this->registerEloquentFactory();
		$this->registerQueueableEntityResolver();
	}

	protected function registerConnectionServices()
	{
		$this->app->singleton('db.factory', function($app) {
			return new Connectors\ConnectionFactory($app);
		});
		$this->app->singleton('db', function($app) {
			return new DatabaseManager($app, $app['db.factory']);
		});
		$this->app->bind('db.connection', function($app) {
			return $app['db']->connection();
		});
	}

	protected function registerEloquentFactory()
	{
		$this->app->singleton('Faker\\Generator', function($app) {
			return \Faker\Factory::create($app['config']->get('app.faker_locale', 'en_US'));
		});
		$this->app->singleton('Illuminate\\Database\\Eloquent\\Factory', function($app) {
			return Eloquent\Factory::construct($app->make('Faker\\Generator'), $this->app->databasePath('factories'));
		});
	}

	protected function registerQueueableEntityResolver()
	{
		$this->app->singleton('Illuminate\\Contracts\\Queue\\EntityResolver', function() {
			return new Eloquent\QueueEntityResolver();
		});
	}
}

?>
