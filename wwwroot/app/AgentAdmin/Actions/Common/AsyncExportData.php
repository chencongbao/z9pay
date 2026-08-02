<?php

namespace App\AgentAdmin\Actions\Common;

use App\Admin\Actions\Grid\Common\AsyncExportData as BaseAsyncExportData;
use Illuminate\Http\Request;

abstract class AsyncExportData extends BaseAsyncExportData
{
    protected int $chunkSize = 2000;

    protected function exportParams(Request $request, int $adminId): array
    {
        return array_merge(parent::exportParams($request, $adminId), [
            'locale' => config('app.locale'),
        ], $this->forceParams());
    }

    protected function actionScript()
    {
        $maxTotal = $this->maxTotal;

        return <<<JS
function(data, target, action) {
    let total = window.agentExportTotal ? window.agentExportTotal() : 0;
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

        return <<<JS
function(target, results) {
    if(!results.status){
        return;
    }
    window.agentExportTotal = window.agentExportTotal || function () {
        let totalText = $('.grid-footer, .box-footer, .pagination-info, .pull-left').text() || '';
        let match = totalText.match(/总共\\s*(\\d+)\\s*条/);
        return match ? parseInt(match[1]) : 1;
    };
    let currentTotal = window.agentExportTotal();
    if(currentTotal <= 0){
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
          let total = window.agentExportTotal();
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
}
