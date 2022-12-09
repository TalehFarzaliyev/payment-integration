<?php

namespace App\Enums;


abstract class OrderStatus {

    const ORDER_CREATED     = 0;

    const WAITING_PAYMENT   = 1;

    const ORDER_PLACED      = 2;

    const PREPARING         = 3;

    const SENT_FOR_DELIVERY = 4;

    const DELIVERED         = 5;

    const DELETED           = 6;

}

