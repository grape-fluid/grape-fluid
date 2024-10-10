<?php

namespace Grapesc\GrapeFluid\LatteMacro;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;


class IfModuleNode extends StatementNode
{
	public ArrayNode $module;

	public static function create(Tag $tag): self
	{
		$node         = new self;
		$node->module = $tag->parser->parseExpression();

		return $node;
	}

	public function print(PrintContext $context): string
	{
		return $context->format(
			'if ($presenter->ifModuleExists(%node)): ',
			$this->module
		);
	}

	public function &getIterator(): \Generator
	{
		yield $this->module;
	}
}