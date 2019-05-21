<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class MigrateCommand extends BaseCommand
{
	use \Illuminate\Console\ConfirmableTrait;

	/**
     * The name and signature of the console command.
     *
     * @var string
     */
	protected $signature = "migrate {--database= : The database connection to use.}\n                {--force : Force the operation to run when in production.}\n                {--path= : The path of migrations files to be executed.}\n                {--pretend : Dump the SQL queries that would be run.}\n                {--seed : Indicates if the seed task should be re-run.}\n                {--step : Force the migrations to be run so they can be rolled back individually.}";
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Run the database migrations';
	/**
     * The migrator instance.
     *
     * @var \Illuminate\Database\Migrations\Migrator
     */
	protected $migrator;

	public function __construct(\Illuminate\Database\Migrations\Migrator $migrator)
	{
		parent::__construct();
		$this->migrator = $migrator;
	}

	public function fire()
	{
		if (!$this->confirmToProceed()) {
			return NULL;
		}

		$this->prepareDatabase();
		$this->migrator->run($this->getMigrationPaths(), array('pretend' => $this->option('pretend'), 'step' => $this->option('step')));

		foreach ($this->migrator->getNotes() as $note) {
			$this->output->writeln($note);
		}

		if ($this->option('seed')) {
			$this->call('db:seed', array('--force' => true));
		}
	}

	protected function prepareDatabase()
	{
		$this->migrator->setConnection($this->option('database'));

		if (!$this->migrator->repositoryExists()) {
			$this->call('migrate:install', array('--database' => $this->option('database')));
		}
	}
}

?>
