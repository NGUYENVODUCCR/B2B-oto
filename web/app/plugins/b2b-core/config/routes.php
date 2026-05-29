<?php

return [

    'auth' => [

        [
            'method' => 'POST',
            'route'  => '/auth/register',
            'action' => ['AuthController', 'register'],
            'public' => true
        ],

        [
            'method' => 'POST',
            'route'  => '/auth/verify',
            'action' => ['AuthController', 'verify'],
            'public' => true
        ],

        [
            'method' => 'POST',
            'route'  => '/auth/login',
            'action' => ['AuthController', 'login'],
            'public' => true
        ],

        [
            'method' => 'POST',
            'route'  => '/auth/refresh',
            'action' => ['AuthController', 'refresh'],
            'public' => true
        ],

        [
            'method' => 'POST',
            'route'  => '/auth/logout',
            'action' => ['AuthController', 'logout']
        ],

        [
            'method' => 'POST',
            'route'  => '/auth/forgot-password',
            'action' => ['AuthController', 'forgotPassword'],
            'public' => true
        ],

        [
            'method' => 'POST',
            'route'  => '/auth/reset-password',
            'action' => ['AuthController', 'resetPassword'],
            'public' => true
        ]
    ],

    'admin' => [
        [
            'method' => 'GET',
            'route'  => '/admin/users',
            'action' => ['AdminController', 'getUsers']
        ],
        [
            'method' => 'POST',
            'route'  => '/admin/users',
            'action' => ['AdminController', 'createUser'] 
        ],
        [
            'method' => ['PUT', 'POST'],
            'route'  => '/admin/users/update', 
            'action' => ['AdminController', 'updateUser']
        ],
        [
            'method' => 'DELETE',
            'route'  => '/admin/users',
            'action' => ['AdminController', 'deleteUser']
        ],

        [
            'method' => 'POST',
            'route'  => '/admin/users/toggle-status',
            'action' => ['AdminController', 'toggleUserStatus']
        ],
        [
            'method' => 'GET',
            'route'  => '/admin/companies',
            'action' => ['AdminController', 'getCompanies']
        ],
        
        [
            'method' => 'POST',
            'route'  => '/admin/support/send',
            'action' => ['AdminController', 'sendToSupport']
        ],
        [
            'method' => 'GET',
            'route'  => '/admin/support/list',
            'action' => ['AdminController', 'getSupportTeamList']
        ],
        [
            'method' => 'GET',
            'route'  => '/admin/support/messages',
            'action' => ['AdminController', 'getSupportMessages']
        ],


        [
            'method' => 'GET',
            'route'  => '/admin/chat/admin/messages',
            'action' => ['AdminController', 'getAdminMessages']
        ],

        [
            'method' => 'POST',
            'route'  => '/admin/chat/admin/send',
            'action' => ['AdminController', 'sendToAdmin']
        ],
        [
            'method' => 'GET',
            'route'  => '/admin/companies',
            'action' => ['AdminController', 'getCompanies']
        ],
    ],
    
    'user' => [

        [
            'method' => 'GET',
            'route'  => '/user/profile',
            'action' => ['UserController', 'me']
        ],

        [
            'method' => 'GET',
            'route'  => '/user/capabilities',
            'action' => ['UserController', 'capabilities']
        ],

        [
            'method' => ['PUT', 'POST'],
            'route'  => '/user/update',
            'action' => ['UserController', 'updateProfile']
        ]
    ],


    'company' => [

        [
            'method' => 'POST',
            'route'  => '/company/create',
            'action' => ['UserController', 'createCompany']
        ],

        [
            'method' => 'POST',
            'route'  => '/company/join',
            'action' => ['UserController', 'joinCompany']
        ]
    ],


    'seller_request' => [

        [
            'method' => 'POST',
            'route'  => '/seller-request/create',
            'action' => ['SellerRequestController', 'create']
        ],

        [
            'method' => 'POST',
            'route'  => '/seller-request/approve',
            'action' => ['SellerRequestController', 'approve']
        ],

        [
            'method' => 'POST',
            'route'  => '/seller-request/reject',
            'action' => ['SellerRequestController', 'reject']
        ],

        [
            'method' => 'GET',
            'route'  => '/seller-request/my-request',
            'action' => ['SellerRequestController', 'myRequest']
        ],

        [
            'method' => 'GET',
            'route'  => '/seller-request/list',
            'action' => ['SellerRequestController', 'list']
        ]
    ],

    'product' => [

        [
            'method' => 'POST',
            'route'  => '/product/create',
            'action' => ['ProductController', 'create']
        ],

        [
            'method' => ['PUT', 'POST'],
            'route'  => '/product/update',
            'action' => ['ProductController', 'update']
        ],

        [
            'method' => 'DELETE',
            'route'  => '/product/delete',
            'action' => ['ProductController', 'delete']
        ],

        [
            'method' => 'GET',
            'route'  => '/product/detail',
            'action' => ['ProductController', 'detail']
        ],

        [
            'method' => 'GET',
            'route'  => '/product/my-products',
            'action' => ['ProductController', 'myProducts']
        ],

        [
            'method' => 'GET',
            'route'  => '/product/list',
            'action' => ['ProductController', 'list'],
            'public' => true
        ],

        [
            'method' => 'GET',
            'route'  => '/products/search',
            'action' => ['ProductController', 'search']
        ],

        [
            'method' => 'GET',
            'route'  => '/products/filter',
            'action' => ['ProductController', 'filter'],
            'public' => true
        ]
    ],


    'rfq' => [

        [
            'method' => 'POST',
            'route'  => '/rfq/create',
            'action' => ['RFQController', 'create']
        ],

        [
            'method' => 'POST',
            'route'  => '/rfq/add-item',
            'action' => ['RFQController', 'addItem']
        ],

        [
            'method' => 'POST',
            'route'  => '/rfq/send',
            'action' => ['RFQController', 'send']
        ],

        [
            'method' => 'GET',
            'route'  => '/rfq/list',
            'action' => ['RFQController', 'list']
        ],

        [
            'method' => 'GET',
            'route'  => '/rfq/detail',
            'action' => ['RFQController', 'detail']
        ],

        [
            'method' => 'POST',
            'route'  => '/rfq/negotiation/send',
            'action' => ['RFQController', 'sendNegotiation']
        ],

        [
            'method' => 'GET',
            'route'  => '/rfq/negotiation/list',
            'action' => ['RFQController', 'negotiationList']
        ]
    ],

    'chat' => [

        [
            'method' => 'GET',
            'route'  => '/chat/conversations',
            'action' => ['ChatController', 'conversations']
        ],

        [
            'method' => 'GET',
            'route'  => '/chat/detail',
            'action' => ['ChatController', 'detail']
        ],

        [
            'method' => 'GET',
            'route'  => '/chat/messages',
            'action' => ['ChatController', 'messages']
        ],

        [
            'method' => 'GET',
            'route'  => '/chat/thread',
            'action' => ['ChatController', 'thread']
        ],

        [
            'method' => 'POST',
            'route'  => '/chat/send-message',
            'action' => ['ChatController', 'sendMessage']
        ]
    ],

    'quotation' => [

        [
            'method' => 'POST',
            'route'  => '/quotation/submit',
            'action' => ['QuotationController', 'submit']
        ],

        [
            'method' => 'POST',
            'route'  => '/quotation/update',
            'action' => ['QuotationController', 'update']
        ],

        [
            'method' => 'POST',
            'route'  => '/quotation/accept',
            'action' => ['QuotationController', 'accept']
        ],

        [
            'method' => 'POST',
            'route'  => '/quotation/reject',
            'action' => ['QuotationController', 'reject']
        ],

        [
            'method' => 'GET',
            'route'  => '/quotation/by-rfq',
            'action' => ['QuotationController', 'byRFQ']
        ]
    ],

    'contract' => [

        [
            'method' => 'POST',
            'route'  => '/contract/create-from-quotation',
            'action' => ['ContractController', 'createFromQuotation']
        ],

        [
            'method' => 'GET',
            'route'  => '/contract/by-quotation',
            'action' => ['ContractController', 'byQuotation']
        ],

        [
            'method' => 'POST',
            'route'  => '/contract/sign',
            'action' => ['ContractController', 'sign']
        ],

        [
            'method' => 'POST',
            'route'  => '/contract/cancel',
            'action' => ['ContractController', 'cancel']
        ],

        [
            'method' => 'GET',
            'route'  => '/contract/detail',
            'action' => ['ContractController', 'detail']
        ]
    ],

    'order' => [

        [
            'method' => 'POST',
            'route'  => '/order/create-from-contract',
            'action' => ['OrderController', 'createFromContract']
        ],

        [
            'method' => 'GET',
            'route'  => '/order/detail',
            'action' => ['OrderController', 'detail']
        ],

        [
            'method' => 'GET',
            'route'  => '/order/list',
            'action' => ['OrderController', 'list']
        ],

        [
            'method' => 'GET',
            'route'  => '/order/seller-history',
            'action' => ['OrderController', 'sellerHistory']
        ],

        [
            'method' => 'POST',
            'route'  => '/order/cancel',
            'action' => ['OrderController', 'cancel']
        ],

        [
            'method' => 'POST',
            'route'  => '/order/mark-delivering',
            'action' => ['OrderController', 'markDelivering']
        ],

        [
            'method' => 'POST',
            'route'  => '/order/complete',
            'action' => ['OrderController', 'complete']
        ]
    ],

    'payment' => [

        [
            'method' => 'POST',
            'route'  => '/payment/create',
            'action' => ['PaymentController', 'create']
        ],

        [
            'method' => 'POST',
            'route'  => '/payment/pay',
            'action' => ['PaymentController', 'pay']
        ],

        [
            'method' => 'POST',
            'route'  => '/payment/release',
            'action' => ['PaymentController', 'release']
        ],

        [
            'method' => 'GET',
            'route'  => '/payment/by-order',
            'action' => ['PaymentController', 'byOrder']
        ],

        [
            'method' => 'GET',
            'route'  => '/payment/pending-releases',
            'action' => ['PaymentController', 'pendingReleases']
        ],

        [
            'method' => 'POST',
            'route'  => '/payment/support-settle',
            'action' => ['PaymentController', 'supportSettle']
        ],
        [
            'method' => 'POST',
            'route'  => '/payment/manual-checkout',
            'action' => ['PaymentController', 'manualCheckout']
        ],
        [
            'method' => 'POST',
            'route'  => '/payment/manual-confirm',
            'action' => ['PaymentController', 'manualConfirm']
        ],
    ],

    'wallet' => [

        [
            'method' => 'GET',
            'route'  => '/wallet/balance',
            'action' => ['WalletController', 'balance']
        ],

        [
            'method' => 'POST',
            'route'  => '/wallet/deposit',
            'action' => ['WalletController', 'deposit']
        ],

        [
            'method' => 'POST',
            'route'  => '/wallet/withdraw',
            'action' => ['WalletController', 'withdraw']
        ],

        [
            'method' => 'POST',
            'route'  => '/wallet/casso-webhook',
            'action' => ['WalletController', 'cassoWebhook'],
            'public' => true
        ]
    ],


    'support' => [

        [
            'method' => 'POST',
            'route'  => '/support/create-ticket',
            'action' => ['SupportController', 'createTicket']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/send-message',
            'action' => ['SupportController', 'sendMessage']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/close',
            'action' => ['SupportController', 'close']
        ],

        [
            'method' => 'GET',
            'route'  => '/support/list',
            'action' => ['SupportController', 'list']
        ],

        [
            'method' => 'GET',
            'route'  => '/support/detail',
            'action' => ['SupportController', 'detail']
        ],

        [
            'method' => 'GET',
            'route'  => '/support/ticket-context',
            'action' => ['SupportController', 'ticketContext']
        ],

        [
            'method' => 'GET',
            'route'  => '/support/agents',
            'action' => ['SupportController', 'supportAgents']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/start',
            'action' => ['SupportController', 'startBulkPurchase']
        ],

        [
            'method' => 'GET',
            'route'  => '/support/bulk/list',
            'action' => ['SupportController', 'bulkPurchases']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/request-form',
            'action' => ['SupportController', 'requestBulkForm']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/buyer-form',
            'action' => ['SupportController', 'submitBulkProductForm']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/send-rfq',
            'action' => ['SupportController', 'sendBulkRFQ']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/join',
            'action' => ['SupportController', 'joinBulkRFQ']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/send-buyer',
            'action' => ['SupportController', 'sendBulkToBuyer']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/accept',
            'action' => ['SupportController', 'acceptBulkQuotation']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/bulk/message',
            'action' => ['SupportController', 'sendBulkMessage']
        ],

        [
            'method' => 'GET',
            'route'  => '/support/seller-channel',
            'action' => ['SupportController', 'sellerChannel']
        ],

        [
            'method' => 'POST',
            'route'  => '/support/seller-channel/message',
            'action' => ['SupportController', 'sendSellerChannelMessage']
        ]
    ],


    'review' => [

        [
            'method' => 'POST',
            'route'  => '/review/create',
            'action' => ['ReviewController', 'create']
        ],

        [
            'method' => 'GET',
            'route'  => '/review/by-order',
            'action' => ['ReviewController', 'byOrder']
        ]
    ],

    'statistics' => [
        [
            'method' => 'GET',
            'route'  => '/statistics/revenue',
            'action' => ['StatisticsController', 'revenue']
        ],
        [
            'method' => 'GET',
            'route'  => '/statistics/product-review-counts',
            'action' => ['StatisticsController', 'productReviewCounts'],
            'public' => true
        ]
    ],
    
    'ai' => [
        [
            'method' => 'POST',
            'route'  => '/ai/ask',
            'action' => ['AIController', 'ask'],
            'public' => true
        ]
    ],
    'notification' => [
        [
            'method' => 'GET',
            'route'  => '/notifications',
            'action' => ['NotificationController', 'items']
        ],
        [
            'method' => 'GET',
            'route'  => '/notifications/unread-count',
            'action' => ['NotificationController', 'unreadCount']
        ],
        [
            'method' => 'POST',
            'route'  => '/notifications/mark-read',
            'action' => ['NotificationController', 'markRead']
        ],
        [
            'method' => 'POST',
            'route'  => '/notifications/mark-all-read',
            'action' => ['NotificationController', 'markAllRead']
        ]
    ],
];
