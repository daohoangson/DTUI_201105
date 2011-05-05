var MediaDialog = {
	init: function()
	{
	},

	submit: function()
	{
		XenForo.ajax(
			'index.php?editor/media',
			{ url: $('#ctrl_url').val() },
			MediaDialog.insert
		);
		return false;
	},

	insert: function(ajaxData)
	{
		if (XenForo.hasResponseError(ajaxData))
		{
			return false;
		}

		var ed = tinyMCEPopup.editor;

		if (ajaxData.matchBbCode)
		{
			ed.execCommand('mceInsertContent', false, ajaxData.matchBbCode);
			tinyMCEPopup.close();
		}
		else if (ajaxData.noMatch)
		{
			alert(ajaxData.noMatch);
		}
	}
};

tinyMCEPopup.onInit.add(MediaDialog.init, MediaDialog);