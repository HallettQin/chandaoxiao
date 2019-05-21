<?php
//cgxlm
namespace Payment\Notify;

abstract class NotifyStrategy
{
	/**
     * 配置信息
     * @var ConfigInterface $config
     */
	protected $config;

	public function __construct(array $config)
	{
		mb_internal_encoding('UTF-8');
	}

	final public function handle(PayNotifyInterface $notify)
	{
		$notifyData = $this->getNotifyData();

		if ($notifyData === false) {
			return $this->replyNotify(false, '获取通知数据失败');
		}

		$checkRet = $this->checkNotifyData($notifyData);

		if ($checkRet === false) {
			return $this->replyNotify(false, '返回数据验签失败，可能数据被篡改');
		}

		$flag = $this->callback($notify, $notifyData);

		if ($flag) {
			$msg = 'OK';
		}
		else {
			$msg = '商户逻辑调用出错';
		}

		return $this->replyNotify($flag, $msg);
	}

	protected function callback(PayNotifyInterface $notify, array $notifyData)
	{
		$data = $this->getRetData($notifyData);

		if ($data === false) {
			return false;
		}

		return $notify->notifyProcess($data);
	}

	abstract public function getNotifyData();

	abstract public function checkNotifyData(array $data);

	abstract protected function getRetData(array $data);

	abstract protected function replyNotify($flag, $msg = 'OK');
}


?>
