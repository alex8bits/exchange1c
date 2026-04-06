<?php
/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\Exchange1C\Laravel;

use Bigperson\Exchange1C\Interfaces\AuthServiceInterface;
use Bigperson\Exchange1C\Interfaces\EventDispatcherInterface;
use Bigperson\Exchange1C\Interfaces\ModelBuilderInterface;
use Bigperson\Exchange1C\ModelBuilder;
use Bigperson\Exchange1C\Services\AuthService;
use Illuminate\Support\ServiceProvider;

/**
 * Class Exchange1CServiceProvider.
 */
class Exchange1CServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(ModelBuilderInterface::class, ModelBuilder::class);
        $this->app->bind(EventDispatcherInterface::class, LaravelEventDispatcher::class);
    }
}
