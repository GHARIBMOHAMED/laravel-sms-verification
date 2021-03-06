<?php

namespace Hizbul\SmsVerification;

use Illuminate\Support\Facades\Log;
use Hizbul\SmsVerification\Exceptions\SmsVerificationException;
use Hizbul\SmsVerification\Exceptions\ValidationException;

/**
 * Class SmsVerification
 * @package Hizbul\SmsVerification
 */
class SmsVerification
{

    /**
     * Send code
     * @param $phoneNumber
     * @return array
     */
    public static function sendCode($phoneNumber)
    {
        $exceptionClass = null;
        $expiresAt = null;
        $response = [];
        try {
            static::validatePhoneNumber($phoneNumber);
            $now = time();
            $code = CodeProcessor::getInstance()->generateCode($phoneNumber);
            $translationCode = config('sms-verification.message-translation-code');
            $text = $translationCode
                ? trans($translationCode, ['code' => $code])
                : 'SMS verification code: ' . $code;
            $senderClassName = config('sms-verification.sender-class', Sender::class);
            $sender = $senderClassName::getInstance();
            if (!($sender instanceof SenderInterface)){
                throw new \Exception('Sender class ' . $senderClassName . ' doesn\'t implement SenderInterface');
            }
            $success = $sender->send($phoneNumber, $text);
            $description = $success ? 'OK' : 'Error';
            if ($success){
                $response['expires_at'] = $now + CodeProcessor::getInstance()->getLifetime();
            }
        } catch (\Exception $e) {
            $description = $e->getMessage();
            if (!($e instanceof ValidationException)) {
                Log::error('SMS Verification code sending was failed: ' . $description);
            }
            $success = false;
            $response['error'] = ($e instanceof SmsVerificationException) ? $e->getErrorCode() : 999;
        }
        $response['success'] = $success;
        $response['description'] = $description;
        return $response;
    }

    /**
     * Check code
     * @param $code
     * @param $phoneNumber
     * @return array
     */
    public static function checkCode($code, $phoneNumber)
    {
        $exceptionClass = null;
        $response = [];
        try {
            if (!is_numeric($code)){
                throw new ValidationException('Incorrect code was provided');
            }
            static::validatePhoneNumber($phoneNumber);
            $success = CodeProcessor::getInstance()->validateCode($code, $phoneNumber);
            $description = $success ? 'OK' : 'Wrong code';
        } catch (\Exception $e) {
            $description = $e->getMessage();
            if (!($e instanceof ValidationException)) {
                Log::error('SMS Verification check was failed: ' . $description);
            }
            $success = false;
            $response['error'] = ($e instanceof SmsVerificationException) ? $e->getErrorCode() : 999;
        }
        $response['success'] = $success;
        $response['description'] = $description;
        return $response;
    }

    /**
     * Validate phone number
     * @param string $phoneNumber
     * @throws ValidationException
     */
    protected static function validatePhoneNumber($phoneNumber){
        $patterns = [
            "\?:\+88|01)?(?:\d{11}|\d{13}", // BD
            "\+?[2-9]\d{9,}", // International
        ];
        if (!@preg_match("/^(" . implode('|', $patterns) . ")\$/", $phoneNumber)) {
            throw new ValidationException('Incorrect phone number was provided');
        }
    }

}
