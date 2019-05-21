<?php
//cgxlm
namespace Symfony\Component\Translation\Tests;

class DataCollectorTranslatorTest extends \PHPUnit\Framework\TestCase
{
	public function testCollectMessages()
	{
		$collector = $this->createCollector();
		$collector->setFallbackLocales(array('fr', 'ru'));
		$collector->trans('foo');
		$collector->trans('bar');
		$collector->transChoice('choice', 0);
		$collector->trans('bar_ru');
		$collector->trans('bar_ru', array('foo' => 'bar'));
		$expectedMessages = array();
		$expectedMessages[] = array(
	'id'                => 'foo',
	'translation'       => 'foo (en)',
	'locale'            => 'en',
	'domain'            => 'messages',
	'state'             => \Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_DEFINED,
	'parameters'        => array(),
	'transChoiceNumber' => null
	);
		$expectedMessages[] = array(
	'id'                => 'bar',
	'translation'       => 'bar (fr)',
	'locale'            => 'fr',
	'domain'            => 'messages',
	'state'             => \Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
	'parameters'        => array(),
	'transChoiceNumber' => null
	);
		$expectedMessages[] = array(
	'id'                => 'choice',
	'translation'       => 'choice',
	'locale'            => 'en',
	'domain'            => 'messages',
	'state'             => \Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_MISSING,
	'parameters'        => array(),
	'transChoiceNumber' => 0
	);
		$expectedMessages[] = array(
	'id'                => 'bar_ru',
	'translation'       => 'bar (ru)',
	'locale'            => 'ru',
	'domain'            => 'messages',
	'state'             => \Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
	'parameters'        => array(),
	'transChoiceNumber' => null
	);
		$expectedMessages[] = array(
	'id'                => 'bar_ru',
	'translation'       => 'bar (ru)',
	'locale'            => 'ru',
	'domain'            => 'messages',
	'state'             => \Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
	'parameters'        => array('foo' => 'bar'),
	'transChoiceNumber' => null
	);
		$this->assertEquals($expectedMessages, $collector->getCollectedMessages());
	}

	private function createCollector()
	{
		$translator = new \Symfony\Component\Translation\Translator('en');
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('foo' => 'foo (en)'), 'en');
		$translator->addResource('array', array('bar' => 'bar (fr)'), 'fr');
		$translator->addResource('array', array('bar_ru' => 'bar (ru)'), 'ru');
		return new \Symfony\Component\Translation\DataCollectorTranslator($translator);
	}
}

?>
