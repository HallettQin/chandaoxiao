<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class RelationNotFoundException extends \RuntimeException
{
	static public function make($model, $relation)
	{
		$class = get_class($model);
		return new static('Call to undefined relationship [' . $relation . '] on model [' . $class . '].');
	}
}

?>
