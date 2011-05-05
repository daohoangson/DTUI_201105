<?php

class XenForo_Importer_vBulletin36x extends XenForo_Importer_vBulletin
{
	public static function getName()
	{
		return 'vBulletin 3.6';
	}

	public function getSteps()
	{
		$steps = parent::getSteps();

		unset($steps['visitorMessages']);

		return $steps;
	}
}

?>