<?php

declare(strict_types=1);

namespace App\Repositories\Lab;

use App\Models\LabTemplateField;
use App\Repositories\Lab\Contracts\LabTemplateFieldRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LabTemplateFieldRepository implements LabTemplateFieldRepositoryInterface
{
    /**
     * @var LabTemplateField
     */
    protected LabTemplateField $model;

    /**
     * Constructor.
     *
     * @param LabTemplateField $model
     */
    public function __construct(LabTemplateField $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?LabTemplateField
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?LabTemplateField
    {
        return $this->model->where('field_uuid', $uuid)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findByCode(string $code, int $templateId): ?LabTemplateField
    {
        return $this->model->where('code', $code)
            ->where('template_id', $templateId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        if (!empty($filters['data_type'])) {
            $query->ofDataType($filters['data_type']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        if (isset($filters['is_required'])) {
            $filters['is_required'] ? $query->required() : $query->where('is_required', false);
        }

        if (isset($filters['is_critical'])) {
            $filters['is_critical'] ? $query->critical() : $query->where('is_critical', false);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%');
            });
        }

        $query->ordered();

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByTemplate(int $templateId, array $filters = []): Collection
    {
        $query = $this->model->where('template_id', $templateId);

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        if (isset($filters['is_required'])) {
            $filters['is_required'] ? $query->required() : $query->where('is_required', false);
        }

        $query->ordered();

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveFieldsByTemplate(int $templateId): Collection
    {
        return $this->model->where('template_id', $templateId)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredFieldsByTemplate(int $templateId): Collection
    {
        return $this->model->where('template_id', $templateId)
            ->required()
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalFieldsByTemplate(int $templateId): Collection
    {
        return $this->model->where('template_id', $templateId)
            ->critical()
            ->active()
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): LabTemplateField
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(LabTemplateField $field, array $data): bool
    {
        return DB::transaction(function () use ($field, $data) {
            return $field->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(LabTemplateField $field): bool
    {
        return DB::transaction(function () use ($field) {
            return $field->delete();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->model->withTrashed()->find($id)?->restore() ?? false;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function activate(LabTemplateField $field): bool
    {
        return DB::transaction(function () use ($field) {
            return $field->activate();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(LabTemplateField $field): bool
    {
        return DB::transaction(function () use ($field) {
            return $field->deactivate();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function bulkCreate(int $templateId, array $fields): Collection
    {
        return DB::transaction(function () use ($templateId, $fields) {
            $createdFields = [];
            
            foreach ($fields as $index => $fieldData) {
                $fieldData['template_id'] = $templateId;
                $fieldData['display_order'] = $fieldData['display_order'] ?? $index;
                $createdFields[] = $this->model->create($fieldData);
            }
            
            return new Collection($createdFields);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function bulkUpdateDisplayOrders(array $orders): bool
    {
        return DB::transaction(function () use ($orders) {
            foreach ($orders as $fieldId => $displayOrder) {
                $this->model->where('id', $fieldId)
                    ->update(['display_order' => $displayOrder]);
            }
            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function existsByNameInTemplate(int $templateId, string $name, ?int $excludeId = null): bool
    {
        $query = $this->model->where('template_id', $templateId)
            ->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function getWithTemplate(int $id): ?LabTemplateField
    {
        return $this->model->with('template')->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function duplicateFromTemplate(int $sourceTemplateId, int $targetTemplateId): Collection
    {
        return DB::transaction(function () use ($sourceTemplateId, $targetTemplateId) {
            $sourceFields = $this->getActiveFieldsByTemplate($sourceTemplateId);
            $newFields = [];
            
            foreach ($sourceFields as $sourceField) {
                $newFieldData = $sourceField->toArray();
                unset($newFieldData['id'], $newFieldData['field_uuid'], $newFieldData['created_at'], $newFieldData['updated_at']);
                $newFieldData['template_id'] = $targetTemplateId;
                $newFields[] = $this->model->create($newFieldData);
            }
            
            return new Collection($newFields);
        });
    }
}