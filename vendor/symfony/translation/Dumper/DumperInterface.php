<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

interface DumperInterface
{
	public function dump(\Symfony\Component\Translation\MessageCatalogue $messages, $options = array());
}


?>
