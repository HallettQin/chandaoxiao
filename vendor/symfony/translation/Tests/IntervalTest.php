<?php
//cgxlm
namespace Symfony\Component\Translation\Tests;

class IntervalTest extends \PHPUnit\Framework\TestCase
{
	public function testTest($expected, $number, $interval)
	{
		$this->assertEquals($expected, \Symfony\Component\Translation\Interval::test($number, $interval));
	}

	public function testTestException()
	{
		\Symfony\Component\Translation\Interval::test(1, 'foobar');
	}

	public function getTests()
	{
		return array(
	array(true, 3, '{1,2, 3 ,4}'),
	array(false, 10, '{1,2, 3 ,4}'),
	array(false, 3, '[1,2]'),
	array(true, 1, '[1,2]'),
	array(true, 2, '[1,2]'),
	array(false, 1, ']1,2['),
	array(false, 2, ']1,2['),
	array(true, log(0), '[-Inf,2['),
	array(true, 0 - log(0), '[-2,+Inf]')
	);
	}
}

?>
