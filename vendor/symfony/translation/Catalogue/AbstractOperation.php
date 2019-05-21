<?php
//cgxlm
namespace Symfony\Component\Translation\Catalogue;

abstract class AbstractOperation implements OperationInterface
{
	/**
     * @var MessageCatalogueInterface The source catalogue
     */
	protected $source;
	/**
     * @var MessageCatalogueInterface The target catalogue
     */
	protected $target;
	/**
     * @var MessageCatalogue The result catalogue
     */
	protected $result;
	/**
     * @var null|array The domains affected by this operation
     */
	private $domains;
	/**
     * This array stores 'all', 'new' and 'obsolete' messages for all valid domains.
     *
     * The data structure of this array is as follows:
     * ```php
     * array(
     *     'domain 1' => array(
     *         'all' => array(...),
     *         'new' => array(...),
     *         'obsolete' => array(...)
     *     ),
     *     'domain 2' => array(
     *         'all' => array(...),
     *         'new' => array(...),
     *         'obsolete' => array(...)
     *     ),
     *     ...
     * )
     * ```
     *
     * @var array The array that stores 'all', 'new' and 'obsolete' messages
     */
	protected $messages;

	public function __construct(\Symfony\Component\Translation\MessageCatalogueInterface $source, \Symfony\Component\Translation\MessageCatalogueInterface $target)
	{
		if ($source->getLocale() !== $target->getLocale()) {
			throw new \Symfony\Component\Translation\Exception\LogicException('Operated catalogues must belong to the same locale.');
		}

		$this->source = $source;
		$this->target = $target;
		$this->result = new \Symfony\Component\Translation\MessageCatalogue($source->getLocale());
		$this->messages = array();
	}

	public function getDomains()
	{
		if (null === $this->domains) {
			$this->domains = array_values(array_unique(array_merge($this->source->getDomains(), $this->target->getDomains())));
		}

		return $this->domains;
	}

	public function getMessages($domain)
	{
		if (!in_array($domain, $this->getDomains())) {
			throw new \Symfony\Component\Translation\Exception\InvalidArgumentException(sprintf('Invalid domain: %s.', $domain));
		}

		if (!isset($this->messages[$domain]['all'])) {
			$this->processDomain($domain);
		}

		return $this->messages[$domain]['all'];
	}

	public function getNewMessages($domain)
	{
		if (!in_array($domain, $this->getDomains())) {
			throw new \Symfony\Component\Translation\Exception\InvalidArgumentException(sprintf('Invalid domain: %s.', $domain));
		}

		if (!isset($this->messages[$domain]['new'])) {
			$this->processDomain($domain);
		}

		return $this->messages[$domain]['new'];
	}

	public function getObsoleteMessages($domain)
	{
		if (!in_array($domain, $this->getDomains())) {
			throw new \Symfony\Component\Translation\Exception\InvalidArgumentException(sprintf('Invalid domain: %s.', $domain));
		}

		if (!isset($this->messages[$domain]['obsolete'])) {
			$this->processDomain($domain);
		}

		return $this->messages[$domain]['obsolete'];
	}

	public function getResult()
	{
		foreach ($this->getDomains() as $domain) {
			if (!isset($this->messages[$domain])) {
				$this->processDomain($domain);
			}
		}

		return $this->result;
	}

	abstract protected function processDomain($domain);
}

?>
