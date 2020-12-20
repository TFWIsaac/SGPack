mw.sgpack = {

	ie9_hack : 0,	// Angabe ob IE9 Hack aktiv
	edit_x : 0,		// Position X für IE9 Hack
	exit_y : 0,		// Position Y für IE9 Hack

	// String (UTF-8 sicher) decode
    rawdecode : function (str) {
		return decodeURIComponent((str+'')
	    	.replace(/%(?![\da-f]{2})/gi, function() {
				return '%25';
			}
		))
    },

	// String decodieren und mit insertTags in Editorfeld einsetzen
    insert : function (str) {
		var text = this.rawdecode(str+'');
		console.debug(text);
		astr = text.split('+');
		if(this.ie9_hack) {
			this.edit_x = window.pageXOffset;
			this.edit_y = window.pageYOffset;
		}
		mw.toolbar.insertTags(astr[0],astr[1],astr[2]);
		if(this.ie9_hack) {
			window.scrollTo(this.edit_x,this.edit_y);
		}
	},

	// String aus Dropdown Auswahl auslesen und in Editorfeld einsetzen
	insertSelect : function (sel) {
		var str = sel.options[sel.options.selectedIndex].value;
		this.insert(str);
	}
};
