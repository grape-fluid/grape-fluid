<?php

namespace Grapesc\GrapeFluid;

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\Localization\ITranslator;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class FluidTranslator implements ITranslator
{
	
	private $localeName = 'default_Default';

	/** @var \Symfony\Component\Translation\Translator */
	private $symfonyTranslator;
	
	/** @var \Nette\Caching\Cache */
	private $cache;

	/** @var array */
	private $config;
	
	
	function __construct(array $config, \Nette\Caching\IStorage $cacheStorage)
	{
		$this->cache = new \Nette\Caching\Cache($cacheStorage, 'Fluid.Translator');
		
		$translator = new \Symfony\Component\Translation\Translator($this->localeName);
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', $this->getDictionary($config['locales']), $this->localeName);

		$this->config = $config;
		
		$this->symfonyTranslator = $translator;
	}	
	
	
	/**
	 * @param array $configsFiles
	 * @return array
	 */
	private function getDictionary(array $configsFiles)
	{
		$fromCache = $this->cache->load('dictionary');
		
		if ($fromCache) {
			return $fromCache;
		}
		
		$dictionary = [];
		
		foreach ($configsFiles AS $file) {
			if (file_exists($file)) {
				$neon = file_get_contents($file);
				if ($neon) {
					$dictionary = \Nette\DI\Config\Helpers::merge(\Nette\Neon\Neon::decode($neon), $dictionary);
				}
			}
		}
		
		$this->cache->save('dictionary', $dictionary);
		
		return $dictionary;
	}


	/**
	 * Pokusí se přeložit zprávu
	 *
	 * Pokud chybí záznam ve slovníku a catchUntranslated (v configu) je true,
	 * zaloguje tento záznam do var/log/untranslated.neon
	 *
	 * @param $message
	 * @param null $count
	 * @return string
	 */
	public function translate($message, $count = NULL)
	{
		$trans = $this->symfonyTranslator->trans($message, [], null, $this->localeName);

		if ($this->config['catchUntranslated'] && $trans == $message) {
			$file = fopen($this->config['catchFile'], 'a');

			if (!key_exists($message, (new NeonAdapter)->load($this->config['catchFile']))) {
				fwrite($file, "'" . $message . "': ''\n");
				fclose($file);
			}
		}

		if ($count !== null) {
			$trans = str_replace("%count%", $count, $this->symfonyTranslator->transChoice($trans, $count));
		}

		return $trans;
	}

}
