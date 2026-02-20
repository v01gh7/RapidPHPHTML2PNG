<?php
/**
 * @var modX $modx
 */

$settings = array();

$settingRows = array(
    'rapidhtml2png_convert_url' => array(
        'xtype' => 'textfield',
        'value' => ''
    ),
    'rapidhtml2png_convert_api_key' => array(
        'xtype' => 'textfield',
        'value' => ''
    ),
    'rapidhtml2png_css_url' => array(
        'xtype' => 'textfield',
        'value' => ''
    ),
    'rapidhtml2png_render_engine' => array(
        'xtype' => 'textfield',
        'value' => 'auto'
    ),
    'rapidhtml2png_request_timeout' => array(
        'xtype' => 'numberfield',
        'value' => '120'
    ),
    'rapidhtml2png_batch_max_bytes' => array(
        'xtype' => 'numberfield',
        'value' => '4500000'
    ),
    'rapidhtml2png_batch_max_blocks' => array(
        'xtype' => 'numberfield',
        'value' => '75'
    ),
    'rapidhtml2png_default_skip_classes' => array(
        'xtype' => 'textfield',
        'value' => 'no-render,skip-export'
    ),
);

foreach ($settingRows as $key => $meta) {
    /** @var modSystemSetting $setting */
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray(array(
        'key' => $key,
        'value' => $meta['value'],
        'xtype' => $meta['xtype'],
        'namespace' => 'rapidhtml2png',
        'area' => 'rapidhtml2png_main'
    ), '', true, true);
    $settings[$key] = $setting;
}

unset($settingRows, $setting, $key, $meta);
return $settings;
