<?php
//cgxlm
namespace Symfony\Component\Translation\Tests;

class StringClass
{
	protected $str;

	public function __construct($str)
	{
		$this->str = $str;
	}

	public function __toString()
	{
		return $this->str;
	}
}

class TranslatorTest extends \PHPUnit\Framework\TestCase
{
	public function testConstructorInvalidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator($locale, new \Symfony\Component\Translation\MessageSelector());
	}

	public function testConstructorValidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator($locale, new \Symfony\Component\Translation\MessageSelector());
		$this->assertEquals($locale, $translator->getLocale());
	}

	public function testConstructorWithoutLocale()
	{
		$translator = new \Symfony\Component\Translation\Translator(null, new \Symfony\Component\Translation\MessageSelector());
		$this->assertNull($translator->getLocale());
	}

	public function testSetGetLocale()
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$this->assertEquals('en', $translator->getLocale());
		$translator->setLocale('fr');
		$this->assertEquals('fr', $translator->getLocale());
	}

	public function testSetInvalidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator('fr', new \Symfony\Component\Translation\MessageSelector());
		$translator->setLocale($locale);
	}

	public function testSetValidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator($locale, new \Symfony\Component\Translation\MessageSelector());
		$translator->setLocale($locale);
		$this->assertEquals($locale, $translator->getLocale());
	}

	public function testGetCatalogue()
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$this->assertEquals(new \Symfony\Component\Translation\MessageCatalogue('en'), $translator->getCatalogue());
		$translator->setLocale('fr');
		$this->assertEquals(new \Symfony\Component\Translation\MessageCatalogue('fr'), $translator->getCatalogue('fr'));
	}

	public function testGetCatalogueReturnsConsolidatedCatalogue()
	{
		$locale = 'whatever';
		$translator = new \Symfony\Component\Translation\Translator($locale);
		$translator->addLoader('loader-a', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addLoader('loader-b', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('loader-a', array('foo' => 'foofoo'), $locale, 'domain-a');
		$translator->addResource('loader-b', array('bar' => 'foobar'), $locale, 'domain-b');
		$catalogue = $translator->getCatalogue($locale);
		$this->assertTrue($catalogue->defines('foo', 'domain-a'));
		$this->assertTrue($catalogue->defines('bar', 'domain-b'));
	}

	public function testSetFallbackLocales()
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foofoo'), 'en');
		$translator->addResource('array', array('bar' => 'foobar'), 'fr');
		$translator->trans('bar');
		$translator->setFallbackLocales(array('fr'));
		$this->assertEquals('foobar', $translator->trans('bar'));
	}

	public function testSetFallbackLocalesMultiple()
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foo (en)'), 'en');
		$translator->addResource('array', array('bar' => 'bar (fr)'), 'fr');
		$translator->trans('bar');
		$translator->setFallbackLocales(array('fr_FR', 'fr'));
		$this->assertEquals('bar (fr)', $translator->trans('bar'));
	}

	public function testSetFallbackInvalidLocales($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator('fr', new \Symfony\Component\Translation\MessageSelector());
		$translator->setFallbackLocales(array('fr', $locale));
	}

	public function testSetFallbackValidLocales($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator($locale, new \Symfony\Component\Translation\MessageSelector());
		$translator->setFallbackLocales(array('fr', $locale));
		$this->addToAssertionCount(1);
	}

	public function testTransWithFallbackLocale()
	{
		$translator = new \Symfony\Component\Translation\Translator('fr_FR');
		$translator->setFallbackLocales(array('en'));
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('bar' => 'foobar'), 'en');
		$this->assertEquals('foobar', $translator->trans('bar'));
	}

	public function testAddResourceInvalidLocales($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator('fr', new \Symfony\Component\Translation\MessageSelector());
		$translator->addResource('array', array('foo' => 'foofoo'), $locale);
	}

	public function testAddResourceValidLocales($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator('fr', new \Symfony\Component\Translation\MessageSelector());
		$translator->addResource('array', array('foo' => 'foofoo'), $locale);
		$this->addToAssertionCount(1);
	}

	public function testAddResourceAfterTrans()
	{
		$translator = new \Symfony\Component\Translation\Translator('fr');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->setFallbackLocales(array('en'));
		$translator->addResource('array', array('foo' => 'foofoo'), 'en');
		$this->assertEquals('foofoo', $translator->trans('foo'));
		$translator->addResource('array', array('bar' => 'foobar'), 'en');
		$this->assertEquals('foobar', $translator->trans('bar'));
	}

	public function testTransWithoutFallbackLocaleFile($format, $loader)
	{
		$loaderClass = 'Symfony\\Component\\Translation\\Loader\\' . $loader;
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addLoader($format, new $loaderClass());
		$translator->addResource($format, __DIR__ . '/fixtures/non-existing', 'en');
		$translator->addResource($format, __DIR__ . '/fixtures/resources.' . $format, 'en');
		$translator->trans('foo');
	}

	public function testTransWithFallbackLocaleFile($format, $loader)
	{
		$loaderClass = 'Symfony\\Component\\Translation\\Loader\\' . $loader;
		$translator = new \Symfony\Component\Translation\Translator('en_GB');
		$translator->addLoader($format, new $loaderClass());
		$translator->addResource($format, __DIR__ . '/fixtures/non-existing', 'en_GB');
		$translator->addResource($format, __DIR__ . '/fixtures/resources.' . $format, 'en', 'resources');
		$this->assertEquals('bar', $translator->trans('foo', array(), 'resources'));
	}

	public function testTransWithFallbackLocaleBis()
	{
		$translator = new \Symfony\Component\Translation\Translator('en_US');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foofoo'), 'en_US');
		$translator->addResource('array', array('bar' => 'foobar'), 'en');
		$this->assertEquals('foobar', $translator->trans('bar'));
	}

	public function testTransWithFallbackLocaleTer()
	{
		$translator = new \Symfony\Component\Translation\Translator('fr_FR');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foo (en_US)'), 'en_US');
		$translator->addResource('array', array('bar' => 'bar (en)'), 'en');
		$translator->setFallbackLocales(array('en_US', 'en'));
		$this->assertEquals('foo (en_US)', $translator->trans('foo'));
		$this->assertEquals('bar (en)', $translator->trans('bar'));
	}

	public function testTransNonExistentWithFallback()
	{
		$translator = new \Symfony\Component\Translation\Translator('fr');
		$translator->setFallbackLocales(array('en'));
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$this->assertEquals('non-existent', $translator->trans('non-existent'));
	}

	public function testWhenAResourceHasNoRegisteredLoader()
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addResource('array', array('foo' => 'foofoo'), 'en');
		$translator->trans('foo');
	}

	public function testNestedFallbackCatalogueWhenUsingMultipleLocales()
	{
		$translator = new \Symfony\Component\Translation\Translator('fr');
		$translator->setFallbackLocales(array('ru', 'en'));
		$translator->getCatalogue('fr');
		$this->assertNotNull($translator->getCatalogue('ru')->getFallbackCatalogue());
	}

	public function testFallbackCatalogueResources()
	{
		$translator = new \Symfony\Component\Translation\Translator('en_GB', new \Symfony\Component\Translation\MessageSelector());
		$translator->addLoader('yml', new \Symfony\Component\Translation\Loader\YamlFileLoader());
		$translator->addResource('yml', __DIR__ . '/fixtures/empty.yml', 'en_GB');
		$translator->addResource('yml', __DIR__ . '/fixtures/resources.yml', 'en');
		$this->assertEquals('bar', $translator->trans('foo', array()));
		$resources = $translator->getCatalogue('en')->getResources();
		$this->assertCount(1, $resources);
		$this->assertContains(__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'resources.yml', $resources);
		$resources = $translator->getCatalogue('en_GB')->getResources();
		$this->assertCount(2, $resources);
		$this->assertContains(__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'empty.yml', $resources);
		$this->assertContains(__DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'resources.yml', $resources);
	}

	public function testTrans($expected, $id, $translation, $parameters, $locale, $domain)
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array((string) $id => $translation), $locale, $domain);
		$this->assertEquals($expected, $translator->trans($id, $parameters, $domain, $locale));
	}

	public function testTransInvalidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator('en', new \Symfony\Component\Translation\MessageSelector());
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foofoo'), 'en');
		$translator->trans('foo', array(), '', $locale);
	}

	public function testTransValidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator($locale, new \Symfony\Component\Translation\MessageSelector());
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('test' => 'OK'), $locale);
		$this->assertEquals('OK', $translator->trans('test'));
		$this->assertEquals('OK', $translator->trans('test', array(), null, $locale));
	}

	public function testFlattenedTrans($expected, $messages, $id)
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', $messages, 'fr', '');
		$this->assertEquals($expected, $translator->trans($id, array(), '', 'fr'));
	}

	public function testTransChoice($expected, $id, $translation, $number, $parameters, $locale, $domain)
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array((string) $id => $translation), $locale, $domain);
		$this->assertEquals($expected, $translator->transChoice($id, $number, $parameters, $domain, $locale));
	}

	public function testTransChoiceInvalidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator('en', new \Symfony\Component\Translation\MessageSelector());
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foofoo'), 'en');
		$translator->transChoice('foo', 1, array(), '', $locale);
	}

	public function testTransChoiceValidLocale($locale)
	{
		$translator = new \Symfony\Component\Translation\Translator('en', new \Symfony\Component\Translation\MessageSelector());
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foofoo'), 'en');
		$translator->transChoice('foo', 1, array(), '', $locale);
		$this->addToAssertionCount(1);
	}

	public function getTransFileTests()
	{
		return array(
	array('csv', 'CsvFileLoader'),
	array('ini', 'IniFileLoader'),
	array('mo', 'MoFileLoader'),
	array('po', 'PoFileLoader'),
	array('php', 'PhpFileLoader'),
	array('ts', 'QtFileLoader'),
	array('xlf', 'XliffFileLoader'),
	array('yml', 'YamlFileLoader'),
	array('json', 'JsonFileLoader')
	);
	}

	public function getTransTests()
	{
		return array(
	array(
		'Symfony est super !',
		'Symfony is great!',
		'Symfony est super !',
		array(),
		'fr',
		''
		),
	array(
		'Symfony est awesome !',
		'Symfony is %what%!',
		'Symfony est %what% !',
		array('%what%' => 'awesome'),
		'fr',
		''
		),
	array(
		'Symfony est super !',
		new StringClass('Symfony is great!'),
		'Symfony est super !',
		array(),
		'fr',
		''
		)
	);
	}

	public function getFlattenedTransTests()
	{
		$messages = array(
			'symfony' => array(
				'is' => array('great' => 'Symfony est super!')
				),
			'foo'     => array(
				'bar' => array('baz' => 'Foo Bar Baz'),
				'baz' => 'Foo Baz'
				)
			);
		return array(
	array('Symfony est super!', $messages, 'symfony.is.great'),
	array('Foo Bar Baz', $messages, 'foo.bar.baz'),
	array('Foo Baz', $messages, 'foo.baz')
	);
	}

	public function getTransChoiceTests()
	{
		return array(
	array(
		'Il y a 0 pomme',
		'{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples',
		'[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes',
		0,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 1 pomme',
		'{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples',
		'[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes',
		1,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 10 pommes',
		'{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples',
		'[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes',
		10,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 0 pomme',
		'There is one apple|There is %count% apples',
		'Il y a %count% pomme|Il y a %count% pommes',
		0,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 1 pomme',
		'There is one apple|There is %count% apples',
		'Il y a %count% pomme|Il y a %count% pommes',
		1,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 10 pommes',
		'There is one apple|There is %count% apples',
		'Il y a %count% pomme|Il y a %count% pommes',
		10,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 0 pomme',
		'one: There is one apple|more: There is %count% apples',
		'one: Il y a %count% pomme|more: Il y a %count% pommes',
		0,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 1 pomme',
		'one: There is one apple|more: There is %count% apples',
		'one: Il y a %count% pomme|more: Il y a %count% pommes',
		1,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 10 pommes',
		'one: There is one apple|more: There is %count% apples',
		'one: Il y a %count% pomme|more: Il y a %count% pommes',
		10,
		array(),
		'fr',
		''
		),
	array(
		'Il n\'y a aucune pomme',
		'{0} There are no apples|one: There is one apple|more: There is %count% apples',
		'{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes',
		0,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 1 pomme',
		'{0} There are no apples|one: There is one apple|more: There is %count% apples',
		'{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes',
		1,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 10 pommes',
		'{0} There are no apples|one: There is one apple|more: There is %count% apples',
		'{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes',
		10,
		array(),
		'fr',
		''
		),
	array(
		'Il y a 0 pomme',
		new StringClass('{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples'),
		'[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes',
		0,
		array(),
		'fr',
		''
		),
	array(
		'Il y a quelques pommes',
		'one: There is one apple|more: There are %count% apples',
		'one: Il y a %count% pomme|more: Il y a %count% pommes',
		2,
		array('%count%' => 'quelques'),
		'fr',
		''
		)
	);
	}

	public function getInvalidLocalesTests()
	{
		return array(
	array('fr FR'),
	array('fran