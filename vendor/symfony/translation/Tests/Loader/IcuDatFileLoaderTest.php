<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class IcuDatFileLoaderTest extends LocalizedTestCase
{
	public function testLoadInvalidResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\IcuDatFileLoader();
		$loader->load(__DIR__ . '/../fixtures/resourcebundle/corrupted/resources', 'es', 'domain2');
	}

	public function testDatEnglishLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\IcuDatFileLoader();
		$resource = __DIR__ . '/../fixtures/resourcebundle/dat/resources';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('symfony' => 'Symfony 2 is great'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource . '.dat')), $catalogue->getResources());
	}

	public function testDatFrenchLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\IcuDatFileLoader();
		$resource = __DIR__ . '/../fixtures/resourcebundle/dat/resources';
		$catalogue = $loader->load($resource, 'fr', 'domain1');
		$this->assertEquals(array('symfony' => 'Symfony 2 est génial'), $catalogue->all('domain1'));
		$this->assertEquals('fr', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource . '.dat')), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\IcuDatFileLoader();
		$loader->load(__DIR__ . '/../fixtures/non-existing.txt', 'en', 'domain1');
	}
}

?>
