<?php
/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Tests\Services;

use Bigperson\Exchange1C\Config;
use Bigperson\Exchange1C\Exceptions\Exchange1CException;
use Illuminate\Contracts\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ServerBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    public function testCheckAuth(): void
    {
        $values = [
            'login'      => 'logintest',
            'password'   => 'passwordtest',
        ];
        $config = new Config($values);
        $request = $this->createMock(Request::class);

        $session = $this->createMock(SessionInterface::class);
        $request->method('getSession')
            ->willReturn($session);

        $authService = new AuthServiceTestRealization($config, true);
        $response = $authService->checkAuth($request);
        $this->assertTrue(str_starts_with($response, 'success'));
    }

    public function testCheckAuthIlluminate(): void
    {
        $values = [
            'login'      => 'logintest',
            'password'   => 'passwordtest',
        ];
        $config = new Config($values);
        $session = $this->createMock(SessionInterface::class);

        $request = $this->createMock(Request::class);
        $request->method('getSession')
            ->willReturn($session);

        $authService = new AuthServiceTestRealization($config, true);
        $response = $authService->checkAuth($request);
        $this->assertTrue(str_starts_with($response, 'success'));
    }

    public function testCheckAuthFail(): void
    {
        $values = [
            'login'      => 'logintest',
            'password'   => 'passwordtest',
        ];
        $config = new Config($values);
        $request = $this->createMock(Request::class);

        $session = $this->createMock(SessionInterface::class);
        $request->method('getSession')
            ->willReturn($session);

        $authService = new AuthServiceTestRealization($config, false);
        $response = $authService->checkAuth($request);
        $this->assertTrue(str_starts_with($response, 'failure'));
    }

    public function testAuth(): void
    {
        $this->expectNotToPerformAssertions();
        $values = [
            'login'      => 'logintest',
            'password'   => 'passwordtest',
        ];
        $config = new Config($values);
        $request = $this->createMock(Request::class);
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')
            ->willReturn('logintest');
        $request->method('getSession')
            ->willReturn($session);

        $authService = new AuthServiceTestRealization($config, true);

        $authService->auth($request);
    }

    public function testAuthException(): void
    {
        $this->expectException(Exchange1CException::class);
        $values = [
            'login'      => 'logintest',
            'password'   => 'passwordtest',
        ];
        $config = new Config($values);
        $request = $this->createMock(Request::class);
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')
            ->willReturn(null);
        $request->method('getSession')
            ->willReturn($session);

        $authService = new AuthServiceTestRealization($config, false);
        $authService->auth($request);
    }
}
