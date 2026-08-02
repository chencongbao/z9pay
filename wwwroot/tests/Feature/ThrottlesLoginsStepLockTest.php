<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Traits\ThrottlesLogins;
use Illuminate\Support\Facades\Cache;

class ThrottlesLoginsStepLockTest extends TestCase
{
    private const USERNAME = 'step-lock-user';
    private const PREFIXES = ['step_alpha', 'step_beta'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearKeys();
    }

    protected function tearDown(): void
    {
        $this->clearKeys();

        parent::tearDown();
    }

    public function test_lock_minutes_cover_every_step_without_gaps(): void
    {
        $throttle = new LoginThrottleHarness('step_alpha');

        foreach ([2 => 0, 3 => 10, 4 => 10, 9 => 10, 10 => 30, 11 => 30, 19 => 30, 20 => 120, 21 => 1440] as $total => $minutes) {
            $this->assertSame($minutes, $throttle->lockMinutes($total), "Unexpected lock duration at total {$total}");
        }
    }

    public function test_next_failure_relocks_after_previous_step_lock_expires(): void
    {
        $throttle = new LoginThrottleHarness('step_alpha');
        $request = $this->loginRequest();

        foreach (range(1, 3) as $attempt) {
            $throttle->incrementLoginAttempts($request);
        }

        $this->assertSame(3, $throttle->totalAttempts(self::USERNAME));
        $this->assertGreaterThanOrEqual(599, $throttle->lockAvailableIn(self::USERNAME));

        $throttle->clearLock(self::USERNAME);
        $this->assertSame(0, $throttle->lockAvailableIn(self::USERNAME));

        $throttle->incrementLoginAttempts($request);

        $this->assertSame(4, $throttle->totalAttempts(self::USERNAME));
        $this->assertGreaterThanOrEqual(599, $throttle->lockAvailableIn(self::USERNAME));
    }

    public function test_prefixes_stay_isolated_and_unlock_still_clears_total_lock_and_risk(): void
    {
        $alpha = new LoginThrottleHarness('step_alpha');
        $beta = new LoginThrottleHarness('step_beta');
        $request = $this->loginRequest();

        foreach (range(1, 3) as $attempt) {
            $alpha->incrementLoginAttempts($request);
        }
        $alpha->recordLoginRisk($request);

        $this->assertSame(3, $alpha->totalAttempts(self::USERNAME));
        $this->assertGreaterThan(0, $alpha->lockAvailableIn(self::USERNAME));
        $this->assertSame(0, $beta->totalAttempts(self::USERNAME));
        $this->assertSame(0, $beta->lockAvailableIn(self::USERNAME));

        $alpha->unlockByUsername(self::USERNAME);

        $this->assertSame(0, $alpha->totalAttempts(self::USERNAME));
        $this->assertSame(0, $alpha->lockAvailableIn(self::USERNAME));
        $this->assertFalse(Cache::has('step_alpha:risk:' . self::USERNAME));
    }

    private function loginRequest(): Request
    {
        return Request::create('/login', 'POST', ['username' => self::USERNAME]);
    }

    private function clearKeys(): void
    {
        foreach (self::PREFIXES as $prefix) {
            app(\Illuminate\Cache\RateLimiter::class)->clear("{$prefix}:total:" . self::USERNAME);
            app(\Illuminate\Cache\RateLimiter::class)->clear("{$prefix}:lock:" . self::USERNAME);
            Cache::forget("{$prefix}:risk:" . self::USERNAME);
        }
    }
}

class LoginThrottleHarness
{
    use ThrottlesLogins;

    public function __construct(private string $prefix)
    {
    }

    public function username(): string
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return $this->prefix;
    }

    public function lockMinutes(int $total): int
    {
        return $this->lockMinutesByTotalAttempts($total);
    }

    public function totalAttempts(string $username): int
    {
        return $this->limiter()->attempts("{$this->prefix}:total:{$username}");
    }

    public function lockAvailableIn(string $username): int
    {
        return $this->limiter()->availableIn("{$this->prefix}:lock:{$username}");
    }

    public function clearLock(string $username): void
    {
        $this->limiter()->clear("{$this->prefix}:lock:{$username}");
    }
}
