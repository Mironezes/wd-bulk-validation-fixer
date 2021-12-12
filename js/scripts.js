(function( $ ){
	
	// declaring variables at initialization
	var $scanBtn = $('#wdbvf-scan-btn'),
		$convertAllBtn = $('#wdbvf-convert-all-btn'),
		$output = $('#wdbvf-output'),
		$singleConvertLinks = $('.wdbvf-single-convert'),
		$doactionTopBtn = $('#doaction'),
		$doactionBottomBtn = $('#doaction2'),
		$validationOnlyCheckbox = $('#wdbvf-validation-only input');
		$resetValidationBtn = $('#wdbvf-validation-only button');
		convertQueue = [],
		doingAjax = false;
		
		if(sessionStorage.getItem('wdbc_validation_only')){
			$validationOnlyCheckbox.prop('checked', true);
		}

   $validationOnlyCheckbox.on('click', function() {
      if(sessionStorage.getItem('wdbc_validation_only')) { 
				sessionStorage.removeItem('wdbc_validation_only');
			}
			else {
				sessionStorage.setItem('wdbc_validation_only', true);
			}
	 });


	// creating hidden Blocks editor
	const settings = {
		editorMode: 'visual',
		panels: {
			'post-status': {
				opened: true
			}
		},
		features: {
			fixedToolbar: false,
			welcomeGuide: true,
			fullscreenMode: true,
			showIconLabels: false,
			themeStyles: true,
			showBlockBreadcrumbs: true,
			welcomeGuideTemplate: true
		},
		hiddenBlockTypes: [],
		preferredStyleVariations: {},
		localAutosaveInterval: 15
	};

	$('<div />').attr('id', 'wdbvf-editor').attr('style', 'display: none').appendTo('body');
	wp.editPost.initializeEditor('wdbvf-editor', 'post', 1, settings);
	
	$scanBtn.click(function(e){

		e.preventDefault();
		$scanBtn.prop("disabled", true);
		$convertAllBtn.hide();

		if(sessionStorage.getItem('wdbc_validation_only')) 	{
			scanPosts(0, -1, 1);
		}
		else {
			scanPosts(0, -1, 0);
		}
	});
	
	// scanning posts via ajax
	function scanPosts( offset = 0, total = -1, mode) {
		$mode = mode;
		console.log($mode);

		if ( doingAjax ) return;
		doingAjax = true;
		var nonce = $scanBtn.data('nonce');

		$output.html( wdbvfObj.scanningMessage );
		$.ajax({
			method: "POST",
			url: wdbvfObj.ajaxUrl,
			data: { action : "wdbvf_scan_posts", mode : $mode, offset : offset, total : total, _wpnonce : nonce }
		})
		.done(function( data ){
			doingAjax = false;
			if ( typeof data !== "object" ) {
				$output.html( wdbvfObj.serverErrorMessage );
				return;
			}
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
			if(sessionStorage.getItem('wdbc_validation_only')){
				$validationOnlyCheckbox.prop('checked', true);
			}
			scanPosts( data.offset, data.total, $mode);
			$output.html( data.message );
		})
		.fail(function(){
			doingAjax = false;
			$output.html( wdbvfObj.serverErrorMessage );
		});
	}
	
	// "Bulk Convert All" button handler
	$convertAllBtn.click(function(e){
		e.preventDefault();
		if( ! confirm( wdbvfObj.confirmConvertAllMessage ) ) return;
		$convertAllBtn.prop("disabled", true);
		bulkConvertPosts();
	});
	
	// table "Convert" link handler
	$singleConvertLinks.click(function(e){
		e.preventDefault();
		
		var postID = $(this).data('json').post;
		convertQueue.push( postID );
		convertPosts();
	});
	
	// reset validation status
	$resetValidationBtn.on('click', function() {
		let confirm = window.confirm('Are you sure?') ? true : false;
		let data_obj = {
			data: 'true',
			action: 'reset_posts_validation_status',
			noncw: wdbvfObj.resetPostsValidationNonce
		};
		if(confirm) {
			jQuery.ajax({
				url: wdbvfObj.ajaxUrl,
				type: 'post',
				data: data_obj
			});
		}

	});


	// bulk posts converting via ajax
	function bulkConvertPosts( offset = 0, total = -1){
		if ( doingAjax ) return;

		let is_validation_only = sessionStorage.getItem('wdbc_validation_only');

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
			if ( typeof data !== "object" ) {
				$output.html( wdbvfObj.serverErrorMessage );
				return;
			}
			if ( data.error ) {
				$output.html( data.message );
				return;
			}
			var convertedData = [];
			var arrayLength = data.postsData.length;
			for (var i = 0; i < arrayLength; i++) {

				if(is_validation_only) {
					var convertedPost = {
						id		: data.postsData[i].id,
						content	: data.postsData[i].content
					};
				}
				else {
					var convertedPost = {
						id		: data.postsData[i].id,
						content	: convertToBlocks( data.postsData[i].content )
					};
				}

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
			if ( typeof data !== "object" ) {
				$output.html( wdbvfObj.serverErrorMessage );
				return;
			}
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
		if(sessionStorage.getItem('wdbc_validation_only')){
			$validationOnlyCheckbox.prop('checked', true);
		}

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
			let is_validation_only = sessionStorage.getItem('wdbc_validation_only');

			doingAjax = false;
			if ( typeof data !== "object" ) {
				$linkObject.parent().html( wdbvfObj.failedMessage );
				return;
			}
			if ( data.error ) {
				$linkObject.parent().html( wdbvfObj.failedMessage );
				return;
			}
			if(is_validation_only) {
				saveConverted( data.message, $linkObject );
			}
			else {
				var content = convertToBlocks( data.message );
				saveConverted( content, $linkObject );	
			}

			return;
		})
		.fail(function(){
			doingAjax = false;
			$linkObject.parent().html( wdbvfObj.failedMessage );
		});
	}
	
	// posts converting using built in Wordpress library
	function convertToBlocks( content ){
		var blocks = wp.blocks.rawHandler({ 
			HTML: content
		});
		return wp.blocks.serialize(blocks);
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
			$("#wdbvf-convert-checkbox-"+jsonData.post).prop("checked", false);
			$("#wdbvf-convert-checkbox-"+jsonData.post).prop("disabled", true);
			if ( typeof data !== "object" ) {
				$linkObject.parent().html( wdbvfObj.failedMessage );
				return;
			}
			if ( data.error ) {
				$linkObject.parent().html( wdbvfObj.failedMessage );
				return;
			}
			$linkObject.parent().html( wdbvfObj.convertedSingleMessage );
			convertPosts();
			return;
		})
		.fail(function(){
			doingAjax = false;
			$("#wdbvf-convert-checkbox-"+jsonData.post).prop("checked", false);
			$("#wdbvf-convert-checkbox-"+jsonData.post).prop("disabled", true);
			$linkObject.parent().html( wdbvfObj.failedMessage );
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