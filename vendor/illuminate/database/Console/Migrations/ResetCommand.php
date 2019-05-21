<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class ResetCommand extends BaseCommand
{
	use \Illuminate\Console\ConfirmableTrait;

	/**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'migrate:reset';
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Rollback all database migrations';
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

		$this->migrator->setConnection($this->option('database'));

		if (!$this->migrator->repositoryExists()) {
			return $this->comment('Migration table not found.');
		}

		$this->migrator->reset($this->getMigrationPaths(), $this->option('pretend'));

		foreach ($this->migrator->getNotes() as $note) {
			$this->output->writeln($note);
		}
	}

	protected function getOptions()
	{
		return array(
	array('database', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The database connection to use.'),
	array('force', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Force the operation to run when in production.'),
	array('path', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL | \Symfony\Component\Console\Input\InputOption::VALUE_IS_ARRAY, 'The path(s) of migrations files to be executed.'),
	array('pretend', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.')
	);
	}
}

?>
