<?php

return [
    'login' => [
        'organization_code' => 'Organization code',
        'username' => 'Username',
        'password' => 'Password',
        'remember_me' => 'Remember me',
        'validation' => [
            'organization_code_required' => 'Please select an organization code.',
            'username_required' => 'Please enter your username.',
            'password_required' => 'Please enter your password.',
        ],
        'error' => [
            'invalid_credentials' => 'The username, organization code, or password is incorrect.',
            'activity_timeout' => 'You have been inactive for more than 15 minutes and were logged out automatically.',
        ],
    ],
];
