<?php

$routes = require __DIR__ . '/config/routes.php';

$paths = [];

foreach ($routes as $group => $items) {

    foreach ($items as $item) {

        $methods = is_array($item['method'])
            ? $item['method']
            : [$item['method']];

        foreach ($methods as $method) {

            $method = strtolower($method);

            $route = '/b2b/v1' . $item['route'];

            $paths[$route][$method] = [

                'tags' => [ucfirst($group)],

                'summary' => ucfirst($item['action'][1]),

                'description' => 'API endpoint for ' . $item['route'],

                'requestBody' => [

                    'required' => true,

                    'content' => [

                        'application/json' => [

                            'schema' => [

                                'type' => 'object',

                                'properties' => [

                                    'phone' => [
                                        'type' => 'string',
                                        'example' => '0943234949'
                                    ],

                                    'password' => [
                                        'type' => 'string',
                                        'example' => '123456'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],

                'responses' => [

                    '200' => [
                        'description' => 'Success'
                    ],

                    '401' => [
                        'description' => 'Unauthorized'
                    ],

                    '500' => [
                        'description' => 'Server Error'
                    ]
                ]
            ];
        }
    }
}

echo json_encode([

    'openapi' => '3.0.0',

    'info' => [

        'title' => 'B2B Marketplace API',

        'version' => '1.0.0',

        'description' => 'Swagger documentation for B2B Marketplace'
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

    'security' => [
        [
            'bearerAuth' => []
        ]
    ],

    'paths' => $paths

], JSON_PRETTY_PRINT);

exit;