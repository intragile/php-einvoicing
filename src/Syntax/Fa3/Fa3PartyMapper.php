<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Poland\ContactDetails;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\TaxpayerStatus;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;

/**
 * Reads the FA(3) parties into the EN 16931 party and the Polish party data; the inverse of
 * Fa3PartyWriter.
 *
 * - The seller VAT identifier (BT-31) is "PL" and the NIP; a buyer or third-party NIP is read the
 *   same way, KodUE and NrVatUE as one EU VAT identifier, and NrID as the VAT identifier as
 *   written. A missing buyer name (BrakID consumers) is read as an empty name.
 * - AdresL1 is address line 1. A Polish AdresL2 of the form "00-000 City" is split into post
 *   code and city; any other AdresL2 (a foreign address, or a Polish one in another layout) is
 *   read whole as the city, with no post code. The writer emits the city unchanged as AdresL2, so
 *   the document survives a round trip; a foreign post code comes back as part of the city.
 * - The first contact entry (DaneKontaktowe) is the EN 16931 contact; with more than one entry,
 *   all are kept in the Polish party data.
 *
 * @internal
 */
final class Fa3PartyMapper
{
    private const POLISH_POST_CODE = '/^(\d{2}-\d{3}) (.+)$/';

    /** A Polish AdresL2 that is a post code alone. */
    private const POLISH_POST_CODE_ONLY = '/^\d{2}-\d{3}$/';

    public function __construct(private readonly Fa3Document $d) {}

    /**
     * @return array{Party, ?PolishParty}
     *
     * @throws UnsupportedDocument when the seller has no NIP
     */
    public function seller(DOMElement $element, ?string $tradingName, ?string $contactName): array
    {
        $identification = $this->d->one('fa:DaneIdentyfikacyjne', $element);
        $nip = $identification === null ? null : $this->d->token('fa:NIP', $identification);
        if ($nip === null) {
            throw new UnsupportedDocument(sprintf('Missing required seller NIP (BT-31) in %s.', $this->d->path($element)));
        }
        [$contact, $contacts] = $this->contacts($element, $contactName);
        $party = new Party(
            name: $this->d->text('fa:Nazwa', $identification) ?? '',
            tradingName: $tradingName,
            vatIdentifier: 'PL'.$nip,
            address: $this->address($this->d->one('fa:Adres', $element)),
            contact: $contact,
        );

        return [$party, $this->extras(
            element: $element,
            contacts: $contacts,
            vatPrefix: $this->d->code(CountryCode::class, $this->d->one('fa:PrefiksPodatnika', $element)),
            status: $this->d->intCode(TaxpayerStatus::class, $this->d->one('fa:StatusInfoPodatnika', $element)),
        )];
    }

    /**
     * @return array{Party, ?PolishParty}
     */
    public function buyer(DOMElement $element, ?string $tradingName, ?string $contactName): array
    {
        [$contact, $contacts] = $this->contacts($element, $contactName);
        $address = $this->address($this->d->one('fa:Adres', $element));
        [$vat, $name] = $this->identification($this->d->one('fa:DaneIdentyfikacyjne', $element), $address);
        $party = new Party(name: $name, tradingName: $tradingName, vatIdentifier: $vat, address: $address, contact: $contact);

        return [$party, $this->extras(
            element: $element,
            contacts: $contacts,
            customerNumber: $this->d->text('fa:NrKlienta', $element),
            buyerId: $this->d->text('fa:IDNabywcy', $element),
        )];
    }

    public function thirdParty(DOMElement $element): ThirdParty
    {
        [$contact, $contacts] = $this->contacts($element, null);
        $address = $this->address($this->d->one('fa:Adres', $element));
        $identification = $this->d->one('fa:DaneIdentyfikacyjne', $element);
        [$vat, $name] = $this->identification($identification, $address);

        $role = $this->d->intCode(ThirdPartyRole::class, $this->d->one('fa:Rola', $element));
        $otherRole = null;
        if ($role === null && $this->d->flag('fa:RolaInna', $element)) {
            $otherRole = $this->d->text('fa:OpisRoli', $element);
        }

        return new ThirdParty(
            party: new Party(name: $name, vatIdentifier: $vat, address: $address, contact: $contact),
            role: $role,
            otherRole: $otherRole,
            internalId: $identification === null ? null : $this->d->text('fa:IDWew', $identification),
            share: $this->d->decimal('fa:Udzial', $element),
            extras: $this->extras(
                element: $element,
                contacts: $contacts,
                customerNumber: $this->d->text('fa:NrKlienta', $element),
                buyerId: $this->d->text('fa:IDNabywcy', $element),
            ),
        );
    }

    /**
     * The seller as shown on the corrected invoice (Podmiot1K): NIP, name and address.
     */
    public function sellerBefore(DOMElement $element): Party
    {
        $identification = $this->d->one('fa:DaneIdentyfikacyjne', $element);
        $nip = $identification === null ? null : $this->d->token('fa:NIP', $identification);

        return new Party(
            name: $identification === null ? '' : ($this->d->text('fa:Nazwa', $identification) ?? ''),
            vatIdentifier: $nip === null ? null : 'PL'.$nip,
            address: $this->address($this->d->one('fa:Adres', $element)),
        );
    }

    /**
     * A buyer as shown on the corrected invoice (Podmiot2K): identification and address.
     */
    public function buyerBefore(DOMElement $element): Party
    {
        $address = $this->address($this->d->one('fa:Adres', $element));
        [$vat, $name] = $this->identification($this->d->one('fa:DaneIdentyfikacyjne', $element), $address);

        return new Party(name: $name, vatIdentifier: $vat, address: $address);
    }

    /**
     * The VAT identifier and name of a buyer-style identification. The country of NrID is the
     * address country; when it differs or there is no address, KodKraju stays unmapped.
     *
     * @return array{?string, string}
     */
    private function identification(?DOMElement $identification, ?PostalAddress $address): array
    {
        if ($identification === null) {
            return [null, ''];
        }
        $name = $this->d->text('fa:Nazwa', $identification) ?? '';
        $nip = $this->d->token('fa:NIP', $identification);
        if ($nip !== null) {
            return ['PL'.$nip, $name];
        }
        $euPrefix = $this->d->one('fa:KodUE', $identification);
        $euNumber = $this->d->one('fa:NrVatUE', $identification);
        if ($euPrefix !== null && $euNumber !== null) {
            return [$this->d->token('fa:KodUE', $identification).$this->d->text('fa:NrVatUE', $identification), $name];
        }
        $number = $this->d->text('fa:NrID', $identification);
        if ($number !== null) {
            $country = $this->d->one('fa:KodKraju', $identification);
            if ($country !== null && $address !== null && trim($country->textContent) === $address->country->value) {
                $this->d->use($country);
            }

            return [$number, $name];
        }
        $this->d->flag('fa:BrakID', $identification);

        return [null, $name];
    }

    private function address(?DOMElement $element): ?PostalAddress
    {
        if ($element === null) {
            return null;
        }
        $country = $this->d->code(CountryCode::class, $this->d->one('fa:KodKraju', $element));
        if ($country === null) {
            return null;
        }
        $line1 = $this->d->text('fa:AdresL1', $element);
        $line2 = $this->d->text('fa:AdresL2', $element);
        if ($line2 !== null && $country === CountryCode::PL && preg_match(self::POLISH_POST_CODE, $line2, $match) === 1) {
            return new PostalAddress(country: $country, line1: $line1, city: $match[2], postCode: $match[1]);
        }
        if ($line2 !== null && $country === CountryCode::PL && preg_match(self::POLISH_POST_CODE_ONLY, $line2) === 1) {
            return new PostalAddress(country: $country, line1: $line1, postCode: $line2);
        }

        return new PostalAddress(country: $country, line1: $line1, city: $line2);
    }

    /**
     * The EN 16931 contact (first entry, with the contact name) and, with more than one entry,
     * every entry as Polish contact details.
     *
     * @return array{?Contact, list<ContactDetails>}
     */
    private function contacts(DOMElement $party, ?string $contactName): array
    {
        $entries = [];
        foreach ($this->d->all('fa:DaneKontaktowe', $party) as $element) {
            $email = $this->d->text('fa:Email', $element);
            $phone = $this->d->text('fa:Telefon', $element);
            if ($email !== null || $phone !== null) {
                $entries[] = new ContactDetails(email: $email, phone: $phone);
            }
        }
        $first = $entries[0] ?? null;
        $contact = $first === null && $contactName === null
            ? null
            : new Contact(name: $contactName, phone: $first?->phone, email: $first?->email);

        return [$contact, count($entries) > 1 ? $entries : []];
    }

    /**
     * @param  list<ContactDetails>  $contacts
     */
    private function extras(
        DOMElement $element,
        array $contacts,
        ?CountryCode $vatPrefix = null,
        ?TaxpayerStatus $status = null,
        ?string $customerNumber = null,
        ?string $buyerId = null,
    ): ?PolishParty {
        $eori = $this->d->text('fa:NrEORI', $element);
        $correspondence = $this->address($this->d->one('fa:AdresKoresp', $element));
        $address = $this->d->one('fa:Adres', $element);
        $gln = $address === null ? null : $this->d->text('fa:GLN', $address);
        if ($eori === null && $contacts === [] && $customerNumber === null && $buyerId === null
            && $vatPrefix === null && $status === null && $correspondence === null && $gln === null) {
            return null;
        }

        return new PolishParty(
            eori: $eori,
            contacts: $contacts,
            customerNumber: $customerNumber,
            buyerId: $buyerId,
            vatPrefix: $vatPrefix,
            status: $status,
            correspondenceAddress: $correspondence,
            gln: $gln,
        );
    }
}
