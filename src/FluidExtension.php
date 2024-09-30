<?php

namespace Grapesc\GrapeFluid;

use Grapesc\GrapeFluid\Security\NamespacesRepository;
use Nette\DI\CompilerExtension;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class FluidExtension extends CompilerExtension
{
	
	/** @var array */
	private $appDir;


	public function __construct($appDir)
	{
		$this->appDir = $appDir;
	}

	
	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('parameters'))
			->setClass(BaseParametersRepository::class);

		$builder->addDefinition($this->prefix('translator'))
			->setFactory(FluidTranslator::class, [$builder->parameters['translator']]);

		$builder->addDefinition($this->prefix('eventDispatcher'))
			->setClass(EventDispatcher::class)
			->addSetup('addServiceListeners', [$builder->parameters['eventListeners']]);
		
		$builder->addDefinition($this->prefix('migration'))
			->setClass(MigrationService::class);

		$builder->addDefinition($this->prefix('moduleRepository'))
			->setClass(ModuleRepository::class);

		foreach ($this->getConfig()['security'] as $namespace => $config) {
			if (is_array($config['authorizator'])) {
				$authorizatorClass = $config['authorizator']['class'];
				$authorizatorArguments = $config['authorizator']['arguments'] ?? [];
			} else {
				$authorizatorClass = is_object($config['authorizator']) ? $config['authorizator']->getEntity() : $config['authorizator'];
				$authorizatorArguments = is_object($config['authorizator']) ? $config['authorizator']->arguments : [];
			}

			$builder->addDefinition($this->prefix("security.$namespace.authorizator"))
				->setClass($authorizatorClass)
				->setArguments($authorizatorArguments)
				->setAutowired(false);

			$builder->addDefinition($this->prefix("security.$namespace.authenticator"))
				->setClass(is_object($config['authenticator']) ? $config['authenticator']->getEntity() : $config['authenticator'])
				->setArguments(is_object($config['authenticator']) ? $config['authenticator']->arguments : [])
				->setAutowired(false);
		}
	
		$builder->addDefinition($this->prefix('security.repository'))
			->setFactory(NamespacesRepository::class, [$this->getConfig()['security']]);
	}
	
}