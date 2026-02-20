<?php
define('MODX_API_MODE', true);

require_once dirname(__DIR__) . '/index.php';

/** @var modX $modx */
$modx->getService('error', 'error.modError', '', '');
$modx->error->reset();

header('Content-Type: application/json; charset=utf-8');

/**
 * @param bool $success
 * @param string $message
 * @param array $data
 * @param int $httpCode
 * @return void
 */
function rapidhtml2pngOutput($success, $message, array $data = array(), $httpCode = 200)
{
    http_response_code((int)$httpCode);
    echo json_encode(array(
        'success' => (bool)$success,
        'message' => (string)$message,
        'data' => $data
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$modx->user || !$modx->user->isAuthenticated('mgr')) {
    rapidhtml2pngOutput(false, 'Access denied. Manager auth required.', array(), 401);
}

if (!($modx->hasPermission('save_document') || $modx->hasPermission('save'))) {
    rapidhtml2pngOutput(false, 'Permission denied.', array(), 403);
}

$action = isset($_REQUEST['action']) ? strtolower(trim((string)$_REQUEST['action'])) : '';
if ($action !== 'render') {
    rapidhtml2pngOutput(false, 'Unknown action.', array(
        'allowed_actions' => array('render')
    ), 400);
}

$servicePath = MODX_CORE_PATH . 'components/rapidhtml2png/model/rapidhtml2png/RendererService.php';
if (!is_file($servicePath)) {
    rapidhtml2pngOutput(false, 'Renderer service file not found.', array(
        'expected_path' => $servicePath
    ), 500);
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

$mode = isset($_REQUEST['mode']) ? strtolower(trim((string)$_REQUEST['mode'])) : 'all';
$resourceIds = isset($_REQUEST['resource_ids']) ? (string)$_REQUEST['resource_ids'] : '';
$skipClasses = isset($_REQUEST['skip_classes']) ? (string)$_REQUEST['skip_classes'] : '';

try {
    $result = $service->run($mode, $resourceIds, $skipClasses);
} catch (Exception $e) {
    rapidhtml2pngOutput(false, 'Unhandled connector exception.', array(
        'error' => $e->getMessage()
    ), 500);
}

$httpCode = isset($result['http_code']) ? (int)$result['http_code'] : (!empty($result['success']) ? 200 : 400);
rapidhtml2pngOutput(
    !empty($result['success']),
    (string)($result['message'] ?? ''),
    (array)($result['data'] ?? array()),
    $httpCode
);
