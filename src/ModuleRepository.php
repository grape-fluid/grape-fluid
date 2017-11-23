<?php

namespace Grapesc\GrapeFluid;

use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;
use Nette\Configurator;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 */
class ModuleRepository
{

	/** @var Configurator $configurator */
	private $configurator;

	/** @var BaseParametersRepository $params */
	private $params = [];

	/** @var BaseModule[] $modules */
	private $modules = [];

	/** @var Cache $cache */
	private $cache;

	/** @var bool */
	private $isSorted = false;


	public function __construct(Configurator $configurator, BaseParametersRepository $params)
	{
		$this->configurator = $configurator;
		$this->params = $params;
	}


	/**
	 * @return Cache
	 */
	private function getCache()
	{
		if (!$this->cache) {
			$this->cache = new Cache(new FileStorage($this->params->getParam("tempDir")), "Fluid.Modules");
		}
		return $this->cache;
	}


	/**
	 * Přidá nalezený modul do zásobníku
	 * automaticky vydedukuje jméno řídící třídy modulu z názvu modulu
	 * @param $module
	 * @param $modulePath
	 */
	public function addModule($module, $modulePath)
	{
		$class = "\\Grapesc\\GrapeFluid\\Module\\" . str_replace("Module", "", $module) . "\\" . $module;

		/** @var BaseModule $class */
		$class = (new $class($this->configurator, $this->params));
		$class->setModuleDir($modulePath);
		$this->modules[$module] = $class;

		$this->isSorted = false;
	}


	/**
	 * V prvním průběhu seřadí moduly podle závislosti a uloží do cache, pak řazení již načítá z cache
	 *
	 * @throws \Exception
	 * @throws \Throwable
	 * @return BaseModule[]
	 */
	public function run()
	{
		foreach ($this->getModules() AS $module) {
			$module->run();
		}
	}


	/**
	 * Vrátí seznam dostupných modulů
	 * Pozor: Seznam modulů  seřazen dle závislostí
	 *
	 * @param bool $forcedSort
	 * @return BaseModule[]
	 */
	public function getModules($forcedSort = false)
	{
		if ($forcedSort) {
			$this->sortModules();
		} elseif (!$this->isSorted) {
			$this->checkSorting();
		}

		return $this->modules;
	}


	/**
	 * @param string $moduleName
	 * @return bool
	 */
	public function moduleExist($moduleName)
	{
		return key_exists($moduleName, $this->modules) || key_exists(ucfirst($moduleName) . "Module", $this->modules);
	}


	private function checkSorting()
	{
		$cachedModules = $this->getCache()->load("modules");

		if (is_null($cachedModules)) {
			if (!$this->isSorted) {
				$this->sortModules();
			}
			$this->getCache()->save("modules", array_keys($this->modules));
		} else {
			$this->resortModules($cachedModules);
			$this->isSorted = true;
		}
	}


	/**
	 * @param array $sorting
	 */
	private function resortModules(array $sorting)
	{
		$sortedModules = [];

		foreach ($sorting AS $moduleName) {
			$sortedModules[$moduleName] = $this->modules[$moduleName];
		}

		$this->modules  = $sortedModules;
		$this->isSorted = true;
	}


	/**
	 * Radi moduly podle zavislosti, v pripade chybejici zavislosti nebo zakruhovane zavisloti vyhodit vyjimku
	 */
	private function sortModules()
	{
		$sortData = [];
		$state    = [];
		$buffer   = [];
		$sorted   = [];

		foreach ($this->modules AS $moduleName => $module) {
			$sortData[$moduleName] = array_unique($module->getParents());
			$state[$moduleName]    = 0;
		}

		foreach ($sortData as $module => $dependencies) {
			if ($state[$module] != 0) {
				continue;
			}

			$buffer[] = $module;

			while (count($buffer) > 0) {
				$current = array_pop($buffer);

				if ($state[$current] == 2) {
					continue;
				}

				if ($state[$current] == 1) {
					$sorted[] = $current;
					$state[$current] = 2;
					continue;
				}

				if ($state[$current] == 0) {
					$state[$current] = 1;
					$buffer[] = $current;

					foreach ($sortData[$current] as $dependency) {
						if (!isset($state[$dependency])) {
							throw new \LogicException("Module $current is dependent on non-exist module $dependency.");
						}

						if ($state[$dependency] == 1) {
							throw new \LogicException("Detected circular dependencies between $current and $dependency.");
						}

						if ($state[$dependency] == 0) {
							$buffer[] = $dependency;
						}
					}
				}
			}
		}
		
		$this->resortModules($sorted);
	}

}