<?php

namespace Grapesc\GrapeFluid\Security;

/**
 * @author Jiri Novy <novy@grapesc.cz>
 */
class RolesRepository
{

	/** @var array */
	private $roles = [];


	/**
	 * @param $namespace
	 * @param $role
	 * @param null $name
	 */
	public function addRole($namespace, $role, $name = null)
	{
		if (!array_key_exists($namespace, $this->roles)) {
			$this->roles[$namespace] = [];
		}

		$this->roles[$namespace][$role] = $name ?: $role;
	}


	/**
	 * @param null $namespace
	 * @return array|mixed
	 */
	public function getRoles($namespace = null) {
		if ($namespace == null) {
			return $this->roles;
		}

		if (array_key_exists($namespace, $this->roles)) {
			return $this->roles[$namespace];
		} else {
			return [];
		}
	}

}