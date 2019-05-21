<?php
//cgxlm
namespace Illuminate\Database\Console\Migrations;

class BaseCommand extends \Illuminate\Console\Command
{
	protected function getMigrationPaths()
	{
		if ($this->input->hasOption('path') && $this->option('path')) {
			return collect($this->option('path'))->map(function($path) {
				return $this->laravel->basePath() . '/' . $path;
			})->all();
		}

		return array_merge(array($this->getMigrationPath()), $this->migrator->paths());
	}

	protected function getMigrationPath()
	{
		return $this->laravel->databasePath() . DIRECTORY_SEPARATOR . 'migrations';
	}
}

?>
