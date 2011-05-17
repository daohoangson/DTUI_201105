<?php

class XenForo_Model_Transmogrifier extends XenForo_Model
{
	const TRANSMOGRIFIER_KEY = 'transmogrifierResets';

	/**
	 * @return array
	 */
	public function getTransmogrificationCount()
	{
		$data = $this->_getDataRegistryModel()->get(self::TRANSMOGRIFIER_KEY);

		if (!isset($data['count']))
		{
			return array(
				'count' => 0,
				'date' => 0,
				'user_id' => XenForo_Visitor::getUserId()
			);
		}

		return $data;
	}

	/**
	 * @return array
	 */
	public function resetTransmogrifier()
	{
		$data = $this->getTransmogrificationCount();

		$this->_setTransmogrifier(++$data['count']);

		return $data;
	}

	protected function _setTransmogrifier($count)
	{
		$this->_getDataRegistryModel()->set(self::TRANSMOGRIFIER_KEY, array(
			'count' => $count,
			'date' => XenForo_Application::$time,
			'user_id' => XenForo_Visitor::getUserId()
		));
	}
}