<?php
/**
 * @var modX $modx
 */

$plugins = array();

/** @var modPlugin $plugin */
$plugin = $modx->newObject('modPlugin');
$plugin->fromArray(array(
    'name' => 'RapidHTML2PNGHeaderButton',
    'description' => 'Adds header button and manager modal for bulk render actions.',
    'plugincode' => 'return;',
    'static' => 1,
    'static_file' => 'core/components/rapidhtml2png/elements/plugins/rapidhtml2png_header_button.plugin.php',
    'source' => 1,
), '', true, true);

$events = array();
$eventNames = array(
    'OnManagerPageBeforeRender'
);

foreach ($eventNames as $eventName) {
    /** @var modPluginEvent $event */
    $event = $modx->newObject('modPluginEvent');
    $event->fromArray(array(
        'event' => $eventName,
        'priority' => 0,
        'propertyset' => 0
    ), '', true, true);
    $events[] = $event;
}

$plugin->addMany($events);
$plugins[] = $plugin;

unset($plugin, $events, $eventNames, $event, $eventName);
return $plugins;
