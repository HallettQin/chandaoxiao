<?php
//cgxlm
namespace Illuminate\Contracts\Validation;

interface Factory
{
	public function make(array $data, array $rules, array $messages = array(), array $customAttributes = array());

	public function extend($rule, $extension, $message = NULL);

	public function extendImplicit($rule, $extension, $message = NULL);

	public function replacer($rule, $replacer);
}


?>
