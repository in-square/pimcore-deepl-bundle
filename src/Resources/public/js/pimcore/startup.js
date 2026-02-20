pimcore.registerNS("pimcore.plugin.insquareDeepl");

pimcore.plugin.insquareDeepl = Class.create({
    getClassName: function () {
        return "pimcore.plugin.insquareDeepl";
    },

    initialize: function () {
        document.addEventListener(pimcore.events.postOpenObject, this.postOpenObject.bind(this));
        document.addEventListener(pimcore.events.postOpenDocument, this.postOpenDocument.bind(this));
    },

    postOpenObject: function (event) {
        var detail = event.detail || {};
        var object = detail.object;
        if (!object || detail.type !== 'object') {
            return;
        }

        if (object.insquareDeeplAdded) {
            return;
        }

        if (this.hasLocalizedFields(object)) {
            object.tabbar.add(new pimcore.element.insquareDeeplObjectTranslate(object).getLayout());
            object.insquareDeeplAdded = true;
        }
    },

    postOpenDocument: function (event) {
        var detail = event.detail || {};
        var document = detail.document;
        if (!document || detail.type !== 'page' && detail.type !== 'snippet' && detail.type !== 'printpage') {
            return;
        }

        if (document.insquareDeeplAdded) {
            return;
        }

        if (!document.data || !document.data.properties || !document.data.properties.language) {
            return;
        }

        insquare.deepl.fetchDocumentTranslationStatus(document.id, function (isTranslation) {
            if (!isTranslation) {
                document.insquareDeeplAdded = true;
                return;
            }

            var handler = function () {
                insquare.deepl.translateDocument(document);
            };

            var iconCls = 'pimcore_material_icon_translation pimcore_material_icon';

            if (document.toolbarSubmenu && document.toolbarSubmenu.menu) {
                document.toolbarSubmenu.menu.add({
                    text: t('insquare_deepl_translate_document'),
                    iconCls: iconCls,
                    handler: handler
                });
            } else if (document.toolbar) {
                document.toolbar.add({
                    text: t('insquare_deepl_translate_document'),
                    iconCls: iconCls,
                    scale: 'medium',
                    handler: handler
                });
                if (typeof document.toolbar.updateLayout === 'function') {
                    document.toolbar.updateLayout();
                }
            }

            document.insquareDeeplAdded = true;
        });

    },

    hasLocalizedFields: function (object) {
        var data = object.data && object.data.data ? object.data.data : {};

        if (data.localizedfields) {
            return true;
        }

        for (var key in data) {
            if (!data.hasOwnProperty(key)) {
                continue;
            }

            var value = data[key];
            if (Array.isArray(value)) {
                for (var i = 0; i < value.length; i++) {
                    if (value[i] && value[i].data && value[i].data.localizedfields) {
                        return true;
                    }
                }
            } else if (value && typeof value === 'object' && value.activeGroups) {
                return true;
            }
        }

        return false;
    }
});

new pimcore.plugin.insquareDeepl();
