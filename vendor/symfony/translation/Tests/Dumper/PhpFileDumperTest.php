<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class PhpFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new \Symfony\Component\Translation\Dumper\PhpFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/resources.php', $dumper->formatCatalogue($catalogue, 'messages'));
	}
}

?>
