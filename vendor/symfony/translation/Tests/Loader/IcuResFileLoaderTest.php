<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class IcuResFileLoaderTest extends LocalizedTestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\IcuResFileLoader();
		$resource = __DIR__ . '/../fixtures/resourcebundle/res';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\DirectoryResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\IcuResFileLoader();
		$loader->load(__DIR__ . '/../fixtures/non-existing.txt', 'en', 'domain1');
	}

	public function testLoadInvalidResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\IcuResFileLoader();
		$loader->load(__DIR__ . '/../fixtures/resourcebundle/corrupted', 'en', 'domain1');
	}
}

?>
