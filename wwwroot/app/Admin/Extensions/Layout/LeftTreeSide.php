<?php

namespace App\Admin\Extensions\Layout;

use BlueM\Tree;
use Dcat\Admin\Widgets\Box;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Support\Renderable;

class LeftTreeSide  implements Renderable
{
    use ResetsGridPagination;
    protected $title = "列表";

    public $data = [];

    protected $field = "id";

    protected $default = 0;

    protected $prependAllLabel = '';

    public function title($title = "")
    {
        $this->title = $title;
        return $this;
    }

    public function field($field = "")
    {
        $this->field = $field;
        return $this;
    }


    public function default($id = 0)
    {
        $this->default = request($this->field,0);
        if($id > 0){
            $this->default = $id;
        }
        return $this;
    }

    public function prependAll($label = '全部')
    {
        $this->prependAllLabel = $label;
        return $this;
    }

    public function data($data = [])
    {
        $this->data = $this->formatData($data);
    }

    private function formatData($data = [])
    {
        $data = collect($data)->transform(function ($item) {
            $query = array_merge($this->queryWithoutGridPage(),[$this->field => $item['id']]);
            $item['href'] = route(Route::currentRouteName(),$query);
            if($this->default == $item['id']){
                $item['state']['selected'] = true;
            }
            return $item;
        });

        if ($this->prependAllLabel) {
            $query = $this->queryWithoutGridPage();
            unset($query[$this->field]);

            $allItem = [
                'parentid' => 0,
                'text' => $this->prependAllLabel,
                'level' => 0,
                'id' => '__all__',
                'href' => route(Route::currentRouteName(), $query),
            ];

            if ($this->default <= 0) {
                $allItem['state']['selected'] = true;
            }

            $data->prepend($allItem);
        }

        $rootId = 0;
        if($data->count() > 0){
            $rootNode = $data->firstWhere('parentid',0);
            if($rootNode){
                $rootId = 0;
            }else{
                $rootNode = $data->first();
                $rootId = $rootNode['parentid'] ?: 0;
            }
        }

        $tree = new Tree(
            $data,
            ['rootId' => $rootId, 'id' => 'id', 'parent' => 'parentid']
        );

        return bob_tree_to_array($tree->getRootNodes());
    }

    public function render()
    {
        $box = Box::make($this->title, view('extendtions.dcat.layout.left-tree-side', ['data' => $this->data, 'title' => $this->title])->render());
        $box->padding('0px 0px');
        return $box;
    }
}
