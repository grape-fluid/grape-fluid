<?php

namespace Grapesc\GrapeFluid\Security;

use Nette\Security\IAuthenticator;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class Authenticator implements IAuthenticator
{

	/** @var NamespacesRepository */
	private $namespacesRepository;


	public function __construct(NamespacesRepository $namespacesRepository)
	{
		$this->namespacesRepository = $namespacesRepository;
	}


	/** @inheritdoc */
	function authenticate(array $credentials)
	{
		$params = [];
		return $this->namespacesRepository->getAuthenticator($params)->authenticate($credentials);
	}

}