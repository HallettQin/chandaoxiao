<?php
//cgxlm
namespace Symfony\Polyfill\Mbstring;

final class Mbstring
{
	const MB_CASE_FOLD = PHP_INT_MAX;

	static private $encodingList = array('ASCII', 'UTF-8');
	static private $language = 'neutral';
	static private $internalEncoding = 'UTF-8';
	static private $caseFold = array(
		array('