<?php

namespace Grapesc\GrapeFluid\Security;


use Symfony\Component\EventDispatcher\Event;

/**
 * @author Jiri Novy <novy@grapesc.cz>
 */
class NamespacesRolesEvent extends Event
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