<?php

class XenForo_ViewAdmin_Error_ServerError extends XenForo_ViewAdmin_Base
{
	/**
	 * Checks for the presence of an exception in the view parameters, and if one exists,
	 * prepares the trace as HTML in the form of <li> tags.
	 *
	 * @return array Returns 'message', 'trace', and 'traceHtml' keys
	 */
	protected function _getExceptionTraceHtml()
	{
		$traceHtml = '';

		if (isset($this->_params['exception']) && $this->_params['exception'] instanceof Exception)
		{
			$e = $this->_params['exception'];
			$error = $e->getMessage();
			$cwd = getcwd();

			foreach ($e->getTrace() AS $traceEntry)
			{
				$function = (isset($traceEntry['class']) ? $traceEntry['class'] . $traceEntry['type'] : '') . $traceEntry['function'];
				$traceHtml .= "\t<li><b class=\"function\">" . htmlspecialchars($function) . "()</b>" . (isset($traceEntry['file']) && isset($traceEntry['line']) ? ' <span class="shade">in</span> <b class="file">' . str_replace("$cwd/library/", '', $traceEntry['file']) . "</b> <span class=\"shade\">at line</span> <b class=\"line\">$traceEntry[line]</b>" : '') . "</li>\n";
			}
		}
		else
		{
			$message = '';
		}

		return array(
			'error' => $error,
			'traceHtml' => $traceHtml
		);
	}

	public function renderHtml()
	{
		$exception = $this->_getExceptionTraceHtml();

		return "<div class=\"baseHtml exception\"><h2>Server Error</h2> <p>$exception[error]</p> <ol class=\"traceHtml\">\n$exception[traceHtml]</ol></div>";
	}

	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_getExceptionTraceHtml());
	}

	public function renderXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('errors');
		$document->appendChild($rootNode);

		if (isset($this->_params['exception']) && $this->_params['exception'] instanceof Exception)
		{
			$e = $this->_params['exception'];
			$exceptionMessage = $e->getMessage();

			$rootNode->appendChild(
				XenForo_Helper_DevelopmentXml::createDomElement($document, 'error', $exceptionMessage)
			);
			$traceNode = $document->createElement('trace');

			foreach ($e->getTrace() AS $trace)
			{
				$function = (isset($trace['class']) ? $trace['class'] . $trace['type'] : '') . $trace['function'];

				if (!isset($trace['file']))
				{
					$trace['file'] = '';
				}
				if (!isset($trace['line']))
				{
					$trace['line'] = '';
				}

				$entryNode = $document->createElement('entry');
				$entryNode->setAttribute('function', $function);
				$entryNode->setAttribute('file', $trace['file']);
				$entryNode->setAttribute('line', $trace['line']);

				$traceNode->appendChild($entryNode);
			}

			$rootNode->appendChild($traceNode);
		}
		else
		{
			$rootNode->appendChild($document->createElement('error', 'Unknown error, trace unavailable'));
		}

		return $document->saveXML();
	}
}