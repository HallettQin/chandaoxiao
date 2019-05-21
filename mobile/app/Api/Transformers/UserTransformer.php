<?php
//cgxlm
namespace App\Api\Transformers;

class UserTransformer extends \League\Fractal\TransformerAbstract
{
	public function transform(\App\Models\User $user)
	{
		return array('id' => $user->user_id, 'name' => $user->user_name);
	}
}

?>
