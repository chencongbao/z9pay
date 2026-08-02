@include('extendtions.dcat.layout.left-filter-assets')
@php
    $sidebarTitle = $title ?? __('admin.sidebar');
    $expandTitle = __('admin.expand_sidebar_title', ['title' => $sidebarTitle]);
@endphp
<div class="admin-left-filter-side">
    <button type="button" class="btn btn-sm btn-outline-primary admin-left-filter-toggle" data-expand-title="{{ $expandTitle }}" data-collapse-title="{{ __('admin.collapse_sidebar') }}" data-expand-tooltip="{{ __('admin.expand_filter_sidebar') }}" data-collapse-tooltip="{{ __('admin.collapse_filter_sidebar') }}">
        <i class="feather icon-chevrons-left"></i>
        <span data-expand-title="{{ $expandTitle }}">{{ __('admin.collapse_sidebar') }}</span>
    </button>
    <div class="admin-left-filter-body">
        <div class="search1 input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1"><i class="feather icon-search"></i></span>
            </div>
            <input type="text" class="form-control searchLeftContent" placeholder="{{ __('admin.search_keyword_placeholder') }}">
        </div>
        <ul class="list-group list-group-flush">
            @foreach($data as $item)
                <a class="list-group-item search-merchant-item @if($item['active'] == 1)active @endif" href="{{optional($item)->offsetGet('url')}}" >{{optional($item)->offsetGet('bname')}}</a>
            @endforeach
        </ul>
    </div>
</div>

<style>
    .search1 {
        padding: 0px 15px 15px 15px;
    }
</style>
