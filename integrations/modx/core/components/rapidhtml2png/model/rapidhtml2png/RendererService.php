<?php

class RapidHTML2PNGRendererService
{
    /** @var modX */
    protected $modx;

    /** @var array */
    protected $config = array();

    /** @var mixed */
    protected $pdoTools;

    /**
     * @param modX $modx
     * @param array $config
     */
    public function __construct(modX $modx, array $config = array())
    {
        $this->modx = $modx;
        $this->config = array_merge(array(
            'convert_url' => '',
            'convert_api_key' => '',
            'css_url' => '',
            'render_engine' => 'auto',
            'request_timeout' => 120,
            'batch_max_bytes' => 4500000,
            'batch_max_blocks' => 75
        ), $config);

        $this->pdoTools = $this->modx->getService('pdoFetch');
    }

    /**
     * @param string $mode all|ids
     * @param string $resourceIdsCsv
     * @param string $skipClassesCsv
     * @return array
     */
    public function run($mode, $resourceIdsCsv, $skipClassesCsv)
    {
        if (!extension_loaded('curl')) {
            return $this->error('cURL extension is required on MODX side.', 500);
        }

        if (!class_exists('DOMDocument')) {
            return $this->error('DOM extension is required for HTML parsing.', 500);
        }

        $convertUrl = trim((string)$this->config['convert_url']);
        $convertApiKey = trim((string)$this->config['convert_api_key']);
        if ($convertUrl === '') {
            return $this->error('System setting rapidhtml2png_convert_url is empty.', 500);
        }
        if ($convertApiKey === '') {
            return $this->error('System setting rapidhtml2png_convert_api_key is empty.', 500);
        }

        try {
            $resourceIds = $this->resolveResourceIds($mode, $resourceIdsCsv);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if (empty($resourceIds)) {
            return $this->error('No resources found for render request.', 404, array(
                'mode' => $mode
            ));
        }

        $skipClassList = $this->parseCsvList($skipClassesCsv);
        $skipClassMap = array();
        foreach ($skipClassList as $className) {
            $skipClassMap[$this->toLower($className)] = true;
        }

        $allBlocks = array();
        $resourceStats = array();
        $resourceErrors = array();
        $renderedResources = 0;

        foreach ($resourceIds as $resourceId) {
            /** @var modResource|null $resource */
            $resource = $this->modx->getObject('modResource', (int)$resourceId);
            if (!$resource || (int)$resource->get('deleted') === 1) {
                continue;
            }

            try {
                $html = $this->renderResourceToHtml($resource);
                $blocks = $this->extractTextBlocks($html, $skipClassMap);

                $resourceStats[(int)$resource->get('id')] = array(
                    'pagetitle' => (string)$resource->get('pagetitle'),
                    'blocks' => count($blocks)
                );

                foreach ($blocks as $block) {
                    $allBlocks[] = $block;
                }

                $renderedResources++;
            } catch (Exception $e) {
                $resourceErrors[] = array(
                    'resource_id' => (int)$resource->get('id'),
                    'error' => $e->getMessage()
                );
            }
        }

        $allBlocks = $this->deduplicateBlocks($allBlocks);
        if (empty($allBlocks)) {
            return $this->error('No text blocks extracted from rendered resources.', 422, array(
                'rendered_resources' => $renderedResources,
                'resource_errors' => $resourceErrors
            ));
        }

        $batches = $this->splitBlocksToBatches(
            $allBlocks,
            max(100000, (int)$this->config['batch_max_bytes']),
            max(1, (int)$this->config['batch_max_blocks'])
        );

        $batchResults = array();
        $successfulBatches = 0;
        $failedBatches = 0;
        $outputFiles = array();

        foreach ($batches as $index => $batch) {
            $batchResult = $this->sendBatchToConverter($batch, $index + 1, count($batches));
            $batchResults[] = $batchResult;

            if (!empty($batchResult['success'])) {
                $successfulBatches++;
                if (!empty($batchResult['output_file'])) {
                    $outputFiles[] = $batchResult['output_file'];
                }
            } else {
                $failedBatches++;
            }
        }

        $success = ($failedBatches === 0 && $successfulBatches > 0);
        $message = sprintf(
            'Resources scanned: %d. Text blocks: %d. Batches: %d (success: %d, failed: %d).',
            $renderedResources,
            count($allBlocks),
            count($batches),
            $successfulBatches,
            $failedBatches
        );

        return array(
            'success' => $success,
            'http_code' => $success ? 200 : 207,
            'message' => $message,
            'data' => array(
                'mode' => $mode,
                'rendered_resources' => $renderedResources,
                'input_resources' => array_values($resourceIds),
                'skip_classes' => array_values($skipClassList),
                'total_blocks' => count($allBlocks),
                'batches_total' => count($batches),
                'batches_success' => $successfulBatches,
                'batches_failed' => $failedBatches,
                'output_files' => array_values(array_unique($outputFiles)),
                'resource_stats' => $resourceStats,
                'resource_errors' => $resourceErrors,
                'batch_results' => $batchResults
            )
        );
    }

    /**
     * @param string $mode
     * @param string $resourceIdsCsv
     * @return array
     */
    protected function resolveResourceIds($mode, $resourceIdsCsv)
    {
        $mode = strtolower(trim((string)$mode));
        if ($mode !== 'all' && $mode !== 'ids') {
            throw new InvalidArgumentException('Unsupported mode. Use "all" or "ids".');
        }

        $q = $this->modx->newQuery('modResource');
        $q->select($this->modx->getSelectColumns('modResource', 'modResource', '', array('id')));
        $q->where(array('deleted' => 0));
        $q->sortby('id', 'ASC');

        if ($mode === 'ids') {
            $ids = array();
            foreach ($this->parseCsvList($resourceIdsCsv) as $idRaw) {
                if (is_numeric($idRaw)) {
                    $ids[] = (int)$idRaw;
                }
            }
            $ids = array_values(array_unique(array_filter($ids)));
            if (empty($ids)) {
                return array();
            }
            $q->where(array('id:IN' => $ids));
        }

        if (!$q->prepare() || !$q->stmt->execute()) {
            return array();
        }

        $ids = $q->stmt->fetchAll(PDO::FETCH_COLUMN);
        $result = array();
        foreach ($ids as $idValue) {
            $result[] = (int)$idValue;
        }
        return $result;
    }

    /**
     * @param modResource $resource
     * @return string
     */
    protected function renderResourceToHtml(modResource $resource)
    {
        $templateContent = '[[*content]]';
        /** @var modTemplate|null $template */
        $template = $resource->getOne('Template');
        if ($template && trim((string)$template->get('content')) !== '') {
            $templateContent = (string)$template->get('content');
        }

        $placeholders = $resource->toArray('', true, true);
        $placeholders['content'] = (string)$resource->get('content');

        $tvs = $resource->getTemplateVars();
        if (is_array($tvs)) {
            foreach ($tvs as $tv) {
                /** @var modTemplateVar $tv */
                $placeholders[$tv->get('name')] = $tv->renderOutput($resource->get('id'));
            }
        }

        if ($this->pdoTools && method_exists($this->pdoTools, 'parseChunk')) {
            $html = $this->pdoTools->parseChunk('@INLINE ' . $templateContent, $placeholders, true);
        } else {
            $html = str_replace('[[*content]]', $placeholders['content'], $templateContent);
        }

        $previousResource = $this->modx->resource;
        $this->modx->resource = $resource;

        $maxIterations = 12;
        $this->modx->getParser()->processElementTags('', $html, true, true, '[[', ']]', array(), $maxIterations);
        $this->modx->getParser()->processElementTags('', $html, true, false, '[[', ']]', array(), $maxIterations);

        $this->modx->resource = $previousResource;

        return (string)$html;
    }

    /**
     * @param string $html
     * @param array $skipClassMap
     * @return array
     */
    protected function extractTextBlocks($html, array $skipClassMap)
    {
        $blocks = array();
        if (trim((string)$html) === '') {
            return $blocks;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';

        $libxmlPrevious = libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPrevious);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//body//*[not(self::script) and not(self::style) and not(self::noscript) and not(self::template)]');
        if (!$nodes) {
            return $blocks;
        }

        foreach ($nodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            if ($this->isElementSkipped($node, $skipClassMap)) {
                continue;
            }

            if (!$this->elementHasVisibleText($node)) {
                continue;
            }

            if (!$this->elementHasDirectText($node) && $node->getElementsByTagName('*')->length > 0) {
                continue;
            }

            $outerHtml = trim((string)$dom->saveHTML($node));
            if ($outerHtml === '') {
                continue;
            }

            $blocks[] = $outerHtml;
        }

        return $blocks;
    }

    /**
     * @param DOMElement $element
     * @return bool
     */
    protected function elementHasVisibleText(DOMElement $element)
    {
        $text = preg_replace('/\s+/u', ' ', trim((string)$element->textContent));
        return $text !== '';
    }

    /**
     * @param DOMElement $element
     * @return bool
     */
    protected function elementHasDirectText(DOMElement $element)
    {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = preg_replace('/\s+/u', ' ', trim((string)$child->nodeValue));
                if ($text !== '') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param DOMElement $element
     * @param array $skipClassMap
     * @return bool
     */
    protected function isElementSkipped(DOMElement $element, array $skipClassMap)
    {
        if (empty($skipClassMap)) {
            return false;
        }

        $node = $element;
        while ($node instanceof DOMElement) {
            if ($node->hasAttribute('class')) {
                $classes = preg_split('/\s+/', trim((string)$node->getAttribute('class')));
                foreach ($classes as $className) {
                    $className = $this->toLower(trim((string)$className));
                    if ($className !== '' && isset($skipClassMap[$className])) {
                        return true;
                    }
                }
            }

            $parent = $node->parentNode;
            $node = $parent instanceof DOMElement ? $parent : null;
        }

        return false;
    }

    /**
     * @param array $blocks
     * @return array
     */
    protected function deduplicateBlocks(array $blocks)
    {
        $unique = array();
        $seen = array();

        foreach ($blocks as $block) {
            $key = md5($block);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $block;
        }

        return $unique;
    }

    /**
     * @param array $blocks
     * @param int $maxBytes
     * @param int $maxBlocks
     * @return array
     */
    protected function splitBlocksToBatches(array $blocks, $maxBytes, $maxBlocks)
    {
        $batches = array();
        $current = array();
        $currentSize = 0;

        foreach ($blocks as $block) {
            $blockSize = strlen($block) + 64;
            $needNewBatch = (!empty($current) && ($currentSize + $blockSize > $maxBytes || count($current) >= $maxBlocks));

            if ($needNewBatch) {
                $batches[] = $current;
                $current = array();
                $currentSize = 0;
            }

            $current[] = $block;
            $currentSize += $blockSize;
        }

        if (!empty($current)) {
            $batches[] = $current;
        }

        return $batches;
    }

    /**
     * @param array $blocks
     * @param int $batchIndex
     * @param int $batchTotal
     * @return array
     */
    protected function sendBatchToConverter(array $blocks, $batchIndex, $batchTotal)
    {
        $payload = array(
            'api_key' => (string)$this->config['convert_api_key'],
            'html_blocks' => array_values($blocks)
        );

        $cssUrl = trim((string)$this->config['css_url']);
        if ($cssUrl !== '') {
            $payload['css_url'] = $cssUrl;
        }

        $renderEngine = trim((string)$this->config['render_engine']);
        if ($renderEngine === '') {
            $renderEngine = 'auto';
        }
        $payload['render_engine'] = $renderEngine;

        $body = http_build_query($payload, '', '&');
        $requestTimeout = max(15, (int)$this->config['request_timeout']);

        $ch = curl_init((string)$this->config['convert_url']);
        if ($ch === false) {
            return array(
                'success' => false,
                'batch' => $batchIndex . '/' . $batchTotal,
                'error' => 'Failed to init cURL.'
            );
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, $requestTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded'
        ));

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            return array(
                'success' => false,
                'batch' => $batchIndex . '/' . $batchTotal,
                'http_code' => $httpCode,
                'error' => 'cURL error: ' . $curlError
            );
        }

        $decoded = json_decode($responseBody, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300 && is_array($decoded) && !empty($decoded['success']));

        $outputFile = null;
        if (is_array($decoded)) {
            $outputFile = $decoded['data']['rendering']['output_file'] ?? null;
        }

        return array(
            'success' => $isSuccess,
            'batch' => $batchIndex . '/' . $batchTotal,
            'http_code' => $httpCode,
            'blocks' => count($blocks),
            'output_file' => $outputFile,
            'error' => (!$isSuccess && is_array($decoded)) ? ($decoded['error'] ?? 'Converter returned error.') : null
        );
    }

    /**
     * @param string $message
     * @param int $httpCode
     * @param array $data
     * @return array
     */
    protected function error($message, $httpCode = 400, array $data = array())
    {
        return array(
            'success' => false,
            'http_code' => (int)$httpCode,
            'message' => (string)$message,
            'data' => $data
        );
    }

    /**
     * @param string $value
     * @return array
     */
    protected function parseCsvList($value)
    {
        $parts = explode(',', (string)$value);
        $result = array();
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $result[] = $part;
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * @param string $value
     * @return string
     */
    protected function toLower($value)
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower((string)$value, 'UTF-8');
        }
        return strtolower((string)$value);
    }
}
