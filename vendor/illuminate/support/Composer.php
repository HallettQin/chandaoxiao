<?php
//cgxlm
namespace Illuminate\Support;

class Composer
{
	/**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
	protected $files;
	/**
     * The working path to regenerate from.
     *
     * @var string
     */
	protected $workingPath;

	public function __construct(\Illuminate\Filesystem\Filesystem $files, $workingPath = NULL)
	{
		$this->files = $files;
		$this->workingPath = $workingPath;
	}

	public function dumpAutoloads($extra = '')
	{
		$process = $this->getProcess();
		$process->setCommandLine(trim($this->findComposer() . ' dump-autoload ' . $extra));
		$process->run();
	}

	public function dumpOptimized()
	{
		$this->dumpAutoloads('--optimize');
	}

	protected function findComposer()
	{
		if ($this->files->exists($this->workingPath . '/composer.phar')) {
			return \Symfony\Component\Process\ProcessUtils::escapeArgument((new \Symfony\Component\Process\PhpExecutableFinder())->find(false)) . ' composer.phar';
		}

		return 'composer';
	}

	protected function getProcess()
	{
		return (new \Symfony\Component\Process\Process('', $this->workingPath))->setTimeout(null);
	}

	public function setWorkingPath($path)
	{
		$this->workingPath = realpath($path);
		return $this;
	}
}


?>
