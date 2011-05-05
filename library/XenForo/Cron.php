<?php

/**
 * General handler for cron actions.
 *
 * @package XenForo_Cron
 */
class XenForo_Cron
{
	/**
	 * Run all (or as many as possible) outstanding cron entries.
	 * Confirms via an atomic update that the entries are runnable first.
	 */
	public function run()
	{
		/* @var $cronModel XenForo_Model_Cron */
		$cronModel = XenForo_Model::create('XenForo_Model_Cron');

		$entries = $cronModel->getCronEntriesToRun();
		foreach ($entries AS $entry)
		{
			if (!$cronModel->updateCronRunTimeAtomic($entry))
			{
				continue;
			}

			try
			{
				$cronModel->runEntry($entry);
			}
			catch (Exception $e)
			{
				// suppress so we don't get stuck
				XenForo_Error::logException($e);
			}
		}

		$cronModel->updateMinimumNextRunTime();
	}

	/**
	 * Outputs the blank image needed for cron page embedding.
	 */
	public function outputImage()
	{
		header('Content-Type: image/gif');
		echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='); // transparent gif
	}

	/**
	 * Runs all/as many outstanding cron entries and outputs the
	 * blank image.
	 */
	public static function runAndOutput()
	{
		@set_time_limit(180);

		$dependencies = new XenForo_Dependencies_Public();
		$dependencies->preLoadData();

		$cron = new XenForo_Cron();

		$cron->outputImage();
		$cron->run();
	}
}