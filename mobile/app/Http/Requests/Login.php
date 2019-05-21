<?php
//cgxlm
namespace app\http\requests;

class Login extends \yii\base\Model
{
	public $username;
	public $password;
	public $rememberMe = true;
	private $_user;

	public function rules()
	{
		return array(
	array(
		array('username', 'password'),
		'required'
		),
	array('rememberMe', 'boolean'),
	array('password', 'validatePassword')
	);
	}

	public function validatePassword($attribute, $params)
	{
		if (!$this->hasErrors()) {
			$user = $this->getUser();
			if (!$user || !$user->validatePassword($this->password)) {
				$this->addError($attribute, 'Incorrect username or password.');
			}
		}
	}

	public function login()
	{
		if ($this->validate()) {
			return \Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
		}
		else {
			return false;
		}
	}

	protected function getUser()
	{
		if ($this->_user === null) {
			$this->_user = \app\models\Users::findByUsername($this->username);
		}

		return $this->_user;
	}
}

?>
