<?php
/**
 * Build transport package for MODX Revo 2.8+
 *
 * Usage:
 * 1) composer install
 * 2) composer run build
 */

@ini_set('display_errors', 1);
@error_reporting(E_ALL);
@set_time_limit(0);

require_once __DIR__ . '/build.config.php';

$autoloadPath = $sources['root'] . 'vendor/autoload.php';
if (!is_file($autoloadPath)) {
    echo "ERROR: vendor/autoload.php not found. Run: composer install\n";
    exit(1);
}

require_once $autoloadPath;

$vendorModxCore = $sources['root'] . 'vendor/modx/revolution/core/';
if (!defined('MODX_CORE_PATH')) {
    define('MODX_CORE_PATH', $vendorModxCore);
}
if (!defined('MODX_CONFIG_KEY')) {
    define('MODX_CONFIG_KEY', 'config');
}
if (!class_exists('modX', false)) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
}
require_once MODX_CORE_PATH . 'model/modx/transport/modpackagebuilder.class.php';

if (!class_exists('RapidHtml2PngPackageBuilder', false)) {
    class RapidHtml2PngPackageBuilder extends modPackageBuilder
    {
        /**
         * Lightweight package builder constructor for standalone CI/Docker builds
         * without a preconfigured MODX workspace in database.
         */
        public function __construct(modX &$modx, $directory)
        {
            $this->modx = &$modx;
            $this->modx->loadClass('transport.modTransportVehicle', '', false, true);
            $this->modx->loadClass('transport.xPDOTransport', XPDO_CORE_PATH, true, true);
            $this->directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
            if (!is_dir($this->directory)) {
                mkdir($this->directory, 0777, true);
            }
            $this->autoselects = array();
        }

        /**
         * Standalone namespace registration without DB lookups.
         */
        public function registerNamespace($ns = 'core', $autoincludes = true, $packageNamespace = true, $path = '', $assetsPath = '')
        {
            if ($ns instanceof modNamespace) {
                $namespace = $ns;
            } else {
                $namespace = $this->modx->newObject('modNamespace');
                $namespace->set('name', (string)$ns);
                if (!empty($path)) {
                    $namespace->set('path', $path);
                }
                if (!empty($assetsPath)) {
                    $namespace->set('assets_path', $assetsPath);
                }
            }

            $this->namespace = $namespace;

            if ($packageNamespace) {
                $attributes = array(
                    xPDOTransport::UNIQUE_KEY => 'name',
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::RESOLVE_FILES => true,
                    xPDOTransport::RESOLVE_PHP => true,
                );
                $vehicle = $this->createVehicle($namespace, $attributes);
                if (!$this->putVehicle($vehicle)) {
                    return false;
                }
            }

            return true;
        }
    }
}

$buildBasePath = rtrim(str_replace('\\', '/', $sources['root']), '/') . '/';
$constantMap = array(
    'MODX_BASE_URL' => '/',
    'MODX_BASE_PATH' => $buildBasePath,
    'MODX_URL_SCHEME' => 'http://',
    'MODX_HTTP_HOST' => 'localhost',
    'MODX_SITE_URL' => 'http://localhost/',
    'MODX_MANAGER_PATH' => $buildBasePath . 'manager/',
    'MODX_MANAGER_URL' => '/manager/',
    'MODX_ASSETS_PATH' => $buildBasePath . 'assets/',
    'MODX_ASSETS_URL' => '/assets/',
    'MODX_CONNECTORS_PATH' => $buildBasePath . 'connectors/',
    'MODX_CONNECTORS_URL' => '/connectors/',
    'MODX_PROCESSORS_PATH' => MODX_CORE_PATH . 'model/modx/processors/'
);
foreach ($constantMap as $constantName => $constantValue) {
    if (!defined($constantName)) {
        define($constantName, $constantValue);
    }
}

$cliConfigPath = $sources['build'] . '.tmp-config/';
if (!is_dir($cliConfigPath)) {
    mkdir($cliConfigPath, 0777, true);
}
$cliConfigFile = $cliConfigPath . MODX_CONFIG_KEY . '.inc.php';
$configContent = <<<'PHP'
<?php
$database_dsn = 'mysql:host=127.0.0.1;dbname=modx;charset=utf8mb4';
$database_user = 'root';
$database_password = '';
$table_prefix = 'modx_';
$config_options = array();
$driver_options = array();
$site_id = 'rapidhtml2png-build';
$uuid = 'rapidhtml2png-build';
return true;
PHP;
file_put_contents($cliConfigFile, $configContent);

/** @var modX $modx */
$modx = new modX($cliConfigPath);
$initialized = false;
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

echo "Building package " . PKG_NAME . " " . PKG_VERSION . "-" . PKG_RELEASE . "\n";
if (!$initialized) {
    echo "WARNING: MODX manager context is not initialized. Build will continue in limited mode.\n";
}

$builder = new RapidHtml2PngPackageBuilder($modx, $sources['root'] . '_packages/');
$builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(
    PKG_NAMESPACE,
    false,
    true,
    '{core_path}components/' . PKG_NAME_LOWER . '/',
    '{assets_path}components/' . PKG_NAME_LOWER . '/'
);

// Namespace object
$namespaceObject = include $sources['data'] . 'transport.namespace.php';
if ($namespaceObject instanceof modNamespace) {
    $namespaceVehicle = $builder->createVehicle($namespaceObject, array(
        xPDOTransport::UNIQUE_KEY => 'name',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true
    ));
    $builder->putVehicle($namespaceVehicle);
}

// System settings
$settings = include $sources['data'] . 'transport.settings.php';
if (is_array($settings)) {
    foreach ($settings as $setting) {
        /** @var modSystemSetting $setting */
        $vehicle = $builder->createVehicle($setting, array(
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => true
        ));
        $builder->putVehicle($vehicle);
    }
}

// Snippets and plugins inside category
$snippets = include $sources['data'] . 'transport.snippets.php';
$plugins = include $sources['data'] . 'transport.plugins.php';

/** @var modCategory $category */
$category = $modx->newObject('modCategory');
$category->fromArray(array(
    'category' => PKG_NAME
), '', true, true);

if (is_array($snippets) && !empty($snippets)) {
    $category->addMany($snippets);
}
if (is_array($plugins) && !empty($plugins)) {
    $category->addMany($plugins);
}

$categoryVehicle = $builder->createVehicle($category, array(
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
        'Snippets' => array(
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true
        ),
        'Plugins' => array(
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
                'PluginEvents' => array(
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::UNIQUE_KEY => array('pluginid', 'event')
                )
            )
        )
    )
));

// Validators
$categoryVehicle->validate('php', array(
    'source' => $sources['validators'] . 'validate.phpversion.php'
));
$categoryVehicle->validate('php', array(
    'source' => $sources['validators'] . 'validate.modxversion.php'
));

// File resolvers
$categoryVehicle->resolve('file', array(
    'source' => $sources['core'],
    'target' => "return MODX_CORE_PATH . 'components/" . PKG_NAME_LOWER . "/';"
));
$categoryVehicle->resolve('file', array(
    'source' => $sources['assets'],
    'target' => "return MODX_ASSETS_PATH . 'components/" . PKG_NAME_LOWER . "/';"
));
$categoryVehicle->resolve('file', array(
    'source' => $sources['connectors'],
    'target' => "return MODX_CONNECTORS_PATH;"
));

$builder->putVehicle($categoryVehicle);

$builder->pack();
echo "DONE\n";
