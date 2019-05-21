<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class MigrateMakeCommand extends BaseCommand
{
	/**
     * The console command signature.
     *
     * @var string
     */
	protected $signature = "make:migration {name : The name of the migration.}\n        {--create= : The table to be created.}\n        {--table= : The table to migrate.}\n        {--path= : The location where the migration file should be created.}";
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Create a new migration file';
	/**
     * The migration creator instance.
     *
     * @var \Illuminate\Database\Migrations\MigrationCreator
     */
	protected $creator;
	/**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
	protected $composer;

	public function __construct(\Illuminate\Database\Migrations\MigrationCreator $creator, \Illuminate\Support\Composer $composer)
	{
		parent::__construct();
		$this->creator = $creator;
		$this->composer = $composer;
	}

	public function fire()
	{
		$name = trim($this->input->getArgument('name'));
		$table = $this->input->getOption('table');
		$create = $this->input->getOption('create') ?: false;
		if (!$table && is_string($create)) {
			$table = $create;
			$create = true;
		}

		$this->writeMigration($name, $table, $create);
		$this->composer->dumpAutoloads();
	}

	protected function writeMigration($name, $table, $create)
	{
		$file = pathinfo($this->creator->create($name, $this->getMigrationPath(), $table, $create), PATHINFO_FILENAME);
		$this->line('<info>Created Migration:</info> ' . $file);
	}

	protected function getMigrationPath()
	{
		if (!is_null($targetPath = $this->input->getOption('path'))) {
			return $this->laravel->basePath() . '/' . $targetPath;
		}

		return parent::getMigrationPath();
	}
}

?>
