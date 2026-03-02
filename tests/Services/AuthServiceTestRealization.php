<?php

namespace Tests\Services;

use Bigperson\Exchange1C\Config;
use Bigperson\Exchange1C\Exceptions\Exchange1CException;
use Bigperson\Exchange1C\Interfaces\AuthServiceInterface;
use Symfony\Component\HttpFoundation\Request;

class AuthServiceTestRealization implements AuthServiceInterface
{
    private bool $success;

    public function __construct(Config $config, bool $success = true)
    {
        $this->success = $success;
    }

    public function checkAuth(Request $request): string
    {
        if ($this->success) {
            return 'success';
        }
        return 'failure';
    }

    /**
     * @throws \Exception
     */
    public function auth(Request $request): void
    {
        if ($this->success) {
            return;
        }
        throw new Exchange1CException('Auth failed');
    }
}