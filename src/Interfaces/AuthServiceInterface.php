<?php

namespace Bigperson\Exchange1C\Interfaces;

use Symfony\Component\HttpFoundation\Request;

interface AuthServiceInterface
{
    public function checkAuth(Request $request): string;

    public function auth(Request $request): void;
}