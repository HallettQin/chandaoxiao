<?php
//cgxlm
namespace Illuminate\Contracts\Auth\Access;

interface Gate
{
	public function has($ability);

	public function define($ability, $callback);

	public function policy($class, $policy);

	public function before( $callback);

	public function after( $callback);

	public function allows($ability, $arguments = array());

	public function denies($ability, $arguments = array());

	public function check($ability, $arguments = array());

	public function authorize($ability, $arguments = array());

	public function getPolicyFor($class);

	public function forUser($user);
}


?>
