<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

abstract class FileDumper implements DumperInterface
{
	/**
     * A template for the relative paths to files.
     *
     * @var string
     */
	protected $relativePathTemplate = '%domain%.%locale%.%extension%';
	/**
     * Make file backup before the dump.
     *
     * @var bool
     */
	private $backup = true;

	public function setRelativePathTemplate($relativePathTemplate)
	{
		$this->relativePathTemplate = $relativePathTemplate;
	}

	public function setBackup($backup)
	{
		$this->backup = $backup;
	}

	public function dump(\Symfony\Component\Translation\MessageCatalogue $messages, $options = array())
	{
		if (!array_key_exists('path', $options)) {
			throw new \Symfony\Component\Translation\Exception\InvalidArgumentException('The file dumper needs a path option.');
		}

		foreach ($messages->getDomains() as $domain) {
			$fullpath = $options['path'] . '/' . $this->getRelativePath($domain, $messages->getLocale());

			if (file_exists($fullpath)) {
				if ($this->backup) {
					@trigger_error('Creating a backup while dumping a message catalogue is deprecated since version 3.1 and will be removed in 4.0. Use TranslationWriter::disableBackup() to disable the backup.', E_USER_DEPRECATED);
					copy($fullpath, $fullpath . '~');
				}
			}
			else {
				$directory = dirname($fullpath);
				if (!file_exists($directory) && !@mkdir($directory, 511, true)) {
					throw new \Symfony\Component\Translation\Exception\RuntimeException(sprintf('Unable to create directory "%s".', $directory));
				}
			}

			file_put_contents($fullpath, $this->formatCatalogue($messages, $domain, $options));
		}
	}

	abstract public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array());

	abstract protected function getExtension();

	private function getRelativePath($domain, $locale)
	{
		return strtr($this->relativePathTemplate, array('%domain%' => $domain, '%locale%' => $locale, '%extension%' => $this->getExtension()));
	}
}

?>
