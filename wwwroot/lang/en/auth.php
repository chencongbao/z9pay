<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'merchant_login' => [
        'too_many_attempts' => 'Too many failed login attempts. Please try again later.',
        'captcha_failed' => 'Security verification failed. Please try again.',
        'input_merchant_code' => 'Please enter merchant code.',
        'input_username' => 'Please enter username.',
        'input_password' => 'Please enter password.',
        'input_google_code' => 'Please enter Google verification code.',
        'auth_failed' => 'Incorrect account or password.',
        'merchant_code_error' => 'Incorrect merchant code.',
        'login_risk_need_white_ip' => 'Abnormal login risk detected. Please log in from a whitelisted IP or contact the administrator.',
        'need_white_ip_before_google_bind' => 'Please contact the administrator to set a login IP whitelist before binding Google Authenticator.',
        'google_code_error' => 'The verification code is incorrect.',
        'ip_not_in_white_list' => 'Login IP is not in the whitelist.',
        'system_error_retry' => 'System error. Please try again later.',
    ],

    'agent_login' => [
        'too_many_attempts' => 'Too many failed login attempts. Please try again later.',
        'captcha_failed' => 'Security verification failed. Please try again.',
        'invalid_captcha_type' => 'Invalid captcha type.',
        'input_username' => 'Please enter username.',
        'input_password' => 'Please enter password.',
        'input_google_code' => 'Please enter Google verification code.',
        'input_six_digit_google_code' => 'Please enter a 6-digit Google verification code.',
        'auth_failed' => 'Incorrect account or password.',
        'login_failed' => 'Login failed.',
        'login_risk_need_white_ip' => 'Abnormal login risk detected. Please log in from a whitelisted IP or contact the administrator.',
        'need_white_ip_before_google_bind' => 'Please contact the administrator to set a login IP whitelist before binding Google Authenticator.',
        'google_code_error' => 'The verification code is incorrect.',
        'google_verify' => 'Google verification',
        'confirm_google_verify' => 'Verify',
        'google_verify_failed' => 'Google verification failed.',
        'verify_session_expired' => 'The verification session has expired. Please log in again.',
        'ip_not_in_white_list' => 'Login IP is not in the whitelist.',
        'system_error_retry' => 'System error. Please try again later.',
    ],

];
