<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class PhpFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\PhpFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.php';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\PhpFileLoader();
		$resource = __DIR__ . '/../fixtures/non-existing.php';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadThrowsAnExceptionIfFileNotLocal()
	{
		$loader = new \Symfony\Component\Translation\Loader\PhpFileLoader();
		$resource = 'http://example.com/resources.php';
		$loader->load($resource, 'en', 'domain1');
	}
}

?>
