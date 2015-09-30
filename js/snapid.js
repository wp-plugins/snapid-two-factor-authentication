(function($) {
	window.SnapID = {

		ajax: function(data, type, callback) {
			$.ajax({
				url: snapid.ajaxurl,
				data: data,
				type: type,
				dataType: 'json'
			}).done(function(r) {
				if( !r.data ) {
					r.data = {};
				}
				callback(r);
			});;
		},

				update_time: function($parent, time) {
						var self = this;

						$parent.find('.snapid-time').text(--time);
						if(!time) {
								self.add_message($parent, 'Sorry, time is up.', true);
								return;
						}
						SnapID.countdown = setTimeout(function() {
								self.update_time($parent, time);
						}, 1000);
				},

				add_message: function($parent, message, close) {
						clearTimeout(SnapID.countdown); // clear last closed modal
						clearTimeout(SnapID.polling); // clear last closed modal

						$parent.find('.snapid-message').remove();

						if( message === '' ) {
								return;
						}

						$parent.prepend('<div class="snapid-message">' + message + '</div>');
						if (close) {
								setTimeout(function() {
										$parent.find('.snapid-message').remove();
										$.snapid_modal.close();
								}, 5000);
						}
				},

				auth_modal: function($parent) {
					$parent.snapid_modal({
						escapeClose: false,
						clickClose: false,
						showClose: false,
						zIndex: 999999
					});
				}

	};
})(jQuery);
