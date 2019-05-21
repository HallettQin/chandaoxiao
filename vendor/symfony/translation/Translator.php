<?php
//cgxlm
namespace Symfony\Component\Translation;

class Translator implements TranslatorInterface, TranslatorBagInterface
{
	/**
     * @var MessageCatalogueInterface[]
     */
	protected $catalogues = array();
	/**
     * @var string
     */
	private $locale;
	/**
     * @var array
     */
	private $fallbackLocales = array();
	/**
     * @var LoaderInterface[]
     */
	private $loaders = array();
	/**
     * @var array
     */
	private $resources = array();
	/**
     * @var MessageSelector
     */
	private $selector;
	/**
     * @var string
     */
	private $cacheDir;
	/**
     * @var bool
     */
	private $debug;
	/**
     * @var ConfigCacheFactoryInterface|null
     */
	private $configCacheFactory;

	public function __construct($locale, MessageSelector $selector = NULL, $cacheDir = NULL, $debug = false)
	{
		$this->setLocale($locale);
		$this->selector = $selector ?: new MessageSelector();
		$this->cacheDir = $cacheDir;
		$this->debug = $debug;
	}

	public function setConfigCacheFactory(\Symfony\Component\Config\ConfigCacheFactoryInterface $configCacheFactory)
	{
		$this->configCacheFactory = $configCacheFactory;
	}

	public function addLoader($format, Loader\LoaderInterface $loader)
	{
		$this->loaders[$format] = $loader;
	}

	public function addResource($format, $resource, $locale, $domain = NULL)
	{
		if (null === $domain) {
			$domain = 'messages';
		}

		$this->assertValidLocale($locale);
		$this->resources[$locale][] = array($format, $resource, $domain);

		if (in_array($locale, $this->fallbackLocales)) {
			$this->catalogues = array();
		}
		else {
			unset($this->catalogues[$locale]);
		}
	}

	public function setLocale($locale)
	{
		$this->assertValidLocale($locale);
		$this->locale = $locale;
	}

	public function getLocale()
	{
		return $this->locale;
	}

	public function setFallbackLocales(array $locales)
	{
		$this->catalogues = array();

		foreach ($locales as $locale) {
			$this->assertValidLocale($locale);
		}

		$this->fallbackLocales = $locales;
	}

	public function getFallbackLocales()
	{
		return $this->fallbackLocales;
	}

	public function trans($id, array $parameters = array(), $domain = NULL, $locale = NULL)
	{
		if (null === $domain) {
			$domain = 'messages';
		}

		return strtr($this->getCatalogue($locale)->get((string) $id, $domain), $parameters);
	}

	public function transChoice($id, $number, array $parameters = array(), $domain = NULL, $locale = NULL)
	{
		$parameters = array_merge(array('%count%' => $number), $parameters);

		if (null === $domain) {
			$domain = 'messages';
		}

		$id = (string) $id;
		$catalogue = $this->getCatalogue($locale);
		$locale = $catalogue->getLocale();

		while (!$catalogue->defines($id, $domain)) {
			if ($cat = $catalogue->getFallbackCatalogue()) {
				$catalogue = $cat;
				$locale = $catalogue->getLocale();
			}
			else {
				break;
			}
		}

		return strtr($this->selector->choose($catalogue->get($id, $domain), (int) $number, $locale), $parameters);
	}

	public function getCatalogue($locale = NULL)
	{
		if (null === $locale) {
			$locale = $this->getLocale();
		}
		else {
			$this->assertValidLocale($locale);
		}

		if (!isset($this->catalogues[$locale])) {
			$this->loadCatalogue($locale);
		}

		return $this->catalogues[$locale];
	}

	protected function getLoaders()
	{
		return $this->loaders;
	}

	protected function loadCatalogue($locale)
	{
		if (null === $this->cacheDir) {
			$this->initializeCatalogue($locale);
		}
		else {
			$this->initializeCacheCatalogue($locale);
		}
	}

	protected function initializeCatalogue($locale)
	{
		$this->assertValidLocale($locale);

		try {
			$this->doLoadCatalogue($locale);
		}
		catch (Exception\NotFoundResourceException $e) {
			if (!$this->computeFallbackLocales($locale)) {
				throw $e;
			}
		}

		$this->loadFallbackCatalogues($locale);
	}

	private function initializeCacheCatalogue($locale)
	{
		if (isset($this->catalogues[$locale])) {
			return NULL;
		}

		$this->assertValidLocale($locale);
		$cache = $this->getConfigCacheFactory()->cache($this->getCatalogueCachePath($locale), function(\Symfony\Component\Config\ConfigCacheInterface $cache) use($locale) {
			$this->dumpCatalogue($locale, $cache);
		});

		if (isset($this->catalogues[$locale])) {
			return NULL;
		}

		$this->catalogues[$locale] = include $cache->getPath();
	}

	private function dumpCatalogue($locale, \Symfony\Component\Config\ConfigCacheInterface $cache)
	{
		$this->initializeCatalogue($locale);
		$fallbackContent = $this->getFallbackContent($this->catalogues[$locale]);
		$content = sprintf("<?php\n\nuse Symfony\\Component\\Translation\\MessageCatalogue;\n\n\$catalogue = new MessageCatalogue('%s', %s);\n\n%s\nreturn \$catalogue;\n", $locale, var_export($this->catalogues[$locale]->all(), true), $fallbackContent);
		$cache->write($content, $this->catalogues[$locale]->getResources());
	}

	private function getFallbackContent(MessageCatalogue $catalogue)
	{
		$fallbackContent = '';
		$current = '';
		$replacementPattern = '/[^a-z0-9_]/i';
		$fallbackCatalogue = $catalogue->getFallbackCatalogue();

		while ($fallbackCatalogue) {
			$fallback = $fallbackCatalogue->getLocale();
			$fallbackSuffix = ucfirst(preg_replace($replacementPattern, '_', $fallback));
			$currentSuffix = ucfirst(preg_replace($replacementPattern, '_', $current));
			$fallbackContent .= sprintf("\$catalogue%s = new MessageCatalogue('%s', %s);\n\$catalogue%s->addFallbackCatalogue(\$catalogue%s);\n", $fallbackSuffix, $fallback, var_export($fallbackCatalogue->all(), true), $currentSuffix, $fallbackSuffix);
			$current = $fallbackCatalogue->getLocale();
			$fallbackCatalogue = $fallbackCatalogue->getFallbackCatalogue();
		}

		return $fallbackContent;
	}

	private function getCatalogueCachePath($locale)
	{
		return $this->cacheDir . '/catalogue.' . $locale . '.' . sha1(serialize($this->fallbackLocales)) . '.php';
	}

	private function doLoadCatalogue($locale)
	{
		$this->catalogues[$locale] = new MessageCatalogue($locale);

		if (isset($this->resources[$locale])) {
			foreach ($this->resources[$locale] as $resource) {
				if (!isset($this->loaders[$resource[0]])) {
					throw new Exception\RuntimeException(sprintf('The "%s" translation loader is not registered.', $resource[0]));
				}

				$this->catalogues[$locale]->addCatalogue($this->loaders[$resource[0]]->load($resource[1], $locale, $resource[2]));
			}
		}
	}

	private function loadFallbackCatalogues($locale)
	{
		$current = $this->catalogues[$locale];

		foreach ($this->computeFallbackLocales($locale) as $fallback) {
			if (!isset($this->catalogues[$fallback])) {
				$this->initializeCatalogue($fallback);
			}

			$fallbackCatalogue = new MessageCatalogue($fallback, $this->catalogues[$fallback]->all());

			foreach ($this->catalogues[$fallback]->getResources() as $resource) {
				$fallbackCatalogue->addResource($resource);
			}

			$current->addFallbackCatalogue($fallbackCatalogue);
			$current = $fallbackCatalogue;
		}
	}

	protected function computeFallbackLocales($locale)
	{
		$locales = array();

		foreach ($this->fallbackLocales as $fallback) {
			if ($fallback === $locale) {
				continue;
			}

			$locales[] = $fallback;
		}

		if (strrchr($locale, '_') !== false) {
			array_unshift($locales, substr($locale, 0, 0 - strlen(strrchr($locale, '_'))));
		}

		return array_unique($locales);
	}

	protected function assertValidLocale($locale)
	{
		if (1 !== preg_match('/^[a-z0-9@_\\.\\-]*$/i', $locale)) {
			throw new Exception\InvalidArgumentException(sprintf('Invalid "%s" locale.', $locale));
		}
	}

	private function getConfigCacheFactory()
	{
		if (!$this->configCacheFactory) {
			$this->configCacheFactory = new \Symfony\Component\Config\ConfigCacheFactory($this->debug);
		}

		return $this->configCacheFactory;
	}
}

?>
