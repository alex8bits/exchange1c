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
use Bigperson\Exchange1C\Interfaces\AuthServiceInterface;

/**
 * Class AbstractService.
 */
abstract class AbstractService
{
    protected Config $config;

    protected AuthServiceInterface $authService;

    protected FileLoaderService $loaderService;

    protected CategoryService $categoryService;

    protected OfferService $offerService;

    /**
     * AbstractService constructor.
     *
     * @param Config $config
     * @param AuthServiceInterface $authService
     * @param FileLoaderService $loaderService
     * @param CategoryService $categoryService
     * @param OfferService $offerService
     */
    public function __construct(
        Config $config,
        AuthServiceInterface $authService,
        FileLoaderService $loaderService,
        CategoryService $categoryService,
        OfferService $offerService
    ) {
        $this->config = $config;
        $this->authService = $authService;
        $this->loaderService = $loaderService;
        $this->categoryService = $categoryService;
        $this->offerService = $offerService;
    }
}
