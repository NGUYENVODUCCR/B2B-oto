<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/schemas.php';
$routes = require __DIR__ . '/config/routes.php';

$paths = [];

/*
|--------------------------------------------------------------------------
| BUILD EXAMPLE
|--------------------------------------------------------------------------
*/
function buildExample($properties)
{
    $example = [];

    foreach ($properties as $key => $prop) {

        if (isset($prop['example'])) {
            $example[$key] = $prop['example'];
        } elseif (($prop['type'] ?? '') === 'integer') {
            $example[$key] = 1;
        } elseif (($prop['type'] ?? '') === 'number') {
            $example[$key] = 0;
        } elseif (($prop['type'] ?? '') === 'boolean') {
            $example[$key] = true;
        } elseif (($prop['type'] ?? '') === 'array') {
            $example[$key] = [];
        } elseif (($prop['type'] ?? '') === 'object') {
            $example[$key] = [];
        } else {
            $example[$key] = "";
        }
    }

    return $example;
}

/*
|--------------------------------------------------------------------------
| BUILD PATHS
|--------------------------------------------------------------------------
*/
foreach ($routes as $group => $items) {

    foreach ($items as $item) {

        $methods = is_array($item['method']) ? $item['method'] : [$item['method']];

        foreach ($methods as $method) {

            $method = strtolower($method);
            $route = '/b2b/v1' . $item['route'];

            $pathItem = [
                'tags' => [ucfirst($group)],
                'summary' => $item['action'][1] ?? '',
                'description' => 'API ' . $item['route'],
                'responses' => [
                    '200' => ['description' => 'Success'],
                    '400' => ['description' => 'Bad Request'],
                    '401' => ['description' => 'Unauthorized'],
                    '500' => ['description' => 'Server Error']
                ]
            ];

            /*
            |--------------------------------------------------------------------------
            | REQUEST BODY (AUTO FULL)
            |--------------------------------------------------------------------------
            */
            if (in_array($method, ['post', 'put', 'patch'])) {

                $schema = getRequestSchema($item['route'], $method);

                $properties = $schema['properties'] ?? [];

                $pathItem['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $properties,
                                'required' => $schema['required'] ?? []
                            ],
                            'example' => buildExample($properties)
                        ]
                    ]
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | QUERY PARAM AUTO ID
            |--------------------------------------------------------------------------
            */
            if (
                str_contains($item['route'], 'detail') ||
                str_contains($item['route'], 'delete')
            ) {
                $pathItem['parameters'][] = [
                    'name' => 'id',
                    'in' => 'query',
                    'required' => true,
                    'schema' => [
                        'type' => 'integer',
                        'example' => 1
                    ]
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | SECURITY JWT
            |--------------------------------------------------------------------------
            */
            if (!($item['public'] ?? false)) {
                $pathItem['security'] = [
                    [
                        'bearerAuth' => []
                    ]
                ];
            }

            $paths[$route][$method] = $pathItem;
        }
    }
}

/*
|--------------------------------------------------------------------------
| OUTPUT OPENAPI JSON
|--------------------------------------------------------------------------
*/
echo json_encode([
    'openapi' => '3.0.0',
    'info' => [
        'title' => 'B2B Marketplace API',
        'version' => '1.0.0',
        'description' => 'Swagger auto generated API'
    ],
    'servers' => [
        [
            'url' => 'http://localhost/B2B-dev/web/wp-json'
        ]
    ],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT'
            ]
        ]
    ],
    'paths' => $paths
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

exit;