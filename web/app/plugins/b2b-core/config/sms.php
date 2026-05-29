<?php

return [
    'access_token' => getenv('SMS_TOKEN'),
    'type' => 5, 
    'sender' =>  getenv('SMS_SENDER')
];
