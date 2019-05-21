<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Writer;

class TranslationWriterTest extends \PHPUnit\Framework\TestCase
{
	public function testWriteTranslations()
	{
		$dumper = $this->getMockBuilder('Symfony\\Component\\Translation\\Dumper\\DumperInterface')->getMock();
		$dumper->expects($this->once())->method('dump');
		$writer = new \Symfony\Component\Translation\Writer\TranslationWriter();
		$writer->addDumper('test', $dumper);
		$writer->writeTranslations(new \Symfony\Component\Translation\MessageCatalogue(array()), 'test');
	}

	public function testDisableBackup()
	{
		$nonBackupDumper = new NonBackupDumper();
		$backupDumper = new BackupDumper();
		$writer = new \Symfony\Component\Translation\Writer\TranslationWriter();
		$writer->addDumper('non_backup', $nonBackupDumper);
		$writer->addDumper('backup', $backupDumper);
		$writer->disableBackup();
		$this->assertFalse($backupDumper->backup, 'backup can be disabled if setBackup() method does exist');
	}
}
class NonBackupDumper implements \Symfony\Component\Translation\Dumper\DumperInterface
{
	public function dump(\Symfony\Component\Translation\MessageCatalogue $messages, $options = array())
	{
	}
}
class BackupDumper implements \Symfony\Component\Translation\Dumper\DumperInterface
{
	public $backup = true;

	public function dump(\Symfony\Component\Translation\MessageCatalogue $messages, $options = array())
	{
	}

	public function setBackup($backup)
	{
		$this->backup = $backup;
	}
}

?>
