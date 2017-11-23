<?php

namespace Grapesc\GrapeFluid\Security;

use Nette\Security\IAuthorizator;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class Authorizator implements IAuthorizator
{
	
	/** @var NamespacesRepository */
	private $namespacesRepository;
	
	public function __construct(NamespacesRepository $namespacesRepository)
	{
		$this->namespacesRepository = $namespacesRepository;
	}

	
	/** @inheritdoc */
	function isAllowed($role, $resource, $privilege)
	{
		$params       = [];
		$authorizator = $this->namespacesRepository->getAuthorizator($params);
		return $authorizator->isAllowed($role, $resource, $privilege);
	}
	
}