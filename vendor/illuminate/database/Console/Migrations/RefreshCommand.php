<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class RefreshCommand extends \Illuminate\Console\Command
{
	use \Illuminate\Console\ConfirmableTrait;

	/**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'migrate:refresh';
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Reset and re-run all migrations';

	public function fire()
	{
		if (!$this->confirmToProceed()) {
			return NULL;
		}

		$database = $this->input->getOption('database');
		$path = $this->input->getOption('path');
		$force = $this->input->getOption('force');
		$step = $this->input->getOption('step') ?: 0;

		if (0 < $step) {
			$this->runRollback($database, $path, $step, $force);
		}
		else {
			$this->runReset($database, $path, $force);
		}

		$this->call('migrate', array('--database' => $database, '--path' => $path, '--force' => $force));

		if ($this->needsSeeding()) {
			$this->runSeeder($database);
		}
	}

	protected function runRollback($database, $path, $step, $force)
	{
		$this->call('migrate:rollback', array('--database' => $database, '--path' => $path, '--step' => $step, '--force' => $force));
	}

	protected function runReset($database, $path, $force)
	{
		$this->call('migrate:reset', array('--database' => $database, '--path' => $path, '--force' => $force));
	}

	protected function needsSeeding()
	{
		return $this->option('seed') || $this->option('seeder');
	}

	protected function runSeeder($database)
	{
		$this->call('db:seed', array('--database' => $database, '--class' => $this->option('seeder') ?: 'DatabaseSeeder', '--force' => $this->option('force')));
	}

	protected function getOptions()
	{
		return array(
	array('database', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The database connection to use.'),
	array('force', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Force the operation to run when in production.'),
	array('path', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The path of migrations files to be executed.'),
	array('seed', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run.'),
	array('seeder', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The class name of the root seeder.'),
	array('step', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The number of migrations to be reverted & re-run.')
	);
	}
}

?>
