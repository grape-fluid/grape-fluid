<?php

namespace Grapesc\GrapeFluid\Security;

use Grapesc\GrapeFluid\EventDispatcher;
use Nette\DI\Container;
use Nette\Security\IAuthenticator;
use Nette\Security\IAuthorizator;
use Nette\Security\UserStorage;
use Psr\Log\LoggerInterface;

/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class NamespacesRepository
{

	/**
	 * @var AuthNamespace[]
	 */
	protected $authNamespaces = [];

	/** @var AuthNamespace */
	protected $current;

	/** @var AuthNamespace */
	protected $default;

	/** @var IUserStorage */
	protected $userStorage;

	/** @var RolesRepository */
	private $rolesRepository;

	/** @var EventDispatcher */
	private $eventDispatcher;

	/** @var LoggerInterface */
	private $logger;


	function __construct(
		array $params = [],
		?Container $container = null,
		?UserStorage $userStorage = null,
		?EventDispatcher $eventDispatcher = null,
		?LoggerInterface $logger = null,
	)
	{
		$this->userStorage     = $userStorage;
		$this->eventDispatcher = $eventDispatcher;
		$this->logger          = $logger;

		if ($params) {
			$this->build($params, $container);
		}
	}


	/**
	 * @param $name
	 */
	public function setCurrentNamespace($name)
	{
		if (!$this->default) {
			$this->default = key_exists($name, $this->authNamespaces) ? $this->authNamespaces[$name] : null;
		}

		$this->current = key_exists($name, $this->authNamespaces) ? $this->authNamespaces[$name] : $this->current;

		if ($this->userStorage instanceof UserStorage) {
			$this->userStorage->setNamespace($this->current ? $this->current->getName() : null);
		}
	}


	/**
	 * @return null|string
	 */
	public function getCurrentNamespace()
	{
		return $this->current ? $this->current->getName() : null;
	}


	/**
	 * @return null|string
	 */
	public function getDefaultNamespace()
	{
		return $this->default ? $this->default->getName() : null;
	}


	/**
	 * @param AuthNamespace $authNamespace
	 */
	public function addAuthNamespace(AuthNamespace $authNamespace)
	{
		$this->authNamespaces[$authNamespace->getName()] = $authNamespace;
	}


	/**
	 * @param $params
	 * @return IAuthorizator
	 */
	public function getAuthorizator($params)
	{
		return $this->current->getAuthorizator();
	}


	/**
	 * @param $params
	 * @return IAuthenticator
	 */
	public function getAuthenticator($params)
	{
		return $this->current->getAuthenticator();
	}


	/**
	 * @return RolesRepository
	 */
	public function getRoles()
	{
		if (!$this->rolesRepository) {
			$this->rolesRepository = new RolesRepository();
			foreach ($this->authNamespaces as $namespace) {
				$roles = $namespace->getRoles();
				if (is_array($roles)) {
					foreach ($namespace->getRoles() as $role) {
						$this->rolesRepository->addRole($namespace->getName(), $role, $role);
					}
				}
			}

			try {
				$this->eventDispatcher->dispatch(new NamespacesRolesEvent($this->rolesRepository), 'fluid.security.namespaces.roles');
			} catch (\Exception $e) {

				$this->logger->error('Cannot get roles.', [ 'excpetion' => $e]);

				$this->logger->warning('{"component":"NamespaceRepository", "message":Cannot get roles via Api. Error (' . $e->getMessage() . ') "}');
			}

		}
		return $this->rolesRepository;
	}


	/**
	 * @param null $namespace
	 * @return null|string
	 */
	public function getForbiddenRedirectLink($namespace = null)
	{
		if (is_null($namespace)) {
			return $this->current->getForbiddenRedirectLink();
		} elseif (array_key_exists($namespace, $this->authNamespaces)) {
			return $this->authNamespaces[$namespace]->getForbiddenRedirectLink();
		} else {
			return null;
		}
	}


	/**
	 * @param $params
	 * @param Container $container
	 */
	protected function build(array $params, Container $container)
	{
		foreach ($params AS $namespace => $config) {
			$an = new AuthNamespace($namespace);

			$an->setAuthenticator($container->getService("fluid.security.$namespace.authenticator"));
			$an->setAuthorizator($container->getService("fluid.security.$namespace.authorizator"));

//			$serviceName = "fluid.security.$namespace.authenticator";
//			$authenticator = $container->getService($serviceName);
//			$an->setAuthenticator($authenticator);
//			$serviceName = "fluid.security.$namespace.authorizator";
//			$authorizator = $container->getService($serviceName);
//			$an->setAuthorizator($authorizator);

			if (key_exists('roles', $config)) {
				$an->setRoles($config['roles']);
			}

			if (key_exists('forbiddenRedirectLink', $config)) {
				$an->setForbiddenRedirectLink($config['forbiddenRedirectLink']);
			}

			$this->addAuthNamespace($an);
		}
	}

}