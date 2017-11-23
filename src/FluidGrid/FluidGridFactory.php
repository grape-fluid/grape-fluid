<?php
namespace Grapesc\GrapeFluid\FluidGrid;


use Grapesc\GrapeFluid\EventDispatcher;
use Grapesc\GrapeFluid\FluidTranslator;
use Grapesc\GrapeFluid\Logger;
use Grapesc\GrapeFluid\Model\BaseModel;
use Nette\DI\Container;
use Nette\Reflection\ClassType;
use Nette\Utils\Reflection;

class FluidGridFactory
{

	/** @var EventDispatcher */
	private $dispatcher;

	/** @var FluidTranslator */
	private $translator;

	/** @var Logger */
	private $logger;

	/** @var Container */
	private $container;


	public function __construct(EventDispatcher $dispatcher, FluidTranslator $translator, Logger $logger, Container $container)
	{
		$this->dispatcher = $dispatcher;
		$this->translator = $translator;
		$this->logger     = $logger;
		$this->container  = $container;
	}


	/**
	 * @param string $fluidGridClass
	 * @param string|BaseModel $model
	 * @return FluidGrid
	 */
	public function create($fluidGridClass, $model = null)
	{
		$reflection = new ClassType($fluidGridClass);
		if ($reflection->isSubclassOf(FluidGrid::class)) {

			if (is_null($model)) {
				$type = null;

				if ($reflection->hasAnnotation('model')) {
					$type = Reflection::expandClassName($reflection->getAnnotation('model'), $reflection);
				} elseif ($reflection->getProperty('model')->getAnnotation('var') != 'BaseModel') {
					$type = Reflection::expandClassName($reflection->getProperty('model')->getAnnotation('var'), $reflection);
				}

				if ($type) {
					if (class_exists($type)) {
						$modelReflection = new ClassType($type);
					} elseif (class_exists($reflection->getAnnotation('model'))) {
						$modelReflection = new ClassType($reflection->getAnnotation('model'));
					}

					if ($modelReflection->isInstantiable() AND $this->container->getByType($modelReflection->getName(), false)) {
						$model = $this->container->getByType($modelReflection->getName());
					}
				}
			} elseif (!is_object($model)) {
				$model = $this->container->getByType($model, false);
			}

			if (!is_object($model) OR !$model instanceof BaseModel) {
				throw new \InvalidArgumentException("Model must be instance of BaseModel.");
			}

			$fluidGrid = $reflection->newInstance($model, $this->translator);
			$this->container->callInjects($fluidGrid);

			return $fluidGrid;
		} else {
			throw new \InvalidArgumentException("Is not type of FluidGrid");
		}
	}

}