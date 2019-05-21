<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class YamlFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.yml';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadDoesNothingIfEmpty()
	{
		$loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
		$resource = __DIR__ . '/../fixtures/empty.yml';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array(), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
		$resource = __DIR__ . '/../fixtures/non-existing.yml';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadThrowsAnExceptionIfFileNotLocal()
	{
		$loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
		$resource = 'http://example.com/resources.yml';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadThrowsAnExceptionIfNotAnArray()
	{
		$loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
		$resource = __DIR__ . '/../fixtures/non-valid.yml';
		$loader->load($resource, 'en', 'domain1');
	}
}

?>
