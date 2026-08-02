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
        <div class="row">
            <div class="col-sm-12">
                <div  style="padding: 0px 15px 15px 15px">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="feather icon-search"></i></span>
                        </div>
                        <input type="text" class="form-control" placeholder="{{ __('admin.search_keyword_placeholder') }}" id="input-search">
                    </div>
                </div>
                <div id="treeview"></div>
            </div>
        </div>
    </div>
</div>
<style>
    .box-header.with-border, .card-header.with-border{
        border-bottom: none;
    }
    .list-group-item:first-child{
        border-radius:0;
    }
    #treeview .node-selected {
        background-color: #586cb1 !important;
        color: #fff !important;
    }
</style>
<script>
    Dcat.ready(function () {
        var options = {
            levels: 5,
            selectedBackColor: '#586cb1',
            selectedColor: '#fff',
            data: {{ Illuminate\Support\Js::from($data) }},
            onNodeSelected:function (event, node) {
                if (node.href) {
                    window.location.href = node.href;
                }
            }
        };
        var $searchableTree = $('#treeview').treeview(options);

        var search = function(e) {
            var pattern = $('#input-search').val();
            var options = {
                ignoreCase: $('#chk-ignore-case').is(':checked'),
                exactMatch: $('#chk-exact-match').is(':checked'),
                revealResults: $('#chk-reveal-results').is(':checked')
            };
            $searchableTree.treeview('search', [ pattern, options ]);
        }

        $('#btn-search').on('click', search);
        $('#input-search').on('keyup', search);

    });
</script>
