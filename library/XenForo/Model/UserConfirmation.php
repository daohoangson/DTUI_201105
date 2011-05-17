<?php

/**
 * Model for user confirmation.
 *
 * @package XenForo_UserConfirmation
 */
class XenForo_Model_UserConfirmation extends XenForo_Model
{
	/**
	 * Gets the specified user confirmation record.
	 *
	 * @param integer $userId
	 * @param string $type
	 *
	 * @return array|false
	 */
	public function getUserConfirmationRecord($userId, $type)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_confirmation
			WHERE user_id = ?
				AND confirmation_type = ?
		', array($userId, $type));
	}

	/**
	 * Validates a user confirmation request. This works on the base components.
	 * If you already have a record, use validateUserConfirmationRecord.
	 *
	 * @param integer $userId
	 * @param string $type
	 * @param string $key Confirmation key to validate against DB value
	 *
	 * @return boolean
	 */
	public function validateUserConfirmation($userId, $type, $key)
	{
		$confirmation = $this->getUserConfirmationRecord($userId, $type);
		return ($confirmation && $this->validateUserConfirmationRecord($key, $confirmation));
	}

	/**
	 * Validates a user confirmation record against a specific key.
	 *
	 * @param string $key
	 * @param array $confirmation Confirmation record from DB.
	 *
	 * @return boolean
	 */
	public function validateUserConfirmationRecord($key, array $confirmation)
	{
		return ($confirmation['confirmation_key'] === $key);
	}

	/**
	 * Generate a new user confirmation record. Note that a user can only have
	 * one confirmation record of a given type.
	 *
	 * @param integer $userId
	 * @param string $type
	 *
	 * @return array Confirmation record details
	 */
	public function generateUserConfirmationRecord($userId, $type)
	{
		$confirmation = array(
			'user_id' => $userId,
			'confirmation_type' => $type,
			'confirmation_key' => XenForo_Application::generateRandomString(16),
			'confirmation_date' => XenForo_Application::$time
		);

		$this->_getDb()->query('
			INSERT INTO xf_user_confirmation
				(user_id, confirmation_type, confirmation_key, confirmation_date)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				confirmation_key = VALUES(confirmation_key),
				confirmation_date = VALUES(confirmation_date)
		', array($userId, $type, $confirmation['confirmation_key'], $confirmation['confirmation_date']));

		return $confirmation;
	}

	/**
	 * Delete a user's confirmation record.
	 *
	 * @param integer $userId
	 * @param string $type
	 */
	public function deleteUserConfirmationRecord($userId, $type)
	{
		$db = $this->_getDb();
		$db->delete('xf_user_confirmation',
			'user_id = ' . $db->quote($userId) . ' AND confirmation_type = ' . $db->quote($type)
		);
	}

	/**
	 * Send email confirmation to the specified user.
	 *
	 * @param array $user User to send to
	 * @param array|null $confirmation Existing confirmation record; if null, generates a new record
	 *
	 * @return boolean True if the email was sent successfully
	 */
	public function sendEmailConfirmation(array $user, array $confirmation = null)
	{
		if (!$confirmation)
		{
			$confirmation = $this->generateUserConfirmationRecord($user['user_id'], 'email');
		}

		$params = array(
			'user' => $user,
			'confirmation' => $confirmation,
			'boardTitle' => XenForo_Application::get('options')->boardTitle
		);
		$mail = XenForo_Mail::create('user_email_confirmation', $params, $user['language_id']);

		return $mail->send($user['email'], $user['username']);
	}

	/**
	 * Takes an action on a user awaiting moderation.
	 *
	 * @param array $user User info
	 * @param string $action Action to take (accept or reject)
	 * @param boolean $notify True to email user about action
	 * @param string $rejectionReason If rejecting, an optional rejection reason
	 *
	 * @return boolean True if the user was processed
	 */
	public function processUserModeration(array $user, $action, $notify = true, $rejectionReason = '')
	{
		if ($user['user_state'] != 'moderated')
		{
			return false;
		}

		if ($action == 'approve')
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$dw->setExistingData($user);
			$dw->advanceRegistrationUserState();
			$dw->save();

			if ($notify)
			{
				$params = array(
					'user' => $user,
					'boardTitle' => XenForo_Application::get('options')->boardTitle
				);
				$mail = XenForo_Mail::create('user_account_approved', $params, $user['language_id']);
				$mail->send($user['email'], $user['username']);
			}

			return true;
		}
		else if ($action == 'reject')
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$dw->setExistingData($user);
			$dw->delete();

			if ($notify)
			{
				$params = array(
					'user' => $user,
					'boardTitle' => XenForo_Application::get('options')->boardTitle,
					'rejectionReason' => $rejectionReason
				);
				$mail = XenForo_Mail::create('user_account_rejected', $params, $user['language_id']);
				$mail->send($user['email'], $user['username']);
			}

			return true;
		}

		return false;
	}

	/**
	 * Sends a password reset request.
	 *
	 * @param array $user
	 * @param array|null $confirmation If null, generates a new confirmation record
	 *
	 * @return boolean True if email sent successfully
	 */
	public function sendPasswordResetRequest(array $user, array $confirmation = null)
	{
		if (!$confirmation)
		{
			$confirmation = $this->generateUserConfirmationRecord($user['user_id'], 'password');
		}

		$params = array(
			'user' => $user,
			'confirmation' => $confirmation,
			'boardTitle' => XenForo_Application::get('options')->boardTitle
		);
		$mail = XenForo_Mail::create('user_lost_password', $params, $user['language_id']);

		return $mail->send($user['email'], $user['username']);
	}

	/**
	 * Resets the specified user's password and emails the password to them if requested.
	 *
	 * @param integer $userId
	 * @param boolean $sendEmail
	 *
	 * @return string New password
	 */
	public function resetPassword($userId, $sendEmail = true)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($userId);

		$password = XenForo_Application::generateRandomString(8);

		$auth = XenForo_Authentication_Abstract::createDefault();
		$dw->set('scheme_class', $auth->getClassName());
		$dw->set('data', $auth->generate($password));
		$dw->save();

		$user = $dw->getMergedData();

		if ($sendEmail)
		{
			$params = array(
				'user' => $user,
				'password' => $password,
				'boardTitle' => XenForo_Application::get('options')->boardTitle,
				'boardUrl' => XenForo_Application::get('options')->boardUrl,
			);
			$mail = XenForo_Mail::create('user_lost_password_reset', $params, $user['language_id']);
			$mail->send($user['email'], $user['username']);
		}

		return $password;
	}
}