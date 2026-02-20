if (typeof pimcore !== 'undefined' && pimcore.registerNS) {
    pimcore.registerNS("pimcore.element.insquareDeeplObjectTranslate");
}

window.insquare = window.insquare || {};
window.insquare.deepl = window.insquare.deepl || {};

pimcore.element.insquareDeeplObjectTranslate = Class.create({
    initialize: function (element) {
        this.element = element;
    },

    getLayout: function () {
        if (this.layout) {
            return this.layout;
        }

        var langs = this.getLangExtStore(this.getLanguages(), ["id", "name"]);

        var langFrom = new Ext.form.ComboBox({
            id: 'insquareDeeplLangFrom' + this.element.id,
            name: "langFrom" + this.element.id,
            valueField: "id",
            displayField: 'name',
            store: langs,
            editable: false,
            triggerAction: 'all',
            mode: "local",
            listWidth: 250,
            maxWidth: 250,
            emptyText: t('insquare_deepl_source_language')
        });

        var langTo = new Ext.form.ComboBox({
            id: 'insquareDeeplLangTo' + this.element.id,
            name: "langTo" + this.element.id,
            valueField: "id",
            displayField: 'name',
            store: langs,
            editable: false,
            triggerAction: 'all',
            mode: "local",
            listWidth: 250,
            maxWidth: 250,
            emptyText: t('insquare_deepl_target_language')
        });

        var panel = new Ext.Panel({
            id: 'insquareDeeplPanel' + this.element.id,
            title: t('insquare_deepl_translate_tab'),
            width: 320,
            bodyStyle: "padding: 10px;",
            items: [langFrom, langTo]
        });

        panel.add({
            xtype: 'button',
            text: t('insquare_deepl_translate'),
            cls: 'insquare-deepl-translate-btn',
            margin: '10 0 0 0',
            handler: function () {
                this.handleTranslate();
            }.bind(this)
        });

        this.layout = new Ext.Panel({
            tabConfig: {
                tooltip: t('insquare_deepl_translate_tab')
            },
            id: 'insquareDeeplTranslateTab' + this.element.id,
            items: [panel],
            layout: "border",
            iconCls: 'pimcore_material_icon_translation pimcore_material_icon'
        });

        return this.layout;
    },

    handleTranslate: function () {
        var langFrom = Ext.getCmp('insquareDeeplLangFrom' + this.element.id).getValue();
        var langTo = Ext.getCmp('insquareDeeplLangTo' + this.element.id).getValue();

        if (!langFrom || !langTo) {
            insquare.deepl.showMessage(t('insquare_deepl_missing_languages_title'), t('insquare_deepl_missing_languages'));
            return;
        }

        if (langFrom === langTo) {
            insquare.deepl.showMessage(t('insquare_deepl_same_language_title'), t('insquare_deepl_same_language'));
            return;
        }

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

                var translateUrl = insquare.deepl.route('insquare_pimcore_deepl_object_translate_field');
                if (!translateUrl) {
                    insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_error_generic'));
                    return;
                }

                var tasks = this.collectTasks(langFrom);
                if (!tasks.length) {
                    insquare.deepl.showMessage(t('insquare_deepl_nothing_title'), t('insquare_deepl_nothing_to_translate'));
                    return;
                }

                var progress = insquare.deepl.createProgressWindow(t('insquare_deepl_progress_title'), tasks.length);

                insquare.deepl.runQueue(
                    tasks,
                    function (task, done, fail) {
                        Ext.Ajax.request({
                            url: translateUrl,
                            method: 'POST',
                            params: {
                                id: this.element.id,
                                source: langFrom,
                                target: langTo,
                                key: task.key,
                                text: task.text
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
                    }.bind(this),
                    function (processed, total) {
                        progress.update(processed, total, t('insquare_deepl_progress_label') + ' ' + processed + ' / ' + total);
                    },
                    function () {
                        progress.win.close();
                        insquare.deepl.showMessage(
                            t('insquare_deepl_done_title'),
                            t('insquare_deepl_done_object'),
                            function () {
                                this.element.reload();
                            }.bind(this)
                        );
                    }.bind(this),
                    function (error) {
                        progress.win.close();
                        insquare.deepl.showMessage(t('insquare_deepl_error_title'), this.formatError(error));
                    }.bind(this)
                );
            }.bind(this),
            failure: function () {
                insquare.deepl.showMessage(t('insquare_deepl_error_title'), t('insquare_deepl_settings_error'));
            }
        });
    },

    collectTasks: function (langFrom) {
        var tasks = [];
        var elementData = this.element.data.data || {};

        if (elementData.localizedfields && elementData.localizedfields.data && elementData.localizedfields.data[langFrom]) {
            var data = elementData.localizedfields.data[langFrom];
            Object.keys(data).forEach(function (field) {
                var value = data[field];
                if (this.isNonEmptyString(value)) {
                    tasks.push({ key: field, text: value });
                }
            }.bind(this));
        }

        Object.keys(elementData).forEach(function (item) {
            var value = elementData[item];

            if (Array.isArray(value)) {
                value.forEach(function (element, index) {
                    if (element && element.data && element.data.localizedfields && element.data.localizedfields.data && element.data.localizedfields.data[langFrom]) {
                        var localizedFields = element.data.localizedfields.data[langFrom];
                        Object.keys(localizedFields).forEach(function (field) {
                            var fieldValue = localizedFields[field];
                            if (this.isNonEmptyString(fieldValue)) {
                                tasks.push({
                                    key: 'structuredData#.' + item + '.' + index + '.' + element.type + '.' + field,
                                    text: fieldValue
                                });
                            }
                        }.bind(this));
                    }
                }.bind(this));
                return;
            }

            if (value && typeof value === 'object' && value.activeGroups && value.data && value.data[langFrom]) {
                var storeData = value.data[langFrom];
                Object.keys(storeData).forEach(function (storeObject) {
                    var storeFields = storeData[storeObject] || {};
                    Object.keys(storeFields).forEach(function (field) {
                        var fieldValue = storeFields[field];
                        if (this.isNonEmptyString(fieldValue)) {
                            tasks.push({
                                key: 'classificationStore#.' + storeObject + '.' + item + '.' + field,
                                text: fieldValue
                            });
                        }
                    }.bind(this));
                }.bind(this));
            }
        }.bind(this));

        return tasks;
    },

    isNonEmptyString: function (value) {
        return typeof value === 'string' && value.trim() !== '';
    },

    formatError: function (error) {
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
    },

    getLanguages: function () {
        var locales = pimcore.settings.websiteLanguages || [];
        var languages = [];

        for (var i = 0; i < locales.length; i++) {
            var code = locales[i];
            var label = pimcore.available_languages[code] + " [" + code + "]";
            languages.push([code, label]);
        }

        return languages;
    },

    getLangExtStore: function (data, fields) {
        return new Ext.data.ArrayStore({
            fields: fields,
            data: data
        });
    }
});
