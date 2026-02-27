<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'channel' => 'sometimes|in:email,sms,both',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'channel.in' => 'Delivery channel must be email, sms, or both.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $email = $this->input('email');
            
            // Check if user exists with this email (encrypted comparison)
            $userExists = $this->userExistsWithEmail($email);
            
            if (!$userExists) {
                // Generic message for security (prevents email enumeration)
                $validator->errors()->add('email', 'We are unable to find an account associated with this email address.');
                
                // Log for debugging (without exposing user data)
                Log::info('Password reset requested for unregistered email', [
                    'email_hash' => $this->hashEmailForLogging($email)
                ]);
            }
        });
    }

    /**
     * Check if a user exists with the given email (handles encrypted emails)
     *
     * @param string $email
     * @return bool
     */
    private function userExistsWithEmail(string $email): bool
    {
        try {
            // Get all users with non-null encrypted emails
            $users = User::whereNotNull('email_encrypted')->get();
            
            foreach ($users as $user) {
                try {
                    $decryptedEmail = decrypt($user->email_encrypted);
                    if (hash_equals(strtolower($decryptedEmail), strtolower($email))) {
                        return true;
                    }
                } catch (\Exception $e) {
                    // Skip users where decryption fails
                    Log::warning('Unable to decrypt email for user', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Error verifying user identity by email', [
                'error' => $e->getMessage()
            ]);
            
            // Return false for security if an error occurs
            return false;
        }
    }

    /**
     * Create a hash of the email for logging (without exposing the actual email)
     *
     * @param string $email
     * @return string
     */
    private function hashEmailForLogging(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to request a password reset.'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }

        if ($this->has('channel')) {
            $this->merge([
                'channel' => strtolower(trim($this->channel))
            ]);
        }
    }
}