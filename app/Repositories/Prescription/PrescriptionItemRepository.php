<?php

declare(strict_types=1);

namespace App\Repositories\Prescription;

use App\Models\PrescriptionItem;
use App\Repositories\Contracts\PrescriptionItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PrescriptionItemRepository implements PrescriptionItemRepositoryInterface
{
    protected PrescriptionItem $model;

    public function __construct(PrescriptionItem $model)
    {
        $this->model = $model;
    }

    public function create(array $data): object
    {
        return $this->model->create($data);
    }

    public function createMany(int $prescriptionId, array $items): Collection
    {
        $createdItems = [];
        
        foreach ($items as $item) {
            $item['prescription_id'] = $prescriptionId;
            $createdItems[] = $this->create($item);
        }
        
        return new Collection($createdItems);
    }

    public function update(int $id, array $data): bool
    {
        $item = $this->model->find($id);
        
        if (!$item) {
            return false;
        }
        
        return $item->update($data);
    }

    public function delete(int $id): bool
    {
        $item = $this->model->find($id);
        
        if (!$item) {
            return false;
        }
        
        return $item->delete();
    }

    public function deleteByPrescription(int $prescriptionId): bool
    {
        return $this->model->where('prescription_id', $prescriptionId)->delete();
    }

    public function getByPrescription(int $prescriptionId): Collection
    {
        return $this->model->where('prescription_id', $prescriptionId)->get();
    }
}