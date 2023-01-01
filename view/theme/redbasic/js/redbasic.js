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

	var touch_start = null;
	var touch_max = window.innerWidth / 10;

	window.addEventListener('touchstart', function(e) {
		if (e.touches.length === 1){
			//just one finger touched
			touch_start = e.touches.item(0).clientX;
			if (touch_start < touch_max) {
				$('html, body').css('overflow-y', 'hidden');
			}
		}
		else {
			//a second finger hit the screen, abort the touch
			touch_start = null;
		}
	});

	window.addEventListener('touchend', function(e) {
		$('html, body').css('overflow-y', '');

		let touch_offset = 30; //at least 30px are a swipe
		if (touch_start) {
			//the only finger that hit the screen left it
			let touch_end = e.changedTouches.item(0).clientX;

			if (touch_end > (touch_start + touch_offset)) {
				//a left -> right swipe
				if (touch_start < touch_max) {
					toggleAside('right');
				}
			}
			if (touch_end < (touch_start - touch_offset)) {
				//a right -> left swipe
				//toggleAside('left');
			}
		}
	});

	$(document).on('hz:hqControlsClickAction', function(e) {
		toggleAside('left');
	});

});

function setStyle(element, cssProperty) {
	for (var property in cssProperty){
		element.style[property] = cssProperty[property];
	}
}
