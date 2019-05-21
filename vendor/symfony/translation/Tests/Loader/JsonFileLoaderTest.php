<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class JsonFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\JsonFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.json';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadDoesNothingIfEmpty()
	{
		$loader = new \Symfony\Component\Translation\Loader\JsonFileLoader();
		$resource = __DIR__ . '/../fixtures/empty.json';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array(), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\JsonFileLoader();
		$resource = __DIR__ . '/../fixtures/non-existing.json';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testParseException()
	{
		$loader = new \Symfony\Component\Translation\Loader\JsonFileLoader();
		$resource = __DIR__ . '/../fixtures/malformed.json';
		$loader->load($resource, 'en', 'domain1');
	}
}

?>
