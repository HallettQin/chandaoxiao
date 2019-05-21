<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class CsvFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar', 'bar' => "foo\nfoo", 'foo;foo' => 'bar'));
		$dumper = new \Symfony\Component\Translation\Dumper\CsvFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/valid.csv', $dumper->formatCatalogue($catalogue, 'messages'));
	}
}

?>
