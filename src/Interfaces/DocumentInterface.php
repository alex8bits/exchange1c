<?php
/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\Exchange1C\Interfaces;

/**
 * A website order, exported to 1C via type=sale (mode=query / mode=success).
 *
 * getExportFields1c() (inherited from ExportFieldsInterface) supplies the
 * <Документ> level tag => value pairs (Ид, Номер, Дата, Время, ХозОперация,
 * Роль, Валюта, Сумма, ...) — the site decides the exact field set and
 * formatting, SaleService only orchestrates the XML structure around it.
 */
interface DocumentInterface extends ExportFieldsInterface, IdentifierInterface
{
    /**
     * Заказы, ещё не переданные в 1С (offers.xml > ПакетПредложений aside —
     * this is the order side). $fromId mirrors 1C's `orderIDfrom` request
     * parameter and may be ignored if not meaningful for the implementation.
     *
     * @return DocumentInterface[]
     */
    public static function findDocuments1c(int $fromId = 0);

    /**
     * Список предложений в этом заказе — each one supplies its own
     * <Товар> tag => value pairs via getExportFields1c().
     *
     * @return ExportFieldsInterface[]
     */
    public function getOffers1c();

    /**
     * Получить список реквизитов в заказе (например статус заказа).
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function getRequisites1c();

    /**
     * Получаем контрагента у документа — its getExportFields1c() supplies
     * the <Контрагент> tag => value pairs.
     *
     * @return ExportFieldsInterface
     */
    public function getPartner1c();

    /**
     * Called once 1C has acknowledged receipt (type=sale&mode=success) for
     * the batch this document was included in — mark it as sent so the next
     * findDocuments1c() call doesn't return it again.
     */
    public function markExported1c(): void;

    /**
     * 1C also sends order updates back to the site (type=sale&mode=file,
     * then mode=import) — status changes, payment/shipping confirmation,
     * etc. Looks up the local order this incoming <Документ> refers to, by
     * whatever Ид it was exported with (see getExportFields1c()'s 'Ид').
     */
    public static function findByExternalId1c(string $id);

    /**
     * Applies an incoming update from 1C to this order.
     *
     * @param array<string, string>              $fields     <Документ> level tag => value (Ид, Номер, Дата, Сумма, ...)
     * @param array<int, array{name: string, value: string}> $requisites <ЗначенияРеквизитов> as name/value pairs
     */
    public function applyIncomingUpdate1c(array $fields, array $requisites): void;
}
