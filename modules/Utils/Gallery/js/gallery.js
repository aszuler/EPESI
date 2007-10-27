
getY = function( oElement ) {
	var iReturnValue = 0;
	while( oElement != null ) {
		iReturnValue += oElement.offsetTop + 1;
		oElement = oElement.offsetParent;
	}
	return iReturnValue - 2;
}

utils_gallery__set_content_height = function(content) {
	if( $(content) ) {
		var prev = -39;
		var ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
		var tmp = 0;
		while(prev != tmp) {
			prev = tmp;
			ch = (document.documentElement.clientHeight < document.body.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight)
			tmp = ch - getY($(content));
			$(content).style.height = tmp + 'px';
		}
		tmp -= 40;
		$(content).style.height = tmp + 'px';
	} else {
		setTimeout("base_box__set_content_height('"+content+"')", 100);
	}
}
