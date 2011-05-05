var ImageDialog = {
	init : function() {
		var f = document.forms[0], ed = tinyMCEPopup.editor;

		e = ed.selection.getNode();

		if (e.nodeName == 'IMG') {
			f.src.value = ed.dom.getAttrib(e, 'src');
		}
	},

	update : function() {
		var f = document.forms[0], nl = f.elements, ed = tinyMCEPopup.editor, args = {}, el;

		tinyMCEPopup.restoreSelection();

		if (f.src.value === '') {
			if (ed.selection.getNode().nodeName == 'IMG') {
				ed.dom.remove(ed.selection.getNode());
				ed.execCommand('mceRepaint');
			}

			tinyMCEPopup.close();
			return;
		}

		tinymce.extend(args, {
			src : f.src.value,
			'class' : 'bbCodeImage'
		});

		el = ed.selection.getNode();

		if (el && el.nodeName == 'IMG') {
			ed.dom.setAttribs(el, args);
		} else {
			ed.execCommand('mceInsertContent', false, '<img id="__mce_tmp" />', {skip_undo : 1});
			ed.dom.setAttribs('__mce_tmp', args);
			ed.dom.setAttrib('__mce_tmp', 'id', '');
			ed.undoManager.add();
		}

		tinyMCEPopup.close();
	},

	getImageData : function() {
		var f = document.forms[0];

		this.preloadImg = new Image();
		this.preloadImg.src = tinyMCEPopup.editor.documentBaseURI.toAbsolute(f.src.value);
	}
};

tinyMCEPopup.onInit.add(ImageDialog.init, ImageDialog);
