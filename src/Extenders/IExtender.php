<?php

namespace Grapesc\GrapeFluid\Extenders;

use Grapesc\GrapeFluid\EventDispatcher;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
interface IExtender
{

	/**
	 * @param EventDispatcher $eventDispatcher
	 * @param string $className
	 * @return void
	 */
	public function register(EventDispatcher $eventDispatcher, $className);
	
}