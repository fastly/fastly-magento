document.observe("dom:loaded", function() {

    if ($('fastlycdn_general_enabled') === null) {
        return;
    }

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

    /**
     * Dictionary item save
     */
    $(document).on('click', '.save-dictionary-item', function(event, element) {
        var parent = element.up('tr');
        var key = parent.childNodes[0].firstChild.value;
        var value = parent.childNodes[1].firstChild.value;
        var dictionary_id = parent.dataset.id;
        Fastly.addDictionaryItem(dictionary_id, key, value, parent);
    });

    /**
     * Dictionary item remove
     */
    $(document).on('click', '.delete-dictionary-item', function(event, element) {
        var parent = element.up('tr');
        var key = parent.childNodes[0].firstChild.value;
        var dictionary_id = parent.dataset.id;
        Fastly.removeDictionaryItem(dictionary_id, key, parent);
    });

    /**
     * Acl item save
     */
    $(document).on('click', '.save-acl-item', function(event, element) {
        var parent = element.up('tr');
        var ip = parent.childNodes[0].firstChild.value;

        // New entry vs old entry difference
        var acl_item_id;
        if(parent.childNodes[0].firstChild !== parent.childNodes[0].lastChild) {
            acl_item_id = parent.childNodes[0].lastChild.value;
        } else {
            acl_item_id = false;
        }

        var negated = parent.childNodes[1].firstChild.checked;
        var acl_id = parent.dataset.id;
        Fastly.addAclItem(acl_id, acl_item_id, ip, negated, parent);
    });

    /**
     * Acl item delete
     */
    $(document).on('click', '.delete-acl-item', function(event, element) {
        var parent = element.up('tr');
        var acl_id = parent.dataset.id;
        var acl_item_id = parent.childNodes[0].lastChild.value;
        Fastly.removeAclItem(acl_id, acl_item_id, parent);
    });

});

var Fastly = {

    dialogContent: null,
    modalTitle: null,
    service: null,
    dialogId: null,
    identifier: null,
    removeHtmlIdentifier: null,
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
        'acl-create-form-dialog': {
            title: 'You are about to create Acl and create new cloned version in Fastly',
            content: function () {
                return $('acl-create-form').innerHTML;
            }
        },
        'acl-remove-form-dialog': {
            title: 'You are about to remove Acl from Fastly',
            content: function () {
                return $('acl-remove-form').innerHTML;
            }
        },
        'acl-list-form-dialog': {
            title: 'Acl items',
            content: function () {
                var aclItemList = $('acl-list-form');
                Fastly.getAclItemsList();
                return aclItemList.innerHTML;
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
        },
        'dictionary-create-form-dialog': {
            title: 'You are about to create Dictionary and create new cloned version in Fastly',
            content: function () {
                return $('dictionary-create-form').innerHTML;
            }
        },
        'dictionary-remove-form-dialog': {
            title: 'You are about to remove Dictionary from Fastly',
            content: function () {
                return $('dictionary-remove-form').innerHTML;
            }
        },
        'dictionary-list-form-dialog': {
            title: 'Dictionary items',
            content: function () {
                var dictionaryItemList = $('dictionary-list-form');
                Fastly.getDictionaryItemsList();
                return dictionaryItemList.innerHTML;
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

    initDialog: function (divId, identifier, htmlIdentifier) {
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
                this.identifier = identifier;
                this.removeHtmlIdentifier = htmlIdentifier;
                this.dialog = $(divId);
                this.divId = divId;

                // Hide message when showing Dictionary/Acl items
                if(divId == 'dictionary-list-form' || divId == 'acl-list-form') {
                    this.dialog = null;
                }

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
        var errorHtmlChars = $('error-page-form-html').value.length;
        var maxChars = 65535;
        if (errorHtmlChars >= maxChars) {
            var msgWarning = $(this.divId+'-error-dialog-box');
            var text = 'The HTML must contain less than ' + maxChars + ' characters. ' +
                'Current number of characters: ' + errorHtmlChars;
            msgWarning.update(text);
            msgWarning.show();
            return;
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

    /**
     * Create Dictionary
     */
    createDictionary: function () {
        var activate_flag = false;
        if ($('dictionary-create-form-activate').checked == true) {
            activate_flag = true;
        }
        var dictionary_name = $('dictionary-create-form-name').value;

        new Ajax.Request(create_dictionary_url, {
            method:'post',
            parameters: {
                active_version: this.service.active_version,
                activate_flag: activate_flag,
                dictionary_name: dictionary_name
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                var dictName = transport.responseJSON.dictionary_name;
                var dictId = transport.responseJSON.dictionary_id;
                var gridElem = $('fastlycdn_dictionary_cmsblock');
                Element.insert(gridElem, {bottom: $('fastlycdn_dictionary_cmsblock_template').innerHTML});
                // Set input field data
                var gridElemInput = gridElem.lastChild.firstChild.firstChild.firstChild;
                gridElemInput.value = dictName;
                gridElemInput.disabled = true;
                // Set delete
                var gridOnClickDelete = "Fastly.initDialog('dictionary-remove-form', '"+ dictName +"', $(this).up('tr'))";
                gridElem.lastChild.firstChild.lastChild.firstChild.setAttribute('onclick', gridOnClickDelete);
                // Set Manage/Edit
                var gridOnClickManage = "Fastly.initDialog('dictionary-list-form', '"+ dictId +"')";
                gridElem.lastChild.firstChild.childNodes[1].firstChild.setAttribute('onclick', gridOnClickManage);

                var successMsg = 'Dictionary successfully created.';
                this.setButtonMsg(successMsg, 'vcl-upload-btn-success');
                this.closeDialogWindow();
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Remove Dictionary
     */
    removeDictionary: function () {
        var dictionary_name = Fastly.identifier;
        var remove_html = Fastly.removeHtmlIdentifier;
        var activate_flag = false;
        if ($('dictionary-remove-form-activate').checked == true) {
            activate_flag = true;
        }

        new Ajax.Request(delete_dictionary_url, {
            method:'post',
            parameters: {
                active_version: this.service.active_version,
                activate_flag: activate_flag,
                dictionary_name: dictionary_name
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                // Remove HTML entry
                Element.remove(remove_html);

                var successMsg = 'Dictionary successfully removed.';
                this.setButtonMsg(successMsg, 'vcl-upload-btn-success');
                this.closeDialogWindow();
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * List Dictionary Items
     */
    getDictionaryItemsList: function () {
        var dictionary_id = Fastly.identifier;
        new Ajax.Request(list_dictionary_items_url, {
            method:'post',
            parameters: {
                dictionary_id: dictionary_id
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }
                var items = response.items;

                for(var i=0; i < items.length; i++) {
                    var itemKey = items[i].item_key;
                    var itemValue = items[i].item_value;
                    Fastly.addDictionaryItemHtml(itemKey, itemValue);
                }

            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Add Dictionary Item HTML
     */
    addDictionaryItemHtml: function (key, value) {
        var dictionary_id = Fastly.identifier;
        var dictionaryItemBody = $('dictionary-item-body');

        var tr = document.createElement("tr");
        tr.setAttribute('data-id', dictionary_id);

        var td1 = document.createElement("td");
        var inputKey = document.createElement("input");
        inputKey.setAttribute('type', 'text');
        inputKey.setAttribute('name', 'item_key');
        inputKey.setAttribute('style', 'width:100px');
        inputKey.setAttribute('value', key ? key : '');
        inputKey.disabled = key;
        td1.appendChild(inputKey);

        var td2 = document.createElement("td");
        var inputValue = document.createElement("input");
        inputValue.setAttribute('type', 'text');
        inputValue.setAttribute('name', 'item_value');
        inputValue.setAttribute('style', 'width:100px');
        inputValue.setAttribute('value', value ? value : '');
        td2.appendChild(inputValue);

        var td3 = document.createElement("td");
        var btnSave = document.createElement("button");
        btnSave.setAttribute('title', 'Save Item');
        btnSave.setAttribute('type', 'button');
        btnSave.setAttribute('class', 'scalable scalable save-dictionary-item');
        var spanSave = document.createElement("span");
        var saveText = document.createTextNode("Save Item");
        spanSave.appendChild(saveText);
        btnSave.appendChild(spanSave);
        td3.appendChild(btnSave);

        var td4 = document.createElement("td");
        var btnDelete = document.createElement("button");
        btnDelete.setAttribute('title', 'Delete Item');
        btnDelete.setAttribute('type', 'button');
        btnDelete.setAttribute('class', 'scalable scalable v-middle delete-dictionary-item');
        var spanDelete = document.createElement("span");
        var deleteText = document.createTextNode("Delete Item");
        spanDelete.appendChild(deleteText);
        btnDelete.appendChild(spanDelete);
        td4.appendChild(btnDelete);

        tr.appendChild(td1);
        tr.appendChild(td2);
        tr.appendChild(td3);
        tr.appendChild(td4);

        dictionaryItemBody.appendChild(tr);
    },

    /**
     * Add Dictionary Item
     */
    addDictionaryItem: function (dictionary_id, key, value, parent) {
        this.unsetDialogMessage(this.divId);
        new Ajax.Request(add_dictionary_item_url, {
            method:'post',
            parameters: {
                dictionary_id: dictionary_id,
                item_key: key,
                item_value: value
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                // Disable key entry
                parent.childNodes[0].firstChild.disabled = true;
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Remove Dictionary Item
     */
    removeDictionaryItem: function (dictionary_id, key, parent) {
        this.unsetDialogMessage(this.divId);

        // Remove unsaved entry
        if (parent.firstChild.firstChild.disabled == false) {
            parent.parentNode.removeChild(parent);
            return;
        }

        new Ajax.Request(remove_dictionary_item_url, {
            method:'post',
            parameters: {
                dictionary_id: dictionary_id,
                item_key: key
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                if (parent) {
                    parent.parentNode.removeChild(parent);
                }

            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
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

    unsetDialogMessage: function (divId) {
        if($(divId + '-error-dialog-box')) {
            $(divId + '-error-dialog-box').hide();
        }
        if($(divId + '-warning-label')) {
            $(divId + '-warning-label').hide();
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
    },

    /**
     * Create Acl
     */
    createAcl: function () {
        var activate_flag = false;
        if ($('acl-create-form-activate').checked == true) {
            activate_flag = true;
        }
        var acl_name = $('acl-create-form-name').value;

        new Ajax.Request(create_acl_url, {
            method:'post',
            parameters: {
                active_version: this.service.active_version,
                activate_flag: activate_flag,
                acl_name: acl_name
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                var aclName = transport.responseJSON.acl_name;
                var aclId = transport.responseJSON.acl_id;
                var gridElem = $('fastlycdn_acl_cmsblock');
                Element.insert(gridElem, {bottom: $('fastlycdn_acl_cmsblock_template').innerHTML});
                // Set input field data
                var gridElemInput = gridElem.lastChild.firstChild.firstChild.firstChild;
                gridElemInput.value = aclName;
                gridElemInput.disabled = true;
                // Set delete
                var gridOnClickDelete = "Fastly.initDialog('acl-remove-form', '"+ aclName +"', $(this).up('tr'))";
                gridElem.lastChild.firstChild.lastChild.firstChild.setAttribute('onclick', gridOnClickDelete);
                // Set Manage/Edit
                var gridOnClickManage = "Fastly.initDialog('acl-list-form', '"+ aclId +"')";
                gridElem.lastChild.firstChild.childNodes[1].firstChild.setAttribute('onclick', gridOnClickManage);

                var successMsg = 'Acl successfully created.';
                this.setButtonMsg(successMsg, 'vcl-upload-btn-success');
                this.closeDialogWindow();
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Remove Acl
     */
    removeAcl: function () {
        var acl_name = Fastly.identifier;
        var remove_html = Fastly.removeHtmlIdentifier;
        var activate_flag = false;
        if ($('acl-remove-form-activate').checked == true) {
            activate_flag = true;
        }

        new Ajax.Request(delete_acl_url, {
            method:'post',
            parameters: {
                active_version: this.service.active_version,
                activate_flag: activate_flag,
                acl_name: acl_name
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                // Remove HTML entry
                Element.remove(remove_html);

                var successMsg = 'Acl successfully removed.';
                this.setButtonMsg(successMsg, 'vcl-upload-btn-success');
                this.closeDialogWindow();
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * List Acl Items
     */
    getAclItemsList: function () {
        var acl_id = Fastly.identifier;
        new Ajax.Request(list_acl_items_url, {
            method:'post',
            parameters: {
                acl_id: acl_id
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }
                var items = response.items;
                var ip_output;

                for(var i=0; i < items.length; i++) {
                    if(items[i].subnet) {
                        ip_output = items[i].ip + '/' + items[i].subnet;
                    } else {
                        ip_output = items[i].ip;
                    }

                    Fastly.addAclItemHtml(ip_output, items[i].negated, items[i].id);
                }
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Add Acl Item HTML
     */
    addAclItemHtml: function (ip, negated, acl_item_id) {
        var acl_id = Fastly.identifier;
        var aclItemBody = $('acl-item-body');

        var tr = document.createElement("tr");
        tr.setAttribute('data-id', acl_id);

        var td1 = document.createElement("td");
        var inputIp = document.createElement("input");
        inputIp.setAttribute('type', 'text');
        inputIp.setAttribute('name', 'ip');
        inputIp.setAttribute('style', 'width:100px');
        inputIp.setAttribute('value', ip ? ip : '');
        inputIp.disabled = ip;
        td1.appendChild(inputIp);

        if(acl_item_id) {
            var hiddenId = document.createElement("input");
            hiddenId.setAttribute('type', 'hidden');
            hiddenId.setAttribute('name', 'acl_item_id');
            hiddenId.setAttribute('value', acl_item_id);
            td1.appendChild(hiddenId);
        }

        negated = negated != 0;
        var td2 = document.createElement("td");
        var checkboxValue = document.createElement("input");
        checkboxValue.setAttribute('type', 'checkbox');
        checkboxValue.setAttribute('name', 'negated');
        checkboxValue.setAttribute('style', 'width:100px');
        checkboxValue.checked = negated;
        td2.appendChild(checkboxValue);

        var td3 = document.createElement("td");
        var btnSave = document.createElement("button");
        btnSave.setAttribute('title', 'Save Item');
        btnSave.setAttribute('type', 'button');
        btnSave.setAttribute('class', 'scalable scalable save-acl-item');
        var spanSave = document.createElement("span");
        var saveText = document.createTextNode("Save Item");
        spanSave.appendChild(saveText);
        btnSave.appendChild(spanSave);
        td3.appendChild(btnSave);

        var td4 = document.createElement("td");
        var btnDelete = document.createElement("button");
        btnDelete.setAttribute('title', 'Delete Item');
        btnDelete.setAttribute('type', 'button');
        btnDelete.setAttribute('class', 'scalable scalable v-middle delete-acl-item');
        var spanDelete = document.createElement("span");
        var deleteText = document.createTextNode("Delete Item");
        spanDelete.appendChild(deleteText);
        btnDelete.appendChild(spanDelete);
        td4.appendChild(btnDelete);

        tr.appendChild(td1);
        tr.appendChild(td2);
        tr.appendChild(td3);
        tr.appendChild(td4);

        aclItemBody.appendChild(tr);
    },

    /**
     * Add Acl Item
     */
    addAclItem: function (acl_id, acl_item_id, ip, negated, parent) {
        this.unsetDialogMessage(this.divId);
        new Ajax.Request(add_acl_item_url, {
            method:'post',
            parameters: {
                acl_id: acl_id,
                acl_item_id: acl_item_id,
                ip: ip,
                negated: negated
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                // Append Acl Item Id from new entry
                if(response.item.id) {
                    var hiddenId = document.createElement("input");
                    hiddenId.setAttribute('type', 'hidden');
                    hiddenId.setAttribute('name', 'acl_item_id');
                    hiddenId.setAttribute('value', response.item.id);
                    parent.firstChild.appendChild(hiddenId);
                }

                // Disable ip entry
                parent.childNodes[0].firstChild.disabled = true;
            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    },

    /**
     * Remove Acl Item
     */
    removeAclItem: function (acl_id, acl_item_id, parent) {
        this.unsetDialogMessage(this.divId);

        // Remove unsaved entry
        if (parent.firstChild.firstChild.disabled == false) {
            parent.parentNode.removeChild(parent);
            return;
        }

        new Ajax.Request(remove_acl_item_url, {
            method:'post',
            parameters: {
                acl_id: acl_id,
                acl_item_id: acl_item_id
            },
            dataType: 'json',
            onSuccess: function(transport) {

                var response = transport.responseText.evalJSON();
                if(response.status == false) {
                    return this.setDialogMessage(response.msg, this.divId, 'error');
                }

                if (parent) {
                    parent.parentNode.removeChild(parent);
                }

            }.bind(this),
            onFailure: function() { alert('Something went wrong...'); }
        });
    }
};