<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class JsonFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new \Symfony\Component\Translation\Dumper\JsonFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources.json', $dumper->formatCatalogue($catalogue, 'messages'));
	}

	public function testDumpWithCustomEncoding()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => '"bar"'));
		$dumper = new \Symfony\Component\Translation\Dumper\JsonFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources.dump.json', $dumper->formatCatalogue($catalogue, 'messages', array('json_encoding' => JSON_HEX_QUOT)));
	}
}

?>
