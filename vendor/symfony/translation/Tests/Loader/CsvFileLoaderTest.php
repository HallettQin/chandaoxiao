<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class CsvFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\CsvFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.csv';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadDoesNothingIfEmpty()
	{
		$loader = new \Symfony\Component\Translation\Loader\CsvFileLoader();
		$resource = __DIR__ . '/../fixtures/empty.csv';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array(), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\CsvFileLoader();
		$resource = __DIR__ . '/../fixtures/not-exists.csv';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadNonLocalResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\CsvFileLoader();
		$resource = 'http://example.com/resources.csv';
		$loader->load($resource, 'en', 'domain1');
	}
}

?>
