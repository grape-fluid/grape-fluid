<?php

namespace Grapesc\GrapeFluid\Extenders;

use Nette\DI\CompilerExtension;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class ExtenderExtension extends CompilerExtension
{

	public function loadConfiguration()
	{
		$builder   = $this->getContainerBuilder();
		$extenders = $this->getConfig();

		foreach ($extenders as $tag => $items) {
			foreach ($items AS $key => $extender) {
				$builder->addDefinition("fluid.extender.$tag.$key")
					->setClass($extender)
					->setAutowired(false)
					->setInject(true)
					->addTag("fluid.extender.$tag");
			}
		}
	}
	
}