<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class BabelWhatsAppService
{
    public function send(string $mobile, string $message, string $country = 'EG'): bool
    {
        $url = config('services.babel_whatsapp.url');
        $token = config('services.babel_whatsapp.token');
        $instance = config('services.babel_whatsapp.instance');

        if (empty($url) || empty($token)) {
            Log::warning('Babel WhatsApp notification skipped: API configuration is missing.');

            return false;
        }

        $number = $this->normalizeNumber($mobile, $country);
        if ($number === null) {
            Log::warning('Babel WhatsApp notification skipped: invalid mobile number.', [
                'mobile' => $mobile,
            ]);

            return false;
        }

        try {
            $request = Http::asJson()
                ->acceptJson()
                ->withHeaders(['X-API-Key' => $token])
                ->timeout((int) config('services.babel_whatsapp.timeout', 10))
                ->connectTimeout(5);

            if (! config('services.babel_whatsapp.verify_ssl', true)) {
                $request = $request->withoutVerifying();
            }

            $payload = [
                'number' => $number,
                'text' => $message,
            ];

            // instance_id لازم يتبعت صراحةً في الـ payload — من غير ده، الـ
            // Gateway بيستخدم القيمة الافتراضية (babel_notifications) بغض
            // النظر عن اللي متظبط في BABEL_WHATSAPP_INSTANCE، وهي كانت
            // بتتقرا فوق للّوج بس من غير ما توصل فعليًا للطلب نفسه.
            if (! empty($instance)) {
                $payload['instance_id'] = $instance;
            }

            $response = $request->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('Babel WhatsApp notification failed.', [
                    'number' => $number,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'instance' => $instance,
                ]);

                return false;
            }

            // Kept at warning level so it is visible on production servers while
            // the gateway delivery response is being verified.
            Log::warning('Babel WhatsApp notification accepted.', [
                'number' => $number,
                'status' => $response->status(),
                'response' => $response->body(),
                'instance' => $instance,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Babel WhatsApp notification request failed.', [
                'number' => $number,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function normalizeNumber(string $mobile, string $country = 'EG'): ?string
    {
        try {
            $phone_util = PhoneNumberUtil::getInstance();
            $phone_number = $phone_util->parse(trim($mobile), strtoupper($country));

            if (! $phone_util->isValidNumber($phone_number)) {
                return null;
            }

            return ltrim($phone_util->format($phone_number, PhoneNumberFormat::E164), '+');
        } catch (NumberParseException $exception) {
            return null;
        }
    }
}
