<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\AeatCredentials;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\UnknownIssuer;
use IntrAgile\EInvoicing\Spain\Verifactu\InMemoryIssuerResolver;
use IntrAgile\EInvoicing\Spain\Verifactu\IssuerContext;
use IntrAgile\EInvoicing\Spain\Verifactu\IssuerDriver;
use IntrAgile\EInvoicing\Spain\Verifactu\Representative;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class IssuerResolverTest extends TestCase
{
    private function credentials(): AeatCredentials
    {
        return new AeatCredentials('/certs/issuer.p12', 'secret-pass', null, CertificateKind::Seal);
    }

    private function aeatIssuer(string $nif = 'B64829179', string $name = 'Issuer S.L.'): IssuerContext
    {
        return new IssuerContext(
            Nif::parse($nif),
            $name,
            IssuerDriver::Aeat,
            VerifactuEnvironment::Test,
            $this->credentials(),
        );
    }

    public function test_resolves_known_issuer(): void
    {
        $issuer = $this->aeatIssuer();
        $resolver = new InMemoryIssuerResolver($issuer);

        $this->assertSame($issuer, $resolver->resolve(Nif::parse('B64829179')));
    }

    public function test_unknown_issuer_throws_naming_the_nif(): void
    {
        $resolver = new InMemoryIssuerResolver($this->aeatIssuer());

        $this->expectException(UnknownIssuer::class);
        $this->expectExceptionMessage('89890001K');
        $resolver->resolve(Nif::parse('89890001K'));
    }

    public function test_issuers_are_enumerated_in_order(): void
    {
        $resolver = new InMemoryIssuerResolver(
            $this->aeatIssuer('B64829179'),
            new IssuerContext(Nif::parse('89890001K'), 'Second', IssuerDriver::B2Brouter, VerifactuEnvironment::Production),
        );

        $this->assertEquals([Nif::parse('B64829179'), Nif::parse('89890001K')], $resolver->issuers());
        $this->assertSame([], (new InMemoryIssuerResolver)->issuers());
    }

    public function test_duplicate_nif_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryIssuerResolver($this->aeatIssuer(), $this->aeatIssuer(name: 'Again'));
    }

    public function test_aeat_driver_needs_credentials(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IssuerContext(Nif::parse('B64829179'), 'Issuer S.L.', IssuerDriver::Aeat, VerifactuEnvironment::Test);
    }

    public function test_b2brouter_driver_needs_no_credentials(): void
    {
        $context = new IssuerContext(Nif::parse('B64829179'), 'Issuer S.L.', IssuerDriver::B2Brouter, VerifactuEnvironment::Test);

        $this->assertNull($context->credentials);
    }

    public function test_password_never_appears_in_dumps(): void
    {
        $context = $this->aeatIssuer();

        $this->assertSame('secret-pass', $context->credentials?->password());
        foreach ([print_r($context, true), var_export($context, true), self::varDump($context)] as $dump) {
            $this->assertStringNotContainsString('secret-pass', $dump);
        }
        $this->assertStringContainsString('/certs/issuer.p12', print_r($context, true));
    }

    public function test_password_stays_hidden_after_an_array_cast(): void
    {
        $credentials = $this->credentials();

        $this->assertStringNotContainsString('secret-pass', self::varDump((array) $credentials));
        $this->assertStringNotContainsString('secret-pass', print_r((array) $credentials, true));
    }

    public function test_credentials_with_a_password_refuse_serialization(): void
    {
        $this->expectException(LogicException::class);
        serialize($this->credentials());
    }

    public function test_header_and_chain_key(): void
    {
        $representative = new Representative('Agent S.L.', Nif::parse('89890001K'));
        $context = new IssuerContext(
            Nif::parse('B64829179'),
            'Issuer S.L.',
            IssuerDriver::Aeat,
            VerifactuEnvironment::Test,
            $this->credentials(),
            $representative,
        );

        $header = $context->header();
        $this->assertEquals(Nif::parse('B64829179'), $header->issuer);
        $this->assertSame('Issuer S.L.', $header->issuerName);
        $this->assertSame($representative, $header->representative);
        $this->assertSame('B64829179|383|test', $context->chainKey(VerifactuSamples::system())->toString());
    }

    private static function varDump(mixed $value): string
    {
        ob_start();
        var_dump($value);

        return (string) ob_get_clean();
    }
}
