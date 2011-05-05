<?php
	class_exists('XenForo_Application', false) || die('Invalid');

	$contents = strval($contents);
	$hasInstallSidebar = (strpos($contents, 'id="sideNav"') !== false);
?>
<!DOCTYPE html>
<html id="XenForo" class="Install">
<head>
	<meta charset="utf-8" />
	<title><?php echo ($title ? htmlspecialchars($title) . ' | ' : ''); ?>XenForo</title>

	<link rel="stylesheet" type="text/css" href="install.css" />

	<script type="text/javascript" src="../js/jquery/jquery-1.4.4.min.js"></script>
	<script type="text/javascript" src="../js/jquery/jquery.xenforo.rollup.js"></script>
	<script type="text/javascript" src="../js/xenforo/xenforo.js"></script>
	<script type="text/javascript" src="../js/xenforo/cache_rebuild.js"></script>

	<script type="text/javascript">
	jQuery.extend(true, XenForo, {
		<?php if (!empty($visitor['csrf_token_page'])) { echo "_csrfToken: '$visitor[csrf_token_page]'"; } ?>
	});
	</script>
</head>
<body>

<div id="header">

	<div id="logoLine">
		<div class="pageWidth">
			<a href="http://xenforo.com" target="XenForo"><img src="../styles/default/xenforo/XenForo-small.png" id="logo" /></a>
			<h2 id="version"><a href="http://xenforo.com" target="XenForo">XenForo</a> <?php echo XenForo_Application::$version; ?></h2>
		</div>
	</div>

	<div id="tabsNav">
		<div class="pad"></div>
	</div>

</div>

<div id="body" class="pageWidth">
<?php if ($hasInstallSidebar) { ?>
	<?php echo $contents; ?>
<?php } else { ?>
	<div id="contentContainer" class="noSideBar">
		<div id="content">
			<div class="titleBar">
				<?php if (!empty($title)) { ?>
					<h1><?php echo htmlspecialchars($title); ?></h1>
				<?php } ?>
			</div>

			<?php echo $contents; ?>
		</div>

		<div id="footer">
			<div id="copyright"><?php echo new XenForo_Phrase('xenforo_copyright'); ?></div>
		</div>
	</div>
<?php } ?>
</div>

</body>
</html>