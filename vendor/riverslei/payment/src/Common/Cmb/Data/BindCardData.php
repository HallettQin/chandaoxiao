<?php
//cgxlm
namespace Payment\Common\Cmb\Data;

class BindCardData extends CmbBaseData
{
	protected function checkDataParam()
	{
		parent::checkDataParam();
		$agrNo = $this->agr_no;
		if (empty($agrNo) || (30 < mb_strlen($agrNo)) || !is_numeric($agrNo)) {
			throw new \Payment\Common\PayException('客户协议号。必须为纯数字串，不超过30位');
		}
	}

	protected function getReqData()
	{
		$reqData = array('dateTime' => $this->dateTime, 'merchantSerialNo' => $this->serial_no ? $this->serial_no : '', 'agrNo' => $this->agr_no, 'branchNo' => $this->branchNo, 'merchantNo' => $this->merchantNo, 'userID' => $this->user_id ? $this->user_id : '', 'mobile' => $this->mobile ? $this->mobile : '', 'lon' => $this->lon ? $this->lon : '', 'lat' => $this->lat ? $this->lat : '', 'riskLevel' => $this->risk_level ? $this->risk_level : '', 'noticeUrl' => $this->signNoticeUrl ? $this->signNoticeUrl : '', 'noticePara' => $this->return_param ? $this->return_param : '', 'returnUrl' => $this->returnUrl ? $this->returnUrl : '');
		return $reqData;
	}
}

?>
