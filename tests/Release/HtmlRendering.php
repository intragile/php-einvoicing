<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Release;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;

/**
 * A small, self-contained HTML rendering of an invoice: the human-readable page that headless
 * Chrome prints before the Factur-X writer embeds the XML.
 *
 * It shows the seller, buyer, number, dates, lines, VAT breakdown and totals. Every value from the
 * invoice is escaped with htmlspecialchars(), and the page loads no external resource.
 */
final class HtmlRendering
{
    public static function render(Invoice $invoice): string
    {
        $currency = $invoice->currency->value;
        $title = ($invoice->type === InvoiceTypeCode::C_381 ? 'Credit note' : 'Invoice').' '.$invoice->number;

        $lines = '';
        foreach ($invoice->lines as $line) {
            $lines .= '<tr>'
                .'<td>'.self::e($line->id).'</td>'
                .'<td>'.self::e($line->item->name).'</td>'
                .'<td class="num">'.self::e((string) $line->quantity).' '.self::e($line->unit->value).'</td>'
                .'<td class="num">'.self::amount($line->price->netPrice).'</td>'
                .'<td class="num">'.self::e($line->vat->category->value).' '.self::rate($line->vat->rate).'</td>'
                .'<td class="num">'.self::amount($line->netAmount).'</td>'
                .'</tr>';
        }

        $vat = '';
        foreach ($invoice->vatBreakdown as $breakdown) {
            $vat .= '<tr>'
                .'<td>'.self::e($breakdown->category->value).'</td>'
                .'<td class="num">'.self::rate($breakdown->rate).'</td>'
                .'<td class="num">'.self::amount($breakdown->taxableAmount).'</td>'
                .'<td class="num">'.self::amount($breakdown->taxAmount).'</td>'
                .'</tr>';
        }

        $totals = $invoice->totals;
        $preceding = '';
        foreach ($invoice->precedingInvoices as $reference) {
            $preceding .= '<tr><th>Preceding invoice</th><td>'.self::e($reference->number).'</td></tr>';
        }

        return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
            .'<title>'.self::e($title).'</title>'
            .'<style>'
            .'body{font-family:Helvetica,Arial,sans-serif;font-size:10pt;color:#000;margin:20mm}'
            .'h1{font-size:16pt;margin:0 0 8mm}'
            .'.parties{display:flex;gap:20mm;margin-bottom:8mm}'
            .'.parties div{flex:1}'
            .'table{border-collapse:collapse;width:100%;margin-bottom:6mm}'
            .'th,td{text-align:left;padding:1.5mm 2mm;border-bottom:0.3mm solid #999;vertical-align:top}'
            .'.num{text-align:right}'
            .'.meta{width:auto}'
            .'.totals{width:auto;margin-left:auto}'
            .'</style></head><body>'
            .'<h1>'.self::e($title).'</h1>'
            .'<div class="parties">'
            .'<div><h2>Seller</h2>'.self::party($invoice->seller).'</div>'
            .'<div><h2>Buyer</h2>'.self::party($invoice->buyer).'</div>'
            .'</div>'
            .'<table class="meta">'
            .'<tr><th>Number</th><td>'.self::e($invoice->number).'</td></tr>'
            .'<tr><th>Issue date</th><td>'.self::date($invoice->issueDate).'</td></tr>'
            .'<tr><th>Due date</th><td>'.self::date($invoice->dueDate).'</td></tr>'
            .'<tr><th>Buyer reference</th><td>'.self::e($invoice->buyerReference ?? '').'</td></tr>'
            .$preceding
            .'<tr><th>Currency</th><td>'.self::e($currency).'</td></tr>'
            .'</table>'
            .'<table><thead><tr><th>#</th><th>Item</th><th class="num">Quantity</th><th class="num">Net price</th><th class="num">VAT</th><th class="num">Net amount</th></tr></thead>'
            .'<tbody>'.$lines.'</tbody></table>'
            .'<table><thead><tr><th>VAT category</th><th class="num">Rate</th><th class="num">Taxable amount</th><th class="num">VAT amount</th></tr></thead>'
            .'<tbody>'.$vat.'</tbody></table>'
            .'<table class="totals">'
            .'<tr><th>Total without VAT</th><td class="num">'.self::amount($totals?->totalWithoutVat).' '.self::e($currency).'</td></tr>'
            .'<tr><th>Total VAT</th><td class="num">'.self::amount($totals?->totalVat).' '.self::e($currency).'</td></tr>'
            .'<tr><th>Total with VAT</th><td class="num">'.self::amount($totals?->totalWithVat).' '.self::e($currency).'</td></tr>'
            .'<tr><th>Amount due</th><td class="num">'.self::amount($totals?->amountDue).' '.self::e($currency).'</td></tr>'
            .'</table>'
            .($invoice->paymentTerms !== null ? '<p>'.self::e($invoice->paymentTerms).'</p>' : '')
            .'</body></html>';
    }

    private static function party(Party $party): string
    {
        $parts = ['<strong>'.self::e($party->name).'</strong>'];
        $address = $party->address;
        if ($address !== null) {
            foreach ([$address->line1, $address->line2, $address->line3] as $line) {
                if ($line !== null) {
                    $parts[] = self::e($line);
                }
            }
            $parts[] = self::e(trim(($address->postCode ?? '').' '.($address->city ?? '')));
            $parts[] = self::e($address->country->value);
        }
        if ($party->vatIdentifier !== null) {
            $parts[] = 'VAT ID '.self::e($party->vatIdentifier);
        }

        return '<p>'.implode('<br>', $parts).'</p>';
    }

    private static function amount(?BigDecimal $amount): string
    {
        return $amount === null ? '' : self::e((string) $amount->toScale(2, RoundingMode::HalfUp));
    }

    private static function rate(?BigDecimal $rate): string
    {
        return $rate === null ? '' : self::e((string) $rate->strippedOfTrailingZeros()).' %';
    }

    private static function date(?DateTimeImmutable $date): string
    {
        return $date === null ? '' : self::e($date->format('Y-m-d'));
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
