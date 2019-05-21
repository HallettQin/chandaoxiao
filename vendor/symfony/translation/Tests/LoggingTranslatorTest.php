<?php
//cgxlm
namespace Symfony\Component\Translation\Tests;

class LoggingTranslatorTest extends \PHPUnit\Framework\TestCase
{
	public function testTransWithNoTranslationIsLogged()
	{
		$logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();
		$logger->expects($this->exactly(2))->method('warning')->with('Translation not found.');
		$translator = new \Symfony\Component\Translation\Translator('ar');
		$loggableTranslator = new \Symfony\Component\Translation\LoggingTranslator($translator, $logger);
		$loggableTranslator->transChoice('some_message2', 10, array('%count%' => 10));
		$loggableTranslator->trans('bar');
	}

	public function testTransChoiceFallbackIsLogged()
	{
		$logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();
		$logger->expects($this->once())->method('debug')->with('Translation use fallback catalogue.');
		$translator = new \Symfony\Component\Translation\Translator('ar');
		$translator->setFallbackLocales(array('en'));
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', array('some_message2' => 'one thing|%count% things'), 'en');
		$loggableTranslator = new \Symfony\Component\Translation\LoggingTranslator($translator, $logger);
		$loggableTranslator->transChoice('some_message2', 10, array('%count%' => 10));
	}
}

?>
