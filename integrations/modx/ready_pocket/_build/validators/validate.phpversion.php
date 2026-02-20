<?php
$success = true;
$minVersion = '7.4.0';
$currentVersion = PHP_VERSION;

if (version_compare($currentVersion, $minVersion, '<')) {
    $success = false;
}

if (isset($modx) && $modx instanceof modX) {
    if ($success) {
        $modx->log(modX::LOG_LEVEL_INFO, '[rapidhtml2png] PHP version check passed: ' . $currentVersion);
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, '[rapidhtml2png] PHP version check failed. Required >= ' . $minVersion . ', current: ' . $currentVersion);
    }
}

return $success;
