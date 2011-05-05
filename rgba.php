<?php

// generates an rgba png based on colors in inputs, part of XenForo

$r = getFromInput('r');
$g = getFromInput('g');
$b = getFromInput('b');
$a = getFromInput('a', 255);

header('Content-type: image/png');
header('Expires: Wed, 01 Jan 2020 00:00:00 GMT');
header('Cache-Control: public');

echo "\x89PNG\r\n\x1A\n" // signature
	. "\x00\x00\x00\x0DIHDR\00\x00\x00\x0A\x00\x00\x00\x0A\x08\x03\x00\x00\x00\xBA\xEC\x3F\x8F" // header
	. getPngChunk('PLTE', pack('CCCCCC', $r, $g, $b, $r, $g, $b))
	. getPngChunk('tRNS', pack('CC', $a, $a))
	. "\x00\x00\x00\x0EIDAT\x08\xD7\x63\x60\x84\x03\x06\xDA\x33\x01\x15\xEA\x00\x65\x39\xA1\xDA\x84" // data
	. "\x00\x00\x00\x00IEND\xAE\x42\x60\x82"; // end

function getFromInput($key, $default = 0)
{
	if (!isset($_REQUEST[$key]))
	{
		return $default;
	}

	return min(255, max(0, intval($_REQUEST[$key])));
}

function getPngChunk($chunkName, $data)
{
	return pack('N', strlen($data)) . $chunkName . $data . pack('N', crc32($chunkName . $data));
}