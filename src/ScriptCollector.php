<?php

namespace Grapesc\GrapeFluid;

use Nette\Application\UI\ITemplate;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 */
class ScriptCollector
{

	/** @var ITemplate[] */
	private $templates = [];


	/**
	 * Přidá template k vyrenderování
	 *
	 * @param ITemplate $template
	 */
	public function push($template)
	{
		$this->templates[] = $template;
	}


	public function render()
	{
		foreach ($this->templates as $template) {
			$template->render();
		}
	}

}