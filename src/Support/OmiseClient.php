<?php

namespace App\Support;

/**
 * Minimal Omise Charges API (tokenized card). https://www.omise.co/charges-api
 */
class OmiseClient
{
    /**
     * @return array{ok: bool, message?: string, charge_id?: string|null, raw?: mixed}
     */
    public function createCharge(string $secretKey, int $amountSatang, string $cardToken, array $metadata = []): array
    {
        $fields = [
            'amount' => $amountSatang,
            'currency' => 'thb',
            'card' => $cardToken,
        ];
        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $fields['metadata[' . $key . ']'] = (string) $value;
        }

        $ch = curl_init('https://api.omise.co/charges');
        if ($ch === false) {
            return ['ok' => false, 'message' => 'ไม่สามารถเชื่อมต่อ Omise ได้'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_USERPWD => $secretKey . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($raw ?: 'null', true);
        if (!is_array($json)) {
            return ['ok' => false, 'message' => 'คำตอบจาก Omise ไม่ถูกต้อง'];
        }

        if ($httpCode >= 400) {
            $msg = $json['message'] ?? $json['failure_message'] ?? 'การชำระเงินไม่สำเร็จ';

            return ['ok' => false, 'message' => is_string($msg) ? $msg : 'การชำระเงินไม่สำเร็จ', 'raw' => $json];
        }

        if (!empty($json['failure_code'])) {
            $msg = $json['failure_message'] ?? $json['message'] ?? 'การชำระเงินไม่สำเร็จ';

            return ['ok' => false, 'message' => is_string($msg) ? $msg : 'การชำระเงินไม่สำเร็จ', 'raw' => $json];
        }

        $paid = !empty($json['paid']) || ($json['status'] ?? '') === 'successful';
        if (!$paid) {
            return [
                'ok' => false,
                'message' => 'รายการชำระเงินยังไม่สำเร็จ กรุณาลองใหม่หรือใช้วิธีโอนเงิน',
                'raw' => $json,
            ];
        }

        return [
            'ok' => true,
            'charge_id' => isset($json['id']) ? (string) $json['id'] : null,
            'raw' => $json,
        ];
    }
}
