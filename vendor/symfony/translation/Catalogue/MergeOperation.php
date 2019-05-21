<?php
//cgxlm
namespace Symfony\Component\Translation\Catalogue;

class MergeOperation extends AbstractOperation
{
	protected function processDomain($domain)
	{
		$this->messages[$domain] = array(
	'all'      => array(),
	'new'      => array(),
	'obsolete' => array()
	);

		foreach ($this->source->all($domain) as $id => $message) {
			$this->messages[$domain]['all'][$id] = $message;
			$this->result->add(array($id => $message), $domain);

			if (null !== ($keyMetadata = $this->source->getMetadata($id, $domain))) {
				$this->result->setMetadata($id, $keyMetadata, $domain);
			}
		}

		foreach ($this->target->all($domain) as $id => $message) {
			if (!$this->source->has($id, $domain)) {
				$this->messages[$domain]['all'][$id] = $message;
				$this->messages[$domain]['new'][$id] = $message;
				$this->result->add(array($id => $message), $domain);

				if (null !== ($keyMetadata = $this->target->getMetadata($id, $domain))) {
					$this->result->setMetadata($id, $keyMetadata, $domain);
				}
			}
		}
	}
}

?>