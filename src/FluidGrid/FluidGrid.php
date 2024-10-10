<?php

namespace Grapesc\GrapeFluid\FluidGrid;

use Grapesc\GrapeFluid\FluidTranslator;
use Grapesc\GrapeFluid\Model\BaseModel;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;
use Nette\Forms\Controls\SelectBox;
use TwiGrid\Components\Column;
use TwiGrid\Components\RowAction;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 * @author Mira Jakes <jakes@grapesc.cz>
 */
abstract class FluidGrid extends \TwiGrid\DataGrid
{
	
	/** @var FluidTranslator */
	protected $translator;
	
	/** @var BaseModel */
	protected $model;

	/** @var array */
	private $args = [
		"itemsPerPage"    => 15,
		"skip"            => [],
		"sortable"        => [],
		"filterable"      => [],
		'filterIdentical' => [],
		"filters"         => false,
	];


	/**
	 * FluidGrid constructor.
	 * @param BaseModel $model - databázový model
	 * @param FluidTranslator $translator - instance translátoru
	 * @param array $args - volitelné argumenty - možno získat pomocí getArguments($key)
	 */
	function __construct(BaseModel $model, FluidTranslator $translator, $args = [])
	{
		parent::__construct();
		$this->model      = $model;
		$this->args       = array_merge($this->args, $args);
		$this->translator = $translator;
	}


	protected function build(): void
	{
		parent::build();

		$selection = $this->model->getTableSelection()->select("*");

		$this->setTranslator($this->translator);
		$this->setDataLoader(function (array $filters, array $order, $limit, $offset) use ($selection) {
			if ($this->isFilterable()) {
				$this->processFilter($selection, $filters);
			}
			foreach ($order as $column => $dir) {
				$selection->order($column . ($dir === Column::DESC ? ' DESC' : ''));
			}

			$selection->limit($limit, $offset);
			$this->extendDataLoader($selection);

			return $selection;
		});

		$this->setPagination($this->args['itemsPerPage'], function (array $filters) use ($selection) {
			if ($this->isFilterable()) {
				$this->processFilter($selection, $filters);
			}
			
			$this->extendPagination($selection);

			return $selection->count("*");
		});

		if ($this->isFilterable()) {
			$this->setFilterFactory([$this, 'filterFactory']);
		}

		$this->setMultiSort(false);

		foreach ($this->model->getColumns() as $key => $column)
		{
			if ($column['primary']) {
				$this->setPrimaryKey($column['name']);
			} elseif (!in_array($column['name'], $this->args['skip'])) {
				$col = $this->addColumn($column['name'], $column['name']);
				if (in_array($column['name'], $this->args['sortable'])) {
					$col->setSortable();
				}
			}
		}

		$class = new \ReflectionClass($this);
		$file  = str_replace(".php", "", $class->getFileName()) . ".latte";

		if (file_exists($file)) {
			$this->setTemplateFile($file);
		}
	}


	/**
	 * Přetížením této metody můžete upravit formulář pro filtrování.
	 * Jméno inputu souhlasí s názvem sloupce
	 *
	 * Samotné filtrování se provádí v metodě processFilter()
	 *
	 * @return Container
	 */
	public function filterFactory()
	{
		$container = $this->getFilterContainer();
		$columns   = $this->getColumns();

		foreach ($container->getComponents() AS $component) {
			$this->setFilterableColumns([$component->getName()]);
		}

		foreach ($this->args['filterable'] AS $columnName) {
			if ($columns->offsetExists($columnName)) {
				$column = $columns[$columnName];

				if (!$container->getComponent($columnName, false)) {
					$container->addText($columnName, $column->getLabel())
						->setRequired(false);
				}
			}
		}

		foreach ($container->getComponents() AS $component) {
			if ($component instanceof SelectBox) {
				if (!$component->getPrompt()) {
					$component->setPrompt('');
				}

				$this->setFilterIdenticalColumns([$component->getName()]);
			}
		}

		return $container;
	}


	/**
	 * @param string $name
	 * @param string $label
	 * @param string $insertBefore
	 * @return Column
	 */
	public function addColumn(string $name, string $label = NULL, $insertBefore = null): Column
	{
		if ($insertBefore) {
			if (!isset($this['columns'])) {
				$this['columns'] = new NContainer;
			}

			$column = new Column($label === NULL ? $name : $label);
			$this['columns']->addComponent($column, $name, $insertBefore);

			return $column;
		} else {
			return parent::addColumn($name, $label);
		}
	}


	/**
	 * @param  string $name
	 * @param  string $label
	 * @param  callable $callback
	 * @return RowAction
	 */
	public function addRowAction(string $name, string $label, callable $callback): RowAction
	{
		$action = parent::addRowAction($name, $label, $callback);

		if (!in_array($name, ["del", "delete", "remove"])) {
			$action->setProtected(false);
		}

		return $action;
	}


	/**
	 * Přetížením této metody můžete filtrovat záznamy ze svého gridu
	 *
	 * Aby filtrování fungovalo, je potřeba nastavit filtrovací
	 * formulář skrze filterFactory() metodu
	 *
	 * @param Selection $selection
	 * @param $filters
	 * @return void
	 */
	protected function processFilter(Selection &$selection, $filters)
	{
		$modelColumns = $this->getModelColumns();

		foreach ($filters as $column => $value) {
			if (key_exists($column, $modelColumns)) {

				$mdColumn = $modelColumns[$column];

				switch ($mdColumn['nativetype']) {
					case "INT":
					case "TINYINT":
						if ($mdColumn['size'] === 1) {
							$selection->where("$column = ?", $value ? 1 : 0);
						} elseif (!is_null($value)) {
							$selection->where("$column = ?", $value);
						}
						break;

					case "TEXT":
					case "VARCHAR":
						if (strlen($value) > 0) {
							if (in_array($column, $this->args['filterIdentical'])) {
								$selection->where("$column = ?", "$value");
							} else {
								$selection->where("$column LIKE ?", "%$value%");
							}
						}
						break;

					default:
						if ($value) {
							$selection->where("$column = ?", $value);
						}
						break;
				}
			}
		}
	}


	/**
	 * @param Selection $selection
	 * @return void
	 */
	protected function extendDataLoader(Selection $selection)
	{
	}


	/**
	 * @param Selection $selection
	 * @return void
	 */
	protected function extendPagination(Selection $selection)
	{
	}


	/**
	 * Nastaví sloupce, které budou při sestavování gridu ignorovány
	 * @param array $array
	 */
	protected function skipColumns($array = [])
	{
		$this->args['skip'] = array_merge($this->args['skip'], $array);
	}


	/**
	 * Nastaví na sloupce možnost řazení
	 * @param array $array
	 */
	protected function setSortableColumns($array = [])
	{
		$this->args['sortable'] = array_merge($this->args['sortable'], $array);
	}


	/**
	 * Nastaví na sloupce možnost filtrování při využítí výchozí továrny na filtrování
	 * @param array $array
	 */
	protected function setFilterableColumns($array = [])
	{
		$this->args['filterable'] = array_merge($this->args['filterable'], $array);
		$this->args['filters'] = (bool) $array;
	}


	/**
	 * Nastaví že se má ve sloupci vyhledávat jen celý výraz
	 * @param array $array
	 */
	protected function setFilterIdenticalColumns($array = [])
	{
		$this->args['filterIdentical'] = array_merge($this->args['filterIdentical'], $array);
	}


	/**
	 * Nastaví počet záznamů na stránku
	 * @param int $count
	 */
	protected function setItemsPerPage($count = 15)
	{
		$this->args['itemsPerPage'] = ($count < 1 ? 15 : $count);
	}


	/**
	 * Povolit filtry v gridu?
	 * @param bool|array $filterableColums true pokud chcete povolit vsechny sloupce k fitlrace, pripadne pole povolenych sloupcu
	 */
	protected function enableFilters($filterableColums = true)
	{
		$this->args['filters'] = true;

		if ($filterableColums) {
			if (is_array($filterableColums)) {
				$this->setFilterableColumns($filterableColums);
			} else {
				$this->setFilterableColumns(array_keys($this->getModelColumns()));
			}
		}
	}


	/**
	 * Je filtrování v gridu povoleno?
	 * @return mixed
	 */
	private function isFilterable()
	{
		return $this->args['filters'];
	}


	/**
	 * Vrátí argument podle klíče pole arguments předaného konstruktorem
	 * Pokud neexistuje, vrátí NULL
	 * @param $key
	 * @return array|mixed
	 */
	protected function getArgument($key)
	{
		return (array_key_exists($key, $this->args) ? $this->args[$key] : NULL);
	}


	/**
	 * Můžete pretížit, pokud chcete doplnit vlastní logiku pro filtrování sloupce
	 * @return Container
	 */
	protected function getFilterContainer()
	{
		return new Container;
	}


	/**
	 * @return array
	 */
	protected function getModelColumns()
	{
		$modelColumns = $this->model->getColumns();
		$modelColumns = array_combine(array_column($modelColumns, 'name'), $modelColumns);
		return $modelColumns;
	}

	/** @return \ArrayIterator<string, Column>|null */
	public function getColumns(): ?\ArrayIterator
	{
		return isset($this['columns']) ? new \ArrayIterator($this['columns']->getComponents()) : null;
	}


	/** @return \ArrayIterator<string, RowAction>|null */
	public function getRowActions(): ?\ArrayIterator
	{
		return isset($this['rowActions']) ? new \ArrayIterator($this['rowActions']->getComponents()) : null;
	}

}
