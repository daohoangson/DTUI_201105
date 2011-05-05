var CodeDialog = {
	init: function()
	{
	},

	submit: function()
	{
		var ed = tinyMCEPopup.editor, tag, code, output;
		
		switch ($('#ctrl_type').val())
		{
			case 'html': tag = 'HTML'; break;
			case 'php':  tag = 'PHP'; break;
			default:     tag = 'CODE';
		}
		
		code = $('#ctrl_code').val();
		code = code.replace(/&/g, '&amp;').replace(/</g, '&lt;')
			.replace(/>/g, '&gt;').replace(/"/g, '&quot;')
			.replace(/\t/g, '    ').replace(/  /g, '&nbsp; ')
			.replace(/\n/g, '</p>\n<p>');
		
		output = '[' + tag + ']' + code + '[/' + tag + ']';
		if (output.match(/\n/))
		{
			output = '<p>' + output + '</p>';
		}
		
		ed.execCommand('mceInsertContent', false, output);
		tinyMCEPopup.close();
		
		return false;
	}
};

tinyMCEPopup.onInit.add(CodeDialog.init, CodeDialog);