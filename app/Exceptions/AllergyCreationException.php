<?php
// app/Exceptions/AllergyCreationException.php

namespace App\Exceptions;

use Exception;

class AllergyCreationException extends Exception
{
    public int $status;

    public function __construct(string $message = "", int $status = 500)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}