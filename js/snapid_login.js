(function($) {
    var SnapID_Login = {
        nonce: $('#snapid-nonce').val(),
        snapid_time: 90,
        two_step: false,

        init: function() {
            this.events();
        },

        events: function() {
            var self = this;
            
            // Set Events for One-Step
            if (snapid.one_step_enabled) {
                $('#snapid-login').click(function(e) {
                    self.login(e, '');
                });
            }

            // Set Events for Two-Step
            if (snapid.two_step_enabled) {
                $('#loginform').submit(function(e) {
                    if (self.two_step === false && !$(this).hasClass('snapid-form')) {
                        $(this).addClass('snapid-form');
                        self.two_step_check(e, $(this));
                    }
                });
            }

            // Prevent submitting multiple times
            $(document.body).on('submit', '.snapid-form', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        },

        login: function(e, $form) {
            e.preventDefault();
            var self = this,
                $snapid_auth = $('#snapid-auth'),
                data = {
                    action: 'snapid_authenticate',
                    nonce: self.nonce,
                    two_step: ($form !== '') 
                };

            $snapid_auth.find('#snapid-tocode').text('*****');
            $snapid_auth.find('#snapid-key').text('*******');

            SnapID.add_message($snapid_auth, '', true); // empty message to remove previous message and clear timeouts

            SnapID.auth_modal($snapid_auth); 
            
            SnapID.ajax(data, 'GET', function(response) {
                if (!response) {
                    SnapID.add_message($snapid_auth, 'Something went wrong...', true);
                }
                $snapid_auth.find('#snapid-tocode').text(response.data.tocode);
                $snapid_auth.find('#snapid-key').text(response.data.snapidkey);
                SnapID.update_time( $snapid_auth, 90 ); // TODO: make time a setting
                self.keyid_check(response, $form);
            });
        },

        two_step_check: function(e, $form) {
            e.preventDefault();

            var self = this,
                data = $form.serialize() + '&action=snapid_two_step_check&nonce='+self.nonce;

            SnapID.ajax(data, 'POST', function(response) {
                if (response.success) {
                    self.login(e, $form);
                } else {
                    self.two_step = true;
                    $form.removeClass('snapid-form');
                    $form.submit();
                }
            });
        },

        keyid_check: function(response, $form) {
            var self = this,
                $snapid_auth = $('#snapid-auth'),
                data = {
                    action: 'snapid_keyid_check',
                    nonce: self.nonce,
                    response: response
                };

            SnapID.ajax(data, 'POST', function(response) {
                if (!response) {
                    SnapID.add_message($snapid_auth, 'Sorry, something went wrong...', true);
                    return;
                }
                if (response.data.errordescr && !response.data.keyreceived) {
                    SnapID.add_message($snapid_auth, response.data.errordescr, true);
                    return;
                }
                if (response.data.keyreceived) {
                    SnapID.add_message($snapid_auth, response.data.errordescr, false);
                    if ($form === '' ) {
                        setTimeout(function() {
                            window.location.replace(snapid.redirect_to);
                        }, 2000);
                    } else {
                        if ($form !== '') {
                            $form.removeClass('snapid-form');
                        }
                        self.two_step = true;
                        $form.submit();
                    }
                    clearTimeout(SnapID.countdown); // clear last closed modal
                    clearTimeout(SnapID.polling); // clear last closed modal
                    return;
                }
                SnapID.polling = setTimeout(function() {
                    self.keyid_check(response, $form);
                }, 1000);
            });
        },

    };
    SnapID_Login.init();
})(jQuery);