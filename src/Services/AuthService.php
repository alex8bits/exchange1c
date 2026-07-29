<?php
/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\Exchange1C\Services;

use Bigperson\Exchange1C\Config;
use Bigperson\Exchange1C\Exceptions\Exchange1CException;
use Bigperson\Exchange1C\Interfaces\AuthServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class AuthService.
 */
class AuthService implements AuthServiceInterface
{
    public const SESSION_KEY = 'cml_import';

    public function __construct(private Config $config) {}

    /**
     * @throws Exchange1CException
     */
    public function checkAuth(Request $request): string
    {
        $login = $this->config->getLogin();
        if (
            $login !== '' &&
            $request->server->get('PHP_AUTH_USER') === $login &&
            $request->server->get('PHP_AUTH_PW') === $this->config->getPassword()
        ) {
            $session = $this->resolveSession($request);
            $session->set(self::SESSION_KEY . '_auth', $login);
            $session->save();

            return "success\n" . $this->config->getSessionCookie() . "\n" . $session->getId() . "\ntimestamp=" . time();
        }

        return "failure\n";
    }

    /**
     * @throws Exchange1CException
     */
    public function auth(Request $request): void
    {
        $auth = $this->config->getAuth();

        if (!empty($auth['custom']) && isset($auth['callback'])) {
            if (!($auth['callback'])($this->config->getLogin(), $this->config->getPassword())) {
                throw new Exchange1CException('auth error');
            }

            return;
        }

        $session = $this->resolveSession($request);
        $user = $session->get(self::SESSION_KEY . '_auth');

        if (!$user || $user !== $this->config->getLogin()) {
            throw new Exchange1CException('auth error');
        }
    }

    private function resolveSession(Request $request): SessionInterface
    {
        if (!$request->hasSession()) {
            $session = new \Symfony\Component\HttpFoundation\Session\Session();
            $session->start();
            $request->setSession($session);
        }

        return $request->getSession();
    }
}
