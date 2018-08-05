function fran_test_talk_to_frontend(method, server_options, success_callback, error_callback) {

    if (!server_options) {
        server_options = {};
    }

    // noinspection ES6ModulesDependencies
    var outvars = jQuery.extend({}, server_options);
    // noinspection JSUnresolvedVariable
    outvars._ajax_nonce = fran_test_frontend_ajax_obj.nonce;
    // noinspection JSUnresolvedVariable
    outvars.action = fran_test_frontend_ajax_obj.action;
    outvars.method = method;
    // noinspection ES6ModulesDependencies
    // noinspection JSUnresolvedVariable
    var fran_test_ajax_req = jQuery.ajax({
        type: 'POST',
        beforeSend: function () {
            if (fran_test_ajax_req && (fran_test_ajax_req !== 'ToCancelPrevReq') && (fran_test_ajax_req.readyState < 4)) {
                //    fran_test_ajax_req.abort();
            }
        },
        dataType: "json",
        url: fran_test_frontend_ajax_obj.ajax_url,
        data: outvars,
        success: success_handler,
        error: error_handler
    });

    function success_handler(data) {

        // noinspection JSUnresolvedVariable
        if (data && data.is_valid) {
            if (success_callback) {
                success_callback(data.data);
            } else {
                console.debug(data);
            }
        } else {
            if (!data) {
                data = {message: 'no response'};
            }
            if (error_callback) {
                error_callback(data);
            } else {
                console.debug(data);
            }

        }
    }

    /**
     *
     * @param {XMLHttpRequest} jqXHR
     * @param {Object} jqXHR.responseJSON
     * @param {string} textStatus
     * @param {string} errorThrown
     */
    function error_handler(jqXHR, textStatus, errorThrown) {
        if (errorThrown === 'abort' || errorThrown === 'undefined') return;
        var what = '';
        var message = '';
        if (jqXHR && jqXHR.responseText) {
            try {
                what = jQuery.parseJSON(jqXHR.responseText);
                if (what !== null && typeof what === 'object') {
                    if (what.hasOwnProperty('message')) {
                        message = what.message;
                    } else {
                        message = jqXHR.responseText;
                    }
                }
            } catch (err) {
                message = jqXHR.responseText;
            }
        } else {
            message = "textStatus";
            console.info('Admin Ecomhub ajax failed but did not return json information, check below for details', what);
            console.error(jqXHR, textStatus, errorThrown);
        }

        if (error_callback) {
            error_callback(message);
        } else {
            console.warn(message);
        }


    }
}