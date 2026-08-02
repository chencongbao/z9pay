<div id="test1"></div>
<style>
    .layui-tree *, :after, :before {
        box-sizing: unset !important;
    }
</style>
<script>
    Dcat.ready(function () {
        layui.use('tree', function(){
            var tree = layui.tree;

            //渲染
            var inst1 = tree.render({
                elem: '#test1',
                onlyIconControl:true
                ,data: {!! json_encode($tree) !!}
                ,click: function(obj){
                    Dcat.reload('{{ \Dcat\Admin\Admin::app()->getRoute('balance-logs.index') }}?agent_id='+obj.data.id);
                }
            });
        });

    });

</script>
