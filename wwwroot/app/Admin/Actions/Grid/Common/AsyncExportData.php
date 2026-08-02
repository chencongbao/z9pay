<?php

namespace App\Admin\Actions\Grid\Common;

use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Actions\Response;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Grid\Tools\AbstractTool;

abstract class AsyncExportData extends AbstractTool
{
    protected $style = 'btn btn-primary btn-outline pull-right mr-1 ml-1';

    protected string $jobClass = '';

    protected string $lockPrefix = '';

    protected string $eventType = '';

    protected string $queue = 'export';

    protected string $totalSelector = '.export-total';

    protected int $maxTotal = 1000000;

    protected int $chunkSize = 2000;

    protected int $lockMinutes = 60;

    protected string $historyRenderableClass = '';

    protected string $historyTitle = '历史导出-只保留当天数据';

    protected string $historyButtonText = '<i class="feather icon-crosshair"></i> 历史导出';

    protected string $historyStyle = 'btn btn-primary btn-outline pull-right mr-1';

    public function title()
    {
        return '<i class="feather icon-download"></i> 导出';
    }

    protected function html()
    {
        $exportButton = parent::html();

        if ($this->historyRenderableClass === '') {
            return $exportButton;
        }

        $historyButton = Modal::make()
            ->title($this->historyTitle)
            ->body($this->historyRenderableClass::make())
            ->button("<button class=\"{$this->historyStyle}\">{$this->historyButtonText}</button>");

        return $historyButton . $exportButton;
    }

    public function handle(Request $request): Response
    {
        $adminId = Admin::user()->id;
        $params = $this->exportParams($request, $adminId);

        if (!$this->canStartExport($adminId)) {
            return $this->response()->error("当前有正在导出的数据");
        }

        dispatch(new $this->jobClass($params))->onQueue($this->queue);

        return $this->response()->data(['admin_id' => $adminId]);
    }

    protected function exportParams(Request $request, int $adminId): array
    {
        return array_merge($this->defaultParams(), $request->all(), [
            'admin_id' => $adminId,
            'url' => config('filesystems.disks.public.url'),
        ], $this->forceParams());
    }

    protected function defaultParams(): array
    {
        return [];
    }

    protected function todayDateParams(string $field = 'created_at'): array
    {
        return [
            $field => [
                'start' => date('Y-m-d') . " 00:00:00",
                'end' => date('Y-m-d', strtotime('+1 day')) . " 00:00:00",
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

    protected function actionScript()
    {
        $totalSelector = $this->totalSelector;
        $maxTotal = $this->maxTotal;

        return <<<JS
function(data, target, action) {
    let total = parseInt($("{$totalSelector}").text()) || 0;
    if(total > {$maxTotal}){
        Dcat.error("导出数据不能大于100万条！");
        return false;
    }
}
JS;
    }

    public function resolverScript()
    {
        $eventType = $this->eventType;
        $chunkSize = $this->chunkSize;
        $totalSelector = $this->totalSelector;

        return <<<JS
function(target, results) {
    if(!results.status){
        return;
    }
    let currentTotal = parseInt($("{$totalSelector}").text()) || 0;
    if(currentTotal == 0){
        Dcat.error("导出数据不能为空！");
        return;
    }
    Dcat.swal.fire({
      title: "数据导出进度",
      html: `
<div class="export-loading mb-1 mt-1">
    <div class="spinner-grow text-primary" role="status" style="width: 3rem; height: 3rem;">
        <span class="sr-only">Loading...</span>
    </div>
    <h6 class="mt-1">正在等待任务执行，请稍后</h6>
</div>
<div class="export-progress progress mb-1 mt-1 hidden">
  <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
</div>
<div class="export-download text-center mt-1 hidden"><a class="btn btn-primary" href="#" role="button">点击下载</a></div>
      `,
      showCloseButton: true,
      showCancelButton: false,
      showConfirmButton: false,
      allowOutsideClick: false,
      onOpen: () => {
          const export_user_id = results.data.admin_id;
          let popup = Dcat.swal.getPopup();
          let exportLoading = popup.querySelector(".export-loading");
          let exportDownload = popup.querySelector(".export-download");
          if (!window.Echo || typeof window.Echo.channel !== 'function') {
             $(exportLoading).find("h6").html("导出任务已提交，当前页面未连接实时进度服务。<br>请稍后点击“历史导出”下载文件。");
             $(exportDownload).removeClass("hidden").find("a").attr("href", "javascript:void(0)").text("关闭后查看历史导出").on("click", function () {
                 Dcat.swal.close();
             });
             return;
          }
         window.Echo.channel('system').listen('.export', function(data) {
          if(data.admin_id != export_user_id || data.type != '{$eventType}') return;
          let total = parseInt($("{$totalSelector}").text()) || 0;
          let totalBlock = Math.ceil(total / {$chunkSize});
          let progressBar = Dcat.swal.getPopup().querySelector(".progress-bar");
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
        return request()->all();
    }
}
