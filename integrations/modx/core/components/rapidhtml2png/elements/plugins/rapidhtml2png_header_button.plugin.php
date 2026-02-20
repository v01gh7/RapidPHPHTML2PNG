<?php
/**
 * Plugin: RapidHTML2PNG Header Button
 * Event: OnManagerPageBeforeRender
 */

if ($modx->event->name !== 'OnManagerPageBeforeRender') {
    return;
}

if (!$modx->user || !$modx->user->isAuthenticated('mgr')) {
    return;
}

if (!($modx->hasPermission('save_document') || $modx->hasPermission('save'))) {
    return;
}

$assetsUrl = MODX_ASSETS_URL . 'components/rapidhtml2png/';
$connectorUrl = MODX_CONNECTORS_URL . 'rapidhtml2png.php';

$config = array(
    'connectorUrl' => $connectorUrl,
    'defaultSkipClasses' => (string)$modx->getOption('rapidhtml2png_default_skip_classes', null, ''),
);

$modx->controller->addJavascript($assetsUrl . 'js/mgr/rapidhtml2png.js');
$modx->controller->addHtml('<script type="text/javascript">window.RapidHTML2PNGConfig = ' . $modx->toJSON($config) . ';</script>');
$modx->controller->addHtml(
    '<script type="text/javascript">
        Ext.onReady(function () {
            if (window.RapidHTML2PNGManager) {
                window.RapidHTML2PNGManager.init(window.RapidHTML2PNGConfig || {});
            }
        });
    </script>'
);
