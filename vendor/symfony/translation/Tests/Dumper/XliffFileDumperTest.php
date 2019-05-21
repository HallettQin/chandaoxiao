<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class XliffFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en_US');
		$catalogue->add(array('foo' => 'bar', 'key' => '', 'key.with.cdata' => '<source> & <target>'));
		$catalogue->setMetadata('foo', array(
	'notes' => array(
		array('priority' => 1, 'from' => 'bar', 'content' => 'baz')
		)
	));
		$catalogue->setMetadata('key', array(
	'notes' => array(
		array('content' => 'baz'),
		array('content' => 'qux')
		)
	));
		$dumper = new \Symfony\Component\Translation\Dumper\XliffFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources-clean.xlf', $dumper->formatCatalogue($catalogue, 'messages', array('default_locale' => 'fr_FR')));
	}

	public function testFormatCatalogueXliff2()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en_US');
		$catalogue->add(array('foo' => 'bar', 'key' => '', 'key.with.cdata' => '<source> & <target>'));
		$catalogue->setMetadata('key', array(
	'target-attributes' => array('order' => 1)
	));
		$dumper = new \Symfony\Component\Translation\Dumper\XliffFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources-2.0-clean.xlf', $dumper->formatCatalogue($catalogue, 'messages', array('default_locale' => 'fr_FR', 'xliff_version' => '2.0')));
	}

	public function testFormatCatalogueWithCustomToolInfo()
	{
		$options = array(
			'default_locale' => 'en_US',
			'tool_info'      => array('tool-id' => 'foo', 'tool-name' => 'foo', 'tool-version' => '0.0', 'tool-company' => 'Foo')
			);
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en_US');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new \Symfony\Component\Translation\Dumper\XliffFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources-tool-info.xlf', $dumper->formatCatalogue($catalogue, 'messages', $options));
	}

	public function testFormatCatalogueWithTargetAttributesMetadata()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en_US');
		$catalogue->add(array('foo' => 'bar'));
		$catalogue->setMetadata('foo', array(
	'target-attributes' => array('state' => 'needs-translation')
	));
		$dumper = new \Symfony\Component\Translation\Dumper\XliffFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources-target-attributes.xlf', $dumper->formatCatalogue($catalogue, 'messages', array('default_locale' => 'fr_FR')));
	}
}

?>
