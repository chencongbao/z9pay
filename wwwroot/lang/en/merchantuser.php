<?php
return [
    'labels' => [
        'users' => "Account Management",
        'User' => "Account Management"
    ],
    "fields" => [
        "merchant_coder" => "Merchant Code",
        "please_select_role" => "Please select role",
        "login_white_ip_validate" => "Separate multiple IPs with commas or new lines. Supports single IP or CIDR, e.g. 1.1.1.1, 1.1.1.0/24",
        "login_white_ip_invalid" => "Login IP whitelist contains an invalid IP: :ip",
        "username_required" => "Please enter the sub-account username",
        "username_unique" => "The sub-account username already exists",
        "password_required" => "Please enter the sub-account login password",
        "password_min" => "The sub-account login password must be at least 5 characters",
        "password_max" => "The sub-account login password may not be greater than 20 characters",
        "password_confirm_same" => "The two login passwords do not match",
        "name_required" => "Please enter the sub-account name",
        "role_required" => "Please select the sub-account role",
        "role_invalid" => "Please select a role available to the current merchant",
        "status_required" => "Please select the sub-account status"
    ]
];
