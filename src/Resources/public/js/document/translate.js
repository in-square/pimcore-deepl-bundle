if (typeof pimcore !== 'undefined' && pimcore.registerNS) {
    pimcore.registerNS("insquare.deepl.document");
}

window.insquare = window.insquare || {};
window.insquare.deepl = window.insquare.deepl || {};
window.insquare.deepl.document = window.insquare.deepl.document || {};
window.insquare.deepl.documentTranslationStatus = window.insquare.deepl.documentTranslationStatus || {};
window.insquare.deepl.documentTranslationStatusQueue = window.insquare.deepl.documentTranslationStatusQueue || {};
window.insquare.deepl.documentTranslationStatusLoading = window.insquare.deepl.documentTranslationStatusLoading || {};

insquare.deepl.fetchDocumentTranslationStatus = function (documentId, callback) {
    if (!documentId) {
        if (typeof callback === 'function') {
            callback(false);
        }
        return;
    }

    if (Object.prototype.hasOwnProperty.call(insquare.deepl.documentTranslationStatus, documentId)) {
        if (typeof callback === 'function') {
            callback(insquare.deepl.documentTranslationStatus[documentId]);
        }
        return;
    }

    if (typeof callback === 'function') {
        if (!insquare.deepl.documentTranslationStatusQueue[documentId]) {
            insquare.deepl.documentTranslationStatusQueue[documentId] = [];
        }
        insquare.deepl.documentTranslationStatusQueue[documentId].push(callback);
    }

    if (insquare.deepl.documentTranslationStatusLoading[documentId]) {
        return;
    }

    insquare.deepl.documentTranslationStatusLoading[documentId] = true;

    var statusUrl = insquare.deepl.route('insquare_pimcore_deepl_document_status');
    if (!statusUrl) {
        insquare.deepl.documentTranslationStatus[documentId] = false;
        delete insquare.deepl.documentTranslationStatusLoading[documentId];

        var queueMissing = insquare.deepl.documentTranslationStatusQueue[documentId] || [];
        delete insquare.deepl.documentTranslationStatusQueue[documentId];
        for (var q = 0; q < queueMissing.length; q++) {
            queueMissing[q](false);
        }
        return;
    }

    Ext.Ajax.request({
        url: statusUrl,
        method: 'GET',
        params: {
            id: documentId
        },
        success: function (response) {
            var data = Ext.decode(response.responseText, true) || {};
            var isTranslation = data && data.success ? !!data.isTranslation : false;
            insquare.deepl.documentTranslationStatus[documentId] = isTranslation;
            delete insquare.deepl.documentTranslationStatusLoading[documentId];

            var queue = insquare.deepl.documentTranslationStatusQueue[documentId] || [];
            delete insquare.deepl.documentTranslationStatusQueue[documentId];
            for (var i = 0; i < queue.length; i++) {
                queue[i](isTranslation);
            }
        },
        failure: function () {
            insquare.deepl.documentTranslationStatus[documentId] = false;
            delete insquare.deepl.documentTranslationStatusLoading[documentId];

            var queue = insquare.deepl.documentTranslationStatusQueue[documentId] || [];
            delete insquare.deepl.documentTranslationStatusQueue[documentId];
            for (var i = 0; i < queue.length; i++) {
                queue[i](false);
            }
        }
    });
};

insquare.deepl.translateDocument = function (document) {
    var settingsUrl = insquare.deepl.route('insquare_pimcore_deepl_settings');
    if (!settingsUrl) {
        insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_error_generic'));
        return;
    }

    Ext.Ajax.request({
        url: settingsUrl,
        method: 'GET',
        success: function (response) {
            var settings = Ext.decode(response.responseText, true) || {};
            if (!settings.configured) {
                insquare.deepl.showMessage(t('insquare_deepl_missing_key_title'), t('insquare_deepl_missing_key'));
                return;
            }

            var tasksUrl = insquare.deepl.route('insquare_pimcore_deepl_document_tasks');
            if (!tasksUrl) {
                insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_error_generic'));
                return;
            }

            Ext.Ajax.request({
                url: tasksUrl,
                method: 'GET',
                params: {
                    id: document.id,
                    scope: 'document',
                    skipIfTargetNotEmpty: true
                },
                success: function (taskResponse) {
                    var data = Ext.decode(taskResponse.responseText, true) || {};
                    if (!data.success) {
                        insquare.deepl.showMessage(t('insquare_deepl_error_title'), data.message || t('insquare_deepl_error_generic'));
                        return;
                    }

                    if (data.isTranslation === false) {
                        insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_only_translation_doc'));
                        return;
                    }

                    if (!data.tasks || !data.tasks.length) {
                        insquare.deepl.showMessage(t('insquare_deepl_nothing_title'), t('insquare_deepl_nothing_to_translate'));
                        return;
                    }

                    var progress = insquare.deepl.createProgressWindow(t('insquare_deepl_progress_title'), data.tasks.length);

                    insquare.deepl.runQueue(
                        data.tasks,
                        function (task, done, fail) {
                            var translateUrl = insquare.deepl.route('insquare_pimcore_deepl_document_translate_field');
                            if (!translateUrl) {
                                fail({message: t('insquare_deepl_error_generic')});
                                return;
                            }

                            Ext.Ajax.request({
                                url: translateUrl,
                                method: 'POST',
                                params: {
                                    id: document.id,
                                    name: task.name,
                                    sourceLang: data.sourceLang,
                                    targetLang: data.targetLang,
                                    sourceDocumentId: data.sourceDocumentId,
                                    skipIfTargetNotEmpty: true
                                },
                                success: function (response) {
                                    var res = Ext.decode(response.responseText, true) || {};
                                    if (!res.success) {
                                        fail(res);
                                        return;
                                    }
                                    done(res);
                                },
                                failure: function (response) {
                                    var res = Ext.decode(response.responseText, true) || {};
                                    res.message = res.message || response.responseText || 'Request failed';
                                    fail(res);
                                }
                            });
                        },
                        function (processed, total) {
                            progress.update(processed, total, t('insquare_deepl_progress_label') + ' ' + processed + ' / ' + total);
                        },
                        function () {
                            progress.win.close();
                            insquare.deepl.showMessage(
                                t('insquare_deepl_done_title'),
                                t('insquare_deepl_done_document'),
                                function () {
                                    document.reload();
                                }
                            );
                        },
                        function (error) {
                            progress.win.close();
                            insquare.deepl.showMessage(t('insquare_deepl_error_title'), insquare.deepl.document.formatError(error));
                        }
                    );
                },
                failure: function () {
                    insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_error_generic'));
                }
            });
        },
        failure: function () {
            insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_settings_error'));
        }
    });
};

insquare.deepl.translateBlock = function (documentId, blockName, blockKey) {
    var prefix = blockName + ':' + blockKey + '.';

    var settingsUrl = insquare.deepl.route('insquare_pimcore_deepl_settings');
    if (!settingsUrl) {
        insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_error_generic'));
        return;
    }

    Ext.Ajax.request({
        url: settingsUrl,
        method: 'GET',
        success: function (response) {
            var settings = Ext.decode(response.responseText, true) || {};
            if (!settings.configured) {
                insquare.deepl.showMessage(t('insquare_deepl_missing_key_title'), t('insquare_deepl_missing_key'));
                return;
            }

            var tasksUrl = insquare.deepl.route('insquare_pimcore_deepl_document_tasks');
            if (!tasksUrl) {
                insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_error_generic'));
                return;
            }

            Ext.Ajax.request({
                url: tasksUrl,
                method: 'GET',
                params: {
                    id: documentId,
                    scope: 'brick',
                    brickPrefix: prefix,
                    skipIfTargetNotEmpty: true
                },
                success: function (taskResponse) {
                    var data = Ext.decode(taskResponse.responseText, true) || {};
                    if (!data.success) {
                        insquare.deepl.showMessage(t('insquare_deepl_error_title'), data.message || t('insquare_deepl_error_generic'));
                        return;
                    }

                    if (data.isTranslation === false) {
                        insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_only_translation_doc'));
                        return;
                    }

                    if (data.overridden === false) {
                        insquare.deepl.showMessage(t('insquare_deepl_not_overridden_title'), t('insquare_deepl_not_overridden'));
                        return;
                    }

                    if (!data.tasks || !data.tasks.length) {
                        insquare.deepl.showMessage(t('insquare_deepl_nothing_title'), t('insquare_deepl_nothing_to_translate'));
                        return;
                    }

                    var progress = insquare.deepl.createProgressWindow(t('insquare_deepl_progress_title'), data.tasks.length);

                    insquare.deepl.runQueue(
                        data.tasks,
                        function (task, done, fail) {
                            var translateUrl = insquare.deepl.route('insquare_pimcore_deepl_document_translate_field');
                            if (!translateUrl) {
                                fail({message: t('insquare_deepl_error_generic')});
                                return;
                            }

                            Ext.Ajax.request({
                                url: translateUrl,
                                method: 'POST',
                                params: {
                                    id: documentId,
                                    name: task.name,
                                    sourceLang: data.sourceLang,
                                    targetLang: data.targetLang,
                                    sourceDocumentId: data.sourceDocumentId,
                                    skipIfTargetNotEmpty: true
                                },
                                success: function (response) {
                                    var res = Ext.decode(response.responseText, true) || {};
                                    if (!res.success) {
                                        fail(res);
                                        return;
                                    }
                                    done(res);
                                },
                                failure: function (response) {
                                    var res = Ext.decode(response.responseText, true) || {};
                                    res.message = res.message || response.responseText || 'Request failed';
                                    fail(res);
                                }
                            });
                        },
                        function (processed, total) {
                            progress.update(processed, total, t('insquare_deepl_progress_label') + ' ' + processed + ' / ' + total);
                        },
                        function () {
                            progress.win.close();
                            insquare.deepl.showMessage(
                                t('insquare_deepl_done_title'),
                                t('insquare_deepl_done_block'),
                                function () {
                                    if (typeof editWindow !== 'undefined' && editWindow) {
                                        editWindow.reload();
                                    }
                                }
                            );
                        },
                        function (error) {
                            progress.win.close();
                            insquare.deepl.showMessage(t('insquare_deepl_error_title'), insquare.deepl.document.formatError(error));
                        }
                    );
                },
                failure: function () {
                    insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_error_generic'));
                }
            });
        },
        failure: function () {
            insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_settings_error'));
        }
    });
};

insquare.deepl.document.formatError = function (error) {
    if (!error) {
        return t('insquare_deepl_error_generic');
    }

    if (error.code === 'missing_key') {
        return t('insquare_deepl_missing_key');
    }

    if (error.status === 456) {
        return t('insquare_deepl_quota_error');
    }

    if (error.status === 429) {
        return t('insquare_deepl_rate_error');
    }

    if (error.status === 403) {
        return t('insquare_deepl_auth_error');
    }

    return error.message || t('insquare_deepl_error_generic');
};
