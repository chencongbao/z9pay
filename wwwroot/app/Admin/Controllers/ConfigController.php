<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Content;
use App\Admin\Forms\Config\OkxForm;
use App\Admin\Forms\Config\BaseForm;
use App\Admin\Forms\Config\RiskForm;
use App\Admin\Forms\Config\NoticeForm;
use App\Admin\Forms\Config\DepositForm;
use App\Admin\Forms\Config\MerchantForm;
use App\Admin\Forms\Config\SecurityForm;
use App\Admin\Forms\Config\TelegramForm;
use App\Admin\Forms\Config\TransferForm;
use Dcat\Admin\Http\Controllers\AdminController;

class ConfigController extends AdminController
{
    public function base(Content $content): Content
    {
        return $this->renderConfig($content, '基本设置', '系统基本设置', new BaseForm());
    }

    public function deposit(Content $content): Content
    {
        return $this->renderConfig($content, '代收配置', '系统代收配置', new DepositForm());
    }

    public function transfer(Content $content): Content
    {
        return $this->renderConfig($content, '代付配置', '系统代付配置', new TransferForm());
    }

    public function notice(Content $content): Content
    {
        return $this->renderConfig($content, '通知配置', '系统通知配置', new NoticeForm());
    }

    public function telegram(Content $content): Content
    {
        return $this->renderConfig($content, '飞机配置', '系统飞机配置', new TelegramForm());
    }

    public function merchant(Content $content): Content
    {
        return $this->renderConfig($content, '商户配置', '系统商户配置', new MerchantForm());
    }

    public function risk(Content $content): Content
    {
        return $this->renderConfig($content, '风控配置', '系统风控配置', new RiskForm());
    }

    public function okx(Content $content): Content
    {
        return $this->renderConfig($content, '欧易配置', '欧易机器人汇率配置', new OkxForm());
    }

    public function security(Content $content): Content
    {
        return $this->renderConfig($content, '安全配置', '系统安全配置', new SecurityForm());
    }

    private function renderConfig(Content $content, string $title, string $cardTitle, object $form): Content
    {
        $content->title($title);
        $card = new Card($cardTitle, $form);

        return $content->body($card);
    }
}
