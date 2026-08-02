@if (!empty($default) || !empty($custom))
<div class="grid-dropdown-actions dropdown">
    <a href="#" class="grid-dropdown-actions-trigger" data-toggle="dropdown" data-display="static">
        <i class="feather icon-more-vertical"></i>
    </a>
    <ul class="dropdown-menu dropdown-menu-right" style="left: auto; right: 0; margin-right: 0;">

        @foreach($default as $action)
            <li class="dropdown-item">{!! Dcat\Admin\Support\Helper::render($action) !!}</li>
        @endforeach

        @if(!empty($custom))

            @if(!empty($default))
                <li class="dropdown-divider"></li>
            @endif

            @foreach($custom as $action)
                <li class="dropdown-item">{!! $action !!}</li>
            @endforeach
        @endif
    </ul>
</div>
@endif
