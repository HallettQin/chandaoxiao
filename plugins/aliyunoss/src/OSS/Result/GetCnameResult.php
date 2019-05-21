<?php
//cgxlm
namespace OSS\Result;

class GetCnameResult extends Result
{
	protected function parseDataFromResponse()
	{
		$content = $this->rawResponse->body;
		$config = new \OSS\Model\CnameConfig();
		$config->parseFromXml($content);
		return $config;
	}

	protected function isResponseOk()
	{
		$status = $this->rawResponse->status;
		if (((int) (intval($status) / 100) == 2) || ((int) intval($status) === 404)) {
			return true;
		}

		return false;
	}
}

?>
