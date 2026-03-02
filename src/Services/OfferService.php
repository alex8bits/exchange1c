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
use Bigperson\Exchange1C\Events\AfterOffersSync;
use Bigperson\Exchange1C\Events\AfterUpdateOffer;
use Bigperson\Exchange1C\Events\BeforeOffersSync;
use Bigperson\Exchange1C\Events\BeforeUpdateOffer;
use Bigperson\Exchange1C\Exceptions\Exchange1CException;
use Bigperson\Exchange1C\Interfaces\EventDispatcherInterface;
use Bigperson\Exchange1C\Interfaces\ModelBuilderInterface;
use Bigperson\Exchange1C\Interfaces\OfferInterface;
use Bigperson\Exchange1C\Interfaces\ProductInterface;
use Zenwalker\CommerceML\CommerceML;
use Zenwalker\CommerceML\Model\Offer;

/**
 * Class OfferService.
 */
class OfferService
{
    /**
     * @var array Массив идентификаторов торговых предложений которые были добавлены и обновлены
     */
    private array $_ids;
    /**
     * @var Config
     */
    private Config $config;
    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $dispatcher;
    /**
     * @var ModelBuilderInterface
     */
    private ModelBuilderInterface $modelBuilder;

    /**
     * CategoryService constructor.
     *
     * @param Config $config
     * @param EventDispatcherInterface $dispatcher
     * @param ModelBuilderInterface $modelBuilder
     */
    public function __construct(Config $config, EventDispatcherInterface $dispatcher, ModelBuilderInterface $modelBuilder)
    {
        $this->config = $config;
        $this->dispatcher = $dispatcher;
        $this->modelBuilder = $modelBuilder;
    }

    /**
     * @throws Exchange1CException
     */
    public function import(string $filename): void
    {
        $filename = basename($filename);

        $this->_ids = [];
        $commerce = new CommerceML();

        $classifierFile = $this->config->getFullPath('classifier.xml');
        $commerce->loadOffersXml($this->config->getFullPath($filename));
        if ($commerce->classifier->xml) {
            $commerce->classifier->xml->saveXML($classifierFile);
        } else {
            $commerce->classifier->xml = simplexml_load_string(file_get_contents($classifierFile));
        }
        if ($offerClass = $this->getOfferClass()) {
            $offerClass::createPriceTypes1c($commerce->offerPackage->getPriceTypes());
        }
        $this->beforeOfferSync();
        foreach ($commerce->offerPackage->getOffers() as $offer) {
            $productId = $offer->getClearId();
            if ($product = $this->findProductModelById($productId)) {
                $model = $product->getOffer1c($offer);
                $this->parseProductOffer($model, $offer);
                $this->_ids[] = $model->getPrimaryKey();
            } else {
                throw new Exchange1CException("Продукт $productId не найден в базе");
            }
            unset($model);
        }
        $this->afterOfferSync();
    }

    public function beforeOfferSync(): void
    {
        $event = new BeforeOffersSync();
        $this->dispatcher->dispatch($event);
    }

    public function afterOfferSync(): void
    {
        $event = new AfterOffersSync($this->_ids);
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param OfferInterface $model
     * @param Offer $offer
     */
    public function beforeUpdateOffer(OfferInterface $model, Offer $offer): void
    {
        $event = new BeforeUpdateOffer($model, $offer);
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param OfferInterface $model
     * @param Offer $offer
     */
    public function afterUpdateOffer(OfferInterface $model, Offer $offer): void
    {
        $event = new AfterUpdateOffer($model, $offer);
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param string $id
     *
     * @return ProductInterface|null
     */
    protected function findProductModelById(string $id): ?ProductInterface
    {
        /**
         * @var ProductInterface $class
         */
        $class = $this->modelBuilder->getInterfaceClass($this->config, ProductInterface::class);

        return $class::findProductBy1c($id);
    }

    /**
     * @param OfferInterface $model
     * @param Offer $offer
     */
    protected function parseProductOffer(OfferInterface $model, Offer $offer): void
    {
        $this->beforeUpdateOffer($model, $offer);
        $this->parseSpecifications($model, $offer);
        $this->parsePrice($model, $offer);
        $this->afterUpdateOffer($model, $offer);
    }

    /**
     * @param OfferInterface $model
     * @param Offer $offer
     */
    protected function parseSpecifications(OfferInterface $model, Offer $offer): void
    {
        foreach ($offer->getSpecifications() as $specification) {
            $model->setSpecification1c($specification);
        }
    }

    /**
     * @param OfferInterface $model
     * @param Offer $offer
     */
    protected function parsePrice(OfferInterface $model, Offer $offer): void
    {
        foreach ($offer->getPrices() as $price) {
            $model->setPrice1c($price);
        }
    }

    /**
     * @return OfferInterface|null
     */
    private function getOfferClass(): ?OfferInterface
    {
        return $this->modelBuilder->getInterfaceClass($this->config, OfferInterface::class);
    }
}
