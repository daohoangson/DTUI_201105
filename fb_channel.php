<?php

header('Content-type: text/html; charset=utf-8');
header('Expires: Wed, 01 Jan 2020 00:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: public');

$locale = '';
if (isset($_GET['l']))
{
	$locale = str_replace('-', '_', strval($_GET['l']));
	$locale = preg_replace('/[^a-z_]/i', '', $locale);
}

if (!$locale)
{
	$locale = 'en_US';
}

?>
<script src="http://connect.facebook.net/<?php echo htmlspecialchars($locale); ?>/all.js"></script>
