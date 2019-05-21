<?php
//cgxlm
namespace Symfony\Component\Translation\Tests\Loader;

abstract class LocalizedTestCase extends \PHPUnit\Framework\TestCase
{
	protected function setUp()
	{
		if (!extension_loaded('intl')) {
			$this->markTestSkipped('Extension intl is required.');
		}
	}
}

?>
