<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class BatchMerchantTransferCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 600;

    private ?int $merchantUserId;

    private string $date;

    private int $limit;

    public function __construct(?int $merchantUserId, string $date, int $limit = 200)
    {
        $this->merchantUserId = $merchantUserId;
        $this->date = $date;
        $this->limit = max(1, min($limit, 1000));
    }

    public function handle(): void
    {
        $date = Carbon::parse($this->date);
        $processed = 0;

        $query = TransferOrder::query()
            ->select(['id'])
            ->whereIn('status', [4, 5])
            ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->whereNotNull('notify_url')
            ->where('notify_url', '<>', '');

        if (!empty($this->merchantUserId)) {
            $query->where('mid', $this->merchantUserId);
        }

        $query->chunkById(100, function ($orders) use (&$processed) {
            foreach ($orders as $order) {
                if ($processed >= $this->limit) {
                    return false;
                }

                dispatch(new MerchantTransferCallbackJob($order->id, 'callback_low', true))->onQueue('callback_low');
                $processed++;
            }

            return true;
        });
    }
}
