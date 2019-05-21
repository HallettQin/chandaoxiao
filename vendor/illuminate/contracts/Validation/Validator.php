<?php
//cgxlm
namespace Illuminate\Contracts\Validation;

interface Validator extends \Illuminate\Contracts\Support\MessageProvider
{
	public function fails();

	public function failed();

	public function sometimes($attribute, $rules,  $callback);

	public function after($callback);
}

?>
