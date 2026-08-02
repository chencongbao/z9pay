<?php

namespace App\MerchantAdmin\Actions\Common;

use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Actions\Response;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Grid\Tools\AbstractTool;
use App\Http\Middleware\NormalizeMerchantGridQuery;

abstract class AsyncExportData extends AbstractTool
{
    protected $style = 'btn btn-primary btn-outline pull-right mr-1';

    protected string $jobClass = '';

    protected string $lockPrefix = '';

    protected string $eventType = '';

    protected string $queue = 'export';

    protected string $echoChannel = 'merchant';

    protected string $totalSelector = '.export-total';

    protected int $maxTotal = 1000000;

    protected int $chunkSize = 2000;

    protected int $lockMinutes = 60;

    protected bool $withMerchantId = true;

    protected string $requestRuleKey = '';

    protected string $historyRenderableClass = '';

    protected string $historyStyle = 'btn btn-primary btn-outline pull-right';

    public function title()
    {
        return '<i class="feather icon-download"></i> ' . admin_trans_label('export_data');
    }

    protected function html()
    {
        $exportButton = parent::html();
        if ($this->historyRenderableClass === '') {
            return $exportButton;
        }

        $historyButton = Modal::make()
            ->title(admin_trans_label('history_export_title'))
            ->body($this->historyRenderableClass::make())
            ->button('<button class="' . $this->historyStyle . '"><i class="feather icon-crosshair"></i> ' . admin_trans_label('history_export_data') . '</button>');

        return $historyButton . $exportButton;
    }

    public function handle(Request $request): Response
    {
        $adminId = Admin::user()->id;
        $params = $this->exportParams($request, $adminId);
        if ($this->hasExportData($params) === false) {
            return $this->response()->error(admin_trans_label('export_data_not_empty'));
        }

        if (!$this->canStartExport($adminId)) {
            return $this->response()->error(admin_trans_label('has_exporting_data'));
        }

        dispatch(new $this->jobClass($params))->onQueue($this->queue);

        return $this->response()->data(['admin_id' => $adminId]);
    }

    protected function exportParams(Request $request, int $adminId): array
    {
        $params = array_merge($this->normalizeRequestParams($request->all()), [
            'admin_id' => $adminId,
            'locale' => config('app.locale'),
            'url' => config('filesystems.disks.public.url'),
            'download_base_url' => admin_url('export-download'),
        ], $this->forceParams());

        if ($this->withMerchantId) {
            $params['mid'] = bob_merchant_user_pid();
        } else {
            unset($params['mid']);
        }

        foreach ($this->defaultParams() as $key => $value) {
            if (!array_key_exists($key, $params) || $params[$key] === null || $params[$key] === '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    protected function defaultParams(): array
    {
        return [];
    }

    protected function todayDateParams(string $field = 'created_at'): array
    {
        return [
            $field => [
                'start' => date('Y-m-d') . ' 00:00:00',
                'end' => date('Y-m-d', strtotime('+1 day')) . ' 00:00:00',
            ],
        ];
    }

    protected function forceParams(): array
    {
        return [];
    }

    protected function canStartExport(int $adminId): bool
    {
        return Cache::add($this->lockPrefix . $adminId, 1, now()->addMinutes($this->lockMinutes));
    }

    protected function hasExportData(array $params): ?bool
    {
        return null;
    }

    protected function actionScript()
    {
        $totalSelector = $this->totalSelector;
        $maxTotal = $this->maxTotal;
        $exportTooMany = admin_trans_label('export_dayu_100wan');
        $exportEmpty = admin_trans_label('export_data_not_empty');

        return <<<JS
function(data, target, action) {
    let total = parseInt($("{$totalSelector}").text()) || 0;
    if(total <= 0){
        Dcat.error('{$exportEmpty}');
        return false;
    }
    if(total > {$maxTotal}){
        Dcat.error('{$exportTooMany}');
        return false;
    }
}
JS;
    }

    public function resolverScript()
    {
        $eventType = $this->eventType;
        $chunkSize = $this->chunkSize;
        $echoChannel = $this->echoChannel;
        $totalSelector = $this->totalSelector;
        $exportEmpty = admin_trans_label('export_data_not_empty');
        $exportProgress = admin_trans_label('export_data_pregress');
        $exportWait = admin_trans_label('export_loading_wait');
        $exportDownload = admin_trans_label('export_download');

        return <<<JS
function(target, results) {
    if(!results.status){
        return;
    }
    let currentTotal = parseInt($("{$totalSelector}").text()) || 0;
    if(currentTotal == 0){
        Dcat.error('{$exportEmpty}');
        return;
    }
    Dcat.swal.fire({
      title: '{$exportProgress}',
      html: `
<div class="export-loading mb-1 mt-1">
    <div class="spinner-grow text-primary" role="status" style="width: 3rem; height: 3rem;">
        <span class="sr-only">Loading...</span>
    </div>
    <h6 class="mt-1">{$exportWait}</h6>
</div>
<div class="export-progress progress mb-1 mt-1 hidden">
  <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
</div>
<div class="export-download text-center mt-1 hidden"><a class="btn btn-primary" href="#" role="button">{$exportDownload}</a></div>
      `,
      showCloseButton: true,
      showCancelButton: false,
      showConfirmButton: false,
      allowOutsideClick: false,
      onOpen: () => {
          const export_user_id = results.data.admin_id;
         window.Echo.channel('{$echoChannel}').listen('.export', function(data) {
          if(data.admin_id != export_user_id || data.type != '{$eventType}') return;
          let total = parseInt($("{$totalSelector}").text()) || 0;
          let totalBlock = Math.ceil(total / {$chunkSize});
          let progressBar = Dcat.swal.getPopup().querySelector(".progress-bar");
          let exportLoading = Dcat.swal.getPopup().querySelector(".export-loading");
          let exportDownload = Dcat.swal.getPopup().querySelector(".export-download");
          let exportProgress = Dcat.swal.getPopup().querySelector(".export-progress");
          if(parseInt(data.status) == 0){
             $(exportLoading).addClass("hidden");
             $(exportProgress).removeClass("hidden");
          }
          if(parseInt(data.status) == 1){
              let percent = totalBlock > 0 ? parseInt(data.block) / totalBlock : 0;
              value = parseInt(percent.toFixed(2) * 100);
              progressBar.style.width = value + '%';
              progressBar.setAttribute('aria-valuenow', value);
              progressBar.textContent = value + '%';
          }
          if(parseInt(data.status) == 2){
             $(exportLoading).addClass("hidden");
             $(exportProgress).addClass("hidden");
             $(exportDownload).removeClass("hidden");
             $(exportDownload).find("a").attr("href", data.url);
          }
        });
      }
    });
}
JS;
    }

    protected function parameters()
    {
        return $this->normalizeRequestParams(request()->all());
    }

    protected function normalizeRequestParams(array $params): array
    {
        if ($this->requestRuleKey === '') {
            return $params;
        }

        $rules = (array)config("merchant-admin.grid.request_rules.{$this->requestRuleKey}", []);
        if (empty($rules)) {
            return $params;
        }

        return app(NormalizeMerchantGridQuery::class)->normalizeArray($params, $rules);
    }
}
