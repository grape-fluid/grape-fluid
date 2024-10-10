<?php

namespace Grapesc\GrapeFluid;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class EventDispatcher extends \Symfony\Component\EventDispatcher\EventDispatcher
{
	
	/** @var \Nette\DI\Container */
	private $container;
	
	/** @var array */
	private $serviceListeners = [];
	
	
	public function __construct(\Nette\DI\Container $container)
	{
		$this->container = $container;
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public function dispatch(object $event, ?string $eventName = null): object
	{
		$this->loadServiceListeners($eventName);
		return parent::dispatch($event, $eventName);
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public function hasListeners(?string $eventName = null): bool
	{
		if ($eventName === null) {
			return count($this->serviceListeners) || count(parent::getListeners($eventName));
		}
		if (isset($this->serviceListeners[$eventName])) {
			return true;
		}
		return parent::hasListeners($eventName);
	}


	/**
	 * {@inheritDoc}
	 */
	public function getListeners(?string $eventName = null): array
	{
		$this->loadServiceListeners($eventName);
		return parent::getListeners($eventName);
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public function removeListener(string $eventName, callable|array $listener): void
	{
		if (isset($this->serviceListeners[$eventName])) {
			foreach ($this->serviceListeners[$eventName] AS &$service) {
				$service['loaded'] = false;
			}
		}
		parent::removeListener($eventName, $listener);
	}

	
	/**
	 * @param array $listeners
	 */
	public function addServiceListeners(array $listeners)
	{
		foreach ($listeners AS $eventName => $listener) {
			if (is_array($listener)) {
				if (isset($listener['service'])) {
					$this->addServiceListener($eventName, $listener['service'],
						isset($listener['method']) ? $listener['method'] : 'process',
						isset($listener['priority']) ? $listener['priority'] : 0
					);
				} else {
					foreach ($listener as $l) {
						if (isset($l['service'])) {
							$this->addServiceListener($eventName, $l['service'],
								isset($l['method']) ? $l['method'] : 'process',
								isset($l['priority']) ? $l['priority'] : 0
							);
						} elseif (!is_array($l)) {
							$this->addServiceListener($eventName, $l);
						}
					}
				}
			} else {
				$this->addServiceListener($eventName, $listener);
			}
		}
	}


	/**
	 * @param string $eventName
	 * @param string $service
	 * @param string $method
	 * @param int $priority
	 */
	public function addServiceListener($eventName, $service, $method = 'process', $priority = 0)
	{
		if (!isset($this->serviceListeners[$eventName])) {
			$this->serviceListeners[$eventName] = [];
		}

		$this->serviceListeners[$eventName][] = ['service' => $service, 'method' => $method, 'priority' => $priority, 'loaded' => false];
	}


	/**
	 * Nacteni registorvanych listeneru z DI kontajneru
	 * @param string $eventName Omezeni nacteni na konkretni eventName, kvuli rychlosti (ne vzdy je treba vytvaret vsechny sluzby)
	 */
	private function loadServiceListeners($eventName = null)
	{
		if (is_null($eventName)) {
			foreach (array_keys($this->serviceListeners) as $eventName) {
				$this->loadServiceListeners($eventName);
			}
		} elseif (isset($this->serviceListeners[$eventName])) {
			foreach ($this->serviceListeners[$eventName] as &$service) {
				if ($service['loaded'] === false) {
					if ($this->container->hasService($service['service'])) {
						$listener = $this->container->getService($service['service']);
					} else {
						$listener = $this->container->getByType($service['service']);
					}
					$this->addListener($eventName, array($listener, $service['method']), $service['priority']);
					$service['loaded'] = true;
				}
			}
		}
	}

}
