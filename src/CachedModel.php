<?php

namespace Grapesc\GrapeFluid\Model;

use Nette\Caching\Cache;


// TODO: Experimental!
trait CachedModel
{

	/** @var Cache */
	private $cache;

	/** @var string */
	private $assocBy = 'id';


	/**
	 * @return Cache
	 */
	private function getCache()
	{
		if (!$this->cache) {
			$this->cache = new Cache($this->storage, 'Fluid.Table');
		}

		return $this->cache;
	}


	/**
	 * Načte tabulku z cache
	 * Pokud tabulka v cache neni - nacachuje se
	 *
	 * @return mixed|NULL
	 */
	private function getTableCache()
	{
		return $this->getCache()->load($this->getTableName(), function (&$dependencies) {
			return parent::getAllItems($this->assocBy);
		});
	}


	/**
	 * Smaže tabulku z cache
	 * @return void
	 */
	private function invalidate()
	{
		$this->getCache()->remove($this->getTableName());
	}


	/**
	 * @param bool $invalidate
	 * @return void
	 */
	public function recache($invalidate = true)
	{
		if ($invalidate) {
			$this->invalidate();
		}
		$this->getCache()->save($this->getTableName(), parent::getAllItems($this->assocBy));
	}


	/**
	 * Vloží záznam do tabulky a refreshne cache
	 *
	 * @param array $data
	 * @return int změněných řádků
	 */
	public function insert($data)
	{
		$affected = parent::insert($data);
		$this->recache();
		return $affected;
	}


	/**
	 * Aktualizuje $data kde $params odpovídají $condition
	 * Aktualizuje cache
	 *
	 * @param $data
	 * @param $params
	 * @param string $condition
	 * @return int pozměněných řádků
	 */
	public function update($data, $params, $condition = "id")
	{
		$affected = parent::update($data, $params, $condition);
		$this->recache();
		return $affected;
	}


	/**
	 * Smaže záznam do tabulky a refreshne cache
	 *
	 * @param $params
	 * @param string $condition
	 * @return int smazaných zaznamu
	 */
	public function delete($params, $condition = "id")
	{
		$affected = parent::delete($params, $condition);
		$this->recache();
		return $affected;
	}


	/**
	 * Vrátí záznam z cache tabulky podle primárního klíče
	 * Vrátí false pokud nenajde žádný záznam
	 *
	 * @param $key
	 * @return bool|array
	 */
	public function getItem($key)
	{
		$table = $this->getTableCache();

		if (array_key_exists($key, $table)) {
			return $table[$key];
		} else {
			return false;
		}
	}


	/**
	 * Vrátí všechny záznamy z cache tabulky
	 *
	 * @param null $assocBy - nepoužitý, pouze pro soulad metod
	 * @return array
	 */
	public function getAllItems(string|array|null $assocBy = null)
	{
		return $this->getTableCache();
	}

}