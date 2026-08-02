@php
    $appendIframeChild = function ($href) {
        if (!config('iframe_tab.enable') || !request()->boolean('iframe_tab_child')) {
            return $href;
        }

        if (!is_string($href) || $href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return $href;
        }

        if (str_contains($href, 'iframe_tab_child=')) {
            return $href;
        }

        if (str_starts_with($href, '?')) {
            return $href.(str_contains($href, '?') ? '&' : '?').'iframe_tab_child=1';
        }

        $separator = str_contains($href, '?') ? '&' : '?';

        return $href.$separator.'iframe_tab_child=1';
    };
@endphp

<div {!! $attributes !!}>
    <ul class="nav nav-tabs {{ $tabStyle }}" role="tablist">
        @foreach($tabs as $id => $tab)
            @if($tab['type'] == \Dcat\Admin\Widgets\Tab::TYPE_CONTENT)
                <li class="nav-item" >
                    <a href="#tab_{{ $tab['id'] }}" class=" nav-link  {{ $id == $active ? 'active' : '' }}" data-toggle="tab">{!! $tab['title'] !!}</a>
                </li>
            @elseif($tab['type'] == \Dcat\Admin\Widgets\Tab::TYPE_LINK)
                <li class="nav-item" >
                    <a href="{{ $appendIframeChild($tab['href']) }}"
                       class=" nav-link  {{ $id == $active ? 'active' : '' }}"
                       @if(request()->boolean('iframe_tab_child')) data-no-pjax="true" @endif>{!! $tab['title'] !!}</a>
                </li>
            @endif
        @endforeach

        @if (!empty($dropDown))
        <li class="dropdown nav-item">
            <a class="dropdown-toggle nav-link" data-toggle="dropdown" href="#">
                Dropdown <span class="caret"></span>
            </a>
            <ul class="dropdown-menu">
                @foreach($dropDown as $link)
                <li role="presentation"><a role="menuitem" tabindex="-1" href="{{ $appendIframeChild($link['href']) }}" @if(request()->boolean('iframe_tab_child')) data-no-pjax="true" @endif>{!! $link['name'] !!}</a></li>
                @endforeach
            </ul>
        </li>
        @endif
        <li class="nav-item pull-right header">{!! $title !!}</li>
    </ul>

    <div class="tab-content" style="{!! $padding !!}">
        @foreach($tabs as $id => $tab)
        <div class="tab-pane {{ $id == $active ? 'active' : '' }}" id="tab_{{ $tab['id'] }}">
            {!! $tab['content'] ?? '' !!}
        </div>
        @endforeach

    </div>
</div>
