<?php

namespace Grapesc\GrapeFluid\Security;

use Nette\Security\IAuthenticator;
use Nette\Security\IAuthorizator;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class AuthNamespace
{

	/** @var string */
	private $name;

	/** @var array */
	private $roles;

	/** @var string */
	private $forbiddenRedirectLink;

	/** @var IAuthenticator */
	private $authenticator;

	/** @var IAuthorizator */
	private $authorizator;


	public function __construct($name)
	{
		$this->name = $name;
	}


	/**
	 * @param array $roles
	 */
	public function setRoles(array $roles)
	{
		$this->roles = $roles;
	}


	/**
	 * @return array
	 */
	public function getRoles()
	{
		return $this->roles;
	}


	/**
	 * @param IAuthorizator $authorizator
	 */
	public function setAuthorizator(IAuthorizator $authorizator)
	{
		$this->authorizator = $authorizator;
	}


	/**
	 * @param IAuthenticator $authenticator
	 */
	public function setAuthenticator(IAuthenticator $authenticator)
	{
		$this->authenticator = $authenticator;
	}


	/**
	 * @param string|null $link
	 */
	public function setForbiddenRedirectLink($link = null)
	{
		$this->forbiddenRedirectLink = $link;
	}


	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * @return IAuthorizator
	 */
	public function getAuthorizator()
	{
		return $this->authorizator;
	}


	/**
	 * @return IAuthenticator
	 */
	public function getAuthenticator()
	{
		return $this->authenticator;
	}


	/**
	 * @return string
	 */
	public function getForbiddenRedirectLink()
	{
		return $this->forbiddenRedirectLink;
	}

}