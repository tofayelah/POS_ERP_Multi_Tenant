<?php

namespace App\Core\Notifications\Services;

use App\Core\Notifications\Exceptions\OtpRateLimitExceededException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class OtpRateLimiter
{
    protected const COOLDOWN_SECONDS = 60;

    protected const WINDOW_SECONDS = 600; // 10 minutes

    protected const MAX_ATTEMPTS = 3;

    /**
     * Asserts that an OTP can be dispatched to the recipient under current rate limit policies.
     *
     * @throws OtpRateLimitExceededException
     */
    public function assertCanSendOtp(string $tenantId, string $phoneNumber): void
    {
        $cooldownKey = "otp_cd:{$tenantId}:{$phoneNumber}";
        if (Cache::has($cooldownKey)) {
            throw new OtpRateLimitExceededException(
                'Please wait at least 60 seconds before requesting another OTP.'
            );
        }

        $historyKey = "otp_hist:{$tenantId}:{$phoneNumber}";
        /** @var array<int, int> $history */
        $history = Cache::get($historyKey, []);

        $cutoff = Carbon::now()->getTimestamp() - self::WINDOW_SECONDS;
        $recentAttempts = array_filter($history, fn (int $ts) => $ts > $cutoff);

        if (count($recentAttempts) >= self::MAX_ATTEMPTS) {
            throw new OtpRateLimitExceededException(
                'Maximum 3 OTP requests allowed within 10 minutes. Please try again later.'
            );
        }
    }

    /**
     * Records a successful OTP dispatch event in the rate limiter cache.
     */
    public function recordOtpDispatch(string $tenantId, string $phoneNumber): void
    {
        $cooldownKey = "otp_cd:{$tenantId}:{$phoneNumber}";
        Cache::put($cooldownKey, true, self::COOLDOWN_SECONDS);

        $historyKey = "otp_hist:{$tenantId}:{$phoneNumber}";
        /** @var array<int, int> $history */
        $history = Cache::get($historyKey, []);

        $cutoff = Carbon::now()->getTimestamp() - self::WINDOW_SECONDS;
        $history = array_filter($history, fn (int $ts) => $ts > $cutoff);
        $history[] = Carbon::now()->getTimestamp();

        Cache::put($historyKey, array_values($history), self::WINDOW_SECONDS);
    }
}
