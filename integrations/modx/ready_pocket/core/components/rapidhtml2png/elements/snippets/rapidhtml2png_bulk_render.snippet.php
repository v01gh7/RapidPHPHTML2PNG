<?php
/**
 * Snippet: RapidHTML2PNGBulkRender
 *
 * Usage examples:
 * [[!RapidHTML2PNGBulkRender? &mode=`all`]]
 * [[!RapidHTML2PNGBulkRender? &mode=`ids` &resource_ids=`1,2,10` &skip_classes=`no-render,hidden`]]
 */

$mode = strtolower(trim((string)$modx->getOption(
    'mode',
    $scriptProperties,
    isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'ids'
)));

$resourceIds = (string)$modx->getOption(
    'resource_ids',
    $scriptProperties,
    isset($_REQUEST['resource_ids']) ? $_REQUEST['resource_ids'] : ''
);

$skipClasses = (string)$modx->getOption(
    'skip_classes',
    $scriptProperties,
    isset($_REQUEST['skip_classes']) ? $_REQUEST['skip_classes'] : ''
);

$returnFormat = strtolower(trim((string)$modx->getOption('return', $scriptProperties, 'json')));

$servicePath = MODX_CORE_PATH . 'components/rapidhtml2png/model/rapidhtml2png/RendererService.php';
if (!is_file($servicePath)) {
    $error = array(
        'success' => false,
        'message' => 'RendererService.php not found.',
        'data' => array('expected_path' => $servicePath)
    );
    return ($returnFormat === 'array') ? $error : $modx->toJSON($error);
}

require_once $servicePath;

$service = new RapidHTML2PNGRendererService($modx, array(
    'convert_url' => (string)$modx->getOption('rapidhtml2png_convert_url', null, ''),
    'convert_api_key' => (string)$modx->getOption('rapidhtml2png_convert_api_key', null, ''),
    'css_url' => (string)$modx->getOption('rapidhtml2png_css_url', null, ''),
    'render_engine' => (string)$modx->getOption('rapidhtml2png_render_engine', null, 'auto'),
    'request_timeout' => (int)$modx->getOption('rapidhtml2png_request_timeout', null, 120),
    'batch_max_bytes' => (int)$modx->getOption('rapidhtml2png_batch_max_bytes', null, 4500000),
    'batch_max_blocks' => (int)$modx->getOption('rapidhtml2png_batch_max_blocks', null, 75)
));

try {
    $result = $service->run($mode, $resourceIds, $skipClasses);
} catch (Exception $e) {
    $result = array(
        'success' => false,
        'http_code' => 500,
        'message' => 'Unhandled snippet exception.',
        'data' => array('error' => $e->getMessage())
    );
}

if ($returnFormat === 'array') {
    return $result;
}

return $modx->toJSON($result);
