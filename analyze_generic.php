<?php

$json = json_decode(file_get_contents('storage/api-docs/openapi.json'), true);

echo "=== Операции с generic schema ===" . PHP_EOL . PHP_EOL;

foreach ($json['paths'] as $path => $methods) {
    foreach ($methods as $method => $operation) {
        if ($method === 'delete') {
            continue;
        }
        
        $hasRef = isset($operation['responses']['200']['content']['application/json']['schema']['$ref']);
        
        if (!$hasRef) {
            $operationId = $operation['operationId'] ?? 'unknown';
            echo "$method $path" . PHP_EOL;
            echo "  operationId: $operationId" . PHP_EOL;
        }
    }
}



