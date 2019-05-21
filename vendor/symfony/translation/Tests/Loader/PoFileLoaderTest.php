<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class PoFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.po';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadPlurals()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/plurals.po';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar', 'foos' => 'bar|bars'), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadDoesNothingIfEmpty()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/empty.po';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array(), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadNonExistingResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/non-existing.po';
		$loader->load($resource, 'en', 'domain1');
	}

	public function testLoadEmptyTranslation()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/empty-translation.po';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals(array('foo' => ''), $catalogue->all('domain1'));
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testEscapedId()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/escaped-id.po';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$messages = $catalogue->all('domain1');
		$this->assertArrayHasKey('escaped "foo"', $messages);
		$this->assertEquals('escaped "bar"', $messages['escaped "foo"']);
	}

	public function testEscapedIdPlurals()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/escaped-id-plurals.po';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$messages = $catalogue->all('domain1');
		$this->assertArrayHasKey('escaped "foo"', $messages);
		$this->assertArrayHasKey('escaped "foos"', $messages);
		$this->assertEquals('escaped "bar"', $messages['escaped "foo"']);
		$this->assertEquals('escaped "bar"|escaped "bars"', $messages['escaped "foos"']);
	}

	public function testSkipFuzzyTranslations()
	{
		$loader = new \Symfony\Component\Translation\Loader\PoFileLoader();
		$resource = __DIR__ . '/../fixtures/fuzzy-translations.po';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$messages = $catalogue->all('domain1');
		$this->assertArrayHasKey('foo1', $messages);
		$this->assertArrayNotHasKey('foo2', $messages);
		$this->assertArrayHasKey('foo3', $messages);
	}
}

?>
