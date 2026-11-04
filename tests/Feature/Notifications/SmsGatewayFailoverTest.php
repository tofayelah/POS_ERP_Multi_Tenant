<?php

namespace Tests\Feature\Notifications;

use App\Core\Notifications\Contracts\SmsProviderInterface;
use App\Core\Notifications\DTOs\SmsSendResult;
use App\Core\Notifications\Exceptions\OtpRateLimitExceededException;
use App\Core\Notifications\Exceptions\SmsDispatchFailedException;
use App\Core\Notifications\Services\OtpRateLimiter;
use App\Core\Notifications\Services\SmsGatewayManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmsGatewayFailoverTest extends TestCase
{
    protected string $tenantId;

    protected string $phoneNumber = '+8801711000000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (string) Str::uuid();
        Cache::flush();
    }

    public function test_sms_gateway_delivers_via_primary_provider_when_healthy(): void
    {
        $primary = $this->createMock(SmsProviderInterface::class);
        $secondary = $this->createMock(SmsProviderInterface::class);

        $primary->expects($this->once())
            ->method('send')
            ->with($this->phoneNumber, 'Test message')
            ->willReturn(new SmsSendResult(success: true, provider: 'primary_sslwireless', messageId: 'MSG-001'));

        $secondary->expects($this->never())->method('send');

        $manager = new SmsGatewayManager($primary, $secondary, app(OtpRateLimiter::class));
        $result = $manager->send($this->phoneNumber, 'Test message');

        $this->assertTrue($result->success);
        $this->assertEquals('primary_sslwireless', $result->provider);
        $this->assertEquals('MSG-001', $result->messageId);
    }

    public function test_sms_gateway_fails_over_to_secondary_provider_when_primary_fails(): void
    {
        $primary = $this->createMock(SmsProviderInterface::class);
        $secondary = $this->createMock(SmsProviderInterface::class);

        $primary->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Connection timed out to primary gateway'));

        $secondary->expects($this->once())
            ->method('send')
            ->with($this->phoneNumber, 'Failover test')
            ->willReturn(new SmsSendResult(success: true, provider: 'secondary_onnorokom', messageId: 'MSG-SEC-002'));

        $manager = new SmsGatewayManager($primary, $secondary, app(OtpRateLimiter::class));
        $result = $manager->send($this->phoneNumber, 'Failover test');

        $this->assertTrue($result->success);
        $this->assertEquals('secondary_onnorokom', $result->provider);
        $this->assertEquals('MSG-SEC-002', $result->messageId);
    }

    public function test_sms_gateway_throws_when_both_providers_fail(): void
    {
        $primary = $this->createMock(SmsProviderInterface::class);
        $secondary = $this->createMock(SmsProviderInterface::class);

        $primary->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Primary 500 error'));

        $secondary->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Secondary out of credits'));

        $manager = new SmsGatewayManager($primary, $secondary, app(OtpRateLimiter::class));

        $this->expectException(SmsDispatchFailedException::class);
        $this->expectExceptionMessageMatches('/All SMS providers failed/');

        $manager->send($this->phoneNumber, 'Crash test');
    }

    public function test_otp_rate_limiter_enforces_60s_cooldown_between_consecutive_requests(): void
    {
        $limiter = app(OtpRateLimiter::class);

        // First attempt allowed
        $limiter->assertCanSendOtp($this->tenantId, $this->phoneNumber);
        $limiter->recordOtpDispatch($this->tenantId, $this->phoneNumber);

        // Immediate subsequent request within 60 seconds is blocked
        $this->expectException(OtpRateLimitExceededException::class);
        $this->expectExceptionMessageMatches('/Please wait at least 60 seconds/');

        $limiter->assertCanSendOtp($this->tenantId, $this->phoneNumber);
    }

    public function test_otp_rate_limiter_blocks_excessive_requests_within_10m_window(): void
    {
        $limiter = app(OtpRateLimiter::class);

        // Simulate 3 dispatches spaced by 61 seconds
        for ($i = 1; $i <= 3; $i++) {
            Carbon::setTestNow(Carbon::now()->addSeconds(65));
            $limiter->assertCanSendOtp($this->tenantId, $this->phoneNumber);
            $limiter->recordOtpDispatch($this->tenantId, $this->phoneNumber);
        }

        // 4th request within the 10-minute sliding window must be blocked
        Carbon::setTestNow(Carbon::now()->addSeconds(65));

        $this->expectException(OtpRateLimitExceededException::class);
        $this->expectExceptionMessageMatches('/Maximum 3 OTP requests allowed within 10 minutes/');

        $limiter->assertCanSendOtp($this->tenantId, $this->phoneNumber);
    }
}
