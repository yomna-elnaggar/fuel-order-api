<?php

namespace App\Interfaces;

interface CRUDRepositoryInterface
{
    public function getAllItems($model, array $filters = [], $perPage = 50, $latest = true, $with = [], $withCount = []);

    public function getItemById($model, $id, $with = [], $withCount = [], array $filters = []);

    public function createItem($model, array $data);

    public function updateItem($model, $id, array $data);

    public function deleteItem($model, $id);

    public function toggleStatus($model, $id, $column = 'active');

    public function getTrashedItems($model, array $filters = [], $perPage = 50);

    public function restoreItem($model, $id);

    public function forceDeleteItem($model, $id);

    public function getCount($model, array $filters = []);
}
