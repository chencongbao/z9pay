<?php

namespace App\Admin\Extensions\Layout;

use Dcat\Admin\Widgets\Box;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Support\Renderable;

class LeftSide implements Renderable
{
    use ResetsGridPagination;

    protected $title = "列表";

    protected $lists = [];

    protected $field = "id";

    protected $default = 0;

    protected $prependAllLabel = '';

    protected $prependAllWithZero = false;

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

    public function prependAll($label = '全部', bool $withZero = false)
    {
        $this->prependAllLabel = $label;
        $this->prependAllWithZero = $withZero;
        return $this;
    }

    public function data($data = [])
    {
        $result = [];
        if ($this->prependAllLabel) {
            $query = $this->queryWithoutGridPage();
            if ($this->prependAllWithZero) {
                $query[$this->field] = 0;
            } else {
                unset($query[$this->field]);
            }

            $result[] = [
                'bname' => $this->prependAllLabel,
                'url' => route(Route::currentRouteName(), $query),
                'active' => $this->default <= 0 ? 1 : 0,
            ];
        }

        if(!empty($data)){
            $items = collect($data)->transform(function ($item){
                $query = array_merge($this->queryWithoutGridPage(),[$this->field => $item['id']]);
                $item['url'] = route(Route::currentRouteName(),$query);
                $item['active'] = $this->default == $item['id'] ? 1 : 0;
                return $item;
            })->toArray();

            $result = array_merge($result, $items);
        }
        $this->lists = $result;
        return $this;
    }

    public function render()
    {
        $box = Box::make($this->title, view('extendtions.dcat.layout.left-side', ['data' => $this->lists, 'title' => $this->title]));
        $box->padding('15px 0px');
        return $box;
    }
}
