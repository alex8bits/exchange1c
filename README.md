# PHP exchange1c - обмен 1С предприятие с сайтом на php
[![Packagist](https://img.shields.io/packagist/l/alexnsk83/exchange1c.svg?style=flat-square)](LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/alexnsk83/exchange1c.svg?style=flat-square)](https://packagist.org/packages/bigperson/exchange1c)
[![Packagist](https://img.shields.io/packagist/v/alexnsk83/exchange1c.svg?style=flat-square)](https://packagist.org/packages/bigperson/exchange1c)



Установка этой библиотеки, должна упрощать интеграцию 1С в ваш сайт.

Библиотека содержит набор интерфейсов, которые необходимо реализовать, чтобы получить возможность обмениваться товарами и документами с 1С. Предполагается, что у Вас есть 1С:Предприятие 8, Управление торговлей", редакция 11.3, версия 11.3.2 на платформе 8.3.9.2033. 

Если у вас версия конфигурации ниже, то скорее всего библиотека все равно будет работать, т.к. по большей части, обмен с сайтами сильно не меняется в 1С от версии к версии.

Данная библиотека была написана на основе модуля https://github.com/carono/yii2-1c-exchange - все основные интерфейсы взяты именно из этого модуля.

# Зависимости
* php ^8.0
* alex8bits/commerceml
* illuminate/contracts ^10|^11|^12
* symfony/http-foundation ^7.2

# Установка
`composer require alex8bits/exchange1c`

# Использование с Laravel

Пакет поддерживает Laravel Package Discovery. После установки через Composer `Exchange1CServiceProvider` подключится автоматически — биндинги `AuthServiceInterface`, `ModelBuilderInterface` и `EventDispatcherInterface` будут зарегистрированы без каких-либо дополнительных действий.

В конфиге укажите данные для авторизации и классы моделей:

```php
$configValues = [
    'import_dir' => storage_path('1c_exchange'),
    'auth' => [
        'login'    => 'admin',
        'password' => 'secret',
        'custom'   => false,
    ],
    'use_zip'    => false,
    'file_part'  => 0,
    'models'     => [
        \Bigperson\Exchange1C\Interfaces\GroupInterface::class   => \App\Models\Category::class,
        \Bigperson\Exchange1C\Interfaces\ProductInterface::class => \App\Models\Product::class,
        \Bigperson\Exchange1C\Interfaces\OfferInterface::class   => \App\Models\Offer::class,
    ],
];
$config = new \Bigperson\Exchange1C\Config($configValues);
```

Получите `CatalogService` из контейнера (Laravel разрешит все зависимости автоматически):

```php
app()->bind(\Bigperson\Exchange1C\Config::class, fn() => $config);

$catalogService = app(\Bigperson\Exchange1C\Services\CatalogService::class);
```

Если вам нужно переопределить реализацию любого интерфейса (например, использовать собственный `AuthService`), добавьте биндинг в `AppServiceProvider`:

```php
$this->app->bind(
    \Bigperson\Exchange1C\Interfaces\AuthServiceInterface::class,
    \App\Services\MyCustomAuthService::class
);
```

# Использование без Laravel (ручная сборка)

```php
require_once './../vendor/autoload.php';

$configValues = [
    'import_dir' => '1c_exchange',
    'auth' => [
        'login'    => 'admin',
        'password' => 'admin',
        'custom'   => false,
    ],
    'use_zip'    => false,
    'file_part'  => 0,
    'models'     => [
        \Bigperson\Exchange1C\Interfaces\GroupInterface::class   => \App\Models\Category::class,
        \Bigperson\Exchange1C\Interfaces\ProductInterface::class => \App\Models\Product::class,
        \Bigperson\Exchange1C\Interfaces\OfferInterface::class   => \App\Models\Offer::class,
    ],
];
$config      = new \Bigperson\Exchange1C\Config($configValues);
$request     = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$dispatcher  = new \Your\EventDispatcher\Implementation(); // реализует EventDispatcherInterface
$modelBuilder = new \Bigperson\Exchange1C\ModelBuilder();

$authService     = new \Bigperson\Exchange1C\Services\AuthService($config);
$loaderService   = new \Bigperson\Exchange1C\Services\FileLoaderService($config);
$categoryService = new \Bigperson\Exchange1C\Services\CategoryService($config, $dispatcher, $modelBuilder);
$offerService    = new \Bigperson\Exchange1C\Services\OfferService($config, $dispatcher, $modelBuilder);
$catalogService  = new \Bigperson\Exchange1C\Services\CatalogService($config, $authService, $loaderService, $categoryService, $offerService);

$mode = $request->get('mode');
$type = $request->get('type');

try {
    if ($type === 'catalog') {
        if (!method_exists($catalogService, $mode)) {
            throw new \Exception('not correct request, mode=' . $mode);
        }
        $body     = $catalogService->$mode($request);
        $response = new \Symfony\Component\HttpFoundation\Response($body, 200, ['Content-Type', 'text/plain']);
        $response->send();
    } else {
        throw new \LogicException(sprintf('Logic for type "%s" not implemented', $type));
    }
} catch (\Exception $e) {
    $body  = "failure\n";
    $body .= $e->getMessage() . "\n";
    $body .= $e->getFile() . "\n";
    $body .= $e->getLine() . "\n";

    $response = new \Symfony\Component\HttpFoundation\Response($body, 500, ['Content-Type', 'text/plain']);
    $response->send();
}
```

Более подробную информацию по интерфейсам и их реализациям можно найти в документации https://github.com/carono/yii2-1c-exchange

# Лицензия
Данный пакет является открытым кодом под лицензией [MIT license](LICENSE).




