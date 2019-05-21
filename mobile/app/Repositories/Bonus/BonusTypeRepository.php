<?php
//cgxlm
namespace App\Repositories\Bonus;

class BonusTypeRepository implements \App\Contracts\Repositories\Bonus\BonusTypeRepositoryInterface
{
	public function bonusInfo($bonus_id, $bonus_sn = '')
	{
		return self::join('user_bonus', 'bonus_type.type_id', '=', 'user_bonus.bonus_type_id')->where('bonus_id', $bonus_id)->first();
	}
}

?>
