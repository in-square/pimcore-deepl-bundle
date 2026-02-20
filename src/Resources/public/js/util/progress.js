if (typeof pimcore !== 'undefined' && pimcore.registerNS) {
    pimcore.registerNS("insquare.deepl");
}

window.insquare = window.insquare || {};
window.insquare.deepl = window.insquare.deepl || {};

insquare.deepl.createProgressWindow = function (title, total) {
    var progressBar = Ext.create('Ext.ProgressBar', {
        width: 420,
        value: 0,
        text: total ? "0 / " + total : "0 / 0"
    });

    var win = new Ext.Window({
        title: title,
        modal: true,
        closable: false,
        width: 460,
        bodyStyle: "padding: 10px;",
        items: [progressBar]
    });

    win.show();

    return {
        win: win,
        bar: progressBar,
        update: function (processed, totalCount, label) {
            var totalSafe = totalCount || 0;
            var ratio = totalSafe > 0 ? processed / totalSafe : 0;
            var text = label || (processed + " / " + totalSafe);
            progressBar.updateProgress(ratio, text);
        }
    };
};

insquare.deepl.showMessage = function (title, message, onReload) {
    var cfg = {
        title: title,
        modal: true,
        width: 420,
        bodyStyle: "padding: 10px;",
        html: message,
        buttons: []
    };

    if (typeof onReload === 'function') {
        cfg.buttons.push({
            text: t('reload'),
            handler: function (btn) {
                btn.up('window').close();
                onReload();
            }
        });
    } else {
        cfg.buttons.push({
            text: t('close'),
            handler: function (btn) {
                btn.up('window').close();
            }
        });
    }

    Ext.create('Ext.Window', cfg).show();
};

insquare.deepl.runQueue = function (tasks, requestFn, onProgress, onDone, onError) {
    var index = 0;

    var next = function () {
        if (index >= tasks.length) {
            if (typeof onDone === 'function') {
                onDone();
            }
            return;
        }

        var task = tasks[index];
        requestFn(task, function successCallback(response) {
            index += 1;
            if (typeof onProgress === 'function') {
                onProgress(index, tasks.length, response);
            }
            next();
        }, function errorCallback(response) {
            if (typeof onError === 'function') {
                onError(response, index, tasks.length);
            }
        });
    };

    if (!tasks || !tasks.length) {
        if (typeof onDone === 'function') {
            onDone();
        }
        return;
    }

    next();
};
