document.addEventListener('DOMContentLoaded', function () {
	var target = document.getElementById('snaporder_qrcode');
	var urlInput = document.getElementById('snaporder_qr_url');
	var colorInput = document.getElementById('snaporder_qr_color');
	var sizeInput = document.getElementById('snaporder_qr_size');
	var sizeValue = document.getElementById('snaporder_qr_size_val');
	var printButton = document.getElementById('snaporder_print_btn');

	if (!target || !urlInput || !colorInput || !sizeInput || typeof QRCode === 'undefined') {
		return;
	}

	function makeCode() {
		if (!urlInput.value) {
			target.replaceChildren();
			return;
		}

		target.replaceChildren();
		new QRCode(target, {
			text: urlInput.value,
			width: Number.parseInt(sizeInput.value, 10),
			height: Number.parseInt(sizeInput.value, 10),
			colorDark: colorInput.value,
			colorLight: '#ffffff',
			correctLevel: QRCode.CorrectLevel.H
		});
	}

	urlInput.addEventListener('keyup', makeCode);
	urlInput.addEventListener('change', makeCode);
	colorInput.addEventListener('change', makeCode);
	sizeInput.addEventListener('input', function () {
		if (sizeValue) {
			sizeValue.textContent = sizeInput.value + 'px';
		}
		makeCode();
	});

	makeCode();

	if (!printButton) {
		return;
	}

	printButton.addEventListener('click', function () {
		var source = document.getElementById('snaporder_qr_container');
		var printWindow = window.open('', '', 'height=600,width=800');
		if (!source || !printWindow) {
			return;
		}

		printWindow.document.title = snaporder_qr_vars.print_title;
		var stylesheet = printWindow.document.createElement('link');
		var printStarted = false;
		var startPrint = function () {
			if (printStarted || printWindow.closed) {
				return;
			}
			printStarted = true;
			printWindow.focus();
			printWindow.print();
			printWindow.close();
		};
		stylesheet.rel = 'stylesheet';
		stylesheet.href = snaporder_qr_vars.print_stylesheet;
		stylesheet.addEventListener('load', startPrint);
		printWindow.document.head.appendChild(stylesheet);

		var printable = source.cloneNode(true);
		var sourceCanvas = source.querySelector('canvas');
		var printableCanvas = printable.querySelector('canvas');
		if (sourceCanvas && printableCanvas) {
			var image = printWindow.document.createElement('img');
			image.src = sourceCanvas.toDataURL('image/png');
			image.width = sourceCanvas.width;
			image.height = sourceCanvas.height;
			printableCanvas.replaceWith(image);
		}
		printWindow.document.body.appendChild(printable);

		window.setTimeout(startPrint, 1000);
	});
});
