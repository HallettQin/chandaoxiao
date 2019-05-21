<?php
//cgxlm
namespace App\Http\Respond\Controllers;

class Index extends \App\Http\Base\Controllers\Frontend
{
	private $data = array();

	public function __construct()
	{
		parent::__construct();
		$this->data = array('code' => I('get.code'));

		if (isset($_GET['code'])) {
			unset($_GET['code']);
		}
	}

	public function actionIndex()
	{
		$msg_type = 2;
		$payment = $this->getPayment();

		if ($payment === false) {
			$msg = L('pay_disabled');
		}
		else if ($payment->callback($this->data)) {
			$msg = L('pay_success');
			$msg_type = 0;
		}
		else {
			$msg = L('pay_fail');
			$msg_type = 1;
		}

		$this->assign('message', $msg);
		$this->assign('msg_type', $msg_type);
		$this->assign('page_title', L('pay_status'));
		$this->display();
	}

	public function actionNotify()
	{
		$payment = $this->getPayment();

		if ($payment === false) {
			exit('plugin load fail');
		}

		$payment->notify($this->data);
	}

	private function getPayment()
	{
		$condition = array('pay_code' => $this->data['code'], 'enabled' => 1);
		$enabled = $this->db->table('payment')->where($condition)->count();
		$plugin = ADDONS_PATH . 'payment/' . $this->data['code'] . '.php';
		if (!is_file($plugin) || ($enabled == 0)) {
			return false;
		}

		require_cache($plugin);
		$payment = new $this->data['code']();
		return $payment;
	}
}

?>
