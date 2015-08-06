(function($) {
    var SnapID_Admin = {

        init: function() {
            this.events();
        },

        events: function() {
            var self = this;
            
            $('#snapid-join').click(function(e) {
                self.register(e, $(this).parents('td'));
            });

            $('#snapid-remove').click(function(e) {
                self.remove(e, $(this).parents('td'));
            });

            $('.snapid-radio input').change(function(e) {
                $(this).parents('td').find('.snapid-roles-wrap').toggle();
            });

            $('.snapid-roles-wrap input').change(function(e) {
                var name = $(this).attr('name');
                if ($(this).is(':checked')) {
                    $('input[name="'+name+'"]').not($(this)).attr('checked', false);
                }
            });

            $('#snapid-learn').click(function(e) {
                e.preventDefault();
                $('#snapid-example').snapid_modal();
            });

            $('#snapid-prev, #snapid-next').click(function(e) {
                e.preventDefault();
                if ($(this).attr('id') === 'snapid-prev' && $('#snapid-example img.snapid-selected').prev('img').length > 0) {
                    $('#snapid-example img.snapid-selected').removeClass('snapid-selected').prev('img').addClass('snapid-selected');
                }
                if ($(this).attr('id') === 'snapid-next' && $('#snapid-example img.snapid-selected').next('img').length > 0) {
                    $('#snapid-example img.snapid-selected').removeClass('snapid-selected').next('img').addClass('snapid-selected');
                }
                if($('#snapid-example img.snapid-selected').next('img').length === 0) {
                    $('#snapid-next').hide();
                } else {
                    $('#snapid-next').show();
                }
                if($('#snapid-example img.snapid-selected').prev('img').length === 0) {
                    $('#snapid-prev').hide();
                } else {
                    $('#snapid-prev').show();
                }
            });

            $('#snapid-uninstall-form').submit(function(e) {
                if ($('#snapid-delete-settings').is(':checked') || $('#snapid-delete-users').is(':checked')) {
                    if (!confirm('Deleting SnapID data cannot be undone. Proceed?')) {
                        e.preventDefault();
                    }
                }
            });
        },

        message: function(str) {
            var $msg = $('.snapid-message-profile');

            clearTimeout(SnapID.countdown); // clear last closed modal
            clearTimeout(SnapID.polling); // clear last closed modal

            $msg.html(str).fadeIn(500, function() {
                $msg.delay(5000).fadeOut(500);
            });
        },

        register: function(e, $parent) {
            e.preventDefault();
            var self = this,
                $snapid_auth = $('#snapid-auth'),
                data = {
                   action: 'snapid_register',
                   nonce: $('#snapid-nonce').val(),
                   user_id: $('#snapid-user-id').val()
                };
                
            $snapid_auth.find('#snapid-tocode').text('*****');
            $snapid_auth.find('#snapid-key').text('*******');

            SnapID.auth_modal($snapid_auth); 

            SnapID.ajax(data, 'POST', function(response) {
                if (response && response.success && !response.data.errordescr) {
                    $snapid_auth.find('#snapid-tocode').text(response.data.tocode);
                    $snapid_auth.find('#snapid-key').text(response.data.joincode);
                    SnapID.update_time( $snapid_auth, 90 ); // TODO: make time a setting
                    self.join_check(response, $parent);
                } else if (response && response.error && response.data.errordescr) {
                    SnapID.add_message($snapid_auth, response.data.errordescr, true);
                } else {
                    SnapID.add_message($snapid_auth, 'Sorry, something went wrong...', true);
                }
            });
        },

        join_check: function(response, $parent) {
            var self = this,
                $snapid_auth = $('#snapid-auth'),
                data = {
                    action: 'snapid_join_check',
                    nonce: $('#snapid-nonce').val(),
                    response: response
                };

            SnapID.ajax(data, 'POST', function(response) {
                if (!response) {
                    self.message('Sorry, something went wrong...');
                    return;
                }
                if (response.data.keyreceived) {
                    $.snapid_modal.close();
                    self.message(response.data.errordescr);
                    if (response.success) {
                        $parent.find('.snapid-toggle').toggle();
                    }
                    return;
                }
                if (response.error) {
                    $.snapid_modal.close();
                    response.errordescr = response.errordescr ? response.errordescr : 'Sorry, something went wront';
                    self.message(response.errordescr);
                    return;
                }
                SnapID.polling = setTimeout(function() {
                    self.join_check(response, $parent);
                }, 1000);
            });
        },

        remove: function(e, $parent) {
            e.preventDefault();
            var self = this,
                data = {
                    action: 'snapid_remove',
                    nonce: $('#snapid-nonce').val(),
                    user_id: $('#snapid-user-id').val()
                };

            $parent.find('.snapid-spinner').show();
           
            SnapID.ajax(data, 'POST', function(response) {
                $parent.find('.snapid-spinner').hide();
                if (!response) {
                    self.message('Sorry, something went wrong.');
                } else if (response.data.errordescr !== ''){
                    self.message(response.data.errordescr);
                } else {
                    self.message('SnapID has been removed from this account.');
                    $parent.find('.snapid-toggle').toggle();
                }
            }); 
        }
    };

    SnapID_Admin.init();

})(jQuery);