<?php

namespace App\Enums;

enum ModelType: string
{
    case DIAGNOSTIC_ASSISTANT = 'diagnostic_assistant';
    case RISK_STRATIFICATION = 'risk_stratification';
    case TREATMENT_RECOMMENDATION = 'treatment_recommendation';
    case DRUG_INTERACTION_CHECKER = 'drug_interaction_checker';
    case IMAGE_ANALYSIS = 'image_analysis';
    case CLINICAL_DECISION_SUPPORT = 'clinical_decision_support';
    case PREDICTIVE_ANALYTICS = 'predictive_analytics';
    case NATURAL_LANGUAGE_PROCESSING = 'natural_language_processing';
    case TRIAGE_ASSISTANT = 'triage_assistant';

    public function label(): string
    {
        return match($this) {
            self::DIAGNOSTIC_ASSISTANT => 'Diagnostic Assistant',
            self::RISK_STRATIFICATION => 'Risk Stratification',
            self::TREATMENT_RECOMMENDATION => 'Treatment Recommendation',
            self::DRUG_INTERACTION_CHECKER => 'Drug Interaction Checker',
            self::IMAGE_ANALYSIS => 'Image Analysis',
            self::CLINICAL_DECISION_SUPPORT => 'Clinical Decision Support',
            self::PREDICTIVE_ANALYTICS => 'Predictive Analytics',
            self::NATURAL_LANGUAGE_PROCESSING => 'Natural Language Processing',
            self::TRIAGE_ASSISTANT => 'Triage Assistant',
        };
    }
}