<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

/**
 * A permission a KSeF token can carry (TokenPermissionType in the KSeF API).
 */
enum TokenPermission: string
{
    case InvoiceRead = 'InvoiceRead';
    case InvoiceWrite = 'InvoiceWrite';
    case CredentialsRead = 'CredentialsRead';
    case CredentialsManage = 'CredentialsManage';
    case SubunitManage = 'SubunitManage';
    case EnforcementOperations = 'EnforcementOperations';
    case Introspection = 'Introspection';
    case CollectiveIdentifierManage = 'CollectiveIdentifierManage';
}
