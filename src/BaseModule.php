<?php

namespace Grapesc\GrapeFluid;

use Nette\Configurator;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class BaseModule
{
	
	/** @var Configurator $configurator */
	private $configurator;

	/** @var array $parents */
	protected $parents = [];

	/** @var array|BaseParametersRepository $params */
	private $params = [];

	/** @var string */
	private $moduleDir;

	/** @var string */
	protected $commandsDirectory = "commands";

	
	public function __construct(Configurator $configurator, BaseParametersRepository $params)
	{
		$this->configurator = $configurator;
		$this->params       = $params;
	}

	
	/**
	 * @return mixed
	 */
	public function getClassName()
	{
		return get_class($this);
	}


	/**
	 * @return mixed
	 */
	public function getModuleName()
	{
		return explode("\\", get_class($this))[4];
	}


	public function run()
	{
		$this->registerConfig($this->configurator);
	}


	/**
	 * @return array
	 */
	public function getParents()
	{
		return $this->getModuleName() !== 'CoreModule' ? array_merge(["CoreModule"], $this->parents) : [];
	}


	/**
	 * @param string $dir
	 */
	public function setModuleDir($dir)
	{
		$this->moduleDir = $dir;
	}


	/**
	 * @return string
	 */
	public function getModuleDir()
	{
		return $this->moduleDir;
	}


	/**
	 * @return bool|string
	 */
	public function getCommandsDirectory()
	{
		return $this->moduleDir . DIRECTORY_SEPARATOR . $this->commandsDirectory;
	}

	
	/**
	 * Zaregistruje config soubor(y) daného modulu
	 * @param Configurator $configurator
	 */
	protected function registerConfig(Configurator $configurator)
	{
		$configurator->addConfig($this->params->getParam("moduleDir") . $this->getModuleName() . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "config.neon");
	}

}