<?php

namespace Grapesc\GrapeFluid\Extenders;

use Nette\DI\CompilerExtension;
use Nette\DI\Extensions\InjectExtension;


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
					->setFactory($extender)
					->setAutowired(false)
					->addTag(InjectExtension::TagInject)
					->addTag("fluid.extender.$tag");
			}
		}
	}
	
}