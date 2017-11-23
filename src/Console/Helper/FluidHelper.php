<?php

namespace Grapesc\GrapeFluid\Console\Helper;

use Grapesc\GrapeFluid\BaseParametersRepository;
use Grapesc\GrapeFluid\ModuleRepository;
use Grapesc\GrapeFluid\Plugins\Container;
use Symfony\Component\Console\Helper\Helper;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class FluidHelper extends Helper
{

	/** @var BaseParametersRepository */
	private $baseParametersRepository;
	
	/** @var ModuleRepository */
	private $moduleRepository;
	
	/** @var Container */
	private $pluginsContainer;


	public function __construct(BaseParametersRepository $baseParametersRepository, ModuleRepository $moduleRepository, Container $pluginsContainer)
	{
		$this->baseParametersRepository = $baseParametersRepository;
		$this->moduleRepository         = $moduleRepository;
		$this->pluginsContainer         = $pluginsContainer;
	}


	/**
	 * {@inheritdoc}
	 */
	public function getName()
	{
		return "fluid";
	}


	/**
	 * @return BaseParametersRepository
	 */
	public function getBaseParametersRepository()
	{
		return $this->baseParametersRepository;	
	}


	/**
	 * @return ModuleRepository
	 */
	public function getModuleRepository()
	{
		return $this->moduleRepository;
	}


	/**
	 * @return Container
	 */
	public function getPluginsRepository()
	{
		return $this->pluginsContainer;
	}
	
}

