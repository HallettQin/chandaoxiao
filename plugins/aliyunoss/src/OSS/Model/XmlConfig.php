<?php
//cgxlm
namespace OSS\Model;

interface XmlConfig
{
	public function parseFromXml($strXml);

	public function serializeToXml();
}


?>
