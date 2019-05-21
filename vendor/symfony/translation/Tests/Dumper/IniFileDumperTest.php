<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class IniFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new \Symfony\Component\Translation\Dumper\IniFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources.ini', $dumper->formatCatalogue($catalogue, 'messages'));
	}
}

?>
