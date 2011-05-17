<?php

/**
 * Helper for version-related comparisons and manipulations.
 *
 * @package XenForo_Helper
 */
class XenForo_Helper_Version
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	public static function parseVersion($version)
	{
		if (!preg_match('#^(?P<version>[0-9.]+)((?P<modifier>[^0-9.]+)(?P<modifierVersion>\d*))?$#siU', trim($version), $match))
		{
			return false;
		}

		$versionParts = explode('.', $match['version']);
		if (isset($match['modifier']))
		{
			$modifier = preg_replace('/[^a-z]/', '', strtolower($match['modifier']));
			switch ($modifier)
			{
				case 'dev':
					$modifier = 1; break;

				case 'alpha':
				case 'a':
					$modifier = 2; break;

				case 'beta':
				case 'b':
					$modifier = 3; break;

				case 'releasecandidate':
				case 'rc':
					$modifier = 4; break;

				case 'gold':
					$modifier = 5; break;

				case 'patchlevel':
				case 'pl':
					$modifier = 6; break;

				default:
					$modifier = 0;
			}
			$modifierVersion = intval($match['modifierVersion']);
		}
		else
		{
			$modifier = 5;
			$modifierVersion = 0;
		}

		return array(
			'versionParts' => $versionParts,
			'modifier' => $modifier,
			'modifierVersion' => $modifierVersion
		);
	}

	protected static function _compareVersionsBasic($version1, $version2)
	{
		if ($version1 === $version2)
		{
			return 0;
		}

		$version1 = self::parseVersion($version1);
		$version2 = self::parseVersion($version2);

		if ($version1 === $version2)
		{
			// both false or both identical arrays
			return 0;
		}
		else if ($version1 === false)
		{
			// version 2 could be parsed but not version 1
			return -1;
		}
		else if ($version2 === false)
		{
			// version 1 could be parsed but not version 2
			return 1;
		}

		if (count($version2) > count($version1))
		{
			$primaryParts = $version2['versionParts'];
			$secondaryParts = $version1['versionParts'];
			$returnInverter = -1;
		}
		else
		{
			$primaryParts = $version1['versionParts'];
			$secondaryParts = $version2['versionParts'];
			$returnInverter = 1;
		}

		foreach ($primaryParts AS $partNum => $primaryPart)
		{
			$secondaryPart = (isset($secondaryParts[$partNum]) ? $secondaryParts[$partNum] : 0);
			if ($primaryPart > $secondaryPart)
			{
				return ($returnInverter * 1);
			}
			else if ($primaryPart < $secondaryPart)
			{
				return ($returnInverter * -1);
			}
		}

		if ($version1['modifier'] > $version2['modifier'])
		{
			return 1;
		}
		else if ($version1['modifier'] < $version2['modifier'])
		{
			return -1;
		}

		if ($version1['modifierVersion'] > $version2['modifierVersion'])
		{
			return 1;
		}
		else if ($version1['modifierVersion'] < $version2['modifierVersion'])
		{
			return -1;
		}

		return 0;
	}

	public static function compareVersions($version1, $version2, $operation = false)
	{
		$compared = self::_compareVersionsBasic($version1, $version2);

		switch ($operation)
		{
			case '<':
			case 'lt':
				return ($compared === -1);

			case '<=':
			case 'le':
				return ($compared === -1 || $compared === 0);

			case '>':
			case 'gt':
				return ($compared === 1);

			case '>=':
			case 'ge':
				return ($compared === 1 || $compared === 0);

			case '==':
			case '=':
			case 'eq':
				return ($compared === 0);

			case '!=':
			case '<>':
			case 'ne';
				return ($compared !== 0);

			default:
				return $compared;
		}
	}
}