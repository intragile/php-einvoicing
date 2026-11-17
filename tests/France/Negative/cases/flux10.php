<?php

declare(strict_types=1);

// Recipes for fr-flux10; the format is documented on NegativeCorpusTest.

$transactions = 'fixture:flux10-base';
$payments = 'fixture:flux10-payments-base';

$document = '/Report/ReportDocument';
$report = '/Report/TransactionsReport';
$invoice = "{$report}/Invoice";
$seller = "{$invoice}/Seller";
$buyer = "{$invoice}/Buyer";
$line = "{$invoice}/Line";
$transaction = "{$report}/Transactions";
$paymentsReport = '/Report/PaymentsReport';
$paymentsInvoice = "{$paymentsReport}/Invoice/Payment/SubTotals";
$paymentsTransaction = "{$paymentsReport}/Transactions/Payment/SubTotals";

// A recipe that sets one node (element text or attribute) to a value.
$set = static fn (string $rule, string $xpath, string $value, string $base = 'fixture:flux10-base'): array => [
    'set' => 'flux10',
    'rule' => $rule,
    'base' => $base,
    'edits' => [[$xpath, 'set', $value]],
];
// A recipe with free edits.
$edit = static fn (string $rule, array $edits, string $base = 'fixture:flux10-base'): array => [
    'set' => 'flux10',
    'rule' => $rule,
    'base' => $base,
    'edits' => $edits,
];

$digits20 = '12345678901234567890';
$tooLong = str_repeat('A', 51);
$tooLong35 = str_repeat('A', 36);

return [
    // Report document.
    $set('G1.104-1', "{$document}/Id", $tooLong),
    $set('G1.104-2', "{$document}/Id", ' TR-1'),
    $set('G1.104-3', "{$document}/Id", 'TR  1'),
    $set('G1.104-4', "{$document}/Id", 'TR$1'),
    $set('G7.53', "{$document}/IssueDateTime/DateTimeString", '2026120109'),
    $set('G1.36-1', "{$document}/IssueDateTime/DateTimeString", '19990101093000'),
    $set('G8.01', "{$document}/TypeCode", 'XX'),
    $edit('G6.29', [[$report, 'remove']]),
    $set('G6.22-1', "{$document}/Sender/Id/@schemeId", '0002'),
    $set('G6.22-2', "{$document}/Sender/Id", 'PA012'),
    $set('G7.51', "{$document}/Sender/RoleCode", 'XX'),
    $set('G6.26-1', "{$document}/Issuer/Id/@schemeId", '0238'),
    $set('G6.26-2', "{$document}/Issuer/Id", '12345'),
    $set('G7.52', "{$document}/Issuer/RoleCode", 'XX'),

    // Transactions report period.
    $set('G1.09-1', "{$report}/ReportPeriod/StartDate", '2026-11-01'),
    $set('G1.36-2', "{$report}/ReportPeriod/StartDate", '19990101'),
    $set('G1.09-2', "{$report}/ReportPeriod/EndDate", '2026-11-30'),
    $set('G1.36-3', "{$report}/ReportPeriod/EndDate", '21001231'),
    $set('G6.25-1', "{$report}/ReportPeriod/EndDate", '20261101'),

    // Invoice header.
    $set('G1.01', "{$invoice}/TypeCode", 'XXX'),
    $set('G1.02', "{$invoice}/BusinessProcess/ID", 'ZZ'),
    $edit('G1.60', [["{$invoice}/BusinessProcess/ID", 'set', 'B4'], ["{$invoice}/TypeCode", 'set', '386']]),
    $set('S1.12', "{$invoice}/BusinessProcess/TypeID", 'urn:other'),
    $set('G1.05-1', "{$invoice}/ID", $tooLong35),
    $set('G1.05-2', "{$invoice}/ID", ' F1'),
    $set('G1.05-3', "{$invoice}/ID", 'F  1'),
    $set('G1.05-4', "{$invoice}/ID", 'F$1'),
    $set('G1.09-3', "{$invoice}/IssueDate", '2026-11-02'),
    $set('G1.36-4', "{$invoice}/IssueDate", '19990102'),
    $set('G1.09-4', "{$invoice}/DueDate", '2026-12-02'),
    $set('G1.36-5', "{$invoice}/DueDate", '19990102'),
    $set('P1.11-1', "{$invoice}/TaxDueDateTypeCode", '99'),
    $edit('G6.21', [["{$invoice}/TaxSubTotal/TaxCategory", 'append', '<TaxExemptionReasonCode>VATEX-FR-CNWVAT</TaxExemptionReasonCode>']]),
    $edit('G1.32-1', [["{$invoice}/TypeCode", 'set', '384'], ["{$invoice}/ReferencedDocument/IssueDate", 'remove']]),
    $edit('G1.32-2', [
        ["{$invoice}/TypeCode", 'set', '381'],
        ["{$invoice}/ReferencedDocument/IssueDate", 'remove'],
        ["{$line}/ReferencedDocument/IssueDate", 'remove'],
    ]),
    $edit('G6.28', [["{$buyer}/CompanyId", 'remove']]),
    $set('G1.09-5', "{$invoice}/ReferencedDocument/IssueDate", '2026-10-01'),
    $set('G1.36-6', "{$invoice}/ReferencedDocument/IssueDate", '19991001'),
    $set('G1.05-5', "{$invoice}/ReferencedDocument/ID", $tooLong35),
    $set('G1.05-6', "{$invoice}/ReferencedDocument/ID", ' F1'),
    $set('G1.05-7', "{$invoice}/ReferencedDocument/ID", 'F  1'),
    $set('G1.05-8', "{$invoice}/ReferencedDocument/ID", 'F$1'),

    // Seller.
    $set('G2.19-1', "{$seller}/CompanyId/@schemeId", '9999'),
    $set('G2.19-2', "{$seller}/CompanyId", '12345'),
    $edit('G2.19-3', [["{$seller}/CompanyId/@schemeId", 'set', '0223'], ["{$seller}/CompanyId", 'set', '1234567890123456789']]),
    $edit('G2.19-4', [["{$seller}/CompanyId/@schemeId", 'set', '0227'], ["{$seller}/CompanyId", 'set', '1234567890123456789']]),
    $edit('G2.19-5', [["{$seller}/CompanyId/@schemeId", 'set', '0228'], ["{$seller}/CompanyId", 'set', '12345']]),
    $edit('G2.19-6', [["{$seller}/CompanyId/@schemeId", 'set', '0229'], ["{$seller}/CompanyId", 'set', '12345']]),
    $edit('G2.33-1', [["{$seller}/TaxRegistrationId", 'remove']]),
    $set('G2.33-2', "{$seller}/TaxRegistrationId/@qualifyingId", 'TAX'),
    $edit('G1.102', [["{$invoice}/TaxSubTotal/TaxCategory/Code", 'set', 'E'], ["{$seller}/TaxRegistrationId", 'remove']]),
    $set('G2.01-1', "{$seller}/PostalAddress/CountryId", 'fr'),

    // Buyer.
    $set('G2.19-7', "{$buyer}/CompanyId/@schemeId", '9999'),
    $set('G2.19-8', "{$buyer}/CompanyId", '12345'),
    $edit('G2.19-9', [["{$buyer}/CompanyId/@schemeId", 'set', '0223'], ["{$buyer}/CompanyId", 'set', '1234567890123456789']]),
    $edit('G2.19-10', [["{$buyer}/CompanyId/@schemeId", 'set', '0227'], ["{$buyer}/CompanyId", 'set', '1234567890123456789']]),
    $edit('G2.19-11', [["{$buyer}/CompanyId/@schemeId", 'set', '0228'], ["{$buyer}/CompanyId", 'set', '12345']]),
    $edit('G2.19-12', [["{$buyer}/CompanyId/@schemeId", 'set', '0229'], ["{$buyer}/CompanyId", 'set', '12345']]),
    $edit('G2.33-3', [["{$buyer}/TaxRegistrationId", 'remove']]),
    $set('G2.33-4', "{$buyer}/TaxRegistrationId/@qualifyingId", 'TAX'),
    $set('G2.01-2', "{$buyer}/PostalAddress/CountryId", 'fr'),

    // Delivery and invoice period.
    $set('G1.09-6', "{$invoice}/Delivery/Date", '2026-10-31'),
    $set('G1.36-7', "{$invoice}/Delivery/Date", '19991031'),
    $set('G2.01-3', "{$invoice}/Delivery/Location/CountryId", 'fr'),
    $set('G1.09-7', "{$invoice}/InvoicePeriod/StartDate", '2026-10-01'),
    $set('G1.36-8', "{$invoice}/InvoicePeriod/StartDate", '19991001'),
    $set('G1.09-8', "{$invoice}/InvoicePeriod/EndDate", '2026-10-31'),
    $set('G1.36-9', "{$invoice}/InvoicePeriod/EndDate", '21001231'),
    $set('G6.25-2', "{$invoice}/InvoicePeriod/EndDate", '20261001'),

    // Allowance and totals.
    $set('G2.31-1', "{$invoice}/AllowanceCharge/TaxCategoryCode", 'X'),
    $set('G1.24-1', "{$invoice}/AllowanceCharge/TaxPercent", '21'),
    $set('G1.14-1', "{$invoice}/AllowanceCharge/Amount", '10.123'),
    $set('G1.14-2', "{$invoice}/AllowanceCharge/Amount", $digits20),
    $set('G1.14-3', "{$invoice}/MonetaryTotal/TaxExclusiveAmount", '990.123'),
    $set('G1.14-4', "{$invoice}/MonetaryTotal/TaxExclusiveAmount", $digits20),
    $set('G6.23', "{$invoice}/MonetaryTotal/TaxAmount/@CurrencyCode", 'USD'),
    $set('G1.14-5', "{$invoice}/MonetaryTotal/TaxAmount", '198.123'),
    $set('G1.14-6', "{$invoice}/MonetaryTotal/TaxAmount", $digits20),
    $set('G1.53-1', "{$invoice}/MonetaryTotal/TaxExclusiveAmount", '995.00'),
    $set('G1.53-2', "{$invoice}/MonetaryTotal/TaxAmount", '200.00'),

    // Tax sub total.
    $set('G2.31-2', "{$invoice}/TaxSubTotal/TaxCategory/Code", 'X'),
    $set('G1.24-2', "{$invoice}/TaxSubTotal/TaxCategory/Percent", '21'),
    $edit('G1.40', [["{$invoice}/TaxSubTotal/TaxCategory/Code", 'set', 'E']]),
    $set('G1.14-7', "{$invoice}/TaxSubTotal/TaxableAmount", '990.123'),
    $set('G1.14-8', "{$invoice}/TaxSubTotal/TaxableAmount", $digits20),
    $set('G1.14-9', "{$invoice}/TaxSubTotal/TaxAmount", '198.123'),
    $set('G1.14-10', "{$invoice}/TaxSubTotal/TaxAmount", $digits20),

    // Line.
    $set('G1.09-9', "{$line}/InvoicePeriod/StartDate", '2026-10-01'),
    $set('G1.36-10', "{$line}/InvoicePeriod/StartDate", '19991001'),
    $set('G1.09-10', "{$line}/InvoicePeriod/EndDate", '2026-10-31'),
    $set('G1.36-11', "{$line}/InvoicePeriod/EndDate", '21001231'),
    $set('G6.25-3', "{$line}/InvoicePeriod/EndDate", '20261001'),
    $set('G2.01-4', "{$line}/Delivery/Location/CountryId", 'fr'),
    $set('G1.14-11', "{$line}/AllowanceCharge/Amount", '5.123'),
    $set('G1.14-12', "{$line}/AllowanceCharge/Amount", $digits20),
    $set('G1.15-1', "{$line}/BilledQuantity", '10.12345'),
    $set('G1.15-2', "{$line}/BilledQuantity", $digits20),
    $set('G1.16-1', "{$line}/Price/PriceAmount", '-100.00'),
    $set('G1.16-2', "{$line}/Price/PriceAmount", $digits20),
    $set('G1.16-3', "{$line}/Price/AllowanceChargeAmount", '-5.00'),
    $set('G1.16-4', "{$line}/Price/AllowanceChargeAmount", $digits20),
    $set('G1.16-5', "{$line}/Price/AllowanceChargeBaseAmount", '-105.00'),
    $set('G1.16-6', "{$line}/Price/AllowanceChargeBaseAmount", $digits20),
    $set('G1.55', "{$line}/Price/PriceAmount", '90.00'),
    $set('G1.09-11', "{$line}/ReferencedDocument/IssueDate", '2026-10-01'),
    $set('G1.36-12', "{$line}/ReferencedDocument/IssueDate", '19991001'),
    $set('G1.05-9', "{$line}/ReferencedDocument/ID", $tooLong35),
    $set('G1.05-10', "{$line}/ReferencedDocument/ID", ' F1'),
    $set('G1.05-11', "{$line}/ReferencedDocument/ID", 'F  1'),
    $set('G1.05-12', "{$line}/ReferencedDocument/ID", 'F$1'),

    // Transactions.
    $set('G1.09-12', "{$transaction}/Date", '2026-11-15'),
    $set('G1.36-13', "{$transaction}/Date", '19991115'),
    $set('G1.68', "{$transaction}/CategoryCode", 'XXXX'),
    $set('P1.11-2', "{$transaction}/TaxDueDateTypeCode", '99'),
    $set('G1.24-3', "{$transaction}/TaxSubtotal/TaxPercent", '21'),
    $set('G1.14-13', "{$transaction}/TaxExclusiveAmount", '500.123'),
    $set('G1.14-14', "{$transaction}/TaxExclusiveAmount", $digits20),
    $set('G1.14-15', "{$transaction}/TaxTotal", '100.123'),
    $set('G1.14-16', "{$transaction}/TaxTotal", $digits20),
    $set('G1.14-17', "{$transaction}/TaxSubtotal/TaxableAmount", '500.123'),
    $set('G1.14-18', "{$transaction}/TaxSubtotal/TaxableAmount", $digits20),
    $set('G1.14-19', "{$transaction}/TaxSubtotal/TaxTotal", '100.123'),
    $set('G1.14-20', "{$transaction}/TaxSubtotal/TaxTotal", $digits20),
    $set('G1.53-3', "{$transaction}/TaxExclusiveAmount", '600.00'),
    $set('G1.53-4', "{$transaction}/TaxTotal", '150.00'),

    // Payments report.
    $set('G1.09-13', "{$paymentsReport}/ReportPeriod/StartDate", '2026-11-01', $payments),
    $set('G1.36-14', "{$paymentsReport}/ReportPeriod/StartDate", '19990101', $payments),
    $set('G1.09-14', "{$paymentsReport}/ReportPeriod/EndDate", '2026-11-30', $payments),
    $set('G1.36-15', "{$paymentsReport}/ReportPeriod/EndDate", '21001231', $payments),
    $set('G6.25-4', "{$paymentsReport}/ReportPeriod/EndDate", '20261101', $payments),
    $set('G1.05-13', "{$paymentsReport}/Invoice/InvoiceID", $tooLong35, $payments),
    $set('G1.05-14', "{$paymentsReport}/Invoice/InvoiceID", ' F1', $payments),
    $set('G1.05-15', "{$paymentsReport}/Invoice/InvoiceID", 'F  1', $payments),
    $set('G1.05-16', "{$paymentsReport}/Invoice/InvoiceID", 'F$1', $payments),
    $set('G1.09-15', "{$paymentsReport}/Invoice/IssueDate", '2026-11-02', $payments),
    $set('G1.36-16', "{$paymentsReport}/Invoice/IssueDate", '19991102', $payments),
    $set('G1.09-16', "{$paymentsReport}/Invoice/Payment/Date", '2026-11-20', $payments),
    $set('G1.36-17', "{$paymentsReport}/Invoice/Payment/Date", '19991120', $payments),
    $set('G1.24-4', "{$paymentsInvoice}/TaxPercent", '21', $payments),
    $set('G6.27-1', "{$paymentsInvoice}/CurrencyCode", 'USD', $payments),
    $set('G1.16-7', "{$paymentsInvoice}/Amount", '1188.1234567', $payments),
    $set('G1.16-8', "{$paymentsInvoice}/Amount", $digits20, $payments),
    $set('G1.09-17', "{$paymentsReport}/Transactions/Payment/Date", '2026-11-21', $payments),
    $set('G1.36-18', "{$paymentsReport}/Transactions/Payment/Date", '19991121', $payments),
    $set('G1.24-5', "{$paymentsTransaction}/TaxPercent", '21', $payments),
    $set('G6.27-2', "{$paymentsTransaction}/CurrencyCode", 'USD', $payments),
    $set('G1.16-9', "{$paymentsTransaction}/Amount", '600.1234567', $payments),
    $set('G1.16-10', "{$paymentsTransaction}/Amount", $digits20, $payments),
    $set('G1.10-1', "{$paymentsInvoice}/CurrencyCode", 'EU', $payments),
    $set('G1.10-2', "{$invoice}/CurrencyCode", 'EU'),
];
