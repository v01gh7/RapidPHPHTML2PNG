<?php
/**
 * @var modX $modx
 */

/** @var modNamespace $namespace */
$namespace = $modx->newObject('modNamespace');
$namespace->fromArray(array(
    'name' => PKG_NAMESPACE,
    'path' => '{core_path}components/' . PKG_NAME_LOWER . '/',
    'assets_path' => '{assets_path}components/' . PKG_NAME_LOWER . '/',
), '', true, true);

return $namespace;
