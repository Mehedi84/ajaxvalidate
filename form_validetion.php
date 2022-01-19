<script type="text/javascript">
	function formValidation(formId){
		event.preventDefault();
		var fail = false;
		$('#' + formId).find('select, textarea, input').removeClass('error_msg');
		$('#' + formId).find('select, textarea, input').each(function () {
			if ($(this).prop('required')) {
				if (!$(this).val()) {
					fail = true;
					name = $(this).attr('id');
					$('#' + name).addClass('error_msg');
				}
			}
		});
		return fail;
	}
</script>