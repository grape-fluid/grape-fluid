<?php

namespace Grapesc\GrapeFluid;

use Nette\Application\IRouter;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use ReflectionClass;


class RouterFactory
{

	/**
	 * @param array $parameters
	 * @return IRouter
	 */
	public static function createRouter(array $parameters)
	{
		$router = new RouteList();

		$lastRoutes = $parameters['base'];
		ksort($lastRoutes);
		unset($parameters['base']);

		foreach ($parameters AS $module => $routes) {
			ksort($routes);

			$routeList = new RouteList(ucfirst($module));
			foreach ($routes AS $routeArgs) {
				$routeList[] = (new ReflectionClass(Route::class))->newInstanceArgs($routeArgs);
			}
			$router[] = $routeList;
		}

		foreach ($lastRoutes AS $routeArgs) {
			$router[] = (new ReflectionClass(Route::class))->newInstanceArgs($routeArgs);
		}

		$router[] = new Route("<module>/<presenter>[/<action>]");

		return $router;
	}

}
