document.observe("dom:loaded", function() {

    $('fastlycdn_general_enabled').observe('change', function (event) {
        $('row_fastlycdn_general_test_connection').toggle(event.findElement().value);
        $('row_fastlycdn_general_upload_vcl').toggle(event.findElement().value);
        $('row_fastlycdn_general_toggle_tls').toggle(event.findElement().value);
    });

    if (is_enabled == true) {
        Fastly.init();
    } else {
        $('row_fastlycdn_general_test_connection').hide();
        $('row_fastlycdn_general_upload_vcl').hide();
        $('row_fastlycdn_general_toggle_tls').hide();
        $('force_tls_state_unknown').show();
    }
});

var Fastly = {

    dialogContent: null,
    modalTitle: null,
    service: null,
    dialogId: null,
    divId: null,
    dialogWindow: null,
    overlayShowEffectOptions: null,
    overlayHideEffectOptions: null,
    activeVersion: null,
    nextVersion: null,
    tlsStatus: null,
    backends: null,
    backendId: null,

    loadDialogContent: {
        'vcl-upload-form-dialog': {
            title: 'You are about to upload VCL to Fastly',
            content: function () {
                return $('vcl-upload-form').innerHTML;
            }
        },
        'toggle-tls-form-dialog': {
            title: 'We are about to turn '+ this.tlsStatusText + ' TLS',
            content: function () {
                return $('toggle-tls-form').innerHTML;
            }
        },
        'error-page-form-dialog': {
            title: 'Update Error Page Content',
            content: function () {
                return $('error-page-form').innerHTML;
            }
        },
        'backend-config-form-dialog': {
            title: 'Update Backend Configuration',
            content: function () {
                return $('backend-config-form').innerHTML;
            }
        }
    },

    setLoaderZIndex: function () {
        $('loading-mask').setStyle({'z-index':10000});
    },

    init: function () {
        new Ajax.Request(check_service_url, {
            method:'post',
            loaderArea:false,
            onSuccess: function(transport) {
                var response = transport.responseText.evalJSON();
                if (response.status != false) {
                    this.activeVersion = response.active_version;
                    this.nextVersion = response.next_version;

                    new Ajax.Request(check_tls_url, {
                        method:'post',
                        loaderArea: false,
                        parameters: {
                            active_version: this.activeVersion
                        },
                        onCreate: function () {
                            $('force-tls-processing').show();
                        },
                        onSuccess: function(transport) {
                            var checkReqSetting = transport.responseText.evalJSON();
                            $('force-tls-processing').hide();
                            this.tlsStatus = checkReqSetting.status;
                            if (checkReqSetting.status != false) {
                                $('force_tls_state_enabled').show();
                            } else {
                                this.tlsStatusText = 'off';
                                $('force_tls_state_disabled').show();
                            }
                        }.bind(this),
                        onFailure: function() {
                            $('force_tls_state_unknown').show();
                        }
                    });

                    new Ajax.Request(get_backends_url, {
                        method:'post',
                        loaderArea: false,
                        parameters: {
                            active_version: this.activeVersion
                        },
                        onCreate: function () {
                            $('backends-loading').show();
                        },
                        onSuccess: function(transport) {
                            var response = transport.responseText.evalJSON();
                            $('backends-loading').hide();
                            if(response.status != false) {
                                var index = 0;
                                this.backends = response.backends;
                                var html = '';
                                response.backends.each(function(backend) {
                                    html += "<tr id='fastly_" + index + "'>";
                                    html += "<td><input name='backend_name_"+index+"' disabled='disabled' id='backend_name_"+index+"' title='Backend name' value='"+backend.name+"' class='input-text' style='width:180px' type='text'></td>";
                                    html += "<td style='text-align:center;'><button data-backendId='"+ index +"' class='backend-edit-btn' type='button'><span><span><span>Edit</span></span></span></button></td>";
                                    html += "</tr>";
                                    index++;
                                });

                                $('fastly-backends-list').update(html);
                                var self = this;
                                $$('.backend-edit-btn').each(function(element) {
                                    element.observe('click', function (event) {
                                        var element = event.findElement();
                                        var backend_id = element.readAttribute('data-backendId');
                                        self.backendId = backend_id;
                                        self.initDialog('backend-config-form');
                                    });
                                });
                            }
                        }.bind(this),
                        onFailure: function() {
                            // TO DO ERROR HANDLING
                        }
                    });
                } else {
                    $('force_tls_state_unknown').show();
                    $('backends-loading').hide();
                }
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    initDialog: function (divId) {
        this.hideAllBtnMessages();
        new Ajax.Request(check_service_url, {
            method:'post',
            onSuccess: function(transport) {
                var response = transport.responseText.evalJSON();
                if(response.error || response.status == false) {
                    var errorBtn = divId.replace('-form', '') + '-btn-error';
                    var errorMsg = 'Please check your Service ID and API key and try again.';
                    return this.setButtonMsg(errorMsg, errorBtn);
                }

                this.service = response;
                this.dialogId = divId + '-dialog';
                this.dialog = $(divId);
                this.divId = divId;

                if (divId == 'error-page-form') {
                    new Ajax.Request(get_error_page_resp_obj_url, {
                        method:'post',
                        parameters: {
                            active_version: this.service.active_version
                        },
                        onSuccess: function(transport) {
                            var response = transport.responseText.evalJSON();
                            if(response.status != false) {
                                $('error-page-form-html').update(response.errorPageResp.content);
                            }
                        }.bind(this),
                        onFailure: function() { alert('Something went wrong...'); }
                    });
                }

                this.prepareVersionMessage();
                this.openDialogWindow();

            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Prepare modal dialog label with active and clone versions
     */
    prepareVersionMessage: function () {
        if(this.dialog != null) {
            var version_label = $(this.divId+'-warning-label');
            var text = 'You are about to clone your active version '+ this.service.active_version
                + '. We\'ll upload your VCL to version ' + this.service.next_version + '.';
            version_label.update(text);
        }
    },

    /**
     * Upload VCL snippets
     */
    uploadVclSnippets: function () {
        var activate_flag = false;
        if ($('vcl-upload-form-activate').checked == true) {
            activate_flag = true;
        }
        new Ajax.Request(upload_snippets_url, {
            method:'post',
            parameters: {
                active_version: this.service.active_version,
                activate_flag: activate_flag
            },
            onSuccess: function(transport) {
                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                var successMsg = 'VCL file is successfully uploaded to the Fastly service.';
                this.setButtonMsg(successMsg, 'vcl-upload-btn-success');
                this.closeDialogWindow();
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Turn on\off TLS
     */
    toggleTls: function () {
        var activate_flag = false;
        if ($('toggle-tls-form-activate').checked == true) {
            activate_flag = true;
        }
        new Ajax.Request(toggle_tls_url, {
            method:'post',
            parameters: {
                active_version: this.service.active_version,
                activate_flag: activate_flag
            },
            onSuccess: function(transport) {
                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                var successMsg = '';
                if(this.tlsStatus) {
                    this.tlsStatus = false;
                    successMsg = 'The Force TLS request setting is successfully turned off.';
                    $('force_tls_state_enabled').hide();
                    $('force_tls_state_disabled').show();

                } else {
                    this.tlsStatus = true;
                    successMsg = 'The Force TLS request setting is successfully turned on.';
                    $('force_tls_state_disabled').hide();
                    $('force_tls_state_enabled').show();
                }

                this.setButtonMsg(successMsg, 'toggle-tls-btn-success');
                this.closeDialogWindow();
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Update Error page content
     */
    updateErrorPage: function () {
        var activate_flag = false;
        if ($('error-page-form-activate').checked == true) {
            activate_flag = true;
        }
        new Ajax.Request(update_error_page_url, {
            method:'post',
            parameters: {
                active_version: this.service.active_version,
                activate_flag: activate_flag,
                html: $('error-page-form-html').value
            },
            onSuccess: function(transport) {
                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                var successMsg = 'Error page HTML is successfully updated.';
                this.setButtonMsg(successMsg, 'error-page-btn-success');
                this.closeDialogWindow();
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Update backend configuration
     */
    updateBackend: function () {
        var activate_flag = false;
        if ($('backend-config-form-activate').checked == true) {
            activate_flag = true;
        }

        var formToValidate = $('backend-config-form-real');
        var validator = new Validation(formToValidate);
        if(validator.validate()) {
            new Ajax.Request(update_backend_url, {
                method:'post',
                parameters: {
                    active_version: this.service.active_version,
                    activate_flag: activate_flag,
                    name: $('backend-config-form-name').value,
                    shield: $('backend-config-form-shield').value,
                    connect_timeout: $('backend-config-form-connection-timeout').value,
                    between_bytes_timeout: $('backend-config-form-between-bytes-timeout').value,
                    first_byte_timeout: $('backend-config-form-first-byte-timeout').value
                },
                onSuccess: function(transport) {
                    var response = transport.responseText.evalJSON();
                    if(response.status == false) {
                        return this.setDialogMessage(response.msg, this.divId, 'error');
                    }

                    this.backends.splice(this.backendId, 1, response.backends);
                    var successMsg = 'Backend is successfully updated.';
                    this.setButtonMsg(successMsg, 'backend-config-btn-success');
                    this.closeDialogWindow();
                }.bind(this),
                onFailure: function() { alert('Something went wrong...'); }
            });
        }
    },

    setButtonMsg: function (msg, btnId) {
        var btn = $(btnId);
        btn.select('span')[0].update(msg);
        btn.show();
    },

    setDialogMessage: function (msg, divId, type) {
        if(type == 'error') {
            $(divId + '-error-label').show().update(msg);
            $(divId + '-error-dialog-box').show();
            $(divId + '-warning-dialog-box').hide();
        } else {
            $(divId + '-warning-label').show().update(msg);
            $(divId + '-warning-dialog-box').show();
            $(divId + '-error-dialog-box').hide();
        }
    },

    closeDialogWindow: function () {
        Windows.close(this.dialogId);
    },

    hideAllBtnMessages: function () {
        var messages = $$('ul.button-messages');
        messages.each(function (elem) {
            elem.hide();
        });
    },

    openDialogWindow: function() {
        this.overlayShowEffectOptions = Windows.overlayShowEffectOptions;
        this.overlayHideEffectOptions = Windows.overlayHideEffectOptions;
        Windows.overlayShowEffectOptions = {duration:0};
        Windows.overlayHideEffectOptions = {duration:0};

        this.dialogWindow = Dialog.info(this.loadDialogContent[this.dialogId].content(), {
            draggable:true,
            resizable:true,
            closable:true,
            className:"magento",
            windowClassName:"popup-window",
            title: this.loadDialogContent[this.dialogId].title,
            width:900,
            height: 500,
            zIndex:1000,
            recenterAuto:false,
            hideEffect:Element.hide,
            showEffect:Element.show,
            id:this.dialogId,
            onClose: this.onClose.bind(this)
        });

        if (this.divId == 'backend-config-form' && this.backends != null && this.backendId != null) {
            console.log(this.backends[this.backendId].connect_timeout);
            $('backend-config-form-name').value = this.backends[this.backendId].name;
            $('backend-config-form-connection-timeout').value = this.backends[this.backendId].connect_timeout;
            $('backend-config-form-between-bytes-timeout').value = this.backends[this.backendId].between_bytes_timeout;
            $('backend-config-form-first-byte-timeout').value = this.backends[this.backendId].first_byte_timeout;

            /* Preselect dropdown option */
            var options = $$('select#backend-config-form-shield option');
            var len = options.length;
            for (var i = 0; i < len; i++) {
                if (options[i].value == this.backends[this.backendId].shield) {
                    options[i].selected = true;
                }
            }
        }

        this.setLoaderZIndex();
    },

    onClose: function(window) {
        if (!window) {
            window = this.dialogWindow;
        }
        if (window) {
            window.close();
            Windows.overlayShowEffectOptions = this.overlayShowEffectOptions;
            Windows.overlayHideEffectOptions = this.overlayHideEffectOptions;
        }
    }
};