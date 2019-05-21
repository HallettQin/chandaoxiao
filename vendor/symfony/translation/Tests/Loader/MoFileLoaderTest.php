<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class MoFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\MoFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.mo';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadPlurals()
	{
		$loader = new \Symfony\Component\Translation\Loader\MoFileLoader();
		$resource = __DIR__ . '/../fixtures/plurals.mo';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar', 'foos' => '{0} bar|{1} bars'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\MoFileLoader();
		$resource = __DIR__ . '/../fixtures/non-existing.mo';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadInvalidResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\MoFileLoader();
		$resource = __DIR__ . '/../fixtures/empty.mo';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadEmptyTranslation()
	{
		$loader = new \Symfony\Component\Translation\Loader\MoFileLoader();
		$resource = __DIR__ . '/../fixtures/empty-translation.mo';
		$catalogue = $loader->load($resource, 'en', 'message');
		$this->assertEquals(array(), $catalogue->all('message'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}
}

?>
