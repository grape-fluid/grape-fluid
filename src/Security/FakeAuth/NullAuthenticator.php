<?php

namespace Grapesc\GrapeFluid\Security\FakeAuth;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class NullAuthenticator implements IAuthenticator
{

	/** @inheritdoc */
	function authenticate(array $credentials)
	{
		throw new AuthenticationException('Authentication not required');
	}

}