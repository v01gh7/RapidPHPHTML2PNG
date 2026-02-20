<?php
$success = true;
$minVersion = '2.8.0';
$currentVersion = defined('MODX_VERSION') ? MODX_VERSION : '0.0.0';

if (version_compare($currentVersion, $minVersion, '<')) {
    $success = false;
}

if (isset($modx) && $modx instanceof modX) {
    if ($success) {
        $modx->log(modX::LOG_LEVEL_INFO, '[rapidhtml2png] MODX version check passed: ' . $currentVersion);
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, '[rapidhtml2png] MODX version check failed. Required >= ' . $minVersion . ', current: ' . $currentVersion);
    }
}

return $success;
