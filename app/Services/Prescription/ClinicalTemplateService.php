<?php

declare(strict_types=1);

namespace App\Services\Prescription;

use App\Repositories\Contracts\ClinicalTemplateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClinicalTemplateService
{
    protected ClinicalTemplateRepositoryInterface $templateRepository;

    public function __construct(ClinicalTemplateRepositoryInterface $templateRepository)
    {
        $this->templateRepository = $templateRepository;
    }

    public function getAllTemplates(array $filters = []): Collection
    {
        return $this->templateRepository->all($filters);
    }

    public function getTemplatesPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->templateRepository->paginate($perPage, $filters);
    }

    public function getTemplate(int $id): ?object
    {
        return $this->templateRepository->find($id);
    }

    public function getFacilityTemplates(int $facilityId, bool $includeSystem = true): Collection
    {
        return $this->templateRepository->getByFacility($facilityId, $includeSystem);
    }

    public function getTemplatesByCategory(string $category, int $facilityId): Collection
    {
        return $this->templateRepository->getByCategory($category, $facilityId);
    }

    public function createTemplate(array $data, int $userId): array
    {
        try {
            DB::beginTransaction();

            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
            
            $template = $this->templateRepository->create($data);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create template: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create template: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function updateTemplate(int $id, array $data, int $userId): array
    {
        try {
            $data['updated_by'] = $userId;
            $updated = $this->templateRepository->update($id, $data);

            return [
                'success' => $updated,
                'message' => $updated ? 'Template updated successfully' : 'Template not found',
                'data' => $updated ? $this->templateRepository->find($id) : null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update template: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update template: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function deleteTemplate(int $id): array
    {
        try {
            $deleted = $this->templateRepository->delete($id);

            return [
                'success' => $deleted,
                'message' => $deleted ? 'Template deleted successfully' : 'Template not found',
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete template: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function toggleTemplateStatus(int $id): array
    {
        try {
            $toggled = $this->templateRepository->toggleStatus($id);
            $template = $this->templateRepository->find($id);

            return [
                'success' => $toggled,
                'message' => $toggled ? 'Template status updated' : 'Template not found',
                'data' => $template
            ];
        } catch (\Exception $e) {
            Log::error('Failed to toggle template status: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update template status: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function searchTemplates(string $keyword, int $facilityId): Collection
    {
        return $this->templateRepository->search($keyword, $facilityId);
    }

    public function getTemplateCategories(): array
    {
        return [
            'General Practice',
            'Emergency Medicine',
            'Pediatrics',
            'Geriatrics',
            'Cardiology',
            'Neurology',
            'Pulmonology',
            'Gastroenterology',
            'Endocrinology',
            'Infectious Diseases',
            'Psychiatry',
            'Obstetrics & Gynecology',
            'Orthopedics',
            'Dermatology',
            'Ophthalmology',
            'Dentistry',
            'Urology',
            'Nephrology',
            'Oncology',
            'Rheumatology',
            'Allergy & Immunology',
            'Sports Medicine',
            'Pain Management',
            'Palliative Care'
        ];
    }
}