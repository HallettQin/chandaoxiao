<?php
//cgxlm
namespace Payment\Query\Cmb;

class CmbChargeQuery extends \Payment\Common\Cmb\CmbBaseStrategy
{
	public function getBuildDataClass()
	{
		$this->config->getewayUrl = 'https://payment.ebank.cmbchina.com/NetPayment/BaseHttp.dll?QuerySingleOrder';

		if ($this->config->useSandbox) {
			$this->config->getewayUrl = 'http://121.15.180.66:801/NetPayment_dl/BaseHttp.dll?QuerySingleOrder';
		}

		return 'Payment\\Common\\Cmb\\Data\\Query\\ChargeQueryData';
	}

	protected function retData(array $ret)
	{
		$json = json_encode($ret, JSON_UNESCAPED_UNICODE);
		$postData = \Payment\Common\CmbConfig::REQ_FILED_NAME . '=' . $json;
		$ret = $this->sendReq($postData);

		if ($this->config->returnRaw) {
			$ret['channel'] = \Payment\Config::CMB_CHARGE;
			return $ret;
		}

		$retData = array(
			'is_success' => 'T',
			'response'   => array('amount' => $ret['orderAmount'], 'channel' => \Payment\Config::CMB_CHARGE, 'order_no' => $ret['orderNo'], 'trade_state' => $this->getTradeStatus($ret['orderStatus']), 'transaction_id' => $ret['bankSerialNo'], 'time_end' => date('Y-m-d H:i:s', strtotime($ret['settleDate'] . $ret['settleTime'])), 'fee' => $ret['fee'], 'time_start' => date('Y-m-d H:i:s', strtotime($ret['bankDate'] . $ret['bankTime'])), 'discount_fee' => $ret['discountAmount'], 'card_type' => $ret['cardType'], 'return_param' => $ret['merchantPara'])
			);
		return $retData;
	}
}

?>
