(function( $ ){
	
	// declaring variables at initialization
	var $scanBtn = $('#wdbvf-scan-btn'),
		$convertAllBtn = $('#wdbvf-convert-all-btn'),
		$output = $('#wdbvf-output'),
		$singleConvertLinks = $('.wdbvf-single-convert'),
		$doactionTopBtn = $('#doaction'),
		$doactionBottomBtn = $('#doaction2'),
		convertQueue = [],
		doingAjax = false;
		

	$scanBtn.click(function(e){

		e.preventDefault();
		$scanBtn.prop("disabled", true);
		$convertAllBtn.hide();
		scanPosts(0, -1);
	});
	
	// scanning posts via ajax
	function scanPosts( offset = 0, total = -1) {

		if ( doingAjax ) return;
		doingAjax = true;
		var nonce = $scanBtn.data('nonce');

		$output.html( wdbvfObj.scanningMessage );
		$.ajax({
			method: "POST",
			url: wdbvfObj.ajaxUrl,
			data: { action : "wdbvf_scan_posts", offset : offset, total : total, _wpnonce : nonce }
		})
		.done(function( data ){
			doingAjax = false;
			if ( data.error ) {
				$output.html( data.message );
				return;
			}
			if ( data.offset >= data.total ) {
				$scanBtn.prop("disabled", false);
				$output.html( data.message );
				document.location.href = document.location.href + "&wdbvf_scan_finished=1";
				return;
			}
			scanPosts( data.offset, data.total);
			$output.html( data.message );
		})
		.fail(function(){
			doingAjax = false;
			$output.html( wdbvfObj.serverErrorMessage );
		});
	}
	


	// table "Convert" link handler
	$singleConvertLinks.click(function(e){
		e.preventDefault();
		
		var postID = $(this).data('json').post;
		convertQueue.push( postID );
		convertPosts();
	});
	

	// bulk posts converting via ajax
	function bulkConvertPosts( offset = 0, total = -1){
		if ( doingAjax ) return;

		doingAjax = true;
		var nonce = $convertAllBtn.data('nonce');
		$output.html( wdbvfObj.bulkConvertingMessage );
		$.ajax({
			method: "GET",
			url: wdbvfObj.ajaxUrl,
			data: { action : "wdbvf_bulk_convert", offset : offset, total : total, _wpnonce : nonce }
		})
		.done(function( data ){
			doingAjax = false;
			if ( data.error ) {
				$output.html( data.message );
				return;
			}
			var convertedData = [];
			var arrayLength = data.postsData.length;
			for (var i = 0; i < arrayLength; i++) {

				var convertedPost = {
					id		: data.postsData[i].id,
					content	: data.postsData[i].content
				};

				convertedData.push( convertedPost );
			}
			bulkSaveConverted( convertedData, data.offset, data.total, data.message );
			return;
		})
		.fail(function(){
			doingAjax = false;
			$output.html( wdbvfObj.serverErrorMessage );
		});
	}
	
	// bulk saving converted posts via ajax
	function bulkSaveConverted( convertedData, offset, total, message ) {
		if ( doingAjax ) return;
		doingAjax = true;
		var nonce = $convertAllBtn.data('nonce');
		var jsonData = {
			action : "wdbvf_bulk_convert",
			offset : offset,
			total : total,
			postsData : convertedData,
			_wpnonce : nonce
		};
		$.ajax({
			method: "POST",
			url: wdbvfObj.ajaxUrl,
			data: jsonData
		})
		.done(function( data ){
			doingAjax = false;
			if ( data.error ) {
				$output.html( data.message );
				return;
			}
			if ( data.offset >= data.total ) {
				$convertAllBtn.prop("disabled", false);
				$output.html( wdbvfObj.bulkConvertingSuccessMessage );
				return;
			}
			bulkConvertPosts( offset, total );
			$output.html( message );

			return;
		})
		.fail(function(){
			doingAjax = false;
			$output.html( wdbvfObj.serverErrorMessage );
		});
	}
	
	// single or group posts converting via ajax
	function convertPosts(){
		if( convertQueue.length == 0 ){
			$doactionTopBtn.prop("disabled", false);
			$doactionBottomBtn.prop("disabled", false);
			return;
		}
		if ( doingAjax ) return;
		doingAjax = true;
		var postID = convertQueue.shift();
		var $linkObject = $('#wdbvf-single-convert-' + postID);
		$linkObject.hide().after( wdbvfObj.convertingSingleMessage );
		$.ajax({
			method: "GET",
			url: wdbvfObj.ajaxUrl,
			data: $linkObject.data('json')
		})
		.done(function( data ) {
			doingAjax = false;
			if ( data.error ) {
				return;
			}
			saveConverted( data.message, $linkObject );
			return;
		})
		.fail(function(){
			doingAjax = false;
			
		});
	}
	
	
	// single or group saving of converted posts via ajax
	function saveConverted( content, $linkObject ){
		if ( doingAjax ) return;
		doingAjax = true;
		var jsonData = $linkObject.data('json');
		jsonData.content = content;
		$.ajax({
			method: "POST",
			url: wdbvfObj.ajaxUrl,
			data: jsonData
		})
		.done(function( data ){
			doingAjax = false;
			return;
		})
		.fail(function(){
			doingAjax = false;
			console.log('An error occured');
		}).
		always(function(){
			$("#wdbvf-convert-checkbox-"+jsonData.post).prop("checked", false);
			$("#wdbvf-convert-checkbox-"+jsonData.post).prop("disabled", true);
			$linkObject.parent().html( wdbvfObj.convertedSingleMessage );
			convertPosts();
		});
	}
	
	// top action button handler
	$doactionTopBtn.click(function(e){
		e.preventDefault();
		if( $('select[name="action"]').val() === 'bulk-convert' ){
			convertChecked();
		}
	});
	
	// bottom action button handler
	$doactionBottomBtn.click(function(e){
		e.preventDefault();
		if( $('select[name="action2"]').val() === 'bulk-convert' ){
			convertChecked();
		}
	});
	
	// add checked posts to converting queue and run converting process
	function convertChecked(){
		$('input[name="bulk-convert[]"]').each(function( index ){
			if( $(this).prop("checked") == true ){
				convertQueue.push( $(this).val() );
			}
		});
		$doactionTopBtn.prop("disabled", true);
		$doactionBottomBtn.prop("disabled", true);
		convertPosts();
	}


})( jQuery );