<?php

namespace Grapesc\GrapeFluid\Security;
use Nette;

class User extends Nette\Security\User
{

	/** @var NamespacesRepository  */
	private $namespacesRepository;


	/**
	 * Ma uzivatel pristup k zadanemu zdroji?
	 * @param string $resource - Jmeno zdroje
	 * @param $privilege - Pouze pro zachování kompatibility s Nette\Security\User
	 * @return bool
	 */
	public function isAllowed($resource = Nette\Security\IAuthorizator::ALL, $privilege = Nette\Security\IAuthorizator::ALL)
	{
		$defaultNamespace = null;

		if (strpos($resource, ":") !== false) {
			$exploded = explode(":", $resource);
			if (count($exploded) == 2) {
				list($namespace, $resource) = $exploded;
				$defaultNamespace = $this->namespacesRepository->getDefaultNamespace();
				$this->namespacesRepository->setCurrentNamespace($namespace);
			}
		}

		foreach ($this->getRoles() as $role) {
			if ($this->getAuthorizator()->isAllowed($role, $resource, $privilege)) {
				$this->namespacesRepository->setCurrentNamespace($defaultNamespace);
				return true;
			}
		}

		$this->namespacesRepository->setCurrentNamespace($defaultNamespace);
		return false;
	}


	/**
	 * @param NamespacesRepository $namespacesRepository
	 */
	public function setNamespacesRepository(NamespacesRepository $namespacesRepository)
	{
		$this->namespacesRepository = $namespacesRepository;
	}

}