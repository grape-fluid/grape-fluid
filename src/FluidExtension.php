<?php

namespace Grapesc\GrapeFluid;

use Grapesc\GrapeFluid\Security\NamespacesRepository;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;


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
				$authorizatorFactory = new Statement($config['authorizator']['class'], $config['authorizator']['arguments'] ?? []);
			} elseif (is_object($config['authorizator'])) {
				$authorizatorFactory = $config['authorizator'];
			} else {
				$authorizatorFactory = $config['authorizator'];
			}

			$builder->addDefinition($this->prefix("security.$namespace.authorizator"))
				->setFactory($authorizatorFactory)
//				->setArguments(is_object($config['authorizator']) ? $config['authorizator']->arguments : [])
				->setAutowired(false);

			if (is_array($config['authenticator'])) {
				$authenticatorFactory = new Statement($config['authenticator']['class'], $config['authenticator']['arguments'] ?? []);
			} elseif (is_object($config['authenticator'])) {
				$authenticatorFactory = $config['authenticator'];
			} else {
				$authenticatorFactory = $config['authenticator'];
			}

			$builder->addDefinition($this->prefix("security.$namespace.authenticator"))
				->setFactory($authenticatorFactory)
//				->setArguments(is_object($config['authenticator']) ? $config['authenticator']->arguments : [])
				->setAutowired(false);
		}
	
		$builder->addDefinition($this->prefix('security.repository'))
			->setFactory(NamespacesRepository::class, [$this->getConfig()['security']]);
	}
	
}