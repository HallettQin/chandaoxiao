<?php
//cgxlm
namespace Illuminate\Contracts\Auth;

interface SupportsBasicAuth
{
	public function basic($field = 'email', $extraConditions = array());

	public function onceBasic($field = 'email', $extraConditions = array());
}


?>
