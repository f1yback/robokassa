<?php

namespace app\components;

interface PaymentEndpointInterface
{

    /**
     * Генерация ссылки для оплаты
     * @return string
     */
    public function generatePayLink() : string;

}