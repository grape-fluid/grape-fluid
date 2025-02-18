<?php

namespace Grapesc\GrapeFluid\Model;

use Monolog\Logger;
use Nette\ArrayHash;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\Selection;
use Symfony\Component\Console\Exception\LogicException;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
abstract class BaseModel
{

	/** @var Context */
	protected $context;

	/** @var Logger */
	protected $logger;

	/** @var IStorage */
	public $storage;

	/** @var string */
	private $tableName;
	
	
	public function __construct(Context $context, Logger $logger, IStorage $storage)
	{
		$this->context = $context;
		$this->logger = $logger;

		if (in_array("Grapesc\\GrapeFluid\\Model\\CachedModel", class_uses($this))) {
			$this->storage = $storage;
		}

		$this->tableName = static::getTableName();

		$this->startup();
	}


	public function startup()
	{
	}


	/**
	 * Vrací jméno tabulky dle názvu třídy a modulu, ve kterém se nachází
	 *
	 * Příklad:
	 * ContentModule\PageModel = content_page
	 *
	 * @return string
	 */
	public function getTableName()
	{
		if ($this->tableName) {
			return $this->tableName;
		}

		$exploded = explode("\\", get_called_class());
		$names    = [];
		foreach ($exploded as $part) {
			if (substr($part, -6) == "Module" AND strlen($p = substr($part, 0, -6)) >= 3) {
				$names[] = strtolower($p);
			} elseif (substr($part, -5) == "Model" AND strlen($p = substr($part, 0, -5)) >= 3) {
				$names[] = strtolower($p);
			}
		}

		return implode("_", $names);
	}


	/**
	 * @return \Nette\Database\Table\Selection
	 */
	public function getTableSelection()
	{
		if ($this->context instanceof Context) {
			return $this->context->table(static::getTableName());
		} else {
			// TODO: Not supported DB engine?
		}
	}


	/**
	 * @param bool $throw
	 * @return bool
	 * @throws \Exception
	 */
	public function checkConnection($throw = true)
	{
		try {
			$this->getConnection();
			return true;
		} catch (\Exception $e) {
			if ($throw) {
				throw $e;
			} else {
				return false;
			}
		}
	}


	/**
	 * @return \Nette\Database\Connection
	 */
	public function getConnection()
	{
		return $this->context->getConnection();
	}


	/**
	 * Vrati strukturu tabulky
	 *
	 * @return array
	 */
	public function getColumns()
	{
		return $this->context->getStructure()->getColumns(static::getTableName());
	}


	/**
	 * Vrati prave jeden zaznam odpovidajici primarnimu klici
	 *
	 * @param $key
	 * @return \Nette\Database\Table\IRow
	 */
	public function getItem($key)
	{
		return $this->getTableSelection()->get($key);
	}


	/**
	 * Vrati prave jeden zaznam kde $params odpovidaji $condition
	 *
	 * @param $params
	 * @param $condition
	 * @return bool|mixed|\Nette\Database\Table\IRow
	 */
	public function getItemBy($params, $condition = "id")
	{
		return $this->getTableSelection()->where($condition, $params)->fetch();
	}


	/**
	 * Vrati zaznam(y) kde $params odpovidaji $condition
	 *
	 * @param $params mixed
	 * @param $condition (může obsahovat zástupný znak ?)
	 * @return Selection
	 */
	public function getItemsBy($params, $condition = "id")
	{
		return $this->getTableSelection()->where($condition, $params);
	}


	/**
	 * Vraci vsechny zaznamy v tabulce, muze associovyt podle $assocBy
	 * 
	 * @param string|null $assocBy
	 * @return array|\Nette\Database\Table\IRow[]|\stdClass
	 */
	public function getAllItems(string|array|null $assocBy = null)
	{
		if (is_array($assocBy)) {
			$assocBy = implode('|', $assocBy);
		}

		return $assocBy ? $this->getTableSelection()->fetchAssoc($assocBy) : $this->getTableSelection()->fetchAll();
	}


	/**
	 * Odstraní záznam(y) z tabulky kde $params odpovídají $condition
	 *
	 * @param $params mixed
	 * @param $condition (může obsahovat zástupný znak ?)
	 * @return int smazanych zaznamu
	 */
	public function delete($params, $condition = "id")
	{
		return $this->getTableSelection()->where($condition, $params)->delete();
	}


	/**
	 * Vloží záznam do databáze
	 *
	 * @param array $data
	 * @return bool|int|\Nette\Database\Table\IRow
	 */
	public function insert($data)
	{
		return $this->getTableSelection()->insert($data);
	}


	/**
	 * Aktualizuje $data kde $params odpovídají $condition
	 *
	 * @param $data
	 * @param $params mixed
	 * @param $condition string (může obsahovat ?)
	 * @return int pozměněných řádků
	 */
	public function update($data, $params, $condition = "id")
	{
		return $this->getTableSelection()->where($condition, $params)->update($data);
	}


	/**
	 * @return Context
	 */
	public function getContext()
	{
		return $this->context;
	}


	/**
	 * @param array|ArrayHash $values
	 * @return array|ArrayHash
	 */
	public function clearingValues($values)
	{
		$arrayHash = is_object($values) AND $values instanceof ArrayHash;
		$values    = (array) $values;
		$columns   = array_map(function($item) {
			return $item['name'];
		}, $this->getColumns());

		$values = array_filter($values, function($item) use($columns) {
			return in_array($item, $columns);
		}, ARRAY_FILTER_USE_KEY);

		return $arrayHash ? ArrayHash::from($values, false) : $values;
	}
	
}
