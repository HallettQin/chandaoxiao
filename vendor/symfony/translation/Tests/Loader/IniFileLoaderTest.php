<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class IniFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\IniFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.ini';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadDoesNothingIfEmpty()
	{
		$loader = new \Symfony\Component\Translation\Loader\IniFileLoader();
		$resource = __DIR__ . '/../fixtures/empty.ini';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array(), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\IniFileLoader();
		$resource = __DIR__ . '/../fixtures/non-existing.ini';
		$loader->load($resource, 'en', 'domain1');
	}
}

?>
