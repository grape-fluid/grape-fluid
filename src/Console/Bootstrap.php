<?php

namespace Grapesc\GrapeFluid\Console;

use Grapesc\GrapeFluid\BaseBootstrap;
use Grapesc\GrapeFluid\Console\Helper\FluidHelper;
use Grapesc\GrapeFluid\Plugins\Helper\Classes;
use Nette\Utils\Finder;
use SplFileInfo;
use Symfony\Component\Console\Application AS ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class Bootstrap extends ConsoleApplication
{

	/** @var BaseBootstrap */
	private $bootstrap;

	/** @var bool */
	private $isInitialized = false;

	/** @var string */
	private $appDir;

	/** @var bool */
	private $withoutDIContainer = false;


	public function __construct($appDir)
	{
		$this->appDir = $appDir;
		parent::__construct();
	}


	/**
	 * {@inheritdoc}
	 */
	public function find($name)
	{
		$command = parent::find($name);
		if ($command AND !$this->withoutDIContainer AND !$command instanceof WithoutContainerCommand AND $command->getName() !== 'list') {
			$this->bootstrap->getContainer()->callInjects($command);
		}
		return $command;
	}


	/**
	 * {@inheritdoc}
	 */
	public function doRun(InputInterface $input, OutputInterface $output)
	{
		if (!$this->isInitialized) {
			$this->bootstrap = new BaseBootstrap($this->appDir, false);
			$this->registerCommands();
			$this->isInitialized = true;

			$helperSet = $this->getHelperSet();
			$helperSet->set(new FluidHelper($this->bootstrap->getBaseParametersRepository(), $this->bootstrap->getModuleRepository(), $this->bootstrap->getPluginContainer()));
		}

		return parent::doRun($input, $output);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function configureIO(InputInterface $input, OutputInterface $output)
	{
		if ($input->hasParameterOption('--without-container')) {
			$this->withoutDIContainer = true;
		}

		parent::configureIO($input, $output);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
	{
		if (!$this->withoutDIContainer AND !$command instanceof WithoutContainerCommand AND $command->getName() !== 'list') {
			$this->bootstrap->getContainer()->callInjects($command);
		}

		return parent::doRunCommand($command, $input, $output);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function getDefaultInputDefinition()
	{
		$inputDefinition = parent::getDefaultInputDefinition();
		$inputDefinition->addOption(new InputOption('--without-container', '', InputOption::VALUE_NONE, 'Run command without creating DI Container'));

		return $inputDefinition;
	}


	private function registerCommands()
	{
		$commandsDirectories = [];

		foreach ($this->bootstrap->getModuleRepository()->getModules() AS $module) {
			$commandsDir = $module->getCommandsDirectory();
			if (is_dir($commandsDir)) {
				$commandsDirectories[] = $commandsDir;
			}
		}

		foreach ($this->bootstrap->getPluginContainer()->getPlugins() AS $plugin) {
			$commandsDir = $plugin->getCommandsDirectory();
			if ($plugin->isEnable() AND is_dir($commandsDir)) {
				$commandsDirectories[] = $commandsDir;
			}
		};

		foreach (Finder::findFiles('*.php')->in($commandsDirectories) as $file) {
			$class = Classes::getClassesNameFromFile($file->getPathname());
			if ($class) {
				$this->registerCommand($file, $class[0]);
			}
		}

	}


	/**
	 * @param SplFileInfo $file
	 * @param string $className
	 */
	private function registerCommand(SplFileInfo $file, $className)
	{
		if (!class_exists($className)) {
			require ($file->getPathname());
		}

		$reflection = new \ReflectionClass($className);

		if ($reflection->isInstantiable()) {
			/** @var $command Command */
			$command = $reflection->newInstance();
			$this->add($command);
		}
	}

}

