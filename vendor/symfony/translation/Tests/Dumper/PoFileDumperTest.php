<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class PoFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new \Symfony\Component\Translation\Dumper\PoFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources.po', $dumper->formatCatalogue($catalogue, 'messages'));
	}
}

?>
