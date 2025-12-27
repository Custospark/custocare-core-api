<?php

namespace App\Enums;

enum RegulatoryClassification: string
{
    case CLASS_I_MEDICAL_DEVICE = 'class_i_medical_device';
    case CLASS_II_MEDICAL_DEVICE = 'class_ii_medical_device';
    case CLASS_III_MEDICAL_DEVICE = 'class_iii_medical_device';
    case NON_MEDICAL_DEVICE = 'non_medical_device';
    case WELLNESS_TOOL = 'wellness_tool';

    public function label(): string
    {
        return match($this) {
            self::CLASS_I_MEDICAL_DEVICE => 'Class I Medical Device',
            self::CLASS_II_MEDICAL_DEVICE => 'Class II Medical Device',
            self::CLASS_III_MEDICAL_DEVICE => 'Class III Medical Device',
            self::NON_MEDICAL_DEVICE => 'Non-Medical Device',
            self::WELLNESS_TOOL => 'Wellness Tool',
        };
    }

    public function isMedicalDevice(): bool
    {
        return in_array($this, [
            self::CLASS_I_MEDICAL_DEVICE,
            self::CLASS_II_MEDICAL_DEVICE,
            self::CLASS_III_MEDICAL_DEVICE,
        ]);
    }
}