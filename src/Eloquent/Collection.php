<?php

namespace Staudenmeir\LaravelAdjacencyList\Eloquent;

use Illuminate\Database\Eloquent\Collection as Base;

class Collection extends Base
{
    public function toTree($relation = 'children')
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $parentKeyName = $this->first()->getParentKeyName();
        $localKeyName = $this->first()->getLocalKeyName();
        $depthName = $this->first()->getDepthName();

        $depths = $this->pluck($depthName);

        $tree = new static(
            $this->where($depthName, $depths->min())->values()
        );

        $items = $this->groupBy($parentKeyName);

        foreach ($this->items as $item) {
            $item->setRelation($relation, $items[$item->$localKeyName] ?? new static());
        }

        return $tree;
    }
}
