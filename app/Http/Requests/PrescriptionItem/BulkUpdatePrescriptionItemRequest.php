<?php

declare(strict_types=1);

namespace App\Http\Requests\PrescriptionItem;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdatePrescriptionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'exists:prescription_items,id'],
            'items.*.medication_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.brand_name' => ['nullable', 'string', 'max:255'],
            'items.*.strength' => ['nullable', 'string', 'max:100'],
            'items.*.dosage_form' => ['required_with:items', 'string', 'in:Tablet,Capsule,Injection (IV/IM/SC),Syrup / Liquid,Suspension,Cream,Ointment,Gel,Lotion,Eye Drops,Ear Drops,Nasal Spray,Inhaler,Nebulizer Solution,Patch (Transdermal),Suppository (Rectal),Suppository (Vaginal),Powder,Foam,Shampoo,Mouthwash / Gargle,Lozenge / Troche,Chewing Gum,Implant,Insert (Vaginal Ring),Wafer (Oral Dissolving),Film (Oral Dissolving)'],
            'items.*.dosage_quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.dosage_unit' => ['required_with:items', 'string', 'in:tablet(s),capsule(s),milligram (mg),microgram (mcg),gram (g),milliliter (ml),liter (L),international unit (IU),drop(s),puff(s),spray(s),inhalation(s),application(s),patch(es),suppository(ies),pump(s),actuation(s),vial(s),ampule(s)'],
            'items.*.frequency' => ['required_with:items', 'string', 'in:Once daily (OD) - Take 1 time per day,Twice daily (BD) - Take 2 times per day,Three times daily (TDS) - Take 3 times per day,Four times daily (QID) - Take 4 times per day,Every 2 hours - Take every 2 hours,Every 3 hours - Take every 3 hours,Every 4 hours - Take every 4 hours,Every 6 hours - Take every 6 hours,Every 8 hours - Take every 8 hours,Every 12 hours - Take every 12 hours,Every 24 hours - Take every 24 hours,At bedtime (HS) - Take before sleeping,Before meals (AC) - Take 30 minutes before food,After meals (PC) - Take immediately after food,As needed (PRN) - Take only when symptoms occur,Immediately (STAT) - Take right now,Once weekly - Take 1 time per week,Twice weekly - Take 2 times per week,Once monthly - Take 1 time per month,Every other day - Take once every 2 days,With specific meals - Breakfast/lunch/dinner only'],
            'items.*.duration_value' => ['required_with:items', 'integer', 'min:1'],
            'items.*.duration_unit' => ['required_with:items', 'string', 'in:Day(s),Week(s),Month(s),Year(s)'],
            'items.*.route' => ['required_with:items', 'string', 'in:By mouth (Oral),Under the tongue (Sublingual),Between gum and cheek (Buccal),Into the vein (Intravenous/IV),Into the muscle (Intramuscular/IM),Under the skin (Subcutaneous/SC),Into the skin (Intradermal),On the skin (Topical),Through the skin (Transdermal patch),Into the eye (Ophthalmic),Into the ear (Otic),Into the nose (Nasal),Inhaled into lungs (Inhalation),Into the rectum (Rectal),Into the vagina (Vaginal),Into the bladder (Intravesical),Into the joint (Intra-articular),Into the spine (Intrathecal)'],
            'items.*.instructions' => ['nullable', 'string'],
            'items.*.as_needed' => ['required_with:items', 'boolean'],
            'items.*.as_needed_reason' => ['nullable', 'string'],
            'items.*.administration_instructions' => ['required_with:items', 'string', 'in:No special instructions,Take with food,Take before meals (30 minutes before),Take after meals (immediately after),Take on empty stomach (1 hour before or 2 hours after meals),Take with plenty of water,Take with milk,Avoid grapefruit juice,Avoid alcohol,Avoid dairy products,Shake well before use,Refrigerate - do not freeze,Do not refrigerate - store at room temperature,Protect from light,Chew tablet completely before swallowing,Dissolve under tongue - do not swallow,Swallow whole - do not crush or chew,Crush tablet and mix with soft food,Open capsule and mix with applesauce,Apply to clean, dry skin,Wash hands before and after application,Do not use more than directed'],
            'items.*.refills' => ['required_with:items', 'string', 'in:0 refills - One time only,1 refill,2 refills,3 refills,4 refills,5 refills,6 refills,12 refills - One year supply,Unlimited refills as needed'],
            'items.*.refill_instructions' => ['nullable', 'string'],
            'items.*.medication_type' => ['nullable', 'string', 'in:Prescription only (Rx required),Over-the-counter (OTC),Controlled substance (Special prescription required),Antibiotic (Complete full course),Antibiotic (Complete full course) - High priority,Steroid (Tapering required),Opioid (High risk - monitor),Insulin (Refrigeration required),Biologic (Special handling),Chemotherapy (Special handling),Vaccine (Cold chain required)'],
            'items.*.monitoring_required' => ['nullable', 'string', 'in:No specific monitoring needed,Monitor blood pressure regularly,Monitor blood glucose levels,Monitor kidney function (Creatinine),Monitor liver function (LFTs),Monitor blood counts (CBC),Monitor INR (Blood thinning test),Monitor potassium levels,Monitor drug levels (Therapeutic drug monitoring),Monitor for side effects'],
            'items.*.common_side_effects' => ['nullable', 'string', 'in:No common side effects,May cause drowsiness - Avoid driving,May cause dizziness - Rise slowly,May cause nausea - Take with food,May cause dry mouth,May cause headache,May cause stomach upset,May cause diarrhea,May cause constipation,May cause skin rash - Report immediately,May cause swelling - Report immediately'],
            'items.*.clinical_reasoning' => ['nullable', 'string'],
            'items.*.substitution_instructions' => ['nullable', 'string'],
            'items.*.substitution' => ['required_with:items', 'string', 'in:Generic substitution allowed,Brand name only - No substitution,Therapeutic substitution allowed (same class),Dispense as written (DAW)'],
            'items.*._destroy' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one medication item is required.',
            'items.*.dosage_form.in' => 'The dosage form is invalid. Please select from the list.',
            'items.*.dosage_unit.in' => 'The dosage unit is invalid. Please select from the list.',
            'items.*.frequency.in' => 'The frequency is invalid. Please select from the list.',
            'items.*.duration_unit.in' => 'The duration unit is invalid. Please select Day(s), Week(s), Month(s), or Year(s).',
            'items.*.route.in' => 'The route is invalid. Please select from the list.',
            'items.*.administration_instructions.in' => 'The administration instruction is invalid. Please select from the list.',
            'items.*.refills.in' => 'The refill option is invalid. Please select from the list.',
            'items.*.substitution.in' => 'The substitution option is invalid. Please select from the list.',
        ];
    }
}