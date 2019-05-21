<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class JsonEncodingException extends \RuntimeException
{
	static public function forModel($model, $message)
	{
		return new static('Error encoding model [' . get_class($model) . '] with ID [' . $model->getKey() . '] to JSON: ' . $message);
	}

	static public function forAttribute($model, $key, $message)
	{
		$class = get_class($model);
		return new static('Unable to encode attribute [' . $key . '] for model [' . $class . '] to JSON: ' . $message . '.');
	}
}

?>
