<?php

/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\Exchange1C\Services;

use Bigperson\Exchange1C\Exceptions\Exchange1CException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Catalog
 * Class for implementing CommerceML protocol
 * http://v8.1c.ru/edi/edi_stnd/90/92.htm
 * http://v8.1c.ru/edi/edi_stnd/131/.
 */
class CatalogService extends AbstractService
{
    /**
     * Начало сеанса
     * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
     * http://<сайт>/<путь> /1c-exchange?type=catalog&mode=checkauth.
     * В ответ система управления сайтом передает системе «1С:Предприятие» три строки (используется разделитель строк "\n"):
     * - слово "success";
     * - имя Cookie;
     * - значение Cookie.
     * Примечание. Все последующие запросы к системе управления сайтом со стороны "1С:Предприятия" содержат в заголовке запроса имя и значение Cookie.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return string
     */
    public function checkauth(Request $request): string
    {
        return $this->authService->checkAuth($request);
    }

    /**
     * Запрос параметров от сайта
     * Далее следует запрос следующего вида:
     * http://<сайт>/<путь> /1c-exchange?type=catalog&mode=init
     * В ответ система управления сайтом передает две строки:
     * 1. zip=yes, если сервер поддерживает обмен
     * в zip-формате -  в этом случае на следующем шаге файлы должны быть упакованы в zip-формате
     * или zip=no - в этом случае на следующем шаге файлы не упаковываются и передаются каждый по отдельности.
     * 2. file_limit=<число>, где <число> - максимально допустимый размер файла в байтах для передачи за один запрос.
     * Если системе "1С:Предприятие" понадобится передать файл большего размера, его следует разделить на фрагменты.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return string
     */
    public function init(Request $request): string
    {
        $this->authService->auth($request);
        $this->loaderService->clearImportDirectory();
        $zipEnable = function_exists('zip_open') && $this->config->isUseZip();
        $response = 'zip=' . ($zipEnable ? 'yes' : 'no') . "\n";
        $response .= 'file_limit=' . $this->config->getFilePart();

        return $response;
    }

    /**
     * Загрузка и сохранение файлов на сервер
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return string
     */
    public function file(Request $request): string
    {
        $this->authService->auth($request);

        return $this->loaderService->load($request->query->get('filename'));
    }

    /**
     * На последнем шаге по запросу из "1С:Предприятия" производится пошаговая загрузка данных по запросу
     * с параметрами вида http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
     * Во время загрузки система управления сайтом может отвечать в одном из следующих вариантов.
     * 1. Если в первой строке содержится слово "progress" - это означает необходимость послать тот же запрос еще раз.
     * В этом случае во второй строке будет возвращен текущий статус обработки, объем  загруженных данных, статус импорта и т.д.
     * 2. Если в ответ передается строка со словом "success", то это будет означать сообщение об успешном окончании
     * обработки файла.
     * Примечание. Если в ходе какого-либо запроса произошла ошибка, то в первой строке ответа системы управления
     * сайтом будет содержаться слово "failure", а в следующих строках - описание ошибки, произошедшей в процессе
     * обработки запроса.
     * Если произошла необрабатываемая ошибка уровня ядра продукта или sql-запроса, то будет возвращен html-код.
     *
     * @throws \Bigperson\Exchange1C\Exceptions\Exchange1CException
     */
    public function import(Request $request, string $filename): void
    {
        $this->authService->auth($request);

        switch ($filename) {
            case str_contains($filename, 'import') && str_ends_with($filename, 'xml'):
                $this->categoryService->import($filename);
                break;
            case str_contains($filename, 'offers') && str_ends_with($filename, 'xml'):
            case str_contains($filename, 'rests') && str_ends_with($filename, 'xml'):
            case str_contains($filename, 'prices') && str_ends_with($filename, 'xml'):
                $this->offerService->import($filename);
                break;
            default:
                throw new Exchange1CException("Unsupported file for import: $filename");
        }
    }
}
