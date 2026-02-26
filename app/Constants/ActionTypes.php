<?php
// app/Constants/ActionTypes.php

declare(strict_types=1);

namespace App\Constants;

/**
 * Action types for email verification and notification events
 * 
 * These constants define the different types of actions that trigger
 * email notifications throughout the application.
 */
final class ActionTypes
{
    /**
     * Account creation verification
     * Used when a new user registers
     */
    public const ACCOUNT_CREATION = 'account_creation';
    
    /**
     * Login confirmation / MFA verification
     * Used when a user needs to confirm a login with MFA
     */
    public const LOGIN_CONFIRMATION = 'login_confirmation';
    
    /**
     * Password reset verification
     * Used when a user requests a password reset
     */
    public const PASSWORD_RESET = 'password_reset';
    
    /**
     * Email change verification
     * Used when a user updates their email address
     */
    public const EMAIL_CHANGE = 'email_change';
    
    /**
     * Get all available action types
     * 
     * @return array<string>
     */
    public static function getAll(): array
    {
        return [
            self::ACCOUNT_CREATION,
            self::LOGIN_CONFIRMATION,
            self::PASSWORD_RESET,
            self::EMAIL_CHANGE,
        ];
    }
    
    /**
     * Check if an action type is valid
     * 
     * @param string $action
     * @return bool
     */
    public static function isValid(string $action): bool
    {
        return in_array($action, self::getAll(), true);
    }
}