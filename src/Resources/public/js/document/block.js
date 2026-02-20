if (typeof pimcore !== 'undefined' && pimcore.registerNS) {
    pimcore.registerNS("pimcore.document.editables.block");
}

window.insquare = window.insquare || {};
window.insquare.deepl = window.insquare.deepl || {};

var insquareDeeplHasOverride = function (prefix, element) {
    var hasOverride = false;

    if (typeof editableManager !== 'undefined' && editableManager && typeof editableManager.getEditables === 'function') {
        var editables = editableManager.getEditables();
        for (var name in editables) {
            if (!Object.prototype.hasOwnProperty.call(editables, name)) {
                continue;
            }

            if (name.indexOf(prefix) !== 0) {
                continue;
            }

            var editable = editables[name];
            if (!editable) {
                continue;
            }

            if (typeof editable.getInherited === 'function') {
                if (editable.getInherited() === false) {
                    hasOverride = true;
                    break;
                }
                continue;
            }

            if (typeof editable.inherited !== 'undefined' && editable.inherited === false) {
                hasOverride = true;
                break;
            }

            if (editable.data && typeof editable.data.inherited !== 'undefined' && editable.data.inherited === false) {
                hasOverride = true;
                break;
            }
        }
    }

    if (!hasOverride && element) {
        var elementNode = Ext.get(element);
        if (elementNode) {
            var nodes = elementNode.query('.pimcore_editable');
            for (var i = 0; i < nodes.length; i++) {
                var node = Ext.get(nodes[i]);
                if (node && !node.hasCls('pimcore_editable_inherited')) {
                    hasOverride = true;
                    break;
                }
            }
        }
    }

    return hasOverride;
};

pimcore.document.editables.block = Class.create(pimcore.document.editables.block, {
    refreshControls: function ($super, element, limitReached) {
        $super(element, limitReached);

        if (!this.translateButtons) {
            this.translateButtons = {};
        }

        if (this.translateButtons[element.key]) {
            return;
        }

        var statusCache = insquare.deepl.documentTranslationStatus || {};
        if (Object.prototype.hasOwnProperty.call(statusCache, pimcore_document_id)) {
            if (statusCache[pimcore_document_id] === false) {
                return;
            }
        } else if (typeof insquare.deepl.fetchDocumentTranslationStatus === 'function') {
            insquare.deepl.fetchDocumentTranslationStatus(pimcore_document_id, function (isTranslation) {
                if (isTranslation) {
                    this.refresh();
                }
            }.bind(this));
            return;
        }

        var prefix = this.name + ':' + element.key + '.';
        var hasOverride = insquareDeeplHasOverride(prefix, element);

        if (!hasOverride) {
            return;
        }

        var controls = Ext.get(element).query('.pimcore_block_buttons[data-name="' + this.name + '"]')[0];
        if (!controls) {
            return;
        }

        var translateDiv = Ext.get(controls).query('.pimcore_block_translate[data-name="' + this.name + '"]')[0];
        if (!translateDiv) {
            translateDiv = document.createElement('div');
            translateDiv.setAttribute('class', 'pimcore_block_translate');
            translateDiv.setAttribute('data-name', this.name);

            var clearDiv = Ext.get(controls).query('.pimcore_block_clear[data-name="' + this.name + '"]')[0];
            if (clearDiv) {
                controls.insertBefore(translateDiv, clearDiv);
            } else {
                controls.appendChild(translateDiv);
            }
        }

        var translateButton = new Ext.Button({
            cls: "pimcore_block_button_translate",
            iconCls: 'pimcore_material_icon_translation pimcore_material_icon',
            tooltip: t('insquare_deepl_translate_block'),
            handler: function () {
                insquare.deepl.translateBlock(pimcore_document_id, this.name, element.key);
            }.bind(this)
        });

        translateButton.render(translateDiv);
        this.translateButtons[element.key] = translateButton;
    }
});
