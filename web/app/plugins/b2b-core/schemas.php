
<?php

function getRequestSchema($route, $method)
{
    switch ($route) {

        /*
        |--------------------------------------------------------------------------
        | AUTH
        |--------------------------------------------------------------------------
        */

        case '/auth/register':
            return [
                'type' => 'object',
                'properties' => [

                    'fullname' => [
                        'type' => 'string',
                        'example' => 'Nguyen Van A'
                    ],

                    'phone' => [
                        'type' => 'string',
                        'example' => '0943234949'
                    ],

                    'password' => [
                        'type' => 'string',
                        'example' => '123456'
                    ]
                ],

                'required' => [
                    'fullname',
                    'phone',
                    'password'
                ]
            ];

        case '/auth/login':
            return [
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
                ],

                'required' => [
                    'phone',
                    'password'
                ]
            ];

        case '/auth/verify':
            return [
                'type' => 'object',
                'properties' => [

                    'phone' => [
                        'type' => 'string',
                        'example' => '0943234949'
                    ],

                    'otp' => [
                        'type' => 'string',
                        'example' => '123456'
                    ]
                ],

                'required' => [
                    'phone',
                    'otp'
                ]
            ];

        case '/auth/refresh':
            return [
                'type' => 'object',
                'properties' => [

                    'refresh_token' => [
                        'type' => 'string',
                        'example' => 'refresh_token_here'
                    ]
                ]
            ];

        case '/auth/forgot-password':
            return [
                'type' => 'object',
                'properties' => [

                    'phone' => [
                        'type' => 'string',
                        'example' => '0943234949'
                    ]
                ],

                'required' => [
                    'phone'
                ]
            ];

        case '/auth/reset-password':
            return [
                'type' => 'object',
                'properties' => [

                    'phone' => [
                        'type' => 'string',
                        'example' => '0943234949'
                    ],

                    'otp' => [
                        'type' => 'string',
                        'example' => '123456'
                    ],

                    'new_password' => [
                        'type' => 'string',
                        'example' => '12345678'
                    ]
                ],

                'required' => [
                    'phone',
                    'otp',
                    'new_password'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

        case '/user/update':
            return [
                'type' => 'object',

                'properties' => [

                    'fullname' => [
                        'type' => 'string',
                        'example' => 'Nguyen Van A'
                    ],

                    'phone' => [
                        'type' => 'string',
                        'example' => '0943234949'
                    ],

                    'avatar' => [
                        'type' => 'string',
                        'format' => 'binary'
                    ]
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | COMPANY
        |--------------------------------------------------------------------------
        */

        case '/company/create':
            return [
                'type' => 'object',

                'properties' => [

                    'company_name' => [
                        'type' => 'string',
                        'example' => 'ABC Company'
                    ],

                    'tax_code' => [
                        'type' => 'string',
                        'example' => '0123456789'
                    ],

                    'address' => [
                        'type' => 'string',
                        'example' => 'Da Nang'
                    ],

                    'description' => [
                        'type' => 'string',
                        'example' => 'Cong ty thuong mai'
                    ]
                ],

                'required' => [
                    'company_name',
                    'tax_code'
                ]
            ];

        case '/company/join':
            return [
                'type' => 'object',

                'properties' => [

                    'company_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ]
                ],

                'required' => [
                    'company_id'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | SELLER REQUEST
        |--------------------------------------------------------------------------
        */

        case '/seller-request/create':
            return [
                'type' => 'object',

                'properties' => [

                    'company_name' => [
                        'type' => 'string',
                        'example' => 'ABC Company'
                    ],

                    'tax_code' => [
                        'type' => 'string',
                        'example' => '0123456789'
                    ],

                    'representative_name' => [
                        'type' => 'string',
                        'example' => 'Nguyen Van A'
                    ],

                    'address' => [
                        'type' => 'string',
                        'example' => 'Da Nang'
                    ],

                    'documents' => [
                        'type' => 'array',

                        'items' => [
                            'type' => 'string',
                            'format' => 'binary'
                        ]
                    ]
                ],

                'required' => [
                    'company_name',
                    'tax_code'
                ]
            ];

        case '/seller-request/approve':
        case '/seller-request/reject':
            return [
                'type' => 'object',

                'properties' => [

                    'request_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ]
                ],

                'required' => [
                    'request_id'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | PRODUCT
        |--------------------------------------------------------------------------
        */

        case '/product/create':
            return [
                'type' => 'object',

                'properties' => [

                    'name' => [
                        'type' => 'string',
                        'example' => 'Toyota Camry'
                    ],

                    'brand' => [
                        'type' => 'string',
                        'example' => 'Toyota'
                    ],

                    'color' => [
                        'type' => 'string',
                        'example' => 'Black'
                    ],

                    'description' => [
                        'type' => 'string',
                        'example' => 'Xe mới 100%'
                    ],

                    'price_from' => [
                        'type' => 'number',
                        'example' => 500000000
                    ],

                    'years' => [
                        'type' => 'integer',
                        'example' => 2024
                    ],

                    'quantity' => [
                        'type' => 'integer',
                        'example' => 10
                    ],

                    'status' => [
                        'type' => 'string',

                        'enum' => [
                            'draft',
                            'active',
                            'inactive'
                        ],

                        'example' => 'active'
                    ],

                    'images' => [
                        'type' => 'array',

                        'items' => [
                            'type' => 'string',
                            'format' => 'binary'
                        ]
                    ]
                ],

                'required' => [
                    'name',
                    'price_from'
                ]
            ];

        case '/product/update':
            return [

                'type' => 'object',

                'properties' => [

                    'id' => [
                        'type' => 'integer',
                        'example' => 1
                    ],

                    'name' => [
                        'type' => 'string',
                        'example' => 'Toyota Camry'
                    ],

                    'brand' => [
                        'type' => 'string',
                        'example' => 'Toyota'
                    ],

                    'color' => [
                        'type' => 'string',
                        'example' => 'White'
                    ],

                    'status' => [

                        'type' => 'string',

                        'enum' => [
                            'draft',
                            'active',
                            'inactive',
                            'blocked',
                            'deleted'
                        ],

                        'example' => 'active'
                    ]
                ],

                'required' => [
                    'id'
                ]
            ];

        case '/product/delete':
            return [

                'type' => 'object',

                'properties' => [

                    'id' => [
                        'type' => 'integer',
                        'example' => 1
                    ]
                ],

                'required' => [
                    'id'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | RFQ
        |--------------------------------------------------------------------------
        */

        case '/rfq/create':
            return [

                'type' => 'object',

                'properties' => [

                    'buyer_company_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ],

                    'created_by' => [
                        'type' => 'integer',
                        'example' => 5
                    ],

                    'items' => [

                        'type' => 'array',

                        'items' => [

                            'type' => 'object',

                            'properties' => [

                                'product_id' => [
                                    'type' => 'integer',
                                    'example' => 10
                                ],

                                'quantity' => [
                                    'type' => 'integer',
                                    'example' => 50
                                ]
                            ]
                        ]
                    ]
                ],

                'required' => [
                    'buyer_company_id',
                    'created_by',
                    'items'
                ]
            ];

        case '/rfq/add-item':
            return [
                'type' => 'object',

                'properties' => [

                    'rfq_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ],

                    'product_id' => [
                        'type' => 'integer',
                        'example' => 5
                    ],

                    'quantity' => [
                        'type' => 'integer',
                        'example' => 100
                    ]
                ],

                'required' => [
                    'rfq_id',
                    'product_id',
                    'quantity'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | ORDER
        |--------------------------------------------------------------------------
        */

        case '/order/cancel':
            return [

                'type' => 'object',

                'properties' => [

                    'order_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ],

                    'reason' => [
                        'type' => 'string',
                        'example' => 'Khách yêu cầu hủy'
                    ]
                ],

                'required' => [
                    'order_id'
                ]
            ];

        case '/order/mark-delivering':
        case '/order/complete':
            return [
                'type' => 'object',

                'properties' => [

                    'order_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ]
                ],

                'required' => [
                    'order_id'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | PAYMENT
        |--------------------------------------------------------------------------
        */

        case '/payment/create':
            return [
                'type' => 'object',

                'properties' => [

                    'order_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ],

                    'amount' => [
                        'type' => 'number',
                        'example' => 5000000
                    ]
                ],

                'required' => [
                    'order_id',
                    'amount'
                ]
            ];

        case '/payment/pay':
        case '/payment/release':
            return [
                'type' => 'object',

                'properties' => [

                    'payment_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ]
                ],

                'required' => [
                    'payment_id'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | WALLET
        |--------------------------------------------------------------------------
        */

        case '/wallet/deposit':
        case '/wallet/withdraw':
            return [
                'type' => 'object',

                'properties' => [

                    'amount' => [
                        'type' => 'number',
                        'example' => 1000000
                    ]
                ],

                'required' => [
                    'amount'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | SUPPORT
        |--------------------------------------------------------------------------
        */

        case '/support/create-ticket':
            return [
                'type' => 'object',

                'properties' => [

                    'title' => [
                        'type' => 'string',
                        'example' => 'Lỗi thanh toán'
                    ],

                    'message' => [
                        'type' => 'string',
                        'example' => 'Không thể thanh toán'
                    ]
                ],

                'required' => [
                    'title',
                    'message'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | REVIEW
        |--------------------------------------------------------------------------
        */

        case '/review/create':
            return [

                'type' => 'object',

                'properties' => [

                    'order_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ],

                    'rating' => [
                        'type' => 'integer',
                        'example' => 5
                    ],

                    'comment' => [
                        'type' => 'string',
                        'example' => 'Sản phẩm rất tốt'
                    ]
                ],

                'required' => [
                    'order_id',
                    'rating'
                ]
            ];

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION
        |--------------------------------------------------------------------------
        */

        case '/notifications/mark-read':
            return [
                'type' => 'object',

                'properties' => [

                    'notification_id' => [
                        'type' => 'integer',
                        'example' => 1
                    ]
                ],

                'required' => [
                    'notification_id'
                ]
            ];


            

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        case '/admin/companies':
            return [
                'type' => 'object',
                'properties' => []
            ];

        /*
        |--------------------------------------------------------------------------
        | AI
        |--------------------------------------------------------------------------
        */

        case '/ai/ask':
            return [
                'type' => 'object',

                'properties' => [

                    'question' => [
                        'type' => 'string',
                        'example' => 'Sản phẩm nào bán chạy nhất?'
                    ]
                ],

                'required' => [
                    'question'
                ]
            ];
    }

    return [
        'type' => 'object',
        'properties' => []
    ];

}


            
