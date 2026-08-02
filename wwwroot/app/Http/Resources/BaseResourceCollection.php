<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseResourceCollection extends ResourceCollection
{
    protected $withoutFields = [];

    public $hide = true;

    protected $type = 'default';

    public function type(string $request)
    {
        $this->type = $request;
        return $this;
    }

    public function hide(array $fields)
    {
        $this->withoutFields = $fields;
        return $this;
    }

    public function show(array $fields)
    {
        $this->withoutFields = $fields;
        $this->hide = false;
        return $this;
    }

    public function toArray($request)
    {
        return [
            'lists' => $this->collection->map(function ($item) use ($request) {
                if (!$this->hide) {
                    return collect($item)->only($this->withoutFields)->all();
                }
                return collect($item)->except($this->withoutFields)->all();
            }),
            'pages' => $this->when(!empty($this->pageMeta()), $this->pageMeta())
        ];
    }

    //定义这个方法主要用于分页，当用josn返回的时候是没有 links 和 meta 的
    public function pageMeta()
    {
        try {
            return [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ];
        } catch (\BadMethodCallException $exception) {
            return [];
        }
    }
}
