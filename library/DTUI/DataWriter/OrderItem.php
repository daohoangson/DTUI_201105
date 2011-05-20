<?php
class DTUI_DataWriter_OrderItem extends XenForo_DataWriter {
	const STATUS_WAITING = 'waiting';
	const STATUS_PREPARED = 'prepared';
	const STATUS_SERVED = 'served';
	const STATUS_PAID = 'paid';
	
	const SET_STATUS_FROM_INSIDE = 'setStatusFromInside';
	
	protected static $_onlineUsers = false;
		
	public function updateStatus(array $user) {
		$oldStatus = $this->get('status');
		$next = false;
		
		if (empty($oldStatus)) {
			$next = self::STATUS_WAITING;
		} else {
			switch ($oldStatus) {
				case self::STATUS_WAITING:
					$next = self::STATUS_PREPARED;
					break;
				case self::STATUS_PREPARED:
					$next = self::STATUS_SERVED;
					break;
				case self::STATUS_SERVED:
					$next = self::STATUS_PAID;
					break;
			}
		}
		
		if (empty($next)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_unable_to_update_order_item_status'), true);
		}
		
		$this->set('status', $next, '', array(self::SET_STATUS_FROM_INSIDE => true));
		$this->set('target_user_id', $this->_findTargetUserId($next, $user['user_id']), '', array(self::SET_STATUS_FROM_INSIDE => true));
		
		if ($next == self::STATUS_WAITING) {
			$this->set('trigger_user_id', $user['user_id']);
			$this->set('order_item_date', XenForo_Application::$time);
		} else {
			$this->set('updated_' . $oldStatus . '_user_id', $user['user_id'], '', array('ignoreInvalidFields' => true));
			$this->set('updated_' . $oldStatus . '_date', XenForo_Application::$time, '', array('ignoreInvalidFields' => true));
		}
	}
	
	public function revertStatus(array $user) {
		$oldStatus = $this->get('status');
		$next = false;
		
		switch ($oldStatus) {
			case self::STATUS_PREPARED:
				$next = self::STATUS_WAITING;
				break;
			case self::STATUS_SERVED:
				$next = self::STATUS_PREPARED;
				break;
			case self::STATUS_PAID:
				$order = $this->getModelFromCache('DTUI_Model_Order')->getOrderById($this->get('order_id'));
				if ($order['is_paid']) {
					// the order is marked as paid
					// it's too complicated to revert such a change
					// so... we are preventing it here
					throw new XenForo_Exception(new XenForo_Phrase('dtui_unable_to_update_order_item_status_of_paid'), true);
				} else {
					$next = self::STATUS_SERVED;
				}
				break;
		}
		
		if (empty($next)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_unable_to_update_order_item_status'), true);
		}
		
		$updatedUserId = $this->get('updated_' . $next . '_user_id');
		if ($updatedUserId != $user['user_id']) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_unable_to_update_order_item_status_of_other'), true);
		}
		
		$this->set('status', $next, '', array(self::SET_STATUS_FROM_INSIDE => true));
		$this->set('target_user_id', $user['user_id'], '', array(self::SET_STATUS_FROM_INSIDE => true));
		$this->set('updated_' . $next . '_user_id', 0, '', array('ignoreInvalidFields' => true));
		$this->set('updated_' . $next . '_date', 0, '', array('ignoreInvalidFields' => true));
	}
	
	protected function _findTargetUserId($status, $triggerUserId) {
		if ($status == self::STATUS_PAID) {
			// no more target for PAID
			return 0;
		}
		
		$sessionModel = $this->getModelFromCache('XenForo_Model_Session');
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$orderItemModel = $this->_getOrderItemModel();
		
		// get online users
		if (self::$_onlineUsers === false) {
			$sessionConditions = array(
				'cutOff' => array('>', $sessionModel->getOnlineStatusTimeout()),
				'getInvisible' => true,
				'getUnconfirmed' => true,
			);
			$sessionFetchOptions = array(
				'join' => XenForo_Model_Session::FETCH_USER,
			);
			self::$_onlineUsers = $sessionModel->getSessionActivityRecords($sessionConditions, $sessionFetchOptions);
		}
		$onlineUsers = self::$_onlineUsers;
		
		// get outstanding items
		$orderItemConditions = array(
			'status' => $status,
		);
		$outstandingItems = $orderItemModel->getAllOrderItem($orderItemConditions);
		
		// filter out non-target users
		$usergroups = DTUI_Option::get('target' . ucwords($status));
		foreach (array_keys($onlineUsers) as $key) {
			$isMember = false;
			
			foreach ($usergroups as $userGroupId) {
				if ($userModel->isMemberOfUserGroup($onlineUsers[$key], $userGroupId)) {
					$isMember = true;
					break;
				}
			}
			
			// WE DON'T WANT USER TO ASSIGN THEM SELF, IT DOESN'T MAKE ANY SENSE!!!!
			if ($onlineUsers[$key]['user_id'] == $triggerUserId) {
				$isMember = false;
			}
			
			if (empty($isMember)) {
				unset($onlineUsers[$key]);
			}
		}
		
		// check to see if we have any target online
		if (empty($onlineUsers)) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_no_target_user_found_to_assign_task'), true);
		}
		
		// counting for tasks per user
		$count = array();
		foreach ($onlineUsers as $user) {
			$count[$user['user_id']] = 0;
		}
		foreach ($outstandingItems as $item) {
			if (isset($count[$item['target_user_id']])) {
				$count[$item['target_user_id']]++;
			}
		}
		
		// look for candidates
		asort($count);
		$userIds = array_keys($count);
		$countValues = array_values($count);
		$minCountValue = array_shift($countValues);
		$candidateUserIds = array();
		foreach ($count as $userId => $countValue) {
			if ($countValue == $minCountValue) {
				$candidateUserIds[] = $userId;
			}
		}
		
		// pick one candidate
		$rand = array_rand($candidateUserIds);
		$targetUserId = $candidateUserIds[$rand];
		
		return $targetUserId;
	}
	
	protected function _preSave() {
		if ($this->isInsert() AND $this->get('status') != self::STATUS_WAITING) {
			throw new XenForo_Exception(new XenForo_Phrase('dtui_new_order_item_must_be_in_waiting_status'), true);
		}
		
		$this->set('last_updated', XenForo_Application::$time);
		
		return parent::_preSave();
	}
	
	protected function _postSave() {
		if ($this->get('status') == self::STATUS_PAID) {
			// update the order if no other order item of the same order is pending (status != STATUS_PAID)
			$orderItems = $this->_getOrderItemModel()->getAllOrderItem(array(
				'order_id' => $this->get('order_id'),
			));
			$pendingOrderItems = array();
			foreach ($orderItems as $key => $orderItem) {
				if ($orderItem['status'] != self::STATUS_PAID) {
					$pendingOrderItems[$key] = $orderItem;
				}
			}
			
			if (empty($pendingOrderItems)) {
				// no more item found
				// update the order now!
				$orderDw = XenForo_DataWriter::create('DTUI_DataWriter_Order');
				$orderDw->setExistingData($this->get('order_id'));
				$orderDw->set('is_paid', true);
				$orderDw->save();
			}
		}
		
		if ($this->isInsert()) {
			$this->_db->query('UPDATE `xf_dtui_item` SET item_order_count = item_order_count + 1 WHERE item_id = ?', $this->get('item_id'));
		}
		
		return parent::_postSave();
	}
	
	public function set($field, $value, $tableName = '', array $options = null) {
		if ($field == 'target_user_id' OR $field == 'status') {
			// check to make sure this is a valid set request (from inside)
			if (!empty($options[self::SET_STATUS_FROM_INSIDE])) {
				// confirmed
			} else {
				throw new XenForo_Exception(new XenForo_Phrase('dtui_illegal_status_change_detected'), true);
			}
		}
		
		return parent::set($field, $value, $tableName, $options);
	}
	
	protected function _getFields() {
		return array(
			'xf_dtui_order_item' => array(
				'order_item_id' => array('type' => 'uint', 'autoIncrement' => true),
				'order_id' => array('type' => 'uint', 'required' => true),
				'trigger_user_id' => array('type' => 'uint', 'required' => true),
				'target_user_id' => array('type' => 'uint', 'default' => 0),
				'item_id' => array('type' => 'uint', 'required' => true),
				'order_item_date' => array('type' => 'uint', 'required' => true),
				'status' => array(
					'type' => 'string',
					'allowedValues' => array(
						self::STATUS_WAITING,
						self::STATUS_PREPARED,
						self::STATUS_SERVED,
						self::STATUS_PAID,
					),
					'required' => true
				),
				'updated_waiting_user_id' => array('type' => 'uint', 'default' => 0),
				'updated_waiting_date' => array('type' => 'uint', 'default' => 0),
				'updated_prepared_user_id' => array('type' => 'uint', 'default' => 0),
				'updated_prepared_date' => array('type' => 'uint', 'default' => 0),
				'updated_served_user_id' => array('type' => 'uint', 'default' => 0),
				'updated_served_date' => array('type' => 'uint', 'default' => 0),
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'order_item_id')) {
			return false;
		}

		return array('xf_dtui_order_item' => $this->_getOrderItemModel()->getOrderItemById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('order_item_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	protected function _getOrderItemModel() {
		return $this->getModelFromCache('DTUI_Model_OrderItem');
	}
}