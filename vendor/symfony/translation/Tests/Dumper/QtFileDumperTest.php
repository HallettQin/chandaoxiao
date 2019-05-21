<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class QtFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'), 'resources');
		$dumper = new \Symfony\Component\Translation\Dumper\QtFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources.ts', $dumper->formatCatalogue($catalogue, 'resources'));
	}
}

?>
