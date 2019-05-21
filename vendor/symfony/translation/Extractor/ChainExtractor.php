<?php
//cgxlm
namespace Symfony\Component\Translation\Extractor;

class ChainExtractor implements ExtractorInterface
{
	/**
     * The extractors.
     *
     * @var ExtractorInterface[]
     */
	private $extractors = array();

	public function addExtractor($format, ExtractorInterface $extractor)
	{
		$this->extractors[$format] = $extractor;
	}

	public function setPrefix($prefix)
	{
		foreach ($this->extractors as $extractor) {
			$extractor->setPrefix($prefix);
		}
	}

	public function extract($directory, \Symfony\Component\Translation\MessageCatalogue $catalogue)
	{
		foreach ($this->extractors as $extractor) {
			$extractor->extract($directory, $catalogue);
		}
	}
}

?>
