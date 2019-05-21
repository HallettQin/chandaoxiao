<?php
//cgxlm
namespace Symfony\Component\Translation\DataCollector;

class TranslationDataCollector extends \Symfony\Component\HttpKernel\DataCollector\DataCollector implements \Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface
{
	/**
     * @var DataCollectorTranslator
     */
	private $translator;

	public function __construct(\Symfony\Component\Translation\DataCollectorTranslator $translator)
	{
		$this->translator = $translator;
	}

	public function lateCollect()
	{
		$messages = $this->sanitizeCollectedMessages($this->translator->getCollectedMessages());
		$this->data = $this->computeCount($messages);
		$this->data['messages'] = $messages;
		$this->data = $this->cloneVar($this->data);
	}

	public function collect(\Symfony\Component\HttpFoundation\Request $request, \Symfony\Component\HttpFoundation\Response $response, \Exception $exception = NULL)
	{
	}

	public function getMessages()
	{
		return isset($this->data['messages']) ? $this->data['messages'] : array();
	}

	public function getCountMissings()
	{
		return isset($this->data[\Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_MISSING]) ? $this->data[\Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_MISSING] : 0;
	}

	public function getCountFallbacks()
	{
		return isset($this->data[\Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK]) ? $this->data[\Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK] : 0;
	}

	public function getCountDefines()
	{
		return isset($this->data[\Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_DEFINED]) ? $this->data[\Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_DEFINED] : 0;
	}

	public function getName()
	{
		return 'translation';
	}

	private function sanitizeCollectedMessages($messages)
	{
		$result = array();

		foreach ($messages as $key => $message) {
			$messageId = $message['locale'] . $message['domain'] . $message['id'];

			if (!isset($result[$messageId])) {
				$message['count'] = 1;
				$message['parameters'] = !empty($message['parameters']) ? array($message['parameters']) : array();
				$messages[$key]['translation'] = $this->sanitizeString($message['translation']);
				$result[$messageId] = $message;
			}
			else {
				if (!empty($message['parameters'])) {
					$result[$messageId]['parameters'][] = $message['parameters'];
				}

				++$result[$messageId]['count'];
			}

			unset($messages[$key]);
		}

		return $result;
	}

	private function computeCount($messages)
	{
		$count = array(\Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_DEFINED => 0, \Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_MISSING => 0, \Symfony\Component\Translation\DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK => 0);

		foreach ($messages as $message) {
			++$count[$message['state']];
		}

		return $count;
	}

	private function sanitizeString($string, $length = 80)
	{
		$string = trim(preg_replace('/\\s+/', ' ', $string));

		if (false !== ($encoding = mb_detect_encoding($string, null, true))) {
			if ($length < mb_strlen($string, $encoding)) {
				return mb_substr($string, 0, $length - 3, $encoding) . '...';
			}
		}
		else if ($length < strlen($string)) {
			return substr($string, 0, $length - 3) . '...';
		}

		return $string;
	}
}

?>
