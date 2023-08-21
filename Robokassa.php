<?php

namespace app\components;

use JsonException;
use Yii;

/**
 * Компонент для работы с робокассой
 */
class Robokassa extends yii\base\Component implements PaymentEndpointInterface
{

    private array $receipt;
    private string $password_1;
    private string $password_2;
    private string $shopID;
    private string $baseUrl;
    private string $isTest = '';

    public function __construct(
        private array $config,
    )
    {
        $this->init();
        $this->makeReceipt();
    }

    /**
     * Проверяем на тестовый режим
     * @return bool
     */
    private function checkIsTest(): bool
    {
        return isset($this->config['test']);
    }

    /**
     * Метод начальной инициализации
     * @return void
     */
    private function init(): void
    {
        $this->password_1 = Yii::$app->params[$this->checkIsTest() ? 'rk_password_3' : 'rk_password_1'];
        $this->password_2 = Yii::$app->params[$this->checkIsTest() ? 'rk_password_4' : 'rk_password_2'];
        $this->isTest = $this->checkIsTest() ? '1' : '';
        $this->baseUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=';
        $this->shopID = Yii::$app->params['rk_shop_id'];
    }

    /**
     * Делаем чек для отправки
     * @return void
     */
    private function makeReceipt(): void
    {
        $this->receipt = [
            'sno' => 'osn',
            'items' => [
                [
                    'name' => $this->config['name'],
                    'quantity' => $this->config['quantity'],
                    'sum' => $this->config['price'],
                    'payment_method' => 'full_payment',
                    'payment_object' => 'payment',
                    'tax' => 'none',
                ],
            ]
        ];
    }

    /**
     * Получаем дополнительные параметры для URL
     * @return string
     */
    private function getParams(): string
    {
        return !empty($this->config['params']) ? http_build_query($this->config['params']) : '';
    }

    /**
     * Получаем чек в формате json
     * @throws JsonException
     */
    private function getJsonReceipt(): string
    {
        return json_encode($this->receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function getCrcParams(string $jsonReceipt): string
    {
        $baseString = "{$this->shopID}:{$this->config['price']}:{$this->config['invoice']}:{$jsonReceipt}:{$this->password_1}";
        if (!empty($this->config['params'])) {
            foreach ($this->config['params'] as $key => $item) {
                $baseString .= ":{$key}={$item}";
            }
        }
        return $baseString;
    }

    /**
     * Генерируем линк на оплату в Робокассе
     * @throws JsonException
     */
    public function generatePayLink(): string
    {
        $params = $this->getParams();
        $jsonReceipt = $this->getJsonReceipt();
        $receiptUrl = urlencode($jsonReceipt);
        $baseString = $this->getCrcParams($jsonReceipt);
        $crc = md5($baseString);
        return implode('', [
            "{$this->baseUrl}{$this->shopID}",
            "&OutSum={$this->config['price']}",
            "&InvId={$this->config['invoice']}",
            "&Description={$this->config['description']}",
            "&Receipt={$receiptUrl}",
            "&SignatureValue={$crc}",
            "&IsTest={$this->isTest}",
            "&{$params}"
        ]);
    }
}