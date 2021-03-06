
function WDBVF_Init() {
	// Declaring letiables at initialization
	let scan_button = document.querySelector('#wdbvf-scan-btn'),
	output = document.querySelector('#wdbvf-output'),
	single_convert_links = Array.from(document.querySelectorAll('.wdbvf-single-convert')),

	do_action_top_button = document.querySelector('#doaction'),
	do_action_bottom_button = document.querySelector('#doaction2'),

	convert_queue = [],
	doing_ajax = false;

	convert_images_obj = {
		input: document.querySelector('#wdbvf-convert-images input'),
		nonce: wdbvfObj.ConvertImagesNonce,
		action: 'wdbvf_convert_images'
	};
	auto_apply_obj = {
		input: document.querySelector('#wdbvf-enable-auto-apply input'),
		nonce: wdbvfObj.autoApplyOnPublicationNonce,
		action: 'wdbvf_auto_apply'
	};	
	remove_not_converted_obj = {
		input: document.querySelector('#wdbvf-remove-not-converted input'),
		nonce: wdbvfObj.removeNotConvertedNonce,
		action: 'wdbvf_remove_not_converted'
	};	

	scan_button.addEventListener('click', function(e) {
		e.preventDefault();
		scan_button.setAttribute("disabled", true);
		scanPosts(0, -1);
	});
	

	function ajaxSaveSettings(obj) {
		let status;

		if(obj.input.checked) status = 1;
		else status = 0;

		jQuery.ajax({
			method: "POST",
			url: wdbvfObj.ajaxUrl,
			data: { action : obj.action, status: status, nonce : obj.nonce }
		})
		.done(function() {
			alert('Saved!');
		});
	}
	auto_apply_obj.input.addEventListener('click', function() {
		ajaxSaveSettings(auto_apply_obj);
	});
	convert_images_obj.input.addEventListener('click', function() {
		ajaxSaveSettings(convert_images_obj);
	});
	remove_not_converted_obj.input.addEventListener('click', function() {
		ajaxSaveSettings(remove_not_converted_obj);
	});


	// Scanning posts via ajax
	function scanPosts( offset = 0, total = -1) {

		if ( doing_ajax ) return;
		doing_ajax = true;
		let nonce = scan_button.dataset.nonce;

		output.innerHTML = wdbvfObj.scanningMessage;
		jQuery.ajax({
			method: "POST",
			url: wdbvfObj.ajaxUrl,
			data: { action : "wdbvf_scan_posts", offset : offset, total : total, _wpnonce : nonce }
		})
		.done(function( data ){
			doing_ajax = false;
			if ( data.error ) {
				output.innerHTML = data.message;
				return;
			}
			if ( data.offset >= data.total ) {
				scan_button.setAttribute("disabled", false);
				output.innerHTML = data.message;
				document.location.href = document.location.href + "&wdbvf_scan_finished=1";
				return;
			}
			scanPosts( data.offset, data.total);
			output.innerHTML = data.message;
		})
		.fail(function(){
			doing_ajax = false;
			output.innerHTML = wdbvfObj.serverErrorMessage;
		});
	}



	// table "Convert" link handler
	single_convert_links.forEach(link => {
		link.addEventListener('click', function(e){
			e.preventDefault();
			
			let raw_data = JSON.parse(this.dataset.json);
			let post_id = raw_data.post;
			convert_queue.push(post_id);
			convertPosts();
		});
	});

	
	// Single or group posts converting via ajax
	function convertPosts() {
		if( convert_queue.length == 0 ){
			do_action_top_button.removeAttribute("disabled");
			do_action_bottom_button.removeAttribute("disabled");
			return;
		}
		if ( doing_ajax ) return;
		doing_ajax = true;
		let postID = convert_queue.shift();

		let linkObject = jQuery('#wdbvf-single-convert-' + postID);
		let proceededRow = linkObject.closest('tr');

		if(linkObject){ 
			linkObject.html(wdbvfObj.convertingSingleMessage);
			jQuery.ajax({
				method: "GET",
				url: wdbvfObj.ajaxUrl,
				data: linkObject.data('json')
			})
			.done(function( data ) {
				doing_ajax = false;
				if ( data.error ) {
					return;
				}
				saveConverted( data.message, linkObject, proceededRow );
				return;
			})
			.fail(function(){
				doing_ajax = false;
				
			});
		}
	}


	// Single or group saving of converted posts via ajax
	function saveConverted( content, linkObject, proceededRow ) {

		let is_convert_images = document.querySelector('#wdbvf-convert-images input').checked;

		if ( doing_ajax ) return;
		doing_ajax = true;
		let json_data = linkObject.data('json');
		json_data.content = content;
		json_data.isConvertImages = is_convert_images;
		jQuery.ajax({
			method: "POST",
			url: wdbvfObj.ajaxUrl,
			data: json_data,
		})
		.done(function( ){
			doing_ajax = false;
			return;
		})
		.fail(function(){
			doing_ajax = false;
			console.log('An error occured');
		}).
		always(function(){
			document.querySelector("#wdbvf-convert-checkbox-"+json_data.post).setAttribute("checked", false);
			document.querySelector("#wdbvf-convert-checkbox-"+json_data.post).setAttribute("disabled", true);
			linkObject.html(wdbvfObj.convertedSingleMessage);			
			proceededRow.addClass('proceeded');
			convertPosts();
		});
	}

	// top action button handler
	do_action_top_button.addEventListener('click', function(e){
		e.preventDefault();
		if( document.querySelector('select[name="action"]').value === 'bulk-convert' ){
			convertChecked();
		}
	});

	// bottom action button handler
	do_action_bottom_button.addEventListener('click', function(e){
		e.preventDefault();
		if( document.querySelector('select[name="action2"]').value === 'bulk-convert' ){
			convertChecked();
		}
	});

	// add checked posts to converting queue and run converting process
	function convertChecked() {
		let all_checked = Array.from(document.querySelectorAll('input[name="bulk-convert[]"]'));
		
		all_checked.forEach(element => {
			if(element.checked && !element.hasAttribute('disabled') ){
				convert_queue.push(element.value);
			}
		});

		convertPosts();
		do_action_top_button.setAttribute("disabled", true);
		do_action_bottom_button.setAttribute("disabled", true);
	}
}
document.addEventListener('DOMContentLoaded', WDBVF_Init);
	