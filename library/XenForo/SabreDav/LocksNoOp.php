<?php

class XenForo_SabreDav_LocksNoOp extends Sabre_DAV_Locks_Backend_Abstract
{
	public function getLocks($uri)
	{
		return array();
	}

	public function lock($uri, Sabre_DAV_Locks_LockInfo $lockInfo)
	{
		return true;
	}

	public function unlock($uri, Sabre_DAV_Locks_LockInfo $lockInfo)
	{
		return true;
	}
}