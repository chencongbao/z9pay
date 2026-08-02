<?php

namespace App\Providers;

use App\Extendtions\Dcat\Layout\Menu;
use App\Extendtions\Dcat\src\Support\Translator;
use App\Services\Cache\Config\CacheAdminSettingService;
use App\Services\Common\ModelObserverService;
use App\Services\Common\PrintSqlService;
use App\Services\Common\ReportExceptionService;
use App\Services\Common\UpdateSystemConfigService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('admin.menu', Menu::class);
        $this->app->singleton('admin.translator', Translator::class);
        $this->app->singleton(CacheAdminSettingService::class);
        $this->app->singleton(ReportExceptionService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->make(PrintSqlService::class)->excute();
        $this->app->make(ModelObserverService::class)->excute();
        $this->app->make(UpdateSystemConfigService::class)->excute();
    }
}
