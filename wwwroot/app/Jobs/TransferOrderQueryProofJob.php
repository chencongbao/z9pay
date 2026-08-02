<?php

namespace App\Jobs;

use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class TransferOrderQueryProofJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $id = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id = 0)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $model = TransferOrder::where('id', $this->id)->where('status', 4)->with(['channel' => function ($query) {
            $query->select('id', 'classname');
        }])->first(['channel_id', 'id', 'ordernumber']);
        if ($model) {
            $classname = 'Richard\\Payment\\Channel\\' . $model->channel->classname;
            $payment = new $classname(LogConstService::TRANSFER_ORDER_LOG_PREFIX . $model->id);
            $payment->queryProof($model->ordernumber, $model->id);
        }
    }
}
