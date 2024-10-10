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
	function isAllowed(?string $role, ?string $resource, ?string $privilege): bool
	{
		$params       = [];
		$authorizator = $this->namespacesRepository->getAuthorizator($params);
		return $authorizator->isAllowed($role, $resource, $privilege);
	}
	
}