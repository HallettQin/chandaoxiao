<?php
//cgxlm
namespace Symfony\Component\Translation\Catalogue;

interface OperationInterface
{
	public function getDomains();

	public function getMessages($domain);

	public function getNewMessages($domain);

	public function getObsoleteMessages($domain);

	public function getResult();
}


?>
