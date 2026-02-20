<?php

define('PKG_NAME', 'RapidHTML2PNG');
define('PKG_NAME_LOWER', 'rapidhtml2png');
define('PKG_NAMESPACE', 'rapidhtml2png');
define('PKG_VERSION', '1.0.0');
define('PKG_RELEASE', 'pl');

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;

$sources = array(
    'root' => $root,
    'build' => $root . '_build/',
    'data' => $root . '_build/data/',
    'validators' => $root . '_build/validators/',
    'assets' => $root . 'assets/components/' . PKG_NAME_LOWER . '/',
    'core' => $root . 'core/components/' . PKG_NAME_LOWER . '/',
    'connectors' => $root . 'connectors/',
    'docs' => $root . 'docs/'
);

unset($root);
