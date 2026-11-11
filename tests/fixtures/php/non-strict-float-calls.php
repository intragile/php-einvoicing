<?php

/*
 * Deliberately without declare(strict_types=1): PHP decides scalar coercion by the caller's
 * file, so these calls show how the model behaves for a caller in coercive typing mode.
 * Each closure passes a float in exactly one numeric position.
 */

use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineCharge;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Money;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\VatBreakdown;

$line = static fn (): InvoiceLine => new InvoiceLine('1', '1', UnitCode::C62, new Price('1'), new Item('Item'), new LineVat(VatCategory::StandardRate, '27'));

return [
    'Money amount' => ['Amount', static fn () => new Money(1.5, CurrencyCode::EUR)],
    'Price BT-146' => ['BT-146', static fn () => new Price(0.1)],
    'Price BT-147' => ['BT-147', static fn () => new Price('1', 0.1)],
    'Price BT-148' => ['BT-148', static fn () => new Price('1', null, 1.1)],
    'Price BT-149' => ['BT-149', static fn () => new Price('1', null, null, 2.5)],
    'InvoiceLine BT-129' => ['BT-129', static fn () => new InvoiceLine('1', 1.5, UnitCode::C62, new Price('1'), new Item('Item'), new LineVat(VatCategory::StandardRate, '27'))],
    'InvoiceLine BT-131' => ['BT-131', static fn () => new InvoiceLine('1', '1', UnitCode::C62, new Price('1'), new Item('Item'), new LineVat(VatCategory::StandardRate, '27'), 1.5)],
    'InvoiceLine::withQuantity BT-129' => ['BT-129', static fn () => $line()->withQuantity(1.5)],
    'InvoiceLine::withNetAmount BT-131' => ['BT-131', static fn () => $line()->withNetAmount(1.5)],
    'LineVat BT-152' => ['BT-152', static fn () => new LineVat(VatCategory::StandardRate, 27.5)],
    'LineAllowance BT-136' => ['BT-136', static fn () => new LineAllowance(1.5)],
    'LineAllowance BT-137' => ['BT-137', static fn () => new LineAllowance('1', 10.5)],
    'LineAllowance BT-138' => ['BT-138', static fn () => new LineAllowance('1', '10', 9.5)],
    'LineCharge BT-141' => ['BT-141', static fn () => new LineCharge(1.5)],
    'LineCharge BT-142' => ['BT-142', static fn () => new LineCharge('1', 10.5)],
    'LineCharge BT-143' => ['BT-143', static fn () => new LineCharge('1', '10', 9.5)],
    'DocumentAllowance BT-92' => ['BT-92', static fn () => new DocumentAllowance(1.5, VatCategory::StandardRate)],
    'DocumentAllowance BT-96' => ['BT-96', static fn () => new DocumentAllowance('1', VatCategory::StandardRate, 27.5)],
    'DocumentAllowance BT-93' => ['BT-93', static fn () => new DocumentAllowance('1', VatCategory::StandardRate, '27', 10.5)],
    'DocumentAllowance BT-94' => ['BT-94', static fn () => new DocumentAllowance('1', VatCategory::StandardRate, '27', '10', 9.5)],
    'DocumentCharge BT-99' => ['BT-99', static fn () => new DocumentCharge(1.5, VatCategory::StandardRate)],
    'DocumentCharge BT-103' => ['BT-103', static fn () => new DocumentCharge('1', VatCategory::StandardRate, 27.5)],
    'DocumentCharge BT-100' => ['BT-100', static fn () => new DocumentCharge('1', VatCategory::StandardRate, '27', 10.5)],
    'DocumentCharge BT-101' => ['BT-101', static fn () => new DocumentCharge('1', VatCategory::StandardRate, '27', '10', 9.5)],
    'VatBreakdown BT-116' => ['BT-116', static fn () => new VatBreakdown(100.5, '27', VatCategory::StandardRate, '27')],
    'VatBreakdown BT-117' => ['BT-117', static fn () => new VatBreakdown('100', 27.5, VatCategory::StandardRate, '27')],
    'VatBreakdown BT-119' => ['BT-119', static fn () => new VatBreakdown('100', '27', VatCategory::StandardRate, 27.5)],
    'DocumentTotals BT-106' => ['BT-106', static fn () => new DocumentTotals(1.5, '1', '1', '1')],
    'DocumentTotals BT-109' => ['BT-109', static fn () => new DocumentTotals('1', 1.5, '1', '1')],
    'DocumentTotals BT-112' => ['BT-112', static fn () => new DocumentTotals('1', '1', 1.5, '1')],
    'DocumentTotals BT-115' => ['BT-115', static fn () => new DocumentTotals('1', '1', '1', 1.5)],
    'DocumentTotals BT-107' => ['BT-107', static fn () => new DocumentTotals('1', '1', '1', '1', 1.5)],
    'DocumentTotals BT-108' => ['BT-108', static fn () => new DocumentTotals('1', '1', '1', '1', null, 1.5)],
    'DocumentTotals BT-110' => ['BT-110', static fn () => new DocumentTotals('1', '1', '1', '1', null, null, 1.5)],
    'DocumentTotals BT-111' => ['BT-111', static fn () => new DocumentTotals('1', '1', '1', '1', null, null, null, 1.5)],
    'DocumentTotals BT-113' => ['BT-113', static fn () => new DocumentTotals('1', '1', '1', '1', null, null, null, null, 1.5)],
    'DocumentTotals BT-114' => ['BT-114', static fn () => new DocumentTotals('1', '1', '1', '1', null, null, null, null, null, 1.5)],
];
