<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Catalogue;

class TargetOperationTest extends AbstractOperationTest
{
	public function testGetMessagesFromSingleDomain()
	{
		$operation = $this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'old_a', 'b' => 'old_b')
	)), new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'new_a', 'c' => 'new_c')
	)));
		$this->assertEquals(array('a' => 'old_a', 'c' => 'new_c'), $operation->getMessages('messages'));
		$this->assertEquals(array('c' => 'new_c'), $operation->getNewMessages('messages'));
		$this->assertEquals(array('b' => 'old_b'), $operation->getObsoleteMessages('messages'));
	}

	public function testGetResultFromSingleDomain()
	{
		$this->assertEquals(new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'old_a', 'c' => 'new_c')
	)), $this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'old_a', 'b' => 'old_b')
	)), new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'new_a', 'c' => 'new_c')
	)))->getResult());
	}

	public function testGetResultWithMetadata()
	{
		$leftCatalogue = new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'old_a', 'b' => 'old_b')
	));
		$leftCatalogue->setMetadata('a', 'foo', 'messages');
		$leftCatalogue->setMetadata('b', 'bar', 'messages');
		$rightCatalogue = new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('b' => 'new_b', 'c' => 'new_c')
	));
		$rightCatalogue->setMetadata('b', 'baz', 'messages');
		$rightCatalogue->setMetadata('c', 'qux', 'messages');
		$diffCatalogue = new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('b' => 'old_b', 'c' => 'new_c')
	));
		$diffCatalogue->setMetadata('b', 'bar', 'messages');
		$diffCatalogue->setMetadata('c', 'qux', 'messages');
		$this->assertEquals($diffCatalogue, $this->createOperation($leftCatalogue, $rightCatalogue)->getResult());
	}

	protected function createOperation(\Symfony\Component\Translation\MessageCatalogueInterface $source, \Symfony\Component\Translation\MessageCatalogueInterface $target)
	{
		return new \Symfony\Component\Translation\Catalogue\TargetOperation($source, $target);
	}
}

?>
