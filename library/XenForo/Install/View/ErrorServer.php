<?php

class XenForo_Install_View_ErrorServer extends XenForo_Install_View_Base
{
	protected function _getExceptionTraceHtml()
	{
		$traceHtml = '';

		if (isset($this->_params['exception']) && $this->_params['exception'] instanceof Exception)
		{
			$e = $this->_params['exception'];
			$error = $e->getMessage();
			$cwd = str_replace('\\', '/', getcwd());

			foreach ($e->getTrace() AS $traceEntry)
			{
				$function = (isset($traceEntry['class']) ? $traceEntry['class'] . $traceEntry['type'] : '') . $traceEntry['function'];
				if (isset($traceEntry['file']))
				{
					$file = str_replace("$cwd/library/", '', str_replace('\\', '/', $traceEntry['file']));
				}
				else
				{
					$file = '';
				}
				$traceHtml .= "\t<li><b class=\"function\">" . htmlspecialchars($function) . "()</b>" . (isset($traceEntry['file']) && isset($traceEntry['line']) ? ' <span class="shade">in</span> <b class="file">' . $file . "</b> <span class=\"shade\">at line</span> <b class=\"line\">$traceEntry[line]</b>" : '') . "</li>\n";
			}

			$trace = $e->getTrace();
		}
		else
		{
			$error = '';
			$trace = array();
		}

		return array(
			'error' => $error,
			'traceHtml' => $traceHtml,
			'trace' => $trace
		);
	}

	public function renderHtml()
	{
		$this->_params['traceHtml'] = $this->_getExceptionTraceHtml();
	}

	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_getExceptionTraceHtml());
	}
}