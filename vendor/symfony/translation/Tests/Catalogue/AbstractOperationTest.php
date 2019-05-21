<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Catalogue;

abstract class AbstractOperationTest extends \PHPUnit\Framework\TestCase
{
	public function testGetEmptyDomains()
	{
		$this->assertEquals(array(), $this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en'), new \Symfony\Component\Translation\MessageCatalogue('en'))->getDomains());
	}

	public function testGetMergedDomains()
	{
		$this->assertEquals(array('a', 'b', 'c'), $this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'a' => array(),
	'b' => array()
	)), new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'b' => array(),
	'c' => array()
	)))->getDomains());
	}

	public function testGetMessagesFromUnknownDomain()
	{
		$this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
		$this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en'), new \Symfony\Component\Translation\MessageCatalogue('en'))->getMessages('domain');
	}

	public function testGetEmptyMessages()
	{
		$this->assertEquals(array(), $this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'a' => array()
	)), new \Symfony\Component\Translation\MessageCatalogue('en'))->getMessages('a'));
	}

	public function testGetEmptyResult()
	{
		$this->assertEquals(new \Symfony\Component\Translation\MessageCatalogue('en'), $this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en'), new \Symfony\Component\Translation\MessageCatalogue('en'))->getResult());
	}

	abstract protected function createOperation(\Symfony\Component\Translation\MessageCatalogueInterface $source, \Symfony\Component\Translation\MessageCatalogueInterface $target);
}

?>
