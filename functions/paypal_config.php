<?php
// PayPal settings. Get the Client ID and Secret from
// https://developer.paypal.com/dashboard  ->  Apps & Credentials  ->  (Sandbox)  ->  your app.
//
// KEEP THIS FILE PRIVATE: never share the secret or upload it anywhere public.

return [
    'mode'      => 'sandbox',                    // 'sandbox' = fake money for testing, 'live' = real money
    'client_id' => 'AUkRG5c_tEnvp1v5afFVbwad1drsLALaV9Zax69LSBTCHnBdHLy_cb_ef1uVJZj5KaV2_rx1z4_2ImWy',
    'secret'    => 'EDrTxcBc3fSoAGLkREKwFIOIjeDoXFPOCBb7ENcEfRe_r8_mHdFSMsgxXi4XEm7ISfd4Zffv8l11rvOD',
    'currency'  => 'PHP',                        // if PayPal rejects PHP for your account, try 'USD'
];
