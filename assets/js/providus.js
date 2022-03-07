(function ($) {
    "use strict";

    let providus_submit = false;

    let providus = {}

    providus.providusCustomHandler = function() {

    };

    providus.providusFormHandler = function() {
        //hide the form
        $('#providus-woocommerce-form').hide();

        if ( providus_submit ) {
            providus_submit = false;
            return true;
        }

        let $form = $( '#providus_form form#payment-form, #providus_form form#order_review' );

        let providus_callback = function (response) {
            $form.append( '<input type="hidden" class="providus_reference" name="providus_reference" value="' + response.reference + '"/>' );
            providus_submit = true;

            $form.submit();

            $( 'body' ).block( {
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                },
                css: {
                    cursor: "wait"
                }
            } );
        }

        const email = wc_providus_bank_params.email;
        const amount = parseInt(wc_providus_bank_params.amount);
        const clientId = wc_providus_bank_params.client_id;
        const reference = wc_providus_bank_params.reference;

        let providusData = {
            email,
            amount,
            clientId,
            reference,
            onClose: function () {
                $( '#providus-woocommerce-form').show();
                $( this.el).unblock();
            },
            callback: providus_callback,
        }
        if(wc_providus_bank_params.phoneNumber) {
            providusData.phoneNumber = wc_providus_bank_params.phoneNumber;
        }

        let handler = PayWithProvidus.setup( providusData );

        handler.openIframe();

        return false;
    }

    providus.openProvidusFormDialog = function () {
        let pop_id = $(this).data('custom-popup');

        let find_popup_el = $(this).parents('body').find('#' + pop_id);
        find_popup_el.show();
    }

    providus.closeProvidusFormDialog = function () {
        let find_popup_el = $(this).parents('.providus_custom_payment_container').hide();
        find_popup_el.hide();
    }

    providus.submitPaymentFormHandler = function (e) {
        e.preventDefault();
        e.stopPropagation();

        $(this).find('input[type=submit]').attr('disabled', true);
        $(this).find('input[type=submit]').val('Please wait...');

        const form = new FormData(this);
        const email = form.get('pw-email');
        const amount = parseInt(form.get('pw-amount'));
        const phone = form.get('pw-phone-number');
        const nonce = form.get('pw-nonce');
        const button_id = form.get('pw-button-id');
        const reference = $(this).data('form-reference');

        let error_el = $(this).parents('.pw-providus-container').find('.error');
        let success_el = $(this).parents('.pw-providus-container').find('.success');

        const _this = this;

        error_el.hide();
        success_el.hide();
        error_el.html('');
        success_el.html('');

        let error_count = 0;
        let error_message = '';

        let email_reg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
        let phone_reg = /^\d{11}$/;

        if(email === '') {
            error_count += 1;
            error_message += '<p>Please enter your email</p>'
        }

        if(!email_reg.test(email)) {
            error_count += 1;
            error_message += '<p>Please enter a valid email</p>'
        }

        if(amount === '') {
            error_count += 1;
            error_message += '<p>No amount was specified</p>';
        }

        if(button_id === '') {
            error_count += 1;
            error_message += '<p>There is something wrong, please refresh your page and try again</p>'
        }

        if(phone.length > 0 && !phone_reg.test(phone)) {
            error_count += 1;
            error_message += '<p>Enter a valid phone number</p>'
        }

        if(error_count > 0) {
            error_el.html(error_message);
            error_el.fadeIn(350);
            $(this).find('input[type=submit]').removeAttr('disabled');
            $(this).find('input[type=submit]').val('Proceed');
            return;
        }


        let data = {
            email, amount, phone, nonce, button_id, reference, client_id: wc_providus_bank_params.client_id, 'action': 'providus_custom_payment_handler'
        }

        let successHandler = (response) => {
            if(response.status === false) {
                error_el.html(response.message);
                error_el.fadeIn(350);
                $( this.el).unblock();
                return;
            }

            const email = response.data.email;
            const amount = parseInt(response.data.amount);
            const clientId = response.data.client_id;
            const reference = response.data.reference;

            let providusData = {
                email,
                amount,
                clientId,
                reference,
                onClose: function () {
                    error_el.html('Payment dailog closed');
                    error_el.fadeIn(300);
                    $( this.el).unblock();
                },
                callback: (response) => {
                    success_el.html('<p>Bank Payment with reference number - ' + response.reference + ' was successful. <a href="javascript:void(0)" class="pw-close-dialog" onclick="$(this).parents(\'.providus_custom_payment_container\').hide();">Close dialog</a></p>');
                    $(_this).fadeOut(350);
                    _this.reset();
                    success_el.fadeIn(350);
                },
            }

            if(phone.length > 0) {
                providusData.phoneNumber = phone;
            }

            let handler = PayWithProvidus.setup( providusData );

            handler.openIframe();

            return false;
        }

        let providusData = {
            type: 'POST',
            dataType: 'json',
            url: wc_providus_bank_params.ajax_url,
            data,
            success: successHandler,
            error: (jqXHR, textStatus, errorThrown) => {
                console.log(errorThrown);
                error_el.innerHTML = errorThrown;
                error_el.fadeIn(350);
            }
        }

        $.ajax(providusData);
    }

    providus.stopCloseFormDialog = (e) => {
        e.stopPropagation();
    }

    providus.forceCloseFormDialog = (e) => {
        $('.providus_custom_payment_container').fadeOut(350);
        e.preventDefault();
    }


    providus.init = function () {
        $(document).on('click', '#providus-payment-button', providus.providusFormHandler);
        $(document).on('click', 'button.providus-custom-payment-button', providus.openProvidusFormDialog);
        $(document).on('click', '.pw-close-button, .pw-close-dialog', providus.closeProvidusFormDialog);
        $(document).on('submit', 'form.pw-payment-form', providus.submitPaymentFormHandler);
        $(document).on('click', 'section .pw-providus-container', providus.stopCloseFormDialog);
        $(document).on('click', 'section.providus_custom_payment_container', providus.forceCloseFormDialog);
    }

    $(window).on('load', () => {
        providus.init();
    });


})(jQuery);