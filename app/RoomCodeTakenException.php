<?php
declare(strict_types=1);

/** Thrown when a host asks for a custom live code that is currently in use. */
final class RoomCodeTakenException extends LdError
{
    public function __construct()
    {
        parent::__construct('err.code_taken', 409, 'code_taken');
    }
}
