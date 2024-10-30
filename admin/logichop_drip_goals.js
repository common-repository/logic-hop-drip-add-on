jQuery(function($) {
	$('.logichop_drip_clear').click(function (e) {
		$.each($(this).parent().children('select'), function () {
			$(this)[0].selectedIndex = 0;
		});
		$.each($(this).parent().children('input'), function () {
			$(this).val('');
		});
		e.preventDefault();
	});
});
