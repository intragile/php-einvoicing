<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) margin scheme: each case is the name of the flag element that marks it.
 */
enum MarginScheme: string
{
    case TravelAgency = 'P_PMarzy_2';
    case UsedGoods = 'P_PMarzy_3_1';
    case WorksOfArt = 'P_PMarzy_3_2';
    case CollectorsItems = 'P_PMarzy_3_3';
}
