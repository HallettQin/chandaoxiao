<?php
//cgxlm
namespace Symfony\Component\Translation\Command;

class XliffLintCommand extends \Symfony\Component\Console\Command\Command
{
	private $format;
	private $displayCorrectFiles;
	private $directoryIteratorProvider;
	private $isReadableProvider;

	public function __construct($name = NULL, $directoryIteratorProvider = NULL, $isReadableProvider = NULL)
	{
		parent::__construct($name);
		$this->directoryIteratorProvider = $directoryIteratorProvider;
		$this->isReadableProvider = $isReadableProvider;
	}

	protected function configure()
	{
		$this->setName('lint:xliff')->setDescription('Lints a XLIFF file and outputs encountered errors')->addArgument('filename', null, 'A file or a directory or STDIN')->addOption('format', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'The output format', 'txt')->setHelp("The <info>%command.name%</info> command lints a XLIFF file and outputs to STDOUT\nthe first encountered syntax error.\n\nYou can validates XLIFF contents passed from STDIN:\n\n  <info>cat filename | php %command.full_name%</info>\n\nYou can also validate the syntax of a file:\n\n  <info>php %command.full_name% filename</info>\n\nOr of a whole directory:\n\n  <info>php %command.full_name% dirname</info>\n  <info>php %command.full_name% dirname --format=json</info>\n");
	}

	protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
	{
		$io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
		$filename = $input->getArgument('filename');
		$this->format = $input->getOption('format');
		$this->displayCorrectFiles = $output->isVerbose();

		if (!$filename) {
			if (!($stdin = $this->getStdin())) {
				throw new \RuntimeException('Please provide a filename or pipe file content to STDIN.');
			}

			return $this->display($io, array($this->validate($stdin)));
		}

		if (!$this->isReadable($filename)) {
			throw new \RuntimeException(sprintf('File or directory "%s" is not readable.', $filename));
		}

		$filesInfo = array();

		foreach ($this->getFiles($filename) as $file) {
			$filesInfo[] = $this->validate(file_get_contents($file), $file);
		}

		return $this->display($io, $filesInfo);
	}

	private function validate($content, $file = NULL)
	{
		if ('' === trim($content)) {
			return array('file' => $file, 'valid' => true);
		}

		libxml_use_internal_errors(true);
		$document = new \DOMDocument();
		$document->loadXML($content);

		if ($document->schemaValidate(__DIR__ . '/../Resources/schemas/xliff-core-1.2-strict.xsd')) {
			return array('file' => $file, 'valid' => true);
		}

		$errorMessages = array_map(function($error) {
			return array('line' => $error->line, 'column' => $error->column, 'message' => trim($error->message));
		}, libxml_get_errors());
		libxml_clear_errors();
		libxml_use_internal_errors(false);
		return array('file' => $file, 'valid' => false, 'messages' => $errorMessages);
	}

	private function display(\Symfony\Component\Console\Style\SymfonyStyle $io, array $files)
	{
		switch ($this->format) {
		case 'txt':
			return $this->displayTxt($io, $files);
		case 'json':
			return $this->displayJson($io, $files);
		default:
			throw new \InvalidArgumentException(sprintf('The format "%s" is not supported.', $this->format));
		}
	}

	private function displayTxt(\Symfony\Component\Console\Style\SymfonyStyle $io, array $filesInfo)
	{
		$countFiles = count($filesInfo);
		$erroredFiles = 0;

		foreach ($filesInfo as $info) {
			if ($info['valid'] && $this->displayCorrectFiles) {
				$io->comment('<info>OK</info>' . ($info['file'] ? sprintf(' in %s', $info['file']) : ''));
			}
			else if (!$info['valid']) {
				++$erroredFiles;
				$io->text('<error> ERROR </error>' . ($info['file'] ? sprintf(' in %s', $info['file']) : ''));
				$io->listing(array_map(function($error) {
					return -1 === $error['line'] ? $error['message'] : sprintf('Line %d, Column %d: %s', $error['line'], $error['column'], $error['message']);
				}, $info['messages']));
			}
		}

		if (0 === $erroredFiles) {
			$io->success(sprintf('All %d XLIFF files contain valid syntax.', $countFiles));
		}
		else {
			$io->warning(sprintf('%d XLIFF files have valid syntax and %d contain errors.', $countFiles - $erroredFiles, $erroredFiles));
		}

		return min($erroredFiles, 1);
	}

	private function displayJson(\Symfony\Component\Console\Style\SymfonyStyle $io, array $filesInfo)
	{
		$errors = 0;
		array_walk($filesInfo, function(&$v) use(&$errors) {
			$v['file'] = (string) $v['file'];

			if (!$v['valid']) {
				++$errors;
			}
		});
		$io->writeln(json_encode($filesInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		return min($errors, 1);
	}

	private function getFiles($fileOrDirectory)
	{
		if (is_file($fileOrDirectory)) {
			yield new \SplFileInfo($fileOrDirectory);
			return NULL;
		}

		foreach ($this->getDirectoryIterator($fileOrDirectory) as $file) {
			if (!in_array($file->getExtension(), array('xlf', 'xliff'))) {
				continue;
			}

			yield $file;
		}
	}

	private function getStdin()
	{
		if (0 !== ftell(STDIN)) {
			return NULL;
		}

		$inputs = '';

		while (!feof(STDIN)) {
			$inputs .= fread(STDIN, 1024);
		}

		return $inputs;
	}

	private function getDirectoryIterator($directory)
	{
		$default = function($directory) {
			return new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS), \RecursiveIteratorIterator::LEAVES_ONLY);
		};

		if (null !== $this->directoryIteratorProvider) {
			return call_user_func($this->directoryIteratorProvider, $directory, $default);
		}

		return $default($directory);
	}

	private function isReadable($fileOrDirectory)
	{
		$default = function($fileOrDirectory) {
			return is_readable($fileOrDirectory);
		};

		if (null !== $this->isReadableProvider) {
			return call_user_func($this->isReadableProvider, $fileOrDirectory, $default);
		}

		return $default($fileOrDirectory);
	}
}

?>
