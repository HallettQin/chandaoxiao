<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class RollbackCommand extends BaseCommand
{
	use \Illuminate\Console\ConfirmableTrait;

	/**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'migrate:rollback';
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Rollback the last database migration';
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
		$this->migrator->rollback($this->getMigrationPaths(), array('pretend' => $this->option('pretend'), 'step' => (int) $this->option('step')));

		foreach ($this->migrator->getNotes() as $note) {
			$this->output->writeln($note);
		}
	}

	protected function getOptions()
	{
		return array(
	array('database', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The database connection to use.'),
	array('force', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Force the operation to run when in production.'),
	array('path', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The path of migrations files to be executed.'),
	array('pretend', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'),
	array('step', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The number of migrations to be reverted.')
	);
	}
}

?>
