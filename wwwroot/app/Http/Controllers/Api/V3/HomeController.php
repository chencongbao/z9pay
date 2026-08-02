<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V3\HomeDepositsIndexRequest;
use App\Http\Requests\Api\V3\HomeDepositsQueryRequest;
use App\Http\Requests\Api\V3\HomeQueryBalanceRequest;
use App\Http\Requests\Api\V3\HomeSubmitUtrRequest;
use App\Http\Requests\Api\V3\HomeTransferCheckRequest;
use App\Http\Requests\Api\V3\HomeTransferIndexRequest;
use App\Http\Requests\Api\V3\HomeTransfersQueryRequest;
use App\Services\Api\V3\HomeService;
use App\Traits\ApiServiceResultResponseTrait;
use Illuminate\Support\Facades\App;


class HomeController extends ApiController
{
    use ApiServiceResultResponseTrait;

    public function depositsIndex(HomeDepositsIndexRequest $request)
    {
        return $this->serviceResult(App::make(HomeService::class)->depositsIndex(
            $request->only(['mid', 'amount', 'order_no', 'gateway', 'ip', 'notify_url', 'sign', 'name', 'bank_name', 'card_no', 'card_pin', 'card_name', 'return_url'])
        ));
    }


    public function depositsQuery(HomeDepositsQueryRequest $request)
    {
        return $this->serviceResult(App::make(HomeService::class)->depositsQuery(
            $request->only(['mid', 'order_no', 'sign'])
        ));
    }

    public function transfersIndex(HomeTransferIndexRequest $request)
    {
        return $this->serviceResult(App::make(HomeService::class)->transfersIndex(
            $request->only(['mid', 'amount', 'order_no', 'ip', 'notify_url', 'sign', 'bank_branch', 'bank_name', 'holder_name', 'card_no', 'bank_code', "identity_no", "withdrawQueryUrl"])
        ));
    }


    public function transfersQuery(HomeTransfersQueryRequest $request)
    {
        return $this->serviceResult(App::make(HomeService::class)->transfersQuery(
            $request->only(['mid', 'order_no', 'sign'])
        ));
    }


    public function balance(HomeQueryBalanceRequest $request)
    {
        return $this->serviceResult(App::make(HomeService::class)->balance(
            $request->only(['mid', 'sign'])
        ));
    }


    public function transferCheck(HomeTransferCheckRequest $request)
    {
        return $this->serviceResult(App::make(HomeService::class)->transferCheck(
            $request->only(['cid', 'ordernumber', 'amount', 'sign'])
        ));
    }


    public function submitUtr(HomeSubmitUtrRequest $request)
    {
        return $this->serviceResult(App::make(HomeService::class)->submitUtr(
            $request->only(['mid', 'order_no', 'sign', 'utr'])
        ));
    }
}
