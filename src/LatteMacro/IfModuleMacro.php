<?php

namespace Grapesc\GrapeFluid\LatteMacro;

use Latte\Extension;

class IfModuleMacro extends Extension
{
	public function getTags(): array
	{
		return [
			'ifModule' => [IfModuleNode::class, 'create'],
		];
	}
}