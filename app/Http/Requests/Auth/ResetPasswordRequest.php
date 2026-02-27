<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'code' => 'required|string|min:6|max:64',
            'new_password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'code.required' => 'A verification code is required.',
            'code.min' => 'The verification code must be at least :min characters.',
            'code.max' => 'The verification code must not exceed :max characters.',
            'new_password.required' => 'A new password is required.',
            'new_password.min' => 'The password must be at least :min characters.',
            'new_password.confirmed' => 'The password confirmation does not match.',
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
                Log::info('Password reset attempted for unregistered email', [
                    'email_hash' => $this->hashEmailForLogging($email)
                ]);
            }
            
            // Note: Additional code/token validation should be done in the controller/service
            // as it typically requires checking against the password_resets table
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
                    Log::warning('Unable to decrypt email for user during password reset', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Error verifying user identity by email for password reset', [
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
                'message' => 'You are not authorized to reset your password.'
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

        if ($this->has('code')) {
            // Remove any whitespace from the code
            $this->merge([
                'code' => trim(preg_replace('/\s+/', '', $this->code))
            ]);
        }

        // Note: new_password and new_password_confirmation are handled automatically
    }

}