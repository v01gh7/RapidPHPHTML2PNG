<?php
/**
 * @var modX $modx
 */

$snippets = array();

/** @var modSnippet $snippet */
$snippet = $modx->newObject('modSnippet');
$snippet->fromArray(array(
    'name' => 'RapidHTML2PNGBulkRender',
    'description' => 'Bulk render MODX resources to RapidHTML2PNG converter.',
    'snippet' => 'return "";',
    'static' => 1,
    'static_file' => 'core/components/rapidhtml2png/elements/snippets/rapidhtml2png_bulk_render.snippet.php',
    'source' => 1,
), '', true, true);

$properties = array(
    array(
        'name' => 'mode',
        'desc' => 'Render mode: all|ids',
        'type' => 'textfield',
        'options' => '',
        'value' => 'ids',
        'lexicon' => '',
        'area' => ''
    ),
    array(
        'name' => 'resource_ids',
        'desc' => 'Comma separated resource IDs',
        'type' => 'textfield',
        'options' => '',
        'value' => '',
        'lexicon' => '',
        'area' => ''
    ),
    array(
        'name' => 'skip_classes',
        'desc' => 'Comma separated CSS classes to skip',
        'type' => 'textfield',
        'options' => '',
        'value' => '',
        'lexicon' => '',
        'area' => ''
    ),
    array(
        'name' => 'return',
        'desc' => 'Return format: json|array',
        'type' => 'textfield',
        'options' => '',
        'value' => 'json',
        'lexicon' => '',
        'area' => ''
    ),
);

$snippet->setProperties($properties);
$snippets[] = $snippet;

unset($snippet, $properties);
return $snippets;
