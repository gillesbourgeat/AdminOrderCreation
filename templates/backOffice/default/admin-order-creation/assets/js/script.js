(function(){
    var $modal = $('#modal-order-creation');
    var currentRequest;

    // fix bug bootstrap 3 and select2
    $.fn.modal.Constructor.prototype.enforceFocus = function() {};

    var timer = null;
    function refreshWithTimer($form, event) {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        timer = setTimeout(function($form){
            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        }, 700, $form);
    }

    function initSelect($target, allowClear){

        if (typeof allowClear === "undefined") {
            allowClear = false;
        }
        return $target.select2({
            allowClear: allowClear,
            templateResult: function(data){
                if (!data.id) return data.text;
                var prefix = data.element.dataset.color ? '<span class="label" style="background-color: ' + data.element.dataset.color + ';width: 50px;">&nbsp;</span>' : '';
                prefix += (data.element.dataset.ref ? ' <span style="font-weight: bold;">' + data.element.dataset.ref + '</span>' : '');
                return $(prefix + ' <span>' + data.text + '</span>');
            },
            templateSelection: function(data){
                if (!data.id) return data.text;
                var prefix = data.element.dataset.color ? '<span class="label" style="background-color: ' + data.element.dataset.color + ';width: 50px;">&nbsp;</span>' : '';
                prefix += (data.element.dataset.ref ? ' <span style="font-weight: bold;">' + data.element.dataset.ref + '</span>' : '');
                return $(prefix + ' <span>' + data.text + '</span>');
            }
        });
    }

    function getFormData($form, data){
        var formData = $form.serializeArray();

        for (var i in formData) {
            for (var e in data) {
                if (formData[i].name  === e) {
                    formData[i].value = data[e];
                    delete data[e];
                }
            }
        }

        for (var e in data) {
            formData.push({
                    name: i,
                    value: data[i]
                }
            );
        }

        return formData
    }

    function initAjaxSelectCustomer($target){
        return $target.select2({
            ajax: {
                url: $target.data('url'),
                dataType: 'json',
                delay: 250,
                data: function (params){
                    return {
                        q: params.term,
                        customerId: $target.data('customer-id')
                    };
                },
                processResults: function (data){
                    return {results: data.items};
                },
                error: function(jqXHR, textStatus){
                    if (jqXHR.statusText === 'abort') return;
                    $target.select2('destroy');
                    $modal.displayError(jqXHR, textStatus);
                },
                cache: false
            },
            minimumInputLength: 3,
            placeholder: $target.data('placeholder'),
            templateResult: function(data){
                if (data.loading) return data.text;

                var markup = "<div class='select2-result-repository clearfix'>";
                markup += data.ref + ' : (' + data.firstname + ' ' + data.lastname + ')' + '</br><small>' + data.address + '</small>';
                markup += "</div>";

                return $(markup);
            },
            templateSelection: function(data){
                if (data.text) {
                    return data.text;
                }

                return data.ref + ' : (' + data.firstname + ' ' + data.lastname + ')';
            }
        });
    }

    function initAjaxSelectProduct($target){
        return $target.select2({
            ajax: {
                url: $target.data('url'),
                dataType: 'json',
                delay: 250,
                data: function (params){
                    return {
                        q: params.term
                    };
                },
                processResults: function (data){
                    return {results: data.items};
                },
                error: function(jqXHR, textStatus){
                    if (jqXHR.statusText === 'abort') return;
                    $target.select2('destroy');
                    $modal.displayError(jqXHR, textStatus);
                },
                cache: false
            },
            minimumInputLength: 3,
            placeholder: $target.data('placeholder'),
            templateResult: function(data){
                if (data.loading) return data.text;

                var markup = "<div class='select2-result-repository clearfix'>";
                markup += data.ref + ' : ' + data.title;
                markup += "</div>";

                return $(markup);
            },
            templateSelection: function(data){
                if (data.text) {
                    return data.text;
                }

                return data.ref + ' : ' + data.title;
            }
        });
    }

    /****** Modal methods ******/
    $modal.loaderOff = function(){
        $modal.find('.modal-loader').addClass('hidden');
        $modal.find('.modal-body').removeClass('hidden');
    };

    $modal.loaderOn = function(){
        $modal.find('.modal-loader').removeClass('hidden');
        $modal.find('.modal-body').addClass('hidden');
    };

    $modal.reset = function(){
        $modal.hideError();
        $modal.loaderOn();
    };

    $modal.hideError = function(){
        $modal.find('.modal-error').addClass('hidden').find('iframe').contents().find('html').empty();
    };

    $modal.displayError = function(jqXHR, textStatus){
        if (jqXHR.statusText === 'abort') return;
        $modal.loaderOff();
        $modal.find('.modal-body').addClass('hidden');
        var $error = $modal.find('.modal-error').removeClass('hidden');
        $error.find('.textStatus').html(textStatus);
        $error.find('iframe').contents().find('html').html(jqXHR.responseText);
    };

    $modal.modalReady = function(){
        var $form = $modal.find('.modal-body form');

        var $selectStatus = initSelect($form.find('.js-select-status'));
        var $selectCustomer = initAjaxSelectCustomer($form.find('.js-select-customer'));
        var $selectProduct = initAjaxSelectProduct($form.find('.js-select-product'));
        var $selectProductSaleElement = initSelect($form.find('.js-select-product-sale-element'));
        var $selectInvoiceAddress = initSelect($form.find('.js-select-invoice-address'));
        var $selectDeliveryAddress = initSelect($form.find('.js-select-delivery-address'));
        var $selectCreditNote = initSelect($form.find('.js-select-credit-note'), true);
        var $selectCreditNoteStatus = initSelect($form.find('.js-select-credit-note-status'));
        var $selectCreditNoteType = initSelect($form.find('.js-select-credit-note-type'));

        $form.on('submit', function(event){
            event.preventDefault();

            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'create'
            }));
        });

        $selectStatus.on('select2:select', function(event){
            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        $selectCustomer.on('select2:select', function(event){
            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[customer_id]': event.params.data.id,
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        $selectInvoiceAddress.on('select2:select', function(event){
            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        $selectDeliveryAddress.on('select2:select', function(event){
            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        $selectProduct.on('select2:select', function(event){
            $(event.target).parents('tr').find('.js-refresh-price').val('1');

            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        $selectProductSaleElement.on('select2:select', function(event){
            $(event.target).parents('tr').find('.js-refresh-price').val('1');

            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        $selectCreditNote.on('select2:select', function(event){
            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        $form.on('change', '.js-field-currency', function(event){
            $modal.loadAjax(event, getFormData($form, {
                'credit-note-create[action]': 'refresh'
            }));
        });

        $form.on('keyup change', '.js-action-refresh', function(event){
            if ($(this).val().length) {
                refreshWithTimer($form, event);
            }
        });

        var currentProductRequestTax;
        $form.on('keyup', '.js-product-price-with-tax, .js-product-price-without-tax', function(event){
            if (currentProductRequestTax) currentProductRequestTax.abort();

            var $th = $(this), $thr = $(this).parents('tr');

            var val = parseFloat($(this).val());

            if (!val) {
                val = 0;
            }

            currentProductRequestTax = $.ajax({
                url: $(this).data('url'),
                dataType: 'json',
                data: {
                    price: val,
                    tax_rule: this.dataset.taxRuleId
                }
            });

            // ajax success
            currentProductRequestTax.done(function(data){
                if ($th.hasClass('js-product-price-without-tax')) {
                    $thr.find('.js-product-price-with-tax').val(data.result);
                } else {
                    $thr.find('.js-product-price-without-tax').val(data.result);
                }

                refreshWithTimer($form, event);
            });

            // ajax error
            currentProductRequestTax.fail(function(jqXHR, textStatus){
                if (jqXHR.statusText === 'abort') return;
                $modal.displayError(jqXHR, textStatus);
            });
        });


        /***** Product line *****/
        var $tableProductLine = $form.find('.js-table-product-line');
        var templateProductLine = $('#template-order-creation-product-line').html();

        $tableProductLine.on('click', '.js-action-add', function(event){
            event.preventDefault();
            $(this).data('key', parseInt($(this).data('key')) + 1);

            var templateProductLineKey = templateProductLine.replace(/\[\]/g, '[' + $(this).data('key') + ']');
            $tableProductLine.find('tbody').append(templateProductLineKey);

            var $selectProduct = initAjaxSelectProduct($form.find('.js-table-product-line tbody .js-select-product').last());

            $selectProduct.on('select2:select', function(event){
                $modal.loadAjax(event, getFormData($form, {
                    'admin-order-creation-create[action]': 'refresh'
                }));
            });

            /*if ($tableProductLine.find('tbody tr').not('.js-no-free-amount').length) {
                $tableProductLine.find('.js-no-free-amount').addClass('hidden');
            } else {
                $tableProductLine.find('.js-no-free-amount').removeClass('hidden');
            }*/
        });

        $tableProductLine.on('click', '.js-action-delete', function(event){
            event.preventDefault();
            $(this).parents('tr').remove();

            $modal.loadAjax(event, getFormData($form, {
                'admin-order-creation-create[action]': 'refresh'
            }));
        });

        var $shippingArea = $form.find('.js-shipping-area');

        var currentRequestTax;
        $shippingArea.on('keyup', '.js-field-amount-without-tax, .js-field-amount-with-tax', function(event){
            if (currentRequestTax) currentRequestTax.abort();

            var $th = $(this), $thr = $shippingArea;

            var val = parseFloat($(this).val());

            if (!val) {
                val = 0;
            }

            currentRequestTax = $.ajax({
                url: $(this).data('url'),
                dataType: 'json',
                data: {
                    price: val,
                    tax_rule: parseInt($thr.find('.js-field-tax-rule').val())
                }
            });

            // ajax success
            currentRequestTax.done(function(data){
                if ($th.hasClass('js-field-amount-without-tax')) {
                    $thr.find('.js-field-amount-with-tax').val(data.result);
                } else {
                    $thr.find('.js-field-amount-without-tax').val(data.result);
                }

                refreshWithTimer($form, event);
            });

            // ajax error
            currentRequestTax.fail(function(jqXHR, textStatus){
                if (jqXHR.statusText === 'abort') return;
                $modal.displayError(jqXHR, textStatus);
            });
        });

        $shippingArea.on('change', '.js-field-tax-rule', function(event){
            if (currentRequestTax) currentRequestTax.abort();

            var $th = $(this), $thr = $shippingArea;

            var val = parseFloat($thr.find('.js-field-amount-with-tax').val());

            if (!val) {
                val = 0;
            }

            currentRequestTax = $.ajax({
                url: $(this).data('url'),
                dataType: 'json',
                data: {
                    price: val,
                    tax_rule: parseInt($thr.find('.js-field-tax-rule').val())
                }
            });

            // ajax success
            currentRequestTax.done(function(data){
                if ($th.hasClass('js-field-amount-without-tax')) {
                    $thr.find('.js-field-amount-with-tax').val(data.result);
                } else {
                    $thr.find('.js-field-amount-without-tax').val(data.result);
                }

                refreshWithTimer($form, event);
            });

            // ajax error
            currentRequestTax.fail(function(jqXHR, textStatus){
                if (jqXHR.statusText === 'abort') return;
                $modal.displayError(jqXHR, textStatus);
            });
        });



    };

    $modal.loadAjax = function(event, data){
        if (typeof data === 'undefined') {
            data = {};
        }

        // kill last ajax request if not if it's not finished
        if (currentRequest) currentRequest.abort();

        // to avoid a display bug with select2
        setTimeout(function(data){
            // ajax start
            currentRequest = $.ajax({
                url: $modal.data('ajaxUrl'),
                data: data,
                method: 'POST'
            });

            // ajax success
            currentRequest.done(function(data, textStatus, xhr){
                $modal.loaderOff();
                $modal.find('.modal-body').html(data);
                $modal.modalReady();
            });

            // ajax error
            currentRequest.fail(function(jqXHR, textStatus){
                $modal.displayError(jqXHR, textStatus);
            });
        }, 100, data);
    };

    $('body').on('click', '#btn-create-order', function(event){
        $modal.modal('show');
        var customerId = $(this).data('customerId');
        var creditNoteId = $(this).data('creditNoteId');

        $modal.loadAjax(event, {
            'admin-order-creation-create[action]': 'open',
            'admin-order-creation-create[customer_id]': customerId,
            'admin-order-creation-create[credit_note_id]': creditNoteId
        });
    });

    $modal.on('hidden.bs.modal', function(){
        $modal.reset();
    });
}());