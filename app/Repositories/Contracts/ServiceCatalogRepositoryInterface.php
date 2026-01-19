<?php

namespace App\Repositories\Contracts;

use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServiceCatalogRepositoryInterface
{
    public function findByUuidAndFacility(string $uuid, int $facilityId): ?ServiceCatalog;
    public function findByUuid(string $uuid): ?ServiceCatalog;
    public function findByServiceCodeAndFacility(string $serviceCode, int $facilityId): ?ServiceCatalog;
    public function findByServiceCode(string $serviceCode): ?ServiceCatalog;
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;
    public function all(array $filters = []): Collection;
    public function create(array $data): ServiceCatalog;
    public function update(ServiceCatalog $serviceCatalog, array $data): bool;
    public function delete(ServiceCatalog $serviceCatalog): ?bool;
    public function restore(ServiceCatalog $serviceCatalog): bool;
    public function forceDelete(ServiceCatalog $serviceCatalog): ?bool;
    public function getEffectiveServices(string $date, array $filters = []): Collection;
    public function getByCodeSystem(string $codeSystem, array $filters = []): Collection;
    public function getByCategory(string $category, array $filters = []): Collection;
    public function search(string $searchTerm, array $filters = []): Collection;
    public function serviceCodeExists(string $serviceCode, int $facilityId, ?string $excludeUuid = null): bool;
    public function serviceCodeExistsGlobally(string $serviceCode, ?string $excludeUuid = null): bool;
    public function getByIdsAndFacility(array $ids, int $facilityId): Collection;
    public function getStatusCounts(int $facilityId): array;
    public function getCategoryCounts(int $facilityId): array;
    public function getExpiredServices(int $facilityId, ?string $date = null): Collection;
    public function getServicesExpiringSoon(int $facilityId, int $daysThreshold = 30): Collection;
}