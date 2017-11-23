<?php

namespace Grapesc\GrapeFluid\Model;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class MigrationModel extends BaseModel
{

	/**
	 * @inheritdoc
	 */
	public function getTableName()
	{
		return "migration";
	}

}
