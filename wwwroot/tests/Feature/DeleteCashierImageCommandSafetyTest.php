<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class DeleteCashierImageCommandSafetyTest extends TestCase
{
    public function test_invalid_days_are_rejected_before_scanning_or_deleting_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cashier/codex-old.jpg', 'image');
        touch(Storage::disk('public')->path('cashier/codex-old.jpg'), now()->subDays(10)->timestamp);

        foreach (['abc', '-1', '0', '1abc', '1.5', '3651'] as $days) {
            $this->artisan('images:delete-cashier', ['--days' => $days, '--dry-run' => true])
                ->assertExitCode(1);

            Storage::disk('public')->assertExists('cashier/codex-old.jpg');
        }
    }

    public function test_valid_dry_run_keeps_matched_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cashier/codex-old.jpg', 'image');
        touch(Storage::disk('public')->path('cashier/codex-old.jpg'), now()->subDays(10)->timestamp);

        $this->artisan('images:delete-cashier', ['--days' => '1', '--dry-run' => true])
            ->expectsOutputToContain('收银台图片清理试跑完成')
            ->assertExitCode(0);

        Storage::disk('public')->assertExists('cashier/codex-old.jpg');
    }
}
