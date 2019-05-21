<?php
//cgxlm
namespace Illuminate\Contracts\Hashing;

interface Hasher
{
	public function make($value, array $options = array());

	public function check($value, $hashedValue, array $options = array());

	public function needsRehash($hashedValue, array $options = array());
}


?>
