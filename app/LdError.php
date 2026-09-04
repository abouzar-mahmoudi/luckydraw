<?php
declare(strict_types=1);

/**
 * An error whose message is meant for the end user (translated via t()).
 * Anything else that bubbles up to the API is masked as a generic error.
 */
class LdError extends RuntimeException
{
    public int $status;
    public string $errorCode;
    public string $key;
    public array $args;

    public function __construct(string $key, int $status = 422, string $errorCode = 'runtime', array $args = [])
    {
        parent::__construct(t($key, ...$args));
        $this->key = $key;
        $this->args = $args;
        $this->status = $status;
        $this->errorCode = $errorCode;
    }
}
