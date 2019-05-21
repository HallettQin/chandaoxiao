<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class IcuResFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new \Symfony\Component\Translation\Dumper\IcuResFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resourcebundle/res/en.res', $dumper->formatCatalogue($catalogue, 'messages'));
	}
}

?>
