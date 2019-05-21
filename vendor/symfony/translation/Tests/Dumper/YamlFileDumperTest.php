<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class YamlFileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testTreeFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo.bar1' => 'value1', 'foo.bar2' => 'value2'));
		$dumper = new \Symfony\Component\Translation\Dumper\YamlFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/messages.yml', $dumper->formatCatalogue($catalogue, 'messages', array('as_tree' => true, 'inline' => 999)));
	}

	public function testLinearFormatCatalogue()
	{
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo.bar1' => 'value1', 'foo.bar2' => 'value2'));
		$dumper = new \Symfony\Component\Translation\Dumper\YamlFileDumper();
		$this->assertStringEqualsFile(__DIR__ . '/../fixtures/messages_linear.yml', $dumper->formatCatalogue($catalogue, 'messages'));
	}
}

?>
