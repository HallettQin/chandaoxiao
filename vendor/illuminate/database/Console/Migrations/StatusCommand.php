<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class StatusCommand extends BaseCommand
{
	/**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'migrate:status';
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Show the status of each migration';
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
		$this->migrator->setConnection($this->option('database'));

		if (!$this->migrator->repositoryExists()) {
			return $this->error('No migrations found.');
		}

		$ran = $this->migrator->getRepository()->getRan();

		if (0 < count($migrations = $this->getStatusFor($ran))) {
			$this->table(array('Ran?', 'Migration'), $migrations);
		}
		else {
			$this->error('No migrations found');
		}
	}

	protected function getStatusFor(array $ran)
	{
		return \Illuminate\Support\Collection::make($this->getAllMigrationFiles())->map(function($migration) use($ran) {
			$migrationName = $this->migrator->getMigrationName($migration);
			return in_array($migrationName, $ran) ? array('<info>Y</info>', $migrationName) : array('<fg=red>N</fg=red>', $migrationName);
		});
	}

	protected function getAllMigrationFiles()
	{
		return $this->migrator->getMigrationFiles($this->getMigrationPaths());
	}

	protected function getOptions()
	{
		return array(
	array('database', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The database connection to use.'),
	array('path', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The path of migrations files to use.')
	);
	}
}

?>
