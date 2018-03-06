<?php

namespace Grapesc\GrapeFluid;

use Exception;
use Grapesc\GrapeFluid\Extenders\ExtenderExtension;
use Grapesc\GrapeFluid\Model\MigrationModel;
use Grapesc\GrapeFluid\Plugins\Bridge\TracyBridge;
use Grapesc\GrapeFluid\Plugins\Container;
use Grapesc\GrapeFluid\Plugins\Bridge\ConfiguratorBridge;
use Grapesc\GrapeFluid\Plugins\Bridge\RobotLoaderBridge;
use Grapesc\GrapeFluid\Security\NamespacesRepository;
use Nette;
use Nette\Application\Application;
use Nette\Application\BadRequestException;
use Nette\Caching\Storages\FileStorage;
use Nette\DI;
use Nette\Http\IResponse;
use SplFileInfo;
use Tracy\Debugger;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class BaseBootstrap
{

	/** @var string */
	private $appDir;
	
	/** @var DI\Container */
	private $container;
	
	/** @var Nette\Configurator */
	private $configurator;
	
	/** @var ModuleRepository */
	private $moduleRepository;

	/** @var Container */
	private $pluginsContainer;

	/** @var BaseParametersRepository */
	private $fluidParameters;


	/**
	 * BaseBootstrap constructor.
	 * @param string $appDir
	 * @param bool $createContainer
	 */
	public function __construct($appDir, $createContainer = true)
	{
		$this->appDir          = realpath($appDir);
		$this->fluidParameters = new BaseParametersRepository($this->appDir, ['logDir', 'tempDir']);
		
		$this->boot($createContainer);
	}


	public function run()
	{
		try {
			$application = $this->container->getByType(Application::class);

			$application->onError[] = function (Application $application, $exception) {
				if ($exception instanceof BadRequestException && $exception->getHttpCode() == IResponse::S403_FORBIDDEN) {
					$forbiddenRedirectLink = $this->container->getByType(NamespacesRepository::class)->getForbiddenRedirectLink();
					if ($presenter = $application->getPresenter()) {
						$this->fluidParameters->setParam('redirect_url', $presenter->link($forbiddenRedirectLink));
					}
				}
			};

			$application->run();
		} catch (Exception $e) {
			$this->container->getByType(Logger::class)->critical("[Bootstrap] Application run error", ['exception' => $e]);
			throw $e;
		}
	}


	/**
	 * @param bool $createContainer
	 */
	protected function boot($createContainer = true)
	{
		$this->createPluginContainer();
		$this->createConfigurator();
		$this->configureDebug();
		$this->createModuleRepository();
		$this->loadConfigurations();
		
		if ($createContainer) {
			$this->bootContainer();
		}
	}
	
	
	protected function bootContainer()
	{
		$this->createContainer();
		$this->registerServices();
	}
	
	
	protected function registerServices()
	{
		$this->container->addService('fluid.parameters', $this->fluidParameters);
		$this->container->addService('fluid.migration', new MigrationService($this->container->getByType(MigrationModel::class), $this->moduleRepository, $this->fluidParameters));
		$this->container->addService('fluid.moduleRepository', $this->getModuleRepository());
		$this->container->addService('fluid.pluginsContainer', $this->pluginsContainer);
	}


	/**
	 * @throws Exception
	 * @throws \Throwable
	 */
	protected function loadConfigurations()
	{
		$this->configurator->addConfig(__DIR__ . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "base.neon");

		if ($this->configurator->isDebugMode()) {
			$this->configurator->addConfig( __DIR__ . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "debug.neon");
		}

		$this->configurator->addConfig($this->fluidParameters->getParam("configDir") . "config.neon");
		
		$this->moduleRepository->run();
		
		ConfiguratorBridge::loadConfigurations($this->pluginsContainer, $this->configurator);
		
		$this->configurator->addConfig($this->fluidParameters->getParam("configDir") . "config.local.neon");
	}
	
	
	protected function createConfigurator()
	{
		$configurator = new Nette\Configurator;
		$configurator->addParameters(["appDir"        => $this->fluidParameters->getParam('appDir')]);
		$configurator->addParameters(["logDir"        => $this->fluidParameters->getParam("logDir")]);
		$configurator->addParameters(["tempDir"       => $this->fluidParameters->getParam("tempDir")]);
		$configurator->addParameters(["moduleDir"     => $this->fluidParameters->getParam("moduleDir")]);
		$configurator->addParameters(["vendorDir"     => $this->fluidParameters->getParam("vendorDir")]);
		$configurator->addParameters(["wwwDir"        => $this->fluidParameters->getParam("wwwDir")]);
		$configurator->addParameters(["assetsDir"     => $this->fluidParameters->getParam("assetsDir")]);
		$configurator->addParameters(["grapeFluidDir" => $this->fluidParameters->getParam("grapeFluidDir")]);

		$configurator->setTempDirectory($this->fluidParameters->getParam("tempDir"));

		$loader = $configurator->createRobotLoader()
			->addDirectory($this->fluidParameters->getParam("moduleDir"));
		
		RobotLoaderBridge::registerInRobotLoader($this->pluginsContainer, $loader);
		
		$loader->register();
		
		$this->configurator = $configurator;
	}


	/**
	 * Nastaví chování v debug modu a chování dump / bdump
	 */
	protected function configureDebug()
	{
		$this->configurator->setDebugMode($this->fluidParameters->getParam("debug"));
		$this->configurator->enableDebugger($this->fluidParameters->getParam("logDir"));
		Debugger::$maxDepth = $this->fluidParameters->getParam("maxDepth", 2);
		Debugger::$maxLength = $this->fluidParameters->getParam("maxLength", 150);

		if ($this->configurator->isDebugMode()) {
			TracyBridge::registerPanelBar($this->pluginsContainer);
		}
	}


	/**
	 * Sestaví seznam dostupných modulů a pokusí se vytvořit instanci řídící třídy modulu
	 */
	protected function createModuleRepository()
	{
		$this->moduleRepository = new ModuleRepository($this->configurator, $this->fluidParameters);

		/** @var SplFileInfo $module */
		foreach (\Nette\Utils\Finder::findDirectories("*")->in($this->fluidParameters->getParam("moduleDir")) as $module) {
			$this->moduleRepository->addModule($module->getFilename(), $module->getPathname());
		}
	}
	
	
	/**
	 * @return void
	 */
	protected function createContainer()
	{
		$this->configurator->onCompile[] = function(Nette\Configurator $configurator, DI\Compiler $compiler) {
			$compiler->addExtension('fluid', new FluidExtension($this->appDir));
		};
		
		$this->configurator->onCompile[] = function(Nette\Configurator $configurator, DI\Compiler $compiler) {
			$compiler->addExtension('assets', new AssetLoaderExtension(
				$this->fluidParameters->getParam("appDir"),
				$this->fluidParameters->getParam("assetsDir"),
				$this->fluidParameters->getParam("dirPerm"),
				$this->fluidParameters->getParam("debug")
			));
		};

		$this->configurator->onCompile[] = function(Nette\Configurator $configurator, DI\Compiler $compiler) {
			$compiler->addExtension('extenders', new ExtenderExtension());
		};
		
		$this->container = $this->configurator->createContainer();
	}


	/**
	 * @return DI\Container
	 */
	public function getContainer()
	{
		if (!$this->container AND $this->configurator) {
			$this->bootContainer();
		} elseif (!$this->container) {
			throw new \LogicException("Container not created, call boot() and run() first.");
		}
		
		return $this->container;
	}


	/**
	 * @return ModuleRepository
	 */
	public function getModuleRepository()
	{
		if (!$this->moduleRepository) {
			throw new \LogicException("Module repository not created, call boot() and run() first.");
		}
		return $this->moduleRepository;
	}
	
	
	/**
	 * @return Container
	 */
	public function getPluginContainer()
	{
		if (!$this->pluginsContainer) {
			throw new \LogicException("Plugin container not created, call boot() and run() first.");
		}
		return $this->pluginsContainer;
	}


	/**
	 * @return BaseParametersRepository
	 */
	public function getBaseParametersRepository()
	{
		return $this->fluidParameters;
	}


	private function createPluginContainer()
	{
		$this->pluginsContainer = new Container(new FileStorage($this->fluidParameters->getParam('tempDir')));
		if ($this->fluidParameters->getParam('debug', false)) {
			$this->pluginsContainer->enableDebug();
		}
		$this->pluginsContainer->addPluginsDirectories($this->fluidParameters->getParam('pluginDirs', []));
	}

}
