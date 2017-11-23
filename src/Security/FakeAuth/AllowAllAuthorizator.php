<?php

namespace Grapesc\GrapeFluid\Security\FakeAuth;
use Nette\Security\IAuthorizator;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class AllowAllAuthorizator implements IAuthorizator
{
	
	/** @inheritdoc */
	function isAllowed($role, $resource, $privilege)
	{
		return true;
	}
	
}