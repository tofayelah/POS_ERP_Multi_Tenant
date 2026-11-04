<?php

namespace App\Core\Notifications\Services;

use App\Core\Notifications\Contracts\SmsProviderInterface;
use App\Core\Notifications\DTOs\SmsSendResult;
use App\Core\Notifications\Exceptions\SmsDispatchFailedException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsGatewayManager
{
    public function __construct(
        protected SmsProviderInterface $primaryProvider,
        protected SmsProviderInterface $secondaryProvider,
        protected OtpRateLimiter $rateLimiter
    ) {}

    /**
     * Dispatches an SMS message with automatic failover from primary to secondary provider.
     *
     * @throws SmsDispatchFailedException
     */
    public function send(string $to, string $message): SmsSendResult
    {
        $primaryError = null;

        // 1. Attempt dispatch via Primary Provider
        try {
            $result = $this->primaryProvider->send($to, $message);
            if ($result->success) {
                return $result;
            }
            $primaryError = $result->errorMessage ?? 'Primary provider returned non-success';
        } catch (Throwable $e) {
            $primaryError = $e->getMessage();
            Log::channel('daily')->warning("Primary SMS provider failed: {$primaryError}. Triggering failover to secondary provider.", [
                'recipient' => $to,
            ]);
        }

        // 2. Failover: Attempt dispatch via Secondary Provider
        try {
            $result = $this->secondaryProvider->send($to, $message);
            if ($result->success) {
                return $result;
            }
            $secondaryError = $result->errorMessage ?? 'Secondary provider returned non-success';
        } catch (Throwable $e) {
            $secondaryError = $e->getMessage();
        }

        Log::channel('daily')->error("All SMS providers failed to dispatch message to {$to}.", [
            'primary_error' => $primaryError,
            'secondary_error' => $secondaryError,
        ]);

        throw new SmsDispatchFailedException(
            "All SMS providers failed to dispatch message to {$to}. Primary: {$primaryError}; Secondary: {$secondaryError}"
        );
    }

    /**
     * Sends a time-limited OTP with strict rate limiting and automated failover.
     */
    public function sendOtp(string $tenantId, string $to, string $otpCode): SmsSendResult
    {
        // 1. Assert rate limits before dispatch attempt
        $this->rateLimiter->assertCanSendOtp($tenantId, $to);

        $message = "Your verification code is {$otpCode}. Valid for 5 minutes.";
        $result = $this->send($to, $message);

        // 2. Record successful dispatch
        $this->rateLimiter->recordOtpDispatch($tenantId, $to);

        return $result;
    }
}
