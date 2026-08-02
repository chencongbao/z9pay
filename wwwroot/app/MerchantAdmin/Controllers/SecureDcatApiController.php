<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Actions\Action;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Contracts\LazyRenderable;
use Dcat\Admin\Http\Controllers\HandleFormController;
use Dcat\Admin\Support\Helper;
use Dcat\Admin\Widgets\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Middleware\NormalizeMerchantGridQuery;

class SecureDcatApiController extends HandleFormController
{
    private const ACTION_PERMISSIONS = [
        \App\MerchantAdmin\Actions\BankCode\ExportData::class => 'bank-codes',
        \App\MerchantAdmin\Actions\DepositOrder\ExportData::class => 'deposit-orders',
        \App\MerchantAdmin\Actions\MerchantBalanceLog\ExportData::class => 'balance-logs',
        \App\MerchantAdmin\Actions\SettlementOrder\ExportData::class => 'settlement-orders',
        \App\MerchantAdmin\Actions\TransferOrder\ExportData::class => 'transfer-orders',
        \App\MerchantAdmin\Actions\User\Delete::class => 'musers',
        \App\MerchantAdmin\Actions\User\UnlockLogin::class => 'musers',
    ];

    private const FORM_PERMISSIONS = [
        \App\MerchantAdmin\Form\AmountPassword::class => '',
        \App\MerchantAdmin\Form\LookSecrectDetail::class => 'merchant_reset_secret',
        \App\MerchantAdmin\Form\ResetSecrectDetail::class => 'merchant_reset_secret',
        \App\MerchantAdmin\Form\UpdatePassword::class => '',
        \App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm::class => 'merchant-settlement-order-add',
        \App\MerchantAdmin\Form\User\ResetGooglePassword::class => 'musers',
    ];

    private const RENDERABLE_PERMISSIONS = [
        \App\MerchantAdmin\Form\AmountPassword::class => '',
        \App\MerchantAdmin\Form\LookSecrectDetail::class => 'merchant_reset_secret',
        \App\MerchantAdmin\Form\ResetSecrectDetail::class => 'merchant_reset_secret',
        \App\MerchantAdmin\Form\UpdatePassword::class => '',
        \App\MerchantAdmin\Renderable\BankCode\HistoryExportData::class => 'bank-codes',
        \App\MerchantAdmin\Renderable\DepositOrder\HistoryExportData::class => 'deposit-orders',
        \App\MerchantAdmin\Renderable\MerchantBalanceLog\HistoryExportData::class => 'balance-logs',
        \App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm::class => 'merchant-settlement-order-add',
        \App\MerchantAdmin\Renderable\SettlementOrder\HistoryExportData::class => 'settlement-orders',
        \App\MerchantAdmin\Renderable\TransferOrder\HistoryExportData::class => 'transfer-orders',
    ];

    private const VALUE_PERMISSIONS = [
        \App\Admin\Metrics\MerchantAdmin\DepositOrders::class => '',
        \App\Admin\Metrics\MerchantAdmin\TransferOrders::class => '',
        \App\Admin\Metrics\MerchantAdmin\DepositOrder\Card1::class => '',
        \App\Admin\Metrics\MerchantAdmin\DepositOrder\Card2::class => '',
        \App\Admin\Metrics\MerchantAdmin\DepositOrder\Card3::class => '',
        \App\Admin\Metrics\MerchantAdmin\DepositOrder\Card4::class => '',
        \App\Admin\Metrics\MerchantAdmin\DepositOrder\Card5::class => '',
        \App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card1::class => '',
        \App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card2::class => '',
        \App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card3::class => '',
        \App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card4::class => '',
        \App\Admin\Metrics\MerchantAdmin\SettlementOrders::class => '',
        \App\Admin\Metrics\MerchantAdmin\TransferOrder\Card1::class => '',
        \App\Admin\Metrics\MerchantAdmin\TransferOrder\Card2::class => '',
        \App\Admin\Metrics\MerchantAdmin\TransferOrder\Card3::class => '',
        \App\Admin\Metrics\MerchantAdmin\TransferOrder\Card4::class => '',
    ];

    private const VALUE_REQUEST_RULES = [
        'int' => [
            'id',
            'mid',
            'type',
            'status',
            'payment_id',
            'currency_id',
            'source_id',
        ],
        'decimal' => [
            'amount',
            'amount_min',
            'amount_max',
        ],
        'string' => [
            'ordernumber',
            'order_no',
            'holder_name',
            'pay_name',
            'code',
            'name',
            'ip',
        ],
        'range' => [
            'created_at',
            'success_time',
            'date_add',
        ],
        'date_scalar' => [
            'begin_date',
            'end_date',
        ],
        'sort' => [
            'id',
            'updated_at',
        ],
    ];

    public function action(Request $request)
    {
        $class = $this->resolveAllowedClass($request, '_action', self::ACTION_PERMISSIONS);
        $action = app($class);
        if (!$action instanceof Action || !method_exists($action, 'handle')) {
            return $this->deny();
        }

        $action->setKey($request->get('_key'));
        if (!$action->passesAuthorization()) {
            $response = $action->failedAuthorization();
        } else {
            $response = $action->handle($request);
        }

        return $response instanceof Response ? $response->send() : $response;
    }

    public function form(Request $request)
    {
        $form = $this->resolveMerchantForm($request);

        if (!$form->passesAuthorization()) {
            return response()->json(['status' => false, 'message' => __('admin.deny')]);
        }

        $form->form();
        if ($errors = $form->validate($request)) {
            return $form->validationErrorsResponse($errors);
        }

        $input = $form->sanitize($request->all());

        return $this->sendResponse($form->handle($input));
    }

    public function render(Request $request): string|JsonResponse
    {
        $class = $this->resolveAllowedClass($request, 'renderable', self::RENDERABLE_PERMISSIONS, '_renderable');
        $renderable = app($class);
        if (!$renderable instanceof LazyRenderable) {
            return $this->deny();
        }

        $renderable->payload($request->all());
        if (method_exists($renderable, 'requireAssets')) {
            $renderable->requireAssets();
        }
        if (method_exists($renderable, 'passesAuthorization') && !$renderable->passesAuthorization()) {
            return $renderable->failedAuthorization();
        }

        Admin::script('Dcat.wait()', true);
        Admin::baseJs([], false);
        Admin::baseCss([], false);
        Admin::fonts([]);

        $asset = Admin::asset();

        return Helper::render($renderable->render())
            .Admin::html()
            .$asset->jsToHtml()
            .$asset->cssToHtml()
            .$asset->scriptToHtml()
            .$asset->styleToHtml();
    }

    public function value(Request $request)
    {
        $class = $this->resolveAllowedClass($request, '_key', self::VALUE_PERMISSIONS);
        $this->normalizeValueRequest($request);

        $instance = app($class);
        if (!method_exists($instance, 'handle')) {
            return $this->deny();
        }

        if (method_exists($instance, 'passesAuthorization') && !$instance->passesAuthorization()) {
            return $instance->failedAuthorization();
        }

        $response = $instance->handle($request);
        if ($response) {
            return $response;
        }

        if (method_exists($instance, 'valueResult')) {
            return $instance->valueResult();
        }

        return $this->deny();
    }

    private function normalizeValueRequest(Request $request): void
    {
        $normalizer = app(NormalizeMerchantGridQuery::class);
        $request->query->replace($normalizer->normalizeArray($request->query->all(), self::VALUE_REQUEST_RULES));
        $request->request->replace($normalizer->normalizeArray($request->request->all(), self::VALUE_REQUEST_RULES));
    }

    private function resolveMerchantForm(Request $request): Form
    {
        $class = $this->resolveAllowedClass($request, Form::REQUEST_NAME, self::FORM_PERMISSIONS);
        $form = app($class);
        if (!$form instanceof Form || !method_exists($form, 'handle')) {
            abort(403);
        }

        return $form;
    }

    private function resolveAllowedClass(Request $request, string $key, array $permissions, ?string $fallbackKey = null): string
    {
        $raw = $request->get($key, $fallbackKey ? $request->get($fallbackKey) : null);
        if (!is_string($raw) || $raw === '') {
            abort(403);
        }

        $class = str_contains($raw, '\\') ? $raw : str_replace('_', '\\', $raw);
        if (!array_key_exists($class, $permissions) || !class_exists($class)) {
            abort(403);
        }

        $permission = $permissions[$class];
        if ($permission !== '' && !Admin::user()->can($permission)) {
            abort(403);
        }

        return $class;
    }

    private function deny(?string $message = null): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message ?: __('admin.deny')], 403);
    }
}
