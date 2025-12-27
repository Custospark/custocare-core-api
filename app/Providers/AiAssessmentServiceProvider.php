<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\AiAssessmentRepositoryInterface;
use App\Repositories\AiAssessment\AiAssessmentRepository;
use App\Services\Contracts\AiAssessmentServiceInterface;
use App\Services\AiAssessment\AiAssessmentService;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class AiAssessmentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            AiAssessmentRepositoryInterface::class,
            AiAssessmentRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            AiAssessmentServiceInterface::class,
            AiAssessmentService::class
        );

        // Register policy
        $this->app->singleton(\App\Policies\AiAssessmentPolicy::class, function ($app) {
            return new \App\Policies\AiAssessmentPolicy();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register validation rules
        $this->registerValidationRules();

        // Register API response macros
        $this->registerResponseMacros();

        // Publish configuration if needed
        $this->publishes([
            __DIR__ . '/../Config/ai_assessment.php' => config_path('ai_assessment.php'),
        ], 'ai-assessment-config');
    }

    /**
     * Register custom validation rules.
     *
     * @return void
     */
    private function registerValidationRules(): void
    {
        Validator::extend('valid_ai_model', function ($attribute, $value, $parameters, $validator) {
            // Validate AI model name format
            return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_\-\s\.]{1,199}$/', $value);
        }, 'The :attribute must be a valid AI model name.');

        Validator::extend('valid_confidence_scores', function ($attribute, $value, $parameters, $validator) {
            if (!is_array($value)) {
                return false;
            }

            foreach ($value as $score) {
                if (!is_numeric($score) || $score < 0 || $score > 1) {
                    return false;
                }
            }

            return true;
        }, 'All confidence scores must be numeric values between 0 and 1.');

        Validator::extend('valid_input_features', function ($attribute, $value, $parameters, $validator) {
            if (!is_array($value)) {
                return false;
            }

            // Check for empty arrays
            if (empty($value)) {
                return false;
            }

            // Check for nested arrays that are too deep
            $maxDepth = 5;
            $depth = $this->arrayDepth($value);
            
            return $depth <= $maxDepth;
        }, 'Input features must be a valid array with reasonable nesting depth.');

        Validator::extend('valid_regulatory_number', function ($attribute, $value, $parameters, $validator) {
            $type = $parameters[0] ?? 'fda';
            
            if ($type === 'fda') {
                return preg_match('/^[A-Z0-9]{3,10}$/', $value);
            } elseif ($type === 'ce') {
                return preg_match('/^[0-9]{4}\/[0-9]{2,6}\/[A-Z]{2,4}$/', $value);
            }
            
            return false;
        }, 'The :attribute must be a valid regulatory number.');
    }

    /**
     * Calculate array depth
     *
     * @param array $array
     * @param int $depth
     * @return int
     */
    private function arrayDepth(array $array, int $depth = 1): int
    {
        $maxDepth = $depth;
        
        foreach ($array as $value) {
            if (is_array($value)) {
                $newDepth = $this->arrayDepth($value, $depth + 1);
                $maxDepth = max($maxDepth, $newDepth);
            }
        }
        
        return $maxDepth;
    }

    /**
     * Register response macros for consistent API responses.
     *
     * @return void
     */
    private function registerResponseMacros(): void
    {
        Response::macro('aiAssessmentSuccess', function ($data = null, string $message = 'Success', int $status = 200) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ], $status);
        });

        Response::macro('aiAssessmentError', function (string $message = 'Error', string $errorCode = null, int $status = 400, array $errors = []) {
            $response = [
                'success' => false,
                'message' => $message,
                'timestamp' => now()->toISOString(),
            ];

            if ($errorCode) {
                $response['error_code'] = $errorCode;
            }

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response, $status);
        });

        Response::macro('aiAssessmentValidationError', function ($errors, string $message = 'Validation failed') {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'error_code' => 'VALIDATION_FAILED',
                'timestamp' => now()->toISOString(),
            ], 422);
        });
    }
}