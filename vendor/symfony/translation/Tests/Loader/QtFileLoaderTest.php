<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class QtFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\QtFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.ts';
		$catalogue = $loader->load($resource, 'en', 'resources');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('resources'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\QtFileLoader();
		$resource = __DIR__ . '/../fixtures/non-existing.ts';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadNonLocalResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\QtFileLoader();
		$resource = 'http://domain1.com/resources.ts';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadInvalidResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\QtFileLoader();
		$resource = __DIR__ . '/../fixtures/invalid-xml-resources.xlf';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadEmptyResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\QtFileLoader();
		$resource = __DIR__ . '/../fixtures/empty.xlf';

		if (method_exists($this, 'expectException')) {
			$this->expectException('Symfony\\Component\\Translation\\Exception\\InvalidResourceException');
			$this->expectExceptionMessage(sprintf('Unable to load "%s".', $resource));
		}
		else {
			$this->setExpectedException('Symfony\\Component\\Translation\\Exception\\InvalidResourceException', sprintf('Unable to load "%s".', $resource));
		}

		$loader->load($resource, 'en', 'domain1');
	}
}

?>
