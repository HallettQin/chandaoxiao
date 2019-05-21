<?php
//cgxlm
namespace Illuminate\Database\Console\Seeds;

class SeederMakeCommand extends \Illuminate\Console\GeneratorCommand
{
	/**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'make:seeder';
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Create a new seeder class';
	/**
     * The type of class being generated.
     *
     * @var string
     */
	protected $type = 'Seeder';
	/**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
	protected $composer;

	public function __construct(\Illuminate\Filesystem\Filesystem $files, \Illuminate\Support\Composer $composer)
	{
		parent::__construct($files);
		$this->composer = $composer;
	}

	public function fire()
	{
		parent::fire();
		$this->composer->dumpAutoloads();
	}

	protected function getStub()
	{
		return __DIR__ . '/stubs/seeder.stub';
	}

	protected function getPath($name)
	{
		return $this->laravel->databasePath() . '/seeds/' . $name . '.php';
	}

	protected function qualifyClass($name)
	{
		return $name;
	}
}

?>
