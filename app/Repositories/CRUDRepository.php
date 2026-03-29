<?php

namespace App\Repositories;

use App\Interfaces\CRUDRepositoryInterface;

class CRUDRepository implements CRUDRepositoryInterface
{
    public function getAllItems($model, array $filters = [], $perPage = 50, $latest = true, $with = [], $withCount = [])
    {
        $query = $model::query();

        if (!empty($with)) {
            $query->with($with);
        }

        if (!empty($withCount)) {
            $query->withCount($withCount);
        }

        if ($latest) {
            $query->latest();
        }

        if (isset($filters['ids'])) {
            $query->whereIn('id', (array)$filters['ids']);
        }

        if (isset($filters['not_ids'])) {
            $query->whereNotIn('id', (array)$filters['not_ids']);
        }

        if (method_exists($model, 'scopeFilter')) {
            $query->filter($filters);
        }

        if ($perPage) {
            return $query->paginate($perPage)->appends(request()->all());
        }

        return $query->get();
    }

    public function getItemById($model, $id, $with = [], $withCount = [], array $filters = [])
    {
        $query = $model::query();

        if (!empty($with)) {
            $query->with($with);
        }

        if (!empty($withCount)) {
            $query->withCount($withCount);
        }

        if (!empty($filters) && method_exists($model, 'scopeFilter')) {
            $query->filter($filters);
        }

        return $query->find($id);
    }

    public function createItem($model, array $data)
    {
        return $model::create($data);
    }

    public function updateItem($model, $id, array $data)
    {
        $item = $this->getItemById($model, $id);
        if ($item) {
            $item->update($data);
        }
        return $item;
    }

    public function deleteItem($model, $id)
    {
        $item = $this->getItemById($model, $id);
        if ($item) {
            return $item->delete();
        }
        return false;
    }

    public function toggleStatus($model, $id, $column = 'active')
    {
        $item = $this->getItemById($model, $id);
        if ($item) {
            $item->$column = !$item->$column;
            $item->save();
        }
        return $item;
    }

    public function getTrashedItems($model, array $filters = [], $perPage = 50)
    {
        $query = $model::onlyTrashed();

        if (method_exists($model, 'scopeFilter')) {
            $query->filter($filters);
        }

        if ($perPage) {
            return $query->paginate($perPage)->appends(request()->all());
        }

        return $query->get();
    }

    public function restoreItem($model, $id)
    {
        $item = $model::onlyTrashed()->findOrFail($id);
        $item->restore();
        return $item;
    }

    public function forceDeleteItem($model, $id)
    {
        $item = $model::onlyTrashed()->findOrFail($id);
        return $item->forceDelete();
    }

    public function getCount($model, array $filters = [])
    {
        $query = $model::query();

        if (method_exists($model, 'scopeFilter')) {
            $query->filter($filters);
        }

        return $query->count();
    }
}
