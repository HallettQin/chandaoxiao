<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class InstallCommand extends \Illuminate\Console\Command
{
	/**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'migrate:install';
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Create the migration repository';
	/**
     * The repository instance.
     *
     * @var \Illuminate\Database\Migrations\MigrationRepositoryInterface
     */
	protected $repository;

	public function __construct(\Illuminate\Database\Migrations\MigrationRepositoryInterface $repository)
	{
		parent::__construct();
		$this->repository = $repository;
	}

	public function fire()
	{
		$this->repository->setSource($this->input->getOption('database'));
		$this->repository->createRepository();
		$this->info('Migration table created successfully.');
	}

	protected function getOptions()
	{
		return array(
	array('database', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The database connection to use.')
	);
	}
}

?>
