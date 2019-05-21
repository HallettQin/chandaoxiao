<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Catalogue;

class MergeOperationTest extends AbstractOperationTest
{
	public function testGetMessagesFromSingleDomain()
	{
		$operation = $this->createOperation(new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'old_a', 'b' => 'old_b')
	)), new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'new_a', 'c' => 'new_c')
	)));
		$this->assertEquals(array('a' => 'old_a', 'b' => 'old_b', 'c' => 'new_c'), $operation->getMessages('messages'));
		$this->assertEquals(array('c' => 'new_c'), $operation->getNewMessages('messages'));
		$this->assertEquals(array(), $operation->getObsoleteMessages('messages'));
	}

	public function testGetResultFromSingleDomain()
	{
		$this->assertEquals(new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'old_a', 'b' => 'old_b', 'c' => 'new_c')
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
		$mergedCatalogue = new \Symfony\Component\Translation\MessageCatalogue('en', array(
	'messages' => array('a' => 'old_a', 'b' => 'old_b', 'c' => 'new_c')
	));
		$mergedCatalogue->setMetadata('a', 'foo', 'messages');
		$mergedCatalogue->setMetadata('b', 'bar', 'messages');
		$mergedCatalogue->setMetadata('c', 'qux', 'messages');
		$this->assertEquals($mergedCatalogue, $this->createOperation($leftCatalogue, $rightCatalogue)->getResult());
	}

	protected function createOperation(\Symfony\Component\Translation\MessageCatalogueInterface $source, \Symfony\Component\Translation\MessageCatalogueInterface $target)
	{
		return new \Symfony\Component\Translation\Catalogue\MergeOperation($source, $target);
	}
}

?>
