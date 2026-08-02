<?php

namespace App\Services\SelfChannel;

use App\Models\User;
use App\Models\UserBank;
use App\Models\UserGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\User\GetUserRoundTimesService;
use App\Services\User\GetUserRemainingDepositService;
use App\Services\UserBank\UserBankAutoPriorityService;
use App\Services\User\GetUserDepositOrderDaifukuanAmountService;
use App\Services\SelfNewPayment\GetUserBankSameAmountTimeService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;
use App\Services\SelfNewPayment\GetUserBankTodayDepositTotalAmountService;
use App\Services\SelfNewPayment\GetUserBankTodayDepositTotalNumberService;

class SelfDispatchAnalyzeService
{
    public function execute(int $paymentId = 0, float $amount = 0, int $mid = 0): array
    {
        if ($paymentId <= 0) {
            return $this->emptyResult($paymentId, $amount, $mid);
        }

        $source = '';
        $userBanks = $this->getBankInfoByUserMerchantIds($mid, $paymentId, $amount);
        if (!empty($userBanks)) {
            $source = '金主专接商户';
        } else {
            $userBanks = $this->getBankInfoByGroup($mid, $paymentId, $amount);
            if (!empty($userBanks)) {
                $source = '金主分组';
            }
        }

        $queue = $this->buildVirtualQueue($userBanks, $paymentId);

        return [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'mid' => $mid,
            'source' => $source,
            'banks' => array_values($userBanks),
            'queue' => $queue,
            'current' => $queue[0] ?? null,
            'next' => $queue[1] ?? null,
        ];
    }

    protected function emptyResult(int $paymentId, float $amount, int $mid): array
    {
        return [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'mid' => $mid,
            'source' => '',
            'banks' => [],
            'queue' => [],
            'current' => null,
            'next' => null,
        ];
    }

    protected function buildVirtualQueue(array $userBanks, int $paymentId): array
    {
        if (empty($userBanks)) {
            return [];
        }

        $expandedBanks = $this->expandByRoundTimes($userBanks);
        if (empty($expandedBanks)) {
            return [];
        }

        $priorityKeys = [];
        foreach ($expandedBanks as $item) {
            $priorityKeys[] = $paymentId . '_' . $item['nid'];
        }

        $priorityMap = app(UserBankAutoPriorityService::class)->many($priorityKeys);

        $nodes = [];
        $maxPriority = 0;
        foreach ($expandedBanks as $item) {
            $priority = (int)($priorityMap[$paymentId . '_' . $item['nid']] ?? 0);
            $nodes[] = array_merge($item, [
                'priority' => $priority,
                'round' => intval($item['round'] ?? 1),
            ]);
            if ($priority > $maxPriority) {
                $maxPriority = $priority;
            }
        }

        foreach ($nodes as &$node) {
            if ($node['priority'] <= 0) {
                $maxPriority++;
                $node['priority'] = $maxPriority;
            }
        }
        unset($node);

        usort($nodes, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp((string) $a['nid'], (string) $b['nid']);
            }

            return intval($a['priority']) <=> intval($b['priority']);
        });

        foreach ($nodes as $index => &$node) {
            $node['queue_index'] = $index + 1;
            $node['is_current'] = $index === 0;
            $node['is_next'] = $index === 1;
        }
        unset($node);

        return $nodes;
    }

    protected function expandByRoundTimes(array $userBanks): array
    {
        if (empty($userBanks)) {
            return [];
        }

        $source = array_values($userBanks);
        usort($source, function ($a, $b) {
            return intval($a['id']) <=> intval($b['id']);
        });

        $timesMap = [];
        $maxTimes = 1;
        foreach ($source as $bank) {
            $times = intval(app(GetUserRoundTimesService::class)->excute($bank['user_id']));
            $times = $times <= 1 ? 1 : $times;
            $timesMap[intval($bank['id'])] = $times;
            if ($times > $maxTimes) {
                $maxTimes = $times;
            }
        }

        $banks = [];
        for ($round = 1; $round <= $maxTimes; $round++) {
            foreach ($source as $bank) {
                $cardId = intval($bank['id']);
                if (($timesMap[$cardId] ?? 1) < $round) {
                    continue;
                }

                $item = $bank;
                $item['round'] = $round;
                $item['nid'] = $cardId . '_' . $round;
                $banks[] = $item;
            }
        }

        return $banks;
    }

    protected function getBankInfoByUserMerchantIds(int $mid = 0, int $paymentId = 0, float $amount = 0): array
    {
        if ($mid <= 0) {
            return [];
        }

        $userIds = User::query()
            ->whereRaw('FIND_IN_SET(?,collection_group_merchant_ids)', [$mid])
            ->where('acquisition_status', 1)
            ->where('status', 1)
            ->pluck('id')
            ->toArray();

        if (empty($userIds)) {
            return [];
        }

        return $this->filterValidBanks($this->getBankInfoListByUser($userIds, $paymentId, $amount));
    }

    protected function getBankInfoByGroup(int $mid = 0, int $paymentId = 0, float $amount = 0): array
    {
        $groups = UserGroup::query()
            ->where('status', 1)
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($groups as $group) {
            $allResult = [];

            if (!empty($group->specialized_merchant_user_ids)) {
                $merchantIds = array_filter(explode(',', (string) $group->specialized_merchant_user_ids));
                if ($mid > 0 && in_array((string) $mid, $merchantIds, true)) {
                    $userIds = $this->getGroupUser(intval($group->id), (string) $group->extra_user_ids);
                    if (!empty($userIds)) {
                        $allResult = $this->getBankInfoListByUser($userIds, $paymentId, $amount);
                    }
                }

                $data = $this->filterValidBanks($allResult);
                if (!empty($data)) {
                    return $data;
                }

                continue;
            }

            if (!empty($group->merchant_user_ids)) {
                $merchantIds = array_filter(explode(',', (string) $group->merchant_user_ids));
                if ($mid > 0 && in_array((string) $mid, $merchantIds, true)) {
                    continue;
                }
            }

            $userIds = $this->getGroupUser(intval($group->id), (string) $group->extra_user_ids);
            if (!empty($userIds)) {
                $allResult = $this->getBankInfoListByUser($userIds, $paymentId, $amount);
            }

            $data = $this->filterValidBanks($allResult);
            if (!empty($data)) {
                return $data;
            }
        }

        return [];
    }

    protected function getGroupUser(int $userGroupId = 0, string $extraUserIds = ''): array
    {
        $data = User::query()
            ->where('status', 1)
            ->where('acquisition_status', 1)
            ->where('user_group_id', $userGroupId)
            ->whereNull('collection_group_merchant_ids')
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->toArray();

        if (!empty($extraUserIds)) {
            $extraIds = array_filter(explode(',', $extraUserIds));
            $extraData = User::query()
                ->whereIn('id', $extraIds)
                ->where('status', 1)
                ->where('acquisition_status', 1)
                ->whereNull('collection_group_merchant_ids')
                ->pluck('id')
                ->toArray();

            $data = array_values(array_unique(Arr::collapse([$data, $extraData])));
        }

        return $data;
    }

    protected function filterValidBanks(array $banks): array
    {
        return array_values(array_filter($banks, function ($item) {
            return intval($item['error'] ?? 0) === 0;
        }));
    }

    protected function getBankInfoListByUser(array $userIds = [], int $paymentId = 0, float $amount = 0): array
    {
        $data = [];
        if (empty($userIds) || $paymentId <= 0) {
            return $data;
        }

        $banks = UserBank::query()
            ->with('bank_code')
            ->whereIn('user_id', $userIds)
            ->where('payment_id', $paymentId)
            ->where('collection_status', 1)
            ->orderBy('id', 'asc')
            ->get(['id', 'limint_min_amount', 'limint_max_amount', 'limint_day_amount', 'limit_day_order_number', 'user_id', 'same_amount_interval_time', 'account_type', 'name', 'card_no', 'bank_id']);

        foreach ($banks as $key => $item) {
            $user = App::make(GetUserDetailService::class)->excute($item->user_id);
            $data[$key] = [
                'id' => intval($item->id),
                'name' => $item->bname,
                'account_name' => (string) $item->name,
                'card_no' => (string) $item->card_no,
                'bank_name' => (string) optional($item->bank_code)->name,
                'error' => 0,
                'reason' => '',
                'user' => (string) (optional($user)->offsetGet('bname') ?: ''),
                'user_id' => intval($item->user_id),
            ];
            $daifukuanData = null;

            if ($amount > 0 && intval($item->same_amount_interval_time) > 0) {
                $time = App::make(GetUserBankSameAmountTimeService::class)->excute($item->id, $amount);
                if ($time > 0) {
                    $data[$key]['error'] = 1;
                    $data[$key]['reason'] = '同金额接单时间小于系统设置时间';
                    continue;
                }
            }

            if ($amount > 0) {
                $pushAdvanceOrderTime = intval(bob_admin_setting('push_advance_order_time')) ?: 0;
                $pushCannelOrCancelOrderNumber = intval(bob_admin_setting('push_cannel_or_cancel_order_number')) ?: 0;
                if ($pushAdvanceOrderTime > 0 && $pushCannelOrCancelOrderNumber > 0) {
                    $daifukuanData = App::make(GetUserDaifukuanDepositOrderListService::class)->get($item->user_id);
                    if (!empty($daifukuanData)) {
                        $sameAmountCount = collect($daifukuanData)->filter(function ($row) use ($item, $amount, $pushAdvanceOrderTime) {
                            return intval($row['user_bank_id']) === intval($item->id)
                                && time() - strtotime((string) $row['created_at']) <= $pushAdvanceOrderTime * 60
                                && floatval($row['amount']) === floatval($amount);
                        })->count();
                        if ($sameAmountCount >= $pushCannelOrCancelOrderNumber) {
                            $data[$key]['error'] = 1;
                    $data[$key]['reason'] = '规定时间内相同金额代收待付款过多';
                            continue;
                        }
                    }
                }
            }

            if ($amount > 0) {
                $pendingPayOrderTime = intval(bob_admin_setting('pending_pay_order_time')) ?: 0;
                $pendingPayOrderNumber = intval(bob_admin_setting('pending_pay_order_number')) ?: 0;
                if ($pendingPayOrderTime > 0 && $pendingPayOrderNumber > 0) {
                    if ($daifukuanData === null) {
                        $daifukuanData = App::make(GetUserDaifukuanDepositOrderListService::class)->get($item->user_id);
                    }
                    $pendingCount = collect($daifukuanData)->filter(function ($row) use ($item, $pendingPayOrderTime) {
                        return intval($row['user_bank_id']) === intval($item->id)
                            && time() - strtotime((string) $row['created_at']) <= $pendingPayOrderTime * 60;
                    })->count();
                    if ($pendingCount >= $pendingPayOrderNumber) {
                        $data[$key]['error'] = 1;
                        $data[$key]['reason'] = '规定时间内代收待付款订单过多';
                        continue;
                    }
                }
            }

            if (floatval($item->limint_day_amount) > 0) {
                $limitDayAmount = App::make(GetUserBankTodayDepositTotalAmountService::class)->excute($item->id);
                if ($this->exceedsBankDailyAmountLimit($limitDayAmount, $amount, floatval($item->limint_day_amount))) {
                    $data[$key]['error'] = 1;
                    $data[$key]['reason'] = '收款卡全天总额限制';
                    continue;
                }
            }

            if (intval($item->limit_day_order_number) > 0) {
                $limitDayOrderNumber = App::make(GetUserBankTodayDepositTotalNumberService::class)->excute($item->id);
                if ($limitDayOrderNumber >= intval($item->limit_day_order_number)) {
                    $data[$key]['error'] = 1;
                    $data[$key]['reason'] = '收款卡全天总单数限制';
                    continue;
                }
            }

            if ($amount > 0 && floatval($item->limint_min_amount) > 0 && $amount < floatval($item->limint_min_amount)) {
                $data[$key]['error'] = 1;
                $data[$key]['reason'] = '收款卡单笔最低限额';
                continue;
            }

            if ($amount > 0 && floatval($item->limint_max_amount) > 0 && $amount > floatval($item->limint_max_amount)) {
                $data[$key]['error'] = 1;
                $data[$key]['reason'] = '收款卡单笔最高限额';
                continue;
            }

            if ($amount > 0) {
                $checkResult = App::make(GetUserRemainingDepositService::class)->excute($item->user_id, $amount);
                if (!empty($checkResult) && isset($checkResult['status']) && intval($checkResult['status']) === 0) {
                    $data[$key]['error'] = 1;
                    $data[$key]['reason'] = '代收金额超过金主剩余押金';
                    continue;
                }
            }

            if ($amount > 0 && isset($user['collection_limit_min']) && floatval($user['collection_limit_min']) > 0 && floatval($user['collection_limit_min']) > $amount) {
                $data[$key]['error'] = 1;
                $data[$key]['reason'] = '金主单笔最低限额';
                continue;
            }

            if ($amount > 0 && isset($user['collection_limit_max']) && floatval($user['collection_limit_max']) > 0 && floatval($user['collection_limit_max']) < $amount) {
                $data[$key]['error'] = 1;
                $data[$key]['reason'] = '金主单笔最高限额';
                continue;
            }

            if ($amount > 0) {
                $daifukuanData = App::make(GetUserDaifukuanDepositOrderListService::class)->get($item->user_id);
                $sameAmountCount = 0;
                if (!empty($daifukuanData)) {
                    $sameAmountCount = collect($daifukuanData)->filter(function ($row) use ($amount) {
                        return floatval($row['amount']) === floatval($amount);
                    })->count();
                }

                if (isset($user['limit_deposit_paid_number']) && intval($user['limit_deposit_paid_number']) > 0) {
                    if ($sameAmountCount >= intval($user['limit_deposit_paid_number'])) {
                        $data[$key]['error'] = 1;
                        $data[$key]['reason'] = '金主代收待付款相同金额订单限制';
                        continue;
                    }
                } else {
                    $pushPayOrderTogatherAmount = intval(bob_admin_setting('push_pay_order_togather_amount')) ?: 0;
                    if ($pushPayOrderTogatherAmount > 0 && $sameAmountCount >= $pushPayOrderTogatherAmount) {
                        $data[$key]['error'] = 1;
                        $data[$key]['reason'] = '金主代收待付款相同金额订单限制';
                        continue;
                    }
                }

                $pushPayOrderTotalAmount = floatval(bob_admin_setting('push_pay_order_total_amount')) ?: 0;
                if ($pushPayOrderTotalAmount > 0) {
                    $userDaifukuanTotalAmount = App::make(GetUserDepositOrderDaifukuanAmountService::class)->excute($item->user_id);
                    if ($userDaifukuanTotalAmount + $amount > $pushPayOrderTotalAmount) {
                        $data[$key]['error'] = 1;
                        $data[$key]['reason'] = '金主代收待付款订单总金额限制';
                    }
                }
            }
        }

        return $data;
    }

    protected function exceedsBankDailyAmountLimit(float $currentAmount, float $orderAmount, float $limitAmount): bool
    {
        return $limitAmount > 0 && $currentAmount + $orderAmount > $limitAmount;
    }
}
