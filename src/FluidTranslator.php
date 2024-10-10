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


	function __construct(array $config = [], ?\Nette\Caching\IStorage $cacheStorage = null)
	{
		$this->cache = new \Nette\Caching\Cache($cacheStorage, 'Fluid.Translator');
		
		$translator = new \Symfony\Component\Translation\Translator($this->localeName);
		$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
		$translator->addResource('array', $this->getDictionary($config['locales'] ?? null), $this->localeName);

		$this->config = $config;
		
		$this->symfonyTranslator = $translator;
	}	
	
	
	/**
	 * @param array $configsFiles
	 * @return array
	 */
	private function getDictionary(?array $configsFiles = [])
	{
		$fromCache = $this->cache->load('dictionary');
		
		if ($fromCache) {
			return $fromCache;
		}
		
		$dictionary = [];

		if ($configsFiles) {
			foreach ($configsFiles as $file) {
				if (file_exists($file)) {
					$neon = file_get_contents($file);
					if ($neon) {
						$dictionary = \Nette\DI\Config\Helpers::merge(\Nette\Neon\Neon::decode($neon), $dictionary);
					}
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
	 */
	public function translate(string|int|\Stringable $message, mixed ...$parameters): string|\Stringable
	{
		$count = $parameters[0] ?? null;
		$trans = $this->symfonyTranslator->trans((string) $message, [], null, $this->localeName);

		if ($this->config['catchUntranslated'] && $trans == $message) {
			$file = fopen($this->config['catchFile'], 'a');

			if (!key_exists($message, (new NeonAdapter)->load($this->config['catchFile']))) {
				fwrite($file, "'" . $message . "': ''\n");
				fclose($file);
			}
		}

		if ($count !== null) {
			if (is_array($count)) {
				$transParams = $count;
				$count       = $transParams['count'] ?? 0;
			} else {
				$transParams = ['%count%' => $count];
			}

			$trans = $this->symfonyTranslator->trans($this->symfonyOldPluralize($trans, $count), $transParams);
		}

		return $trans;
	}
	
	
	private function symfonyOldPluralize(string $message, int $count): string
	{
		$parts = explode('|', $message);

		foreach ($parts as $part) {
			if (preg_match('/^\{(\d+)\}\s*(.*)$/', $part, $matches)) { // {0}
				if ($count == $matches[1]) {
					return str_replace('%count%', $count, $matches[2]);
				}
			} elseif (preg_match('/^\{(\d+(?:,\d+)*)\}\s*(.*)$/', $part, $matches)) { // {2,3,4}
				$numbers = explode(',', $matches[1]);
				if (in_array($count, $numbers)) {
					return str_replace('%count%', $count, $matches[2]);
				}
			} elseif (preg_match('/^\[(\d+),Inf\[\s*(.*)$/', $part, $matches)) { // [5,Inf[
				if ($count >= $matches[1]) {
					return str_replace('%count%', $count, $matches[2]);
				}
			}
		}

		return str_replace('%count%', $count, end($parts));
	}

}
