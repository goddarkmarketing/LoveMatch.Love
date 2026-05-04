<?php

/**
 * Registration payment (Omise + bank transfer). Secrets via env or payments.local.php (gitignored).
 *
 * Env: OMISE_PUBLIC_KEY, OMISE_SECRET_KEY, REGISTRATION_FEE_THB
 */

$config = [
    'registration_fee_thb' => 199.0,
    'omise_public_key' => '',
    'omise_secret_key' => '',
    'bank_name' => 'ธนาคารกสิกรไทย',
    'bank_account_name' => 'บจก. ไทยเลิฟแมตช์',
    'bank_account_number' => '000-0-00000-0',
    'transfer_reference_note' => 'ระบุอีเมลที่ใช้สมัครในช่องหมายเหตุการโอน',
];

$local = __DIR__ . '/payments.local.php';
if (is_file($local)) {
    $merge = require $local;
    if (is_array($merge)) {
        $config = array_merge($config, $merge);
    }
}

$envStr = static function (string $key): ?string {
    $v = getenv($key);
    if ($v === false) {
        return null;
    }
    $v = trim((string) $v);
    return $v === '' ? null : $v;
};

if (($v = $envStr('OMISE_PUBLIC_KEY')) !== null) {
    $config['omise_public_key'] = $v;
}
if (($v = $envStr('OMISE_SECRET_KEY')) !== null) {
    $config['omise_secret_key'] = $v;
}
if (($v = $envStr('REGISTRATION_FEE_THB')) !== null) {
    $config['registration_fee_thb'] = (float) $v;
}

return $config;
