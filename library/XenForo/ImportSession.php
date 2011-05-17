<?php

class XenForo_ImportSession
{
	protected $_data = array();

	public function __construct($fresh = false)
	{
		if (!$fresh)
		{
			$data = XenForo_Model::create('XenForo_Model_DataRegistry')->get('importSession');
			if ($data)
			{
				$this->_data = $data;
			}
		}
	}

	public function save()
	{
		XenForo_Model::create('XenForo_Model_DataRegistry')->set('importSession', $this->_data);
	}

	public function delete()
	{
		XenForo_Model::create('XenForo_Model_DataRegistry')->delete('importSession');
		$this->_data = array();
	}

	public function isRunning()
	{
		return ($this->_data ? true : false);
	}

	public function start($importer, array $config)
	{
		$this->_data = array(
			'importer' => $importer,
			'config' => $config,
			'runSteps' => array(),
			'step' => false,
			'stepStart' => 0,
			'stepOptions' => array(),
			'extra' => array()
		);
	}

	public function completeStep($step = false)
	{
		if ($step === false)
		{
			$step = $this->_data['step'];
		}

		$this->_data['runSteps'][$step]['run'] = true;
		$this->_data['runSteps'][$step]['endTime'] = microtime(true);
		$this->startStep(false);
	}

	public function incrementStepImportTotal($add, $step = false)
	{
		if ($step === false)
		{
			$step = $this->_data['step'];
		}

		if (!isset($this->_data['runSteps'][$step]))
		{
			$this->_data['runSteps'][$step] = array('run' => false, 'importTotal' => 0);
		}

		$this->_data['runSteps'][$step]['importTotal'] += $add;
	}

	public function hasRunStep($step)
	{
		return !empty($this->_data['runSteps'][$step]['run']);
	}

	public function getImporterKey()
	{
		return (isset($this->_data['importer']) ? $this->_data['importer'] : false);
	}

	public function getConfig()
	{
		return (isset($this->_data['config']) ? $this->_data['config'] : array());
	}

	public function getRunSteps()
	{
		return (isset($this->_data['runSteps']) ? $this->_data['runSteps'] : array());
	}

	public function getStepInfo()
	{
		if (!isset($this->_data['step']))
		{
			return array(
				'step' => false,
				'stepStart' => 0,
				'stepOptions' => array()
			);
		}
		else
		{
			return array(
				'step' => $this->_data['step'],
				'stepStart' => $this->_data['stepStart'],
				'stepOptions' => $this->_data['stepOptions']
			);
		}
	}

	public function setStepInfo($start, array $stepOptions)
	{
		$this->_data['stepStart'] = $start;
		$this->_data['stepOptions'] = $stepOptions;
	}

	public function startStep($step, $options = array())
	{
		$this->_data['step'] = $step;
		$this->_data['stepStart'] = 0;
		$this->_data['stepOptions'] = $options;
		if ($step)
		{
			$this->_data['runSteps'][$step]['importTotal'] = 0;
			$this->_data['runSteps'][$step]['startTime'] = microtime(true);
		}
	}

	public function setExtraData($key, $subKey, $value = null)
	{
		if ($value === null)
		{
			$value = $subKey;
			$subKey = false;
		}

		if ($subKey === false)
		{
			$this->_data['extra'][$key] = $value;
		}
		else
		{
			$this->_data['extra'][$key][$subKey] = $value;
		}
	}

	public function unsetExtraData($key, $subKey = false)
	{
		if ($subKey === false)
		{
			unset($this->_data['extra'][$key]);
		}
		else
		{
			unset($this->_data['extra'][$key][$subKey]);
		}
	}

	public function getExtraData($key, $subKey = false)
	{
		if ($subKey === false)
		{
			return (isset($this->_data['extra'][$key]) ? $this->_data['extra'][$key] : false);
		}
		else
		{
			return (isset($this->_data['extra'][$key][$subKey]) ? $this->_data['extra'][$key][$subKey] : false);
		}
	}
}