(function (window) {
    "use strict";

    var RapidHTML2PNGManager = {
        config: {
            connectorUrl: (window.MODx && MODx.config && MODx.config.connectors_url ? MODx.config.connectors_url : "/connectors/") + "rapidhtml2png.php",
            defaultSkipClasses: ""
        },
        initialized: false,
        button: null,
        modal: null,

        init: function (cfg) {
            if (this.initialized) {
                return;
            }

            this.config = Ext.apply(this.config, cfg || {});
            this.initialized = true;
            this.injectButton(0);
        },

        injectButton: function (attempt) {
            if (this.button) {
                return;
            }

            var target = Ext.get("modx-navbar") || Ext.get("modx-topnav") || Ext.get("modx-header");
            if (!target) {
                if (attempt < 20) {
                    window.setTimeout(function () {
                        RapidHTML2PNGManager.injectButton(attempt + 1);
                    }, 500);
                }
                return;
            }

            var wrap = Ext.get("rapidhtml2png-btn-wrap");
            if (!wrap) {
                wrap = Ext.DomHelper.append(target, {
                    tag: "div",
                    id: "rapidhtml2png-btn-wrap",
                    style: "float:right;margin:8px 12px 0 0;position:relative;z-index:20;"
                }, true);
            }

            this.button = new Ext.Button({
                text: "Отрендерить",
                cls: "primary-button",
                scale: "small",
                renderTo: wrap,
                handler: function () {
                    RapidHTML2PNGManager.openModal();
                }
            });
        },

        openModal: function () {
            if (!this.modal) {
                this.modal = this.buildModal();
            }

            this.modal.show();
        },

        buildModal: function () {
            var formPanel = new Ext.form.FormPanel({
                border: false,
                bodyStyle: "padding:12px;",
                labelWidth: 240,
                defaults: {
                    anchor: "100%"
                },
                items: [
                    {
                        xtype: "displayfield",
                        value: "Запуск пакетного рендера для ресурсов MODX через RapidHTML2PNG."
                    },
                    {
                        xtype: "textarea",
                        fieldLabel: "ID ресурсов (через запятую)",
                        name: "resource_ids",
                        height: 80,
                        emptyText: "Пример: 1,2,15,42"
                    },
                    {
                        xtype: "textarea",
                        fieldLabel: "CSS классы пропуска (через запятую)",
                        name: "skip_classes",
                        height: 80,
                        value: this.config.defaultSkipClasses || "",
                        emptyText: "Пример: no-render,skip-export,hidden-text"
                    },
                    {
                        xtype: "displayfield",
                        value: "Кнопка \"Отрендерить все ресурсы\" игнорирует поле ID и обрабатывает все не удаленные ресурсы."
                    },
                    {
                        xtype: "box",
                        autoEl: {
                            tag: "pre",
                            id: "rapidhtml2png-result-box",
                            style: "display:none;max-height:180px;overflow:auto;background:#f6f7f9;padding:8px;border:1px solid #ddd;white-space:pre-wrap;"
                        }
                    }
                ]
            });

            return new Ext.Window({
                title: "RapidHTML2PNG",
                width: 760,
                autoHeight: true,
                closeAction: "hide",
                modal: true,
                resizable: false,
                items: [formPanel],
                buttons: [
                    {
                        text: "Отрендерить все ресурсы",
                        handler: function () {
                            RapidHTML2PNGManager.runRender("all", formPanel);
                        }
                    },
                    {
                        text: "Отрендерить по ID",
                        handler: function () {
                            RapidHTML2PNGManager.runRender("ids", formPanel);
                        }
                    },
                    {
                        text: "Закрыть",
                        handler: function (btn) {
                            btn.ownerCt.ownerCt.hide();
                        }
                    }
                ]
            });
        },

        setBusy: function (busy) {
            if (!this.modal) {
                return;
            }

            var buttons = this.modal.buttons || [];
            Ext.each(buttons, function (btn) {
                if (btn && btn.text !== "Закрыть") {
                    btn.setDisabled(!!busy);
                }
            });

            if (busy) {
                this.modal.el.mask("Выполняется пакетный рендер...");
            } else {
                this.modal.el.unmask();
            }
        },

        runRender: function (mode, formPanel) {
            var form = formPanel.getForm();
            var values = form.getValues();

            this.setBusy(true);

            Ext.Ajax.request({
                url: this.config.connectorUrl,
                method: "POST",
                timeout: 300000,
                params: {
                    action: "render",
                    mode: mode,
                    resource_ids: values.resource_ids || "",
                    skip_classes: values.skip_classes || ""
                },
                callback: function (options, success, response) {
                    RapidHTML2PNGManager.setBusy(false);
                    RapidHTML2PNGManager.handleResponse(success, response);
                }
            });
        },

        handleResponse: function (success, response) {
            var payload = null;
            var resultBox = Ext.get("rapidhtml2png-result-box");

            if (!success || !response) {
                MODx.msg.alert("RapidHTML2PNG", "HTTP ошибка при запросе к connector.");
                return;
            }

            try {
                payload = Ext.decode(response.responseText);
            } catch (e) {
                MODx.msg.alert("RapidHTML2PNG", "Некорректный JSON ответ от connector.");
                return;
            }

            if (!payload.success) {
                MODx.msg.alert("RapidHTML2PNG", payload.message || payload.error || "Операция завершилась ошибкой.");
            } else {
                MODx.msg.alert("RapidHTML2PNG", payload.message || "Рендер завершен.");
            }

            if (resultBox) {
                resultBox.dom.style.display = "block";
                resultBox.update(Ext.util.Format.htmlEncode(this.formatSummary(payload)));
            }
        },

        formatSummary: function (payload) {
            var out = [];
            out.push("success: " + (!!payload.success));
            if (payload.message) {
                out.push("message: " + payload.message);
            }
            if (payload.data) {
                out.push("data:");
                out.push(Ext.encode(payload.data));
            }
            return out.join("\n");
        }
    };

    window.RapidHTML2PNGManager = RapidHTML2PNGManager;
}(window));
