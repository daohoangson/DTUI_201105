<?php

/**
 * Handles user upgrade processing with PayPal.
 *
 * @package XenForo_UserUpgrade
 */
class XenForo_UserUpgradeProcessor_PayPal
{
	/**
	 * @var Zend_Controller_Request_Http
	 */
	protected $_request;

	/**
	 * @var XenForo_Input
	 */
	protected $_input;

	/**
	 * List of filtered input for handling a callback.
	 *
	 * @var array
	 */
	protected $_filtered = null;

	/**
	 * Info about the upgrade being processed.
	 *
	 * @var array|false
	 */
	protected $_upgrade = false;

	/**
	 * Info about the user the upgrade is for.
	 *
	 * @var array|false
	 */
	protected $_user = false;

	/**
	 * The upgrade record ID inserted/updated.
	 *
	 * @var integer|null
	 */
	protected $_upgradeRecordId = null;

	/**
	 * The upgrade record being processed.
	 *
	 * @var array|false
	 */
	protected $_upgradeRecord = false;

	/**
	 * @var XenForo_Model_UserUpgrade
	 */
	protected $_upgradeModel = null;

	/**
	 * Initializes handling for processing a request callback.
	 *
	 * @param Zend_Controller_Request_Http $request
	 */
	public function initCallbackHandling(Zend_Controller_Request_Http $request)
	{
		$this->_request = $request;
		$this->_input = new XenForo_Input($request);

		$this->_filtered = $this->_input->filter(array(
			'test_ipn' => XenForo_Input::UINT,
			'business' => XenForo_Input::STRING,
			'txn_type' => XenForo_Input::STRING,
			'txn_id' => XenForo_Input::STRING,
			'mc_currency' => XenForo_Input::STRING,
			'mc_gross' => XenForo_Input::UNUM,
			'payment_status' => XenForo_Input::STRING,
			'custom' => XenForo_Input::STRING,
		));

		$this->_upgradeModel =  XenForo_Model::create('XenForo_Model_UserUpgrade');
	}

	/**
	 * Validates the callback request is valid. If failure happens, the response should
	 * tell the processor to retry.
	 *
	 * @param string $errorString Output error string
	 *
	 * @return boolean
	 */
	public function validateRequest(&$errorString)
	{
		try
		{
			if ($this->_filtered['test_ipn'] && XenForo_Application::debugMode())
			{
				$validator = XenForo_Helper_Http::getClient('http://www.sandbox.paypal.com/cgi-bin/webscr');
			}
			else
			{
				$validator = XenForo_Helper_Http::getClient('http://www.paypal.com/cgi-bin/webscr');
			}
			$validator->setParameterPost('cmd', '_notify-validate');
			$validator->setParameterPost($_POST);
			$validatorResponse = $validator->request('POST');

			if (!$validatorResponse || $validatorResponse->getBody() != 'VERIFIED' || $validatorResponse->getStatus() != 200)
			{
				$errorString = 'Request not validated';
				return false;
			}
		}
		catch (Zend_Http_Client_Exception $e)
		{
			$errorString = 'Connection to PayPal failed';
			return false;
		}

		if (strtolower($this->_filtered['business']) != strtolower(XenForo_Application::get('options')->payPalPrimaryAccount))
		{
			$errorString = 'Invalid business';
			return false;
		}

		return true;
	}

	/**
	 * Validates pre-conditions on the callback. These represent things that likely wouldn't get fixed
	 * (and generally shouldn't happen), so retries are not necessary.
	 *
	 * @param string $errorString
	 *
	 * @return boolean
	 */
	public function validatePreConditions(&$errorString)
	{
		$itemParts = explode(',', $this->_filtered['custom'], 4);
		if (count($itemParts) != 4)
		{
			$errorString = 'Invalid item (custom)';
			return false;
		}

		list($userId, $userUpgradeId, $validationType, $validation) = $itemParts;
		// $validationType allows validation method changes

		$user = XenForo_Model::create('XenForo_Model_User')->getFullUserById($userId);
		if (!$user)
		{
			$errorString = 'Invalid user';
			return false;
		}
		$this->_user = $user;

		$tokenParts = explode(',', $validation);
		if (count($tokenParts) != 3 || sha1($tokenParts[1] . $user['csrf_token']) != $tokenParts[2])
		{
			$errorString = 'Invalid validation';
			return false;
		}

		$upgrade = $this->_upgradeModel->getUserUpgradeById($userUpgradeId);
		if (!$upgrade)
		{
			$errorString = 'Invalid user upgrade';
			return false;
		}
		$this->_upgrade = $upgrade;

		if (!$this->_filtered['txn_id'])
		{
			$errorString = 'No txn_id';
			return false;
		}

		$transaction = $this->_upgradeModel->getProcessedTransactionLog($this->_filtered['txn_id']);
		if ($transaction)
		{
			$errorString = 'Transaction already processed';
			return false;
		}

		$upgradeRecord = $this->_upgradeModel->getActiveUserUpgradeRecord($this->_user['user_id'], $this->_upgrade['user_upgrade_id']);
		if ($upgradeRecord)
		{
			$this->_upgradeRecordId = $upgradeRecord['user_upgrade_record_id'];
			$this->_upgradeRecord = $upgradeRecord;
		}

		switch ($this->_filtered['txn_type'])
		{
			case 'web_accept':
			case 'subscr_payment':
				if ($upgradeRecord)
				{
					$extra = unserialize($upgradeRecord['extra']);
					$cost = $extra['cost_amount'];
					$currency = $extra['cost_currency'];
				}
				else
				{
					$cost = $upgrade['cost_amount'];
					$currency = $upgrade['cost_currency'];
				}
				if (round($this->_filtered['mc_gross'], 2) != round($cost, 2)
					|| strtolower($this->_filtered['mc_currency']) != $currency
				)
				{
					$errorString = 'Invalid payment amount';
					return false;
				}
		}

		return true;
	}

	/**
	 * Once all conditions are validated, process the transaction.
	 *
	 * @return array [0] => log type (payment, cancel, info), [1] => log message
	 */
	public function processTransaction()
	{
		switch ($this->_filtered['txn_type'])
		{
			case 'web_accept':
			case 'subscr_payment':
				if ($this->_filtered['payment_status'] == 'Completed')
				{
					$this->_upgradeRecordId = $this->_upgradeModel->upgradeUser($this->_user['user_id'], $this->_upgrade);

					return array('payment', 'Payment received, upgraded/extended');
				}
				break;
		}

		if ($this->_filtered['payment_status'] == 'Refunded' || $this->_filtered['payment_status'] == 'Reversed')
		{
			if ($this->_upgradeRecord)
			{
				$this->_upgradeModel->downgradeUserUpgrade($this->_upgradeRecord);

				return array('cancel', 'Payment refunded/reversed, downgraded');
			}
		}

		return array('info', 'OK, no action');
	}

	/**
	 * Get details for use in the log.
	 *
	 * @return array
	 */
	public function getLogDetails()
	{
		$details = $_POST;
		$details['_callbackIp'] = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false);

		return $details;
	}

	/**
	 * Gets the transaction ID.
	 *
	 * @return string
	 */
	public function getTransactionId()
	{
		return $this->_filtered['txn_id'];
	}

	/**
	 * Gets the ID of the processor.
	 *
	 * @return string
	 */
	public function getProcessorId()
	{
		return 'paypal';
	}

	/**
	 * Gets the ID of the upgrade record changed.
	 *
	 * @return integer
	 */
	public function getUpgradeRecordId()
	{
		return intval($this->_upgradeRecordId);
	}

	/**
	 * Logs the request.
	 *
	 * @param string $type Log type (info, payment, cancel, error)
	 * @param string $message Log message
	 * @param array $extra Extra details to log (not including output from getLogDetails)
	 */
	public function log($type, $message, array $extra)
	{
		$upgradeRecordId = $this->getUpgradeRecordId();
		$processor = $this->getProcessorId();
		$transactionId = $this->getTransactionId();
		$details = $this->getLogDetails() + $extra;

		$this->_upgradeModel->logProcessorCallback(
			$upgradeRecordId, $processor, $transactionId, $type, $message, $details
		);
	}
}