<?php
//cgxlm
namespace app\models;

class Users extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'users';
	protected $primaryKey = 'user_id';
	public $timestamps = false;
	protected $fillable = array('aite_id', 'email', 'user_name', 'nick_name', 'password', 'question', 'answer', 'sex', 'birthday', 'user_money', 'frozen_money', 'pay_points', 'rank_points', 'address_id', 'reg_time', 'last_login', 'last_time', 'last_ip', 'visit_count', 'user_rank', 'is_special', 'ec_salt', 'salt', 'parent_id', 'flag', 'alias', 'msn', 'qq', 'office_phone', 'home_phone', 'mobile_phone', 'is_validated', 'credit_line', 'passwd_question', 'passwd_answer', 'user_picture', 'old_user_picture');
	protected $guarded = array();

	public function getUserPictureAttribute()
	{
		return empty($this->attributes['user_picture']) ? get_image_path('themes/cgxlm/images/avatar.png') : $this->attributes['user_picture'];
	}
}

?>
