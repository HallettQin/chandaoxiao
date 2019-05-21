<?php
//cgxlm
namespace app\transformer;

abstract class Transformer implements \app\contracts\transformer\TransformerInterface
{
	public function transformCollection(array $map)
	{
		return array_map(array($this, 'transform'), $map);
	}

	abstract public function transform(array $map);
}

?>
