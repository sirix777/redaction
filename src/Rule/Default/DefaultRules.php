<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule\Default;

use Sirix\Redaction\Rule\EmailRule;
use Sirix\Redaction\Rule\FixedValueRule;
use Sirix\Redaction\Rule\FullMaskRule;
use Sirix\Redaction\Rule\NameRule;
use Sirix\Redaction\Rule\NullRule;
use Sirix\Redaction\Rule\PhoneRule;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use Sirix\Redaction\Rule\StartEndRule;

/**
 * Factory class for generating default redaction rules.
 */
final class DefaultRules
{
    /**
     * Get all default redaction rules.
     *
     * @return array<string, RedactionRuleInterface>
     */
    public static function getAll(): array
    {
        return [
            // Credit card/PAN rules
            'card_number' => new StartEndRule(6, 4),
            'pan' => new StartEndRule(6, 4),
            'acctNumber' => new StartEndRule(6, 4),
            'customeraccountnumber' => new StartEndRule(6, 4),
            'destination' => new StartEndRule(6, 4),
            'cardNum' => new StartEndRule(6, 4),

            // Security code/CVV rules
            'security_code' => new FullMaskRule(),
            'cvv' => new FullMaskRule(),
            'securitycode' => new FullMaskRule(),
            'card_cvv' => new FullMaskRule(),
            'exp_month' => new FullMaskRule(),
            'exp_year' => new FullMaskRule(),
            'expiration_month' => new FullMaskRule(),
            'expiration_year' => new FullMaskRule(),
            'cardExpiryDate' => new FullMaskRule(),
            'acquirerBIN' => new FullMaskRule(),
            'ccExpMonth' => new FullMaskRule(),
            'ccExpYear' => new FullMaskRule(),
            'month' => new FullMaskRule(),
            'year' => new FullMaskRule(),

            // Date format rules
            'expirydate' => new FixedValueRule('**/****'),

            // Authentication and sensitive values
            'cavv' => new FixedValueRule('*'),
            'threeddirectorytransactionreference' => new FixedValueRule('*'),
            'authenticationValue' => new FixedValueRule('*'),
            'dsTransID' => new FixedValueRule('*'),
            'sitereference' => new FixedValueRule('*'),
            'address' => new FixedValueRule('*'),
            'street' => new FixedValueRule('*'),
            'zip' => new FixedValueRule('*'),
            'ip' => new FixedValueRule('*'),
            'browser_ip' => new FixedValueRule('*'),
            'customerIp' => new FixedValueRule('*'),
            'password' => new FixedValueRule('*'),
            'auth' => new FixedValueRule('*'),
            'accessor' => new FixedValueRule('*'),
            'payload' => new FixedValueRule('*'),
            'paymentHandleToken' => new FixedValueRule('*'),
            'ciphertext' => new FixedValueRule('*'),
            'threeDSSessionData' => new FixedValueRule('*'),
            'creq' => new FixedValueRule('*'),
            'form3d_html' => new FixedValueRule('*'),
            'auth_code' => new FixedValueRule('*'),
            'dsReferenceNumber' => new FixedValueRule('*'),
            'Signature' => new FixedValueRule('*'),
            'Password' => new FixedValueRule('*'),
            'Username' => new FixedValueRule('*'),
            'IP' => new FixedValueRule('*'),
            'signature' => new FixedValueRule('*'),

            // Completely redact (NullRule)
            'pay_form_3d' => new NullRule(),
            'PaRes' => new NullRule(),
            'pares' => new NullRule(),
            'MD' => new NullRule(),
            'md' => new NullRule(),
            'form3d' => new NullRule(),
            'payment_url' => new NullRule(),
            'SuccessURL' => new NullRule(),
            'FailURL' => new NullRule(),

            // Name redaction
            'card_holder' => new NameRule(),
            'holder' => new NameRule(),
            'name' => new NameRule(),
            'customerfirstname' => new NameRule(),
            'customerlastname' => new NameRule(),
            'full_name' => new NameRule(),
            'wallet' => new NameRule(),
            'firstName' => new NameRule(),
            'lastName' => new NameRule(),
            'consumerId' => new NameRule(),
            'holderName' => new NameRule(),
            'Firstname' => new NameRule(),
            'Lastname' => new NameRule(),

            // Phone numbers
            'phone' => new PhoneRule(),
            'MobilePhone' => new PhoneRule(),

            // Email addresses
            'email' => new EmailRule(),
            'client_email' => new EmailRule(),
            'customeremail' => new EmailRule(),
            'pay_from_email' => new EmailRule(),
            'pay_to_email' => new EmailRule(),
            'Email' => new EmailRule(),
        ];
    }
}
