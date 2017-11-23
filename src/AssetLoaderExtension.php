<?php

namespace Grapesc\GrapeFluid;

use Nette\DI\CompilerExtension;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 */
class AssetLoaderExtension extends CompilerExtension
{
	
	/** @var BaseParametersRepository */
	private $params;
	
	
	public function __construct(BaseParametersRepository $params)
	{
		$this->params = $params;
	}
	
	
	public function loadConfiguration()
	{
		$config  = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('assets'))
			->setClass('Grapesc\\GrapeFluid\\AssetRepository', [$this->params, $config]);
	}
	
}