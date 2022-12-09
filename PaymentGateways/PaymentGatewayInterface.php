<?php

namespace App\PaymentGateways;

interface PaymentGatewayInterface {

    public function execute();

    public function getRedirectUrlToPaymentPage(): string;

    public function isRefundable(): bool;

    public function setTransactionId(int $transactionId);

}



