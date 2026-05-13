<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referral\StoreReferralRequest;
use App\Http\Requests\Referral\UpdateReferralRequest;
use App\Http\Resources\ReferralResource;
use App\Http\Resources\ReferralCollection;
use App\Services\Interfaces\ReferralServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReferralController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param ReferralServiceInterface $referralService
     * @return void
     */
    public function __construct(protected ReferralServiceInterface $referralService)
    {
        //
    }

    /**
     * Display a listing of referrals.
     */
    public function index(Request $request): ReferralCollection
    {
        $filters = $request->only([
            'status', 'priority', 'referral_type', 
            'referring_staff_id', 'receiving_staff_id',
            'from_date', 'to_date', 'search'
        ]);
        
        $perPage = $request->query('per_page', 15);
        
        return $this->referralService->getAllReferrals($filters, $perPage);
    }

    /**
     * Store a newly created referral in storage.
     */
    public function store(StoreReferralRequest $request): ReferralResource
    {
        $data = $request->validated();
        return $this->referralService->createReferral($data);
    }

    /**
     * Display the specified referral.
     */
    public function show(string $uuid): ReferralResource
    {
        return $this->referralService->getReferralByUuid($uuid);
    }

    /**
     * Update the specified referral in storage.
     */
    public function update(UpdateReferralRequest $request, string $uuid): ReferralResource
    {
        // First get the referral to get its ID
        $referral = $this->referralService->getReferralByUuid($uuid);
        $data = $request->validated();
        
        return $this->referralService->updateReferral($referral->id, $data);
    }

    /**
     * Remove the specified referral from storage.
     */
    public function destroy(string $uuid): Response
    {
        $referral = $this->referralService->getReferralByUuid($uuid);
        $this->referralService->deleteReferral($referral->id);
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Accept a referral.
     */
    public function accept(string $uuid, Request $request): ReferralResource
    {
        $referral = $this->referralService->getReferralByUuid($uuid);
        $receivingStaffId = $request->input('receiving_staff_id');
        
        if (!$receivingStaffId) {
            return response()->json([
                'message' => 'Receiving staff ID is required.'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        return $this->referralService->acceptReferral($referral->id, $receivingStaffId);
    }

    /**
     * Reject a referral.
     */
    public function reject(string $uuid, Request $request): ReferralResource
    {
        $referral = $this->referralService->getReferralByUuid($uuid);
        $reason = $request->input('reason');
        
        return $this->referralService->rejectReferral($referral->id, $reason);
    }

    /**
     * Complete a referral.
     */
    public function complete(string $uuid): ReferralResource
    {
        $referral = $this->referralService->getReferralByUuid($uuid);
        return $this->referralService->completeReferral($referral->id);
    }

    /**
     * Cancel a referral.
     */
    public function cancel(string $uuid, Request $request): ReferralResource
    {
        $referral = $this->referralService->getReferralByUuid($uuid);
        $reason = $request->input('reason');
        
        return $this->referralService->cancelReferral($referral->id, $reason);
    }

    /**
     * Get referrals for a specific patient.
     */
    public function patientReferrals(int $patientId, Request $request): ReferralCollection
    {
        $filters = $request->only([
            'status', 'priority', 'referral_type',
            'from_date', 'to_date', 'search'
        ]);
        
        $perPage = $request->query('per_page', 15);
        
        return $this->referralService->getReferralsForPatient($patientId, $filters, $perPage);
    }

    /**
     * Get referrals for a specific facility.
     */
    public function facilityReferrals(int $facilityId, Request $request): ReferralCollection
    {
        $filters = $request->only([
            'status', 'priority', 'referral_type',
            'from_date', 'to_date', 'search'
        ]);
        
        $perPage = $request->query('per_page', 15);
        
        return $this->referralService->getReferralsForFacility($facilityId, $filters, $perPage);
    }

    /**
     * Get pending referrals.
     */
    public function pending(Request $request): ReferralCollection
    {
        $filters = $request->only([
            'priority', 'referral_type',
            'from_date', 'to_date', 'search'
        ]);
        
        $perPage = $request->query('per_page', 15);
        
        return $this->referralService->getPendingReferrals($filters, $perPage);
    }
}