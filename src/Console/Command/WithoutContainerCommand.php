<?php

namespace Grapesc\GrapeFluid\Console;

use Grapesc\GrapeFluid\Console\Helper\FluidHelper;
use Symfony\Component\Console\Command\Command;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class WithoutContainerCommand extends Command
{

	/**
	 * @return FluidHelper
	 */
	protected function getFluidHelper()
	{
		return $this->getHelper('fluid');
	}

}

