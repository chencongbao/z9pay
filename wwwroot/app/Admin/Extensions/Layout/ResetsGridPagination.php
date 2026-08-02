<?php

namespace App\Admin\Extensions\Layout;

trait ResetsGridPagination
{
    protected function queryWithoutGridPage(): array
    {
        $query = request()->all();

        foreach (array_keys($query) as $key) {
            $isNamedPage = is_string($key)
                && str_ends_with($key, '_page')
                && $key !== 'per_page'
                && !str_ends_with($key, '_per_page');

            if ($key === 'page' || $isNamedPage) {
                unset($query[$key]);
            }
        }

        return $query;
    }
}
