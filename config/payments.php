<?php

/**
 * Payment gateway (Omise + bank transfer). ราคาแพ็กเกจมาจาก subscription_plans ไม่ใช่คีย์ในไฟล์นี้
 *
 * Env: OMISE_PUBLIC_KEY, OMISE_SECRET_KEY
 */

$config = [
    'omise_public_key' => '',
    'omise_secret_key' => '',
    /** @deprecated ใช้ bank_accounts แทน — คีย์นี้ยังส่งเป็นบัญชีแรกเพื่อความเข้ากันได้ */
    'bank_name' => 'ธนาคารกสิกรไทย',
    'bank_account_name' => 'ลิษา มณีโชคอมต',
    'bank_account_number' => '1211933336',
    'transfer_reference_note' => 'ระบุอีเมลที่ใช้สมัครในช่องหมายเหตุการโอน',
    /**
     * บัญชีรับโอนสมัครแพ็กเกจเสียเงิน (แสดงทุกบัญชีในหน้าสมัคร)
     * logo = path ภายใต้โฟลเดอร์โปรเจกต์ (เช่น assets/banks/kbank.svg)
     */
    'bank_accounts' => [
        [
            'code' => 'kbank',
            'name_th' => 'ธนาคารกสิกรไทย',
            'account_number' => '1211933336',
            'logo' => 'assets/banks/kbank.svg',
            'type' => 'bank',
        ],
        [
            'code' => 'krungsri',
            'name_th' => 'ธนาคารกรุงศรีอยุธยา จำกัด (มหาชน)',
            'account_number' => '777 1 699966',
            'logo' => 'assets/banks/krungsri.svg',
            'type' => 'bank',
        ],
        [
            'code' => 'krungthai',
            'name_th' => 'ธนาคารกรุงไทย จำกัด (มหาชน)',
            'account_number' => '661 9 44166 1',
            'logo' => 'assets/banks/krungthai.svg',
            'type' => 'bank',
        ],
        [
            'code' => 'promptpay',
            'name_th' => 'พร้อมเพย์ (PromptPay)',
            'account_number' => '0926516969',
            'logo' => 'assets/banks/promptpay.png',
            'type' => 'promptpay',
        ],
    ],
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

return $config;
