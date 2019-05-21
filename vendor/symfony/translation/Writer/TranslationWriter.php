<?php
//cgxlm
namespace Symfony\Component\Translation\Writer;

class TranslationWriter
{
	/**
     * Dumpers used for export.
     *
     * @var array
     */
	private $dumpers = array();

	public function addDumper($format, \Symfony\Component\Translation\Dumper\DumperInterface $dumper)
	{
		$this->dumpers[$format] = $dumper;
	}

	public function disableBackup()
	{
		foreach ($this->dumpers as $dumper) {
			if (method_exists($dumper, 'setBackup')) {
				$dumper->setBackup(false);
			}
		}
	}

	public function getFormats()
	{
		return array_keys($this->dumpers);
	}

	public function writeTranslations(\Symfony\Component\Translation\MessageCatalogue $catalogue, $format, $options = array())
	{
		if (!isset($this->dumpers[$format])) {
			throw new \Symfony\Component\Translation\Exception\InvalidArgumentException(sprintf('There is no dumper associated with format "%s".', $format));
		}

		$dumper = $this->dumpers[$format];
		if (isset($options['path']) && !is_dir($options['path']) && !@mkdir($options['path'], 511, true) && !is_dir($options['path'])) {
			throw new \Symfony\Component\Translation\Exception\RuntimeException(sprintf('Translation Writer was not able to create directory "%s"', $options['path']));
		}

		$dumper->dump($catalogue, $options);
	}
}


?>
