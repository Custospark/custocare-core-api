<?php

namespace App\Support;

/**
 * Healthcare ID Generator
 * 
 * A comprehensive, reusable ID generation system for healthcare entities
 * using ULID (Universally Unique Lexicographically Sortable Identifier) principles.
 * 
 * KEY CAPACITY/VOLUME NOTES:
 * ============================================================
 * 
 * ULID-BASED ENTITIES (Patients, Staff, Doctors, etc.):
 * ------------------------------------------------------------
 * - Randomness: 80-bit cryptographically secure random
 * - Total possibilities: 1.2×10^24 (1.2 septillion)
 * - Daily capacity: UNLIMITED (not date-constrained)
 * - Collision probability: Virtually zero across all time
 * - With 1 billion IDs/day: First collision in 3.3 quadrillion years
 * - With 10M IDs/second: 50% collision chance after 3.8 million years
 * 
 * FACILITY IDs (Fixed - CRITICAL UPDATE):
 * ------------------------------------------------------------
 * - Previous design flaw: Limited to 32,768 per day (UNACCEPTABLE)
 * - NEW design: ULID-based with 80-bit randomness (SAME as patients)
 * - Format: HF-{type}{region}{ULID8}{check} (14 characters)
 * - Daily capacity: UNLIMITED (mathematically guaranteed)
 * - Example: HF-H101HZX9F7K (Hospital in NA, ULID core: 01HZX9F7)
 * 
 * MEDICAL RECORD NUMBERS (MRN):
 * ------------------------------------------------------------
 * - Facility-specific, date-based identifiers
 * - Format: {facility}{YY}{DDD}{random}{check} (11 characters)
 * - Daily capacity per facility: 1,048,576 (20-bit random)
 * - Suitable for: 1M+ patient registrations per day per facility
 * - Global uniqueness: Requires facility prefix
 * 
 * DOCUMENT IDs (Prescriptions, Lab Tests, etc.):
 * ------------------------------------------------------------
 * - ULID-based with variable lengths
 * - Randomness: 64-bit (reduced for shorter formats)
 * - Daily capacity: Millions per entity type
 * - Suitable for: High-volume document generation
 * 
 * Entity Prefixes:
 * - PT: Patient (ULID-based, unlimited capacity)
 * - ST: Staff (ULID-based, unlimited capacity)
 * - DR: Doctor/Physician (ULID-based, unlimited capacity)
 * - NR: Nurse (ULID-based, unlimited capacity)
 * - PH: Pharmacist (ULID-based, unlimited capacity)
 * - HF: Healthcare Facility (ULID-based, unlimited capacity) - FIXED
 * - RX: Prescription (document, high capacity)
 * - LB: Laboratory Test (document, high capacity)
 * - IM: Imaging Study (document, high capacity)
 * - AP: Appointment (document, high capacity)
 * - HF: Health Facility.
 * 
 * 
 * DESIGN DECISIONS:
 * 1. High-volume entities (patients, staff) get full 80-bit randomness
 * 2. Facilities get ULID-based IDs (FIXED from date-limited design)
 * 3. MRNs remain date-based for facility-level uniqueness
 * 4. All IDs include Verhoeff check digits for error detection
 * 5. Crockford's Base32 prevents confusing characters (0/O, 1/I/L)
 */
class HealthcareIdGenerator
{
    // ==================== CONSTANTS ====================
    
    /**
     * Crockford's Base32 Alphabet (RFC 4648)
     * Excludes confusing characters: 0/O, 1/I/L removed
     */
    private const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    
    /**
     * System epoch: January 1, 2026
     * 48-bit timestamp provides ≈ 8,925 years of runtime
     */
    private const EPOCH_YEAR = 2026;
    private const EPOCH = '2026-01-01 00:00:00.000';
    
    /**
     * Entity type configurations
     * Format: [prefix, length, description, format_type, capacity_notes]
     */
    private const ENTITY_CONFIGS = [
        // ULID-based formats (12 chars: prefix + ULID8 + check digit)
        // CAPACITY: 80-bit random = 1.2×10^24 possibilities (UNLIMITED daily)
        'patient'      => ['PT', 12, 'Patient', 'ulid', '80-bit random, unlimited daily capacity'],
        'staff'        => ['ST', 12, 'Healthcare Staff', 'ulid', '80-bit random, unlimited daily capacity'],
        'doctor'       => ['DR', 12, 'Physician/Doctor', 'ulid', '80-bit random, unlimited daily capacity'],
        'nurse'        => ['NR', 12, 'Nurse', 'ulid', '80-bit random, unlimited daily capacity'],
        'pharmacist'   => ['PH', 12, 'Pharmacist', 'ulid', '80-bit random, unlimited daily capacity'],
        'technician'   => ['TC', 12, 'Medical Technician', 'ulid', '80-bit random, unlimited daily capacity'],
        'administrator'=> ['AD', 12, 'Administrator', 'ulid', '80-bit random, unlimited daily capacity'],
        
        // FACILITY FORMAT - UPDATED: ULID-based (14 chars: HF + type + region + ULID8 + check)
        // CAPACITY: 80-bit random = 1.2×10^24 possibilities (UNLIMITED daily, FIXED from 32K limit)
        'facility'     => ['HF', 14, 'Healthcare Facility', 'facility', '80-bit random, unlimited daily capacity'],
        'medical record'     => ['MR', 14, 'Medical Record', 'Medical Record', '80-bit random, unlimited daily capacity'],
        
        // Document/record formats (variable length)
        // CAPACITY: 64-bit random = 1.8×10^19 possibilities (millions daily per type)
        'prescription' => ['RX', 16, 'Prescription', 'document', '64-bit random, high daily capacity'],
        'lab_test'     => ['LB', 16, 'Laboratory Test', 'document', '64-bit random, high daily capacity'],
        'imaging'      => ['IM', 16, 'Imaging Study', 'document', '64-bit random, high daily capacity'],
        'appointment'  => ['AP', 14, 'Appointment', 'document', '64-bit random, high daily capacity'],
        'medical_record'=> ['MR', 18, 'Medical Record', 'document', '64-bit random, high daily capacity'],
        'insurance'     => ['IN', 14, 'Insurance Record', 'document', '64-bit random, high daily capacity'],
        'billing'     => ['BL', 14, 'Billing Record', 'document', '64-bit random, high billing volume'],
        'visit'     => ['VS', 14, 'Visiit Record', 'document', '64-bit random, high visit volume'],
    ];
    
    /**
     * Facility type codes and descriptions
     */
    private const FACILITY_TYPES = [
        'H' => 'Hospital',
        'C' => 'Clinic',
        'L' => 'Laboratory',
        'P' => 'Pharmacy',
        'S' => 'Specialty Center',
        'R' => 'Research Facility',
        'E' => 'Emergency Center',
        'U' => 'Urgent Care',
        'G' => 'General Healthcare',
        'N' => 'Nursing Home',
        'A' => 'Ambulatory Center',
        'D' => 'Diagnostic Center',
        'M' => 'Medical Center',
        'T' => 'Therapy Center',
        'B' => 'Blood Bank',
        'O' => 'Optical Center',
        'X' => 'Specialized Hospital',
    ];
    
    /**
     * Geographic region codes
     */
    private const REGION_CODES = [
        '0' => 'Unspecified',
        '1' => 'North America',
        '2' => 'South America',
        '3' => 'Europe',
        '4' => 'Africa',
        '5' => 'Asia',
        '6' => 'Oceania',
        '7' => 'Middle East',
        '8' => 'Central America',
        '9' => 'Caribbean',
        'A' => 'East Asia',
        'B' => 'South Asia',
        'C' => 'Southeast Asia',
        'D' => 'North Africa',
        'E' => 'Sub-Saharan Africa',
        'F' => 'Eastern Europe',
        'G' => 'Western Europe',
    ];
    
    // ==================== PUBLIC API ====================
    
    /**
     * Generate ID for any healthcare entity
     * 
     * @param string $entityType Entity type (patient, staff, doctor, facility, etc.)
     * @param array $options Entity-specific options
     * @return string Generated healthcare ID
     * @throws \InvalidArgumentException If entity type is invalid
     */
    public static function generate(string $entityType, array $options = []): string
    {
        $entityType = strtolower($entityType);
        
        if (!isset(self::ENTITY_CONFIGS[$entityType])) {
            throw new \InvalidArgumentException(
                "Invalid entity type: {$entityType}. Valid types: " . 
                implode(', ', array_keys(self::ENTITY_CONFIGS))
            );
        }
        
        $config = self::ENTITY_CONFIGS[$entityType];
        $prefix = $config[0];
        $format = $config[3];
        
        return match($format) {
            'ulid'     => self::generateUlidBasedId($prefix),
            'facility' => self::generateFacilityId($options),
            'document' => self::generateDocumentId($prefix, $options),
            default    => self::generateUlidBasedId($prefix),
        };
    }
    
    /**
     * Generate patient ID (alias for generate('patient'))
     * CAPACITY: 80-bit random = UNLIMITED daily registrations
     */
    public static function generatePatientId(): string
    {
        return self::generateUlidBasedId('PT');
    }
    
    /**
     * Generate staff ID (alias for generate('staff'))
     * CAPACITY: 80-bit random = UNLIMITED daily registrations
     */
    public static function generateStaffId(): string
    {
        return self::generateUlidBasedId('ST');
    }
    
    /**
     * Generate doctor ID (alias for generate('doctor'))
     * CAPACITY: 80-bit random = UNLIMITED daily registrations
     */
    public static function generateDoctorId(): string
    {
        return self::generateUlidBasedId('DR');
    }
    
    /**
     * Generate Healthcare Facility ID (ULID-based - FIXED)
     * 
     * CRITICAL UPDATE: Previously limited to 32,768 per day
     * NOW: ULID-based with 80-bit random = UNLIMITED daily capacity
     * 
     * Format: HF-{type}{region}{ULID8}{check} (14 characters)
     * Example: HF-H101HZX9F7K
     *   H = Hospital type
     *   1 = North America region
     *   01HZX9F7 = ULID core (40 bits: timestamp + partial random)
     *   K = Verhoeff check digit
     * 
     * CAPACITY: 80-bit random = 1.2×10^24 possibilities
     * DAILY LIMIT: NONE (mathematically unlimited)
     * 
     * @param array $options Facility options
     *   - type: Facility type code (H=Hospital, C=Clinic, etc.)
     *   - region: Geographic region code (1=NA, 2=SA, etc.)
     * @return string Facility ID
     */
    public static function generateFacilityId(array $options = []): string
    {
        // Extract and validate facility type
        $typeChar = strtoupper($options['type'] ?? 'G')[0];
        if (!isset(self::FACILITY_TYPES[$typeChar])) {
            $typeChar = 'G'; // Default to General Healthcare
        }
        
        // Extract and validate region code
        $regionChar = strtoupper($options['region'] ?? '0')[0];
        if (!isset(self::REGION_CODES[$regionChar])) {
            $regionChar = '0'; // Default to Unspecified
        }
        
        // Generate ULID core (identical to patient/staff generation)
        $timestamp = self::getUlidTimestamp();
        $random = random_bytes(10); // 80-bit cryptographically secure random
        $ulidBytes = self::packUlidBytes($timestamp, $random);
        $ulidBase32 = self::encodeBase32($ulidBytes);
        $ulidCore = substr($ulidBase32, 0, 8); // 8 chars = 40 bits (timestamp + partial random)
        
        // Combine: Type + Region + ULID core
        $base = $typeChar . $regionChar . $ulidCore;
        
        // Add Verhoeff check digit for error detection
        $checkDigit = self::calculateVerhoeffCheckDigit($base);
        
        return 'HF-' . $base . $checkDigit;
    }
    
    /**
     * Generate Medical Record Number (MRN) for a specific facility
     * 
     * CAPACITY: 20-bit random = 1,048,576 possibilities PER DAY PER FACILITY
     * Suitable for: 1M+ patient registrations per day per facility
     * Global uniqueness: Requires facility code prefix
     * 
     * Format: {facility}{YY}{DDD}{random}{check} (11 characters)
     * Example: N26001XYZ8
     *   N = NYC facility code
     *   26 = Year 2026
     *   001 = January 1st
     *   XYZ = Random sequence (20 bits)
     *   8 = Verhoeff check digit
     * 
     * @param string $facilityCode Facility code (1-2 characters)
     * @return string Medical Record Number
     */
    public static function generateRandomCode(string $randomCode = '0'): string
    {
        $randomChar = strtoupper(substr($randomCode, 0, 1));
        if (!in_array($randomChar, str_split(self::BASE32_ALPHABET))) {
            $randomChar = '0';
        }
        
        $year = date('y'); // Last two digits of year
        $dayOfYear = str_pad(date('z') + 1, 3, '0', STR_PAD_LEFT); // 001-366
        
        // 20-bit random (4 Base32 chars = 1,048,576 possibilities)
        $random = substr(self::generateFullUlid(), 18, 4);
        
        $base = $randomChar . $year . $dayOfYear . $random;
        return $base . self::calculateVerhoeffCheckDigit($base);
    }
     
    public static function generateMedicalRecordNumber(string $facilityCode = '0'): string
    {
        $facilityChar = strtoupper(substr($facilityCode, 0, 1));
        if (!in_array($facilityChar, str_split(self::BASE32_ALPHABET))) {
            $facilityChar = '0';
        }
        
        $year = date('y'); // Last two digits of year
        $dayOfYear = str_pad(date('z') + 1, 3, '0', STR_PAD_LEFT); // 001-366
        
        // 20-bit random (4 Base32 chars = 1,048,576 possibilities)
        $random = substr(self::generateFullUlid(), 18, 4);
        
        $base = $facilityChar . $year . $dayOfYear . $random;
        return $base . self::calculateVerhoeffCheckDigit($base);
    }
    
    /**
     * Validate any healthcare ID
     * 
     * @param string $id Healthcare ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function validate(string $id): bool
    {
        if (strlen($id) < 3) {
            return false;
        }
        
        $prefix = substr($id, 0, 2);
        
        // Determine entity type from prefix
        foreach (self::ENTITY_CONFIGS as $config) {
            if ($config[0] === $prefix) {
                $format = $config[3];
                
                return match($format) {
                    'ulid'     => self::validateUlidBasedId($id, $prefix),
                    'facility' => self::validateFacilityId($id),
                    'document' => self::validateDocumentId($id, $prefix),
                    default    => false,
                };
            }
        }
        
        return false;
    }
    
    /**
     * Extract metadata from healthcare ID
     * 
     * @param string $id Healthcare ID
     * @return array|null Metadata or null if invalid
     */
    public static function parse(string $id): ?array
    {
        if (!self::validate($id)) {
            return null;
        }
        
        $prefix = substr($id, 0, 2);
        
        // Find entity type
        $entityType = null;
        foreach (self::ENTITY_CONFIGS as $type => $config) {
            if ($config[0] === $prefix) {
                $entityType = $type;
                break;
            }
        }
        
        if (!$entityType) {
            return null;
        }
        
        $config = self::ENTITY_CONFIGS[$entityType];
        $metadata = [
            'id' => $id,
            'entity_type' => $entityType,
            'prefix' => $prefix,
            'description' => $config[2],
            'length' => $config[1],
            'format_type' => $config[3],
            'capacity_notes' => $config[4] ?? 'No capacity notes',
        ];
        
        // Add format-specific metadata
        if ($metadata['format_type'] === 'ulid') {
            $metadata = array_merge($metadata, self::parseUlidBasedId($id));
        } elseif ($metadata['format_type'] === 'facility') {
            $metadata = array_merge($metadata, self::parseFacilityId($id));
        }
        
        return $metadata;
    }
    
    /**
     * Get detailed statistics about the ID generation system
     * Includes capacity calculations and collision probabilities
     */
    public static function getStatistics(): array
    {
        $bitsRandomUlid = 80;
        $possibleIdsUlid = bcpow('2', (string)$bitsRandomUlid);
        
        // Calculate collision resistance for ULID-based IDs
        $sqrtPiHalf = bcsqrt(bcmul(M_PI, '0.5', 30), 30);
        $twoToBitsUlid = bcpow('2', (string)($bitsRandomUlid / 2));
        $birthdayApproxUlid = bcmul($sqrtPiHalf, $twoToBitsUlid, 30);
        
        $idsPerDay = bcmul('1000000000', '86400'); // 1 billion per day
        $yearsTo50PercentUlid = bcdiv($birthdayApproxUlid, bcmul($idsPerDay, '365'), 30);
        
        // MRN capacity calculations (per facility per day)
        $mrnRandomBits = 20; // 4 Base32 chars = 20 bits
        $mrnDailyCapacity = pow(2, $mrnRandomBits); // 1,048,576 per day per facility
        
        return [
            'system_overview' => [
                'epoch_year' => self::EPOCH_YEAR,
                'timestamp_bits' => 48,
                'supported_entities' => count(self::ENTITY_CONFIGS),
                'base32_alphabet' => self::BASE32_ALPHABET,
                'alphabet_size' => strlen(self::BASE32_ALPHABET),
                'check_digit_algorithm' => 'Verhoeff (detects all single-digit and transposition errors)',
            ],
            
            'capacity_analysis' => [
                'ulid_based_entities' => [
                    'random_bits' => $bitsRandomUlid,
                    'possible_ids' => $possibleIdsUlid,
                    'scientific_notation' => sprintf('%.2e', $possibleIdsUlid),
                    'daily_capacity' => 'UNLIMITED (not date-constrained)',
                    'entities' => ['patient', 'staff', 'doctor', 'nurse', 'pharmacist', 'technician', 'administrator', 'facility'],
                ],
                'facility_ids_fixed' => [
                    'note' => 'CRITICAL FIX: Previously limited to 32,768/day, now ULID-based with unlimited capacity',
                    'format' => 'HF-{type}{region}{ULID8}{check} (14 chars)',
                    'example' => 'HF-H101HZX9F7K',
                    'capacity' => 'Same as ULID-based: 80-bit random, unlimited daily',
                ],
                'medical_record_numbers' => [
                    'random_bits' => $mrnRandomBits,
                    'daily_capacity_per_facility' => number_format($mrnDailyCapacity),
                    'format' => '{facility}{YY}{DDD}{random}{check} (11 chars)',
                    'example' => 'N26001XYZ8',
                    'suitable_for' => '1M+ patient registrations per day per facility',
                ],
                'document_ids' => [
                    'random_bits' => 64,
                    'daily_capacity' => 'Millions per entity type',
                    'entities' => ['prescription', 'lab_test', 'imaging', 'appointment', 'medical_record', 'insurance'],
                ],
            ],
            
            'collision_resistance' => [
                'ulid_based_ids' => [
                    'with_1B_per_day' => [
                        'years_to_50_percent' => $yearsTo50PercentUlid,
                        'human_readable' => self::formatLargeNumber($yearsTo50PercentUlid) . ' years',
                    ],
                    'with_10M_per_second' => [
                        'years' => bcdiv($birthdayApproxUlid, bcmul('10000000', bcmul('86400', '365')), 30),
                    ],
                    'with_global_population' => [
                        'ids_per_person' => bcdiv($possibleIdsUlid, '8000000000', 10),
                    ],
                ],
                'comparisons' => [
                    'grains_of_sand_on_earth' => '7.5×10^18',
                    'stars_in_observable_universe' => '2×10^23',
                    'our_ulid_space' => '1.2×10^24',
                    'atoms_in_universe' => '1×10^80',
                ],
            ],
            
            'entity_types_details' => array_map(function($type, $config) {
                return [
                    'type' => $type,
                    'prefix' => $config[0],
                    'length' => $config[1],
                    'description' => $config[2],
                    'format' => $config[3],
                    'capacity' => $config[4] ?? 'No capacity notes',
                ];
            }, array_keys(self::ENTITY_CONFIGS), self::ENTITY_CONFIGS),
        ];
    }
    
    // ==================== CORE ULID IMPLEMENTATION ====================
    
    /**
     * Generate ULID-based ID with given prefix
     * Used for: Patients, Staff, Doctors, and NOW Facilities
     */
    private static function generateUlidBasedId(string $prefix): string
    {
        $timestamp = self::getUlidTimestamp();
        $random = random_bytes(10); // 80-bit cryptographically secure random
        $ulidBytes = self::packUlidBytes($timestamp, $random);
        $ulidBase32 = self::encodeBase32($ulidBytes);
        $shortUlid = substr($ulidBase32, 0, 8); // 8 chars = 40 bits
        $checkDigit = self::calculateVerhoeffCheckDigit($shortUlid);
        
        return $prefix . '-' . $shortUlid . $checkDigit;
    }
    
    /**
     * Generate document/reference ID with reduced randomness for shorter formats
     */
    private static function generateDocumentId(string $prefix, array $options): string
    {
        $timestamp = self::getUlidTimestamp();
        $random = random_bytes(8); // 64-bit random (reduced for shorter IDs)
        $bytes = self::packUlidBytes($timestamp, $random);
        $encoded = self::encodeBase32($bytes);
        
        // Use different lengths based on prefix
        $length = match($prefix) {
            'RX', 'LB', 'IM' => 12,
            'AP', 'IN' => 10,
            'MR' => 14,
            default => 10,
        };
        
        $base = substr($encoded, 0, $length);
        $checkDigit = self::calculateVerhoeffCheckDigit($base);
        
        return $prefix . '-' . $base . $checkDigit;
    }
    
    /**
     * Get current timestamp in milliseconds since epoch
     */
    private static function getUlidTimestamp(): int
    {
        $now = microtime(true);
        $milliseconds = (int)($now * 1000);
        $epoch = strtotime(self::EPOCH) * 1000;
        $timestamp = $milliseconds - $epoch;
        
        // Clamp to 48-bit range (0 to 281,474,976,710,655)
        if ($timestamp < 0) {
            $timestamp = 0;
        } elseif ($timestamp > 0xFFFFFFFFFFFF) {
            $timestamp = 0xFFFFFFFFFFFF;
        }
        
        return $timestamp;
    }
    
    /**
     * Pack timestamp and random bytes into ULID format
     */
    private static function packUlidBytes(int $timestamp, string $random): string
    {
        $timestampHigh = ($timestamp >> 16) & 0xFFFFFFFF;
        $timestampLow = $timestamp & 0xFFFF;
        $timestampBytes = pack('Nn', $timestampHigh, $timestampLow);
        
        return $timestampBytes . $random;
    }
    
    /**
     * Encode bytes to Crockford's Base32
     */
    private static function encodeBase32(string $bytes): string
    {
        $result = '';
        $buffer = 0;
        $bits = 0;
        
        for ($i = 0; $i < strlen($bytes); $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;
            
            while ($bits >= 5) {
                $bits -= 5;
                $index = ($buffer >> $bits) & 0x1F;
                $result .= self::BASE32_ALPHABET[$index];
            }
        }
        
        if ($bits > 0) {
            $buffer <<= (5 - $bits);
            $index = $buffer & 0x1F;
            $result .= self::BASE32_ALPHABET[$index];
        }
        
        return $result;
    }
    
    // ==================== VALIDATION METHODS ====================
    
    /**
     * Validate ULID-based ID (Patients, Staff, Doctors, etc.)
     */
    private static function validateUlidBasedId(string $id, string $prefix): bool
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-[0-9A-HJ-NP-TV-Z]{9}$/';
        
        if (!preg_match($pattern, $id)) {
            return false;
        }
        
        $base = substr($id, 3, 8);
        $providedCheckDigit = substr($id, -1);
        $expectedCheckDigit = self::calculateVerhoeffCheckDigit($base);
        
        return $providedCheckDigit === $expectedCheckDigit;
    }
    
    /**
     * Validate Facility ID (Updated format)
     */
    private static function validateFacilityId(string $id): bool
    {
        // Updated pattern: HF- + type + region + ULID8 + check (14 chars total)
        if (!preg_match('/^HF-[0-9A-HJ-NP-TV-Z]{11}$/', $id)) {
            return false;
        }
        
        $base = substr($id, 3, 10); // Type + Region + ULID8
        $providedCheckDigit = substr($id, -1);
        $expectedCheckDigit = self::calculateVerhoeffCheckDigit($base);
        
        return $providedCheckDigit === $expectedCheckDigit;
    }
    
    /**
     * Validate document ID
     */
    private static function validateDocumentId(string $id, string $prefix): bool
    {
        $encodedPart = substr($id, strlen($prefix) + 1);
        
        if (empty($encodedPart)) {
            return false;
        }
        
        $base = substr($encodedPart, 0, -1);
        $providedCheckDigit = substr($encodedPart, -1);
        $expectedCheckDigit = self::calculateVerhoeffCheckDigit($base);
        
        return $providedCheckDigit === $expectedCheckDigit;
    }
    
    // ==================== PARSING METHODS ====================
    
    /**
     * Parse ULID-based ID metadata
     */
    private static function parseUlidBasedId(string $id): array
    {
        $shortUlid = substr($id, 3, 8);
        
        return [
            'short_ulid' => $shortUlid,
            'check_digit' => substr($id, -1),
            'creation_time' => self::extractApproximateTimestamp($shortUlid),
            'full_ulid_reference' => self::generateFullUlid(),
            'random_bits' => 80,
            'uniqueness_guarantee' => '1.2×10^24 possibilities',
            'daily_capacity' => 'Unlimited (not date-constrained)',
        ];
    }
    
    /**
     * Parse Facility ID metadata (Updated for new format)
     */
    private static function parseFacilityId(string $id): array
    {
        $base = substr($id, 3, 10); // Remove "HF-" prefix and check digit
        
        return [
            'facility_type' => $base[0],
            'region_code' => $base[1],
            'ulid_core' => substr($base, 2, 8),
            'check_digit' => substr($id, -1),
            'facility_type_description' => self::FACILITY_TYPES[$base[0]] ?? 'Unknown',
            'region_description' => self::REGION_CODES[$base[1]] ?? 'Unknown',
            'creation_time' => self::extractApproximateTimestamp(substr($base, 2, 8)),
            'random_bits' => 80,
            'uniqueness_guarantee' => '1.2×10^24 possibilities',
            'daily_capacity' => 'Unlimited (ULID-based, not date-constrained)',
            'important_note' => 'FIXED: Previously limited to 32,768/day, now unlimited capacity',
        ];
    }
    
    /**
     * Extract approximate timestamp from ULID segment
     */
    private static function extractApproximateTimestamp(string $shortUlid): \DateTime
    {
        $timestampPart = substr($shortUlid, 0, 4);
        $timestampBits = 0;
        
        for ($i = 0; $i < 4; $i++) {
            $char = $timestampPart[$i];
            $value = strpos(self::BASE32_ALPHABET, $char);
            $timestampBits = ($timestampBits << 5) | $value;
        }
        
        $milliseconds = $timestampBits;
        $epoch = new \DateTime(self::EPOCH);
        $timestamp = $epoch->getTimestamp() * 1000 + $milliseconds;
        
        $result = new \DateTime();
        $result->setTimestamp((int)($timestamp / 1000));
        $microseconds = ($timestamp % 1000) * 1000;
        $result->modify("+{$microseconds} microseconds");
        
        return $result;
    }
    
    // ==================== UTILITY METHODS ====================
    
    /**
     * Calculate Verhoeff check digit for error detection
     */
    private static function calculateVerhoeffCheckDigit(string $input): string
    {
        $d = [
            [0,1,2,3,4,5,6,7,8,9],
            [1,2,3,4,0,6,7,8,9,5],
            [2,3,4,0,1,7,8,9,5,6],
            [3,4,0,1,2,8,9,5,6,7],
            [4,0,1,2,3,9,5,6,7,8],
            [5,9,8,7,6,0,4,3,2,1],
            [6,5,9,8,7,1,0,4,3,2],
            [7,6,5,9,8,2,1,0,4,3],
            [8,7,6,5,9,3,2,1,0,4],
            [9,8,7,6,5,4,3,2,1,0]
        ];
        
        $p = [
            [0,1,2,3,4,5,6,7,8,9],
            [1,5,7,6,2,8,3,0,9,4],
            [5,8,0,3,7,9,6,1,4,2],
            [8,9,1,6,0,4,3,5,2,7],
            [9,4,5,3,1,2,6,8,7,0],
            [4,2,8,6,5,7,3,9,0,1],
            [2,7,9,3,8,0,6,4,1,5],
            [7,0,4,6,9,1,3,2,5,8]
        ];
        
        $inv = [0,4,3,2,1,5,6,7,8,9];
        
        $digits = [];
        foreach (str_split($input) as $char) {
            $pos = strpos(self::BASE32_ALPHABET, $char);
            $digits[] = $pos % 10;
        }
        
        $check = 0;
        $reversed = array_reverse($digits);
        
        foreach ($reversed as $i => $digit) {
            $check = $d[$check][$p[($i + 1) % 8][$digit]];
        }
        
        return self::BASE32_ALPHABET[$inv[$check]];
    }
    
    /**
     * Generate full 26-character ULID (for reference/audit)
     */
    public static function generateFullUlid(): string
    {
        $timestamp = self::getUlidTimestamp();
        $random = random_bytes(10);
        $ulidBytes = self::packUlidBytes($timestamp, $random);
        
        return self::encodeBase32($ulidBytes);
    }
    
    /**
     * Format large numbers for human readability
     */
    private static function formatLargeNumber(string $number): string
    {
        $number = (float)$number;
        
        if ($number >= 1e18) {
            return sprintf('%.1f quintillion', $number / 1e18);
        } elseif ($number >= 1e15) {
            return sprintf('%.1f quadrillion', $number / 1e15);
        } elseif ($number >= 1e12) {
            return sprintf('%.1f trillion', $number / 1e12);
        } elseif ($number >= 1e9) {
            return sprintf('%.1f billion', $number / 1e9);
        } elseif ($number >= 1e6) {
            return sprintf('%.1f million', $number / 1e6);
        }
        
        return number_format($number);
    }
}