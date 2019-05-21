<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

class XliffFileLoaderTest extends \PHPUnit\Framework\TestCase
{
	public function testLoad()
	{
		$loader = new \Symfony\Component\Translation\Loader\XliffFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.xlf';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
		$this->assertSame(array(), libxml_get_errors());
		$this->assertContainsOnly('string', $catalogue->all('domain1'));
	}

	public function testLoadWithInternalErrorsEnabled()
	{
		$internalErrors = libxml_use_internal_errors(true);
		$this->assertSame(array(), libxml_get_errors());
		$loader = new \Symfony\Component\Translation\Loader\XliffFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.xlf';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
		$this->assertSame(array(), libxml_get_errors());
		libxml_clear_errors();
		libxml_use_internal_errors($internalErrors);
	}

	public function testLoadWithExternalEntitiesDisabled()
	{
		$disableEntities = libxml_disable_entity_loader(true);
		$loader = new \Symfony\Component\Translation\Loader\XliffFileLoader();
		$resource = __DIR__ . '/../fixtures/resources.xlf';
		$catalogue = $loader->load($resource, 'en', 'domain1');
		libxml_disable_entity_loader($disableEntities);
		$this->assertEquals('en', $catalogue->getLocale());
		$this->assertEquals(array(new \Symfony\Component\Config\Resource\FileResource($resource)), $catalogue->getResources());
	}

	public function testLoadWithResname()
	{
		$loader = new \Symfony\Component\Translation\Loader\XliffFileLoader();
		$catalogue = $loader->load(__DIR__ . '/../fixtures/resname.xlf', 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo'), $catalogue->all('domain1'));
	}

	public function testIncompleteResource()
	{
		$loader = new \Symfony\Component\Translation\Loader\XliffFileLoader();
		$catalogue = $loader->load(__DIR__ . '/../fixtures/resources.xlf', 'en', 'domain1');
		$this->assertEquals(array('foo' => 'bar', 'extra' => 'extra', 'key' => '', 'test' => 'with'), $catalogue->all('domain1'));
	}

	public function testEncoding()
	{
		$loader = new \Symfony\Component\Translation\Loader\XliffFileLoader();
		$catalogue = $loader->load(__DIR__ . '/../fixtures/encoding.xlf', 'en', 'domain1');
		$this->assertEquals(utf8_decode('f