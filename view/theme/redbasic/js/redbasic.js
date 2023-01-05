/* redbasic theme specific JavaScript */

$(document).ready(function() {

	$("input[data-role=cat-tagsinput]").tagsinput({
		tagClass: 'badge rounded-pill bg-warning text-dark'
	});

	$('a.disabled').click(function(e) {
		e.preventDefault();
		e.stopPropagation();
	});

	/* start notifications code */
	var doctitle = document.title;
	function checkNotify() {
		var notifyUpdateElem = document.getElementById('notify-update');
		if(notifyUpdateElem !== null) {
			if(notifyUpdateElem.innerHTML !== "")
				document.title = "(" + notifyUpdateElem.innerHTML + ") " + doctitle;
			else
				document.title = doctitle;
		}
	}
	setInterval(function () {checkNotify();}, 10 * 1000);
	/* end notifications code */

});

function setStyle(element, cssProperty) {
	for (var property in cssProperty){
		element.style[property] = cssProperty[property];
	}
}
