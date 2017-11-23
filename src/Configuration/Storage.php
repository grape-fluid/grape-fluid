<?php

namespace Grapesc\GrapeFluid\Configuration;

use Grapesc\GrapeFluid\Model\BaseModel;
use Nette\InvalidArgumentException;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class Storage implements IStorage
{

	CONST TID_COLUMN         = "variable";
	CONST VALUE_COLUMN       = "value";
	CONST DEFAULT_COLUMN     = "default_value";
	CONST TYPE_COLUMN        = "type";
	CONST DESCRIPTION_COLUMN = "description";
	CONST ENUM_COLUMN        = "options";
	CONST SECURED_COLUMN     = "secured";
	CONST NULLABLE_COLUMN    = "nullable";

	/** @var BaseModel */
	private $model;

	/** @var array */
	private $memCache = [];

	private $requireMigration = false;


	public function __construct(BaseModel $model)
	{
		$this->model = $model;
	}


	/**
	 * @param $name
	 * @param null $defaultValue
	 * @return null
	 */
	public function getValue($name, $defaultValue = null)
	{
		$this->prepareMemCache();

		if ($this->requireMigration) {
			return $defaultValue;
		}

		if (array_key_exists($name, $this->memCache)) {
			return $this->memCache[$name];
		} else {
			$item = $this->model->getItemBy($name, self::TID_COLUMN);
			if ($item) {
				return $item->{self::VALUE_COLUMN};
			}  else {
				return $defaultValue;
			}
		}
	}


	/**
	 * @param $name
	 * @param $value
	 */
	public function setValue($name, $value)
	{
		if ($this->requireMigration) {
			return;
		}

		$this->memCache[$name] = $value;
		$this->model->update([self::VALUE_COLUMN => $value], $name, self::TID_COLUMN);
	}


	/**
	 * @param ParameterEntity $parameter
	 */
	public function newParameter(ParameterEntity $parameter)
	{
		if ($this->hasParameter($parameter)) {
			$this->updateParameter($parameter);
		} else {
			if ($this->requireMigration) {
				return;
			}
			$this->model->insert([
				self::TID_COLUMN         => $parameter->tid,
				self::VALUE_COLUMN       => $parameter->default,
				self::DEFAULT_COLUMN     => $parameter->default,
				self::DESCRIPTION_COLUMN => $parameter->description,
				self::TYPE_COLUMN        => $parameter->type,
				self::ENUM_COLUMN        => $parameter->enum ? json_encode($parameter->enum) : null,
				self::SECURED_COLUMN     => $parameter->secured,
				self::NULLABLE_COLUMN    => $parameter->nullable,
			]);
		}
	}


	/**
	 * @param ParameterEntity $parameter
	 */
	public function updateParameter(ParameterEntity $parameter)
	{
		if ($this->hasParameter($parameter)) {
			if ($this->requireMigration) {
				return;
			}
			$this->model->update([
				self::DEFAULT_COLUMN     => $parameter->default,
				self::DESCRIPTION_COLUMN => $parameter->description,
				self::ENUM_COLUMN        => $parameter->enum ? json_encode($parameter->enum) : null,
			], $parameter->tid, self::TID_COLUMN);
		} else {
			$this->newParameter($parameter);
		}
	}


	/**
	 * @param ParameterEntity $parameter
	 * @return bool
	 */
	public function hasParameter(ParameterEntity $parameter)
	{
		$this->prepareMemCache();

		if ($this->requireMigration) {
			return false;
		}

		return (bool) $this->model->getTableSelection()->select('id')->where(self::TID_COLUMN, $parameter->tid)->count('id');
	}


	private function prepareMemCache()
	{
		if ($this->memCache) {
			return;
		}

		try {
			$this->memCache = $this->model->getTableSelection()->select(implode(",", [self::TID_COLUMN, self::VALUE_COLUMN]))->fetchPairs(self::TID_COLUMN, self::VALUE_COLUMN);
		} catch (InvalidArgumentException $e) {
			$this->requireMigration = true;
		}
	}

}