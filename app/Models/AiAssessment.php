<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Enums\ModelType;
use App\Enums\HumanReviewStatus;
use App\Enums\RegulatoryClassification;

/**
 * App\Models\AiAssessment
 *
 * @property int $id
 * @property string $assessment_uuid
 * @property int $facility_id
 * @property int $clinical_encounter_id
 * @property int $visit_id
 * @property int $patient_id
 * @property string $ai_model_name
 * @property string $ai_model_version
 * @property string|null $ai_model_vendor
 * @property ModelType $model_type
 * @property bool $is_fda_cleared
 * @property string|null $fda_clearance_number
 * @property bool $is_ce_marked
 * @property string|null $ce_marking_number
 * @property RegulatoryClassification|null $regulatory_classification
 * @property array $input_features
 * @property string $input_features_hash
 * @property array|null $input_data_sources
 * @property array $output_predictions
 * @property array $output_confidence_scores
 * @property array $recommendations
 * @property array|null $risk_scores
 * @property array|null $alternative_suggestions
 * @property array|null $feature_importance
 * @property string|null $explanation_text
 * @property array|null $supporting_evidence
 * @property HumanReviewStatus $human_review_status
 * @property int|null $reviewed_by_staff_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property array|null $modifications_made
 * @property string|null $rejection_reason
 * @property bool|null $recommendation_implemented
 * @property array|null $actions_taken
 * @property string|null $reason_not_implemented
 * @property bool $clinical_outcome_recorded
 * @property array|null $actual_outcome
 * @property float|null $prediction_accuracy
 * @property bool $adverse_event_flagged
 * @property string|null $adverse_event_description
 * @property array|null $safety_alerts
 * @property int|null $processing_time_ms
 * @property string|null $processing_server
 * @property \Illuminate\Support\Carbon $assessed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property array|null $metadata
 */
class AiAssessment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_assessments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_uuid',
        'facility_id',
        'clinical_encounter_id',
        'visit_id',
        'patient_id',
        'ai_model_name',
        'ai_model_version',
        'ai_model_vendor',
        'model_type',
        'is_fda_cleared',
        'fda_clearance_number',
        'is_ce_marked',
        'ce_marking_number',
        'regulatory_classification',
        'input_features',
        'input_features_hash',
        'input_data_sources',
        'output_predictions',
        'output_confidence_scores',
        'recommendations',
        'risk_scores',
        'alternative_suggestions',
        'feature_importance',
        'explanation_text',
        'supporting_evidence',
        'human_review_status',
        'reviewed_by_staff_id',
        'reviewed_at',
        'review_notes',
        'modifications_made',
        'rejection_reason',
        'recommendation_implemented',
        'actions_taken',
        'reason_not_implemented',
        'clinical_outcome_recorded',
        'actual_outcome',
        'prediction_accuracy',
        'adverse_event_flagged',
        'adverse_event_description',
        'safety_alerts',
        'processing_time_ms',
        'processing_server',
        'assessed_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'model_type' => ModelType::class,
        'regulatory_classification' => RegulatoryClassification::class,
        'human_review_status' => HumanReviewStatus::class,
        'is_fda_cleared' => 'boolean',
        'is_ce_marked' => 'boolean',
        'input_features' => 'array',
        'input_data_sources' => 'array',
        'output_predictions' => 'array',
        'output_confidence_scores' => 'array',
        'recommendations' => 'array',
        'risk_scores' => 'array',
        'alternative_suggestions' => 'array',
        'feature_importance' => 'array',
        'supporting_evidence' => 'array',
        'modifications_made' => 'array',
        'actions_taken' => 'array',
        'actual_outcome' => 'array',
        'safety_alerts' => 'array',
        'metadata' => 'array',
        'recommendation_implemented' => 'boolean',
        'clinical_outcome_recorded' => 'boolean',
        'adverse_event_flagged' => 'boolean',
        'prediction_accuracy' => 'decimal:4',
        'assessed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'assessment_uuid';
    }

    /**
     * Relationship with Facility
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship with ClinicalEncounter
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    /**
     * Relationship with Visit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Relationship with Patient
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship with Staff (reviewer)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reviewed_by_staff_id');
    }

    /**
     * Check if assessment requires human review
     *
     * @return Attribute
     */
    protected function requiresHumanReview(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->human_review_status === 'Pending_Review'
        );
    }

    /**
     * Check if assessment is FDA cleared
     *
     * @return Attribute
     */
    protected function isRegulatoryApproved(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->is_fda_cleared || $this->is_ce_marked
        );
    }

    /**
     * Calculate assessment age in days
     *
     * @return Attribute
     */
    protected function assessmentAgeDays(): Attribute
    {
        return Attribute::make(
            get: fn (): int => now()->diffInDays($this->assessed_at)
        );
    }

    /**
     * Check if assessment has high confidence scores
     *
     * @return Attribute
     */
    protected function hasHighConfidence(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if (empty($this->output_confidence_scores)) {
                    return false;
                }
                
                $confidenceScores = array_values($this->output_confidence_scores);
                $averageConfidence = array_sum($confidenceScores) / count($confidenceScores);
                
                return $averageConfidence >= 0.85;
            }
        );
    }
}