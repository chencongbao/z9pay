<?php

namespace App\Services\Common;

use App\Models\User;
use App\Models\Channel;
use App\Models\BankCode;
use App\Models\UserBank;
use App\Models\AgentUser;
use App\Models\ChannelRate;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use App\Traits\ServiceTraits;
use App\Models\ChannelAccount;
use App\Models\MerchantChannel;
use App\Models\MerchantPayment;
use App\Observers\UserObserver;
use App\Observers\ChannelObserver;
use App\Observers\BankCodeObserver;
use App\Observers\UserBankObserver;
use App\Observers\AgentUserObserver;
use App\Observers\ChannelRateObserver;
use App\Observers\MerchantInfoObserver;
use App\Observers\MerchantUserObserver;
use App\Observers\ChannelAccountObserver;
use App\Observers\MerchantChannelObserver;
use App\Observers\MerchantPaymentObserver;

class ModelObserverService
{
    use ServiceTraits;

    public function excute()
    {
        MerchantUser::observe(MerchantUserObserver::class);
        MerchantInfo::observe(MerchantInfoObserver::class);
        AgentUser::observe(AgentUserObserver::class);
        User::observe(UserObserver::class);
        BankCode::observe(BankCodeObserver::class);
        Channel::observe(ChannelObserver::class);
        ChannelRate::observe(ChannelRateObserver::class);
        ChannelAccount::observe(ChannelAccountObserver::class);
        UserBank::observe(UserBankObserver::class);
        MerchantPayment::observe(MerchantPaymentObserver::class);
        MerchantChannel::observe(MerchantChannelObserver::class);
    }
}
