<?php
	class_exists('XenForo_Application', false) || die('Invalid');
	$_subView = (string)$_subView;
	$_container = XenForo_Template_Install::getExtraContainerData();
?>

<div id="contentContainer">
	<div id="content">
		<div class="titleBar">
			<?php if (!empty($_container['title'])) { ?>
				<h1><?php echo htmlspecialchars($_container['title']); ?></h1>
			<?php } ?>
		</div>

		<?php echo $_subView; ?>
	</div>

	<div id="footer">
		<div id="copyright"><?php echo new XenForo_Phrase('xenforo_copyright'); ?></div>
	</div>
</div>

<ol id="sideNav">
	<li><span <?php echo ($step == 'index' ? ' class="selected"' : ''); ?>>Welcome</span></li>
	<li><span <?php echo ($step == 1 ? ' class="selected"' : ''); ?>>Verify Configuration</span></li>
	<li><span <?php echo ($step == 2 ? ' class="selected"' : ''); ?>>Install</span></li>
	<li><span <?php echo ($step == 3 ? ' class="selected"' : ''); ?>>Setup Administrator</span></li>
	<li><span <?php echo ($step == 4 ? ' class="selected"' : ''); ?>>Setup Options</span></li>
	<li><span <?php echo ($step == 'complete' ? ' class="selected"' : ''); ?>>Complete</span></li>
</ol>