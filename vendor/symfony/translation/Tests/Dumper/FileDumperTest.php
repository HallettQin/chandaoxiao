<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Dumper;

class FileDumperTest extends \PHPUnit\Framework\TestCase
{
	public function testDump()
	{
		$tempDir = sys_get_temp_dir();
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new ConcreteFileDumper();
		$dumper->dump($catalogue, array('path' => $tempDir));
		$this->assertFileExists($tempDir . '/messages.en.concrete');
	}

	public function testDumpBackupsFileIfExisting()
	{
		$tempDir = sys_get_temp_dir();
		$file = $tempDir . '/messages.en.concrete';
		$backupFile = $file . '~';
		@touch($file);
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new ConcreteFileDumper();
		$dumper->dump($catalogue, array('path' => $tempDir));
		$this->assertFileExists($backupFile);
		@unlink($file);
		@unlink($backupFile);
	}

	public function testDumpCreatesNestedDirectoriesAndFile()
	{
		$tempDir = sys_get_temp_dir();
		$translationsDir = $tempDir . '/test/translations';
		$file = $translationsDir . '/messages.en.concrete';
		$catalogue = new \Symfony\Component\Translation\MessageCatalogue('en');
		$catalogue->add(array('foo' => 'bar'));
		$dumper = new ConcreteFileDumper();
		$dumper->setRelativePathTemplate('test/translations/%domain%.%locale%.%extension%');
		$dumper->dump($catalogue, array('path' => $tempDir));
		$this->assertFileExists($file);
		@unlink($file);
		@rmdir($translationsDir);
	}
}
class ConcreteFileDumper extends \Symfony\Component\Translation\Dumper\FileDumper
{
	public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array())
	{
		return '';
	}

	protected function getExtension()
	{
		return 'concrete';
	}
}

?>
