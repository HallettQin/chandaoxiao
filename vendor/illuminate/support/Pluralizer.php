<?php
//cgxlm
namespace Illuminate\Support;

class Pluralizer
{
	/**
     * Uncountable word forms.
     *
     * @var array
     */
	static public $uncountable = array('audio', 'bison', 'chassis', 'compensation', 'coreopsis', 'data', 'deer', 'education', 'emoji', 'equipment', 'evidence', 'feedback', 'fish', 'furniture', 'gold', 'information', 'jedi', 'knowledge', 'love', 'metadata', 'money', 'moose', 'news', 'nutrition', 'offspring', 'plankton', 'pokemon', 'police', 'rain', 'rice', 'series', 'sheep', 'species', 'swine', 'traffic', 'wheat');

	static public function plural($value, $count = 2)
	{
		if (((int) $count === 1) || static::uncountable($value)) {
			return $value;
		}

		$plural = \Doctrine\Common\Inflector\Inflector::pluralize($value);
		return static::matchCase($plural, $value);
	}

	static public function singular($value)
	{
		$singular = \Doctrine\Common\Inflector\Inflector::singularize($value);
		return static::matchCase($singular, $value);
	}

	static protected function uncountable($value)
	{
		return in_array(strtolower($value), static::$uncountable);
	}

	static protected function matchCase($value, $comparison)
	{
		$functions = array('mb_strtolower', 'mb_strtoupper', 'ucfirst', 'ucwords');

		foreach ($functions as $function) {
			if (call_user_func($function, $comparison) === $comparison) {
				return call_user_func($function, $value);
			}
		}

		return $value;
	}
}


?>
