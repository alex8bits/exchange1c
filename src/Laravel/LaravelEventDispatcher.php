<?php
/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\Exchange1C\Laravel;

use Bigperson\Exchange1C\Interfaces\EventDispatcherInterface;
use Bigperson\Exchange1C\Interfaces\EventInterface;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Class LaravelEventDispatcher.
 *
 * Bridges Bigperson\Exchange1C\Interfaces\EventDispatcherInterface
 * to the Laravel event dispatcher.
 */
class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function dispatch(EventInterface $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
