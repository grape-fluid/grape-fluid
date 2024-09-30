<?php

namespace Grapesc\GrapeFluid\Security;


/**
 * @author Jiri Novy <novy@grapesc.cz>
 */
class NamespacesRolesEvent
{

	/** @var RolesRepository */
	private $rolesRepository;


	/**
	 * NamespacesRolesEvent constructor.
	 * @param RolesRepository $rolesRepository
	 */
	public function __construct(RolesRepository $rolesRepository)
	{
		$this->rolesRepository = $rolesRepository;
	}


	/**
	 * @return RolesRepository
	 */
	public function getRolesRepository()
	{
		return $this->rolesRepository;
	}

}