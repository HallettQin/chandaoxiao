<?php
//cgxlm
namespace Symfony\Component\Translation\Extractor;

interface ExtractorInterface
{
	public function extract($resource, \Symfony\Component\Translation\MessageCatalogue $catalogue);

	public function setPrefix($prefix);
}


?>
