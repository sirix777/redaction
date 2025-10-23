<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule\Default;

use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\Redaction\Rule\RedactionRuleInterface;

/**
 * Factory class for generating default redaction rules with lazy initialization.
 */
final class DefaultRules
{
    /**
     * @var null|array<string, RedactionRuleInterface>
     */
    private static ?array $cachedRules = null;

    /**
     * @return array<string, RedactionRuleInterface>
     */
    public static function getAll(): array
    {
        if (null === self::$cachedRules) {
            self::$cachedRules = self::createRules();
        }

        return self::$cachedRules;
    }

    public static function clearCache(): void
    {
        self::$cachedRules = null;
    }

    /**
     * @return array<string, RedactionRuleInterface>
     */
    private static function createRules(): array
    {
        $rules = [
            'cardNumber' => SharedRuleFactory::startEnd(6, 4),
            'fullMask' => SharedRuleFactory::fullMask(),
            'fixedStar' => SharedRuleFactory::fixedValue('*'),
            'null' => SharedRuleFactory::null(),
            'name' => SharedRuleFactory::name(),
            'phone' => SharedRuleFactory::phone(),
            'email' => SharedRuleFactory::email(),
            'expiryDate' => SharedRuleFactory::fixedValue('**/****'),
        ];
        $groups = self::getGroups();

        // --- Flatten to key => rule map ---
        $result = [];
        foreach ($groups as $ruleKey => $keys) {
            $rule = $rules[$ruleKey];
            foreach ($keys as $key) {
                $result[$key] = $rule;
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function getGroups(): array
    {
        // --- Rule groups (by logical type) ---
        return [
            // Credit card / PAN
            'cardNumber' => [
                'card_number',
                'pan',
                'acctNumber',
                'customeraccountnumber',
                'destination',
                'cardNum',
            ],

            // CVV / security
            'fullMask' => [
                'security_code',
                'cvv',
                'securitycode',
                'card_cvv',
                'exp_month',
                'exp_year',
                'expiration_month',
                'expiration_year',
                'cardExpiryDate',
                'acquirerBIN',
                'ccExpMonth',
                'ccExpYear',
                'month',
                'year',
            ],

            // Expiry date
            'expiryDate' => [
                'expirydate',
            ],

            // Sensitive / authentication data
            'fixedStar' => [
                'cavv',
                'threeddirectorytransactionreference',
                'authenticationValue',
                'dsTransID',
                'sitereference',
                'address',
                'street',
                'zip',
                'ip',
                'browser_ip',
                'customerIp',
                'password',
                'auth',
                'accessor',
                'payload',
                'paymentHandleToken',
                'ciphertext',
                'threeDSSessionData',
                'creq',
                'form3d_html',
                'auth_code',
                'dsReferenceNumber',
                'Signature',
                'Password',
                'Username',
                'IP',
                'signature',
            ],

            // Completely redact (null)
            'null' => [
                'pay_form_3d',
                'PaRes',
                'pares',
                'MD',
                'md',
                'form3d',
                'payment_url',
                'SuccessURL',
                'FailURL',
            ],

            // Names
            'name' => [
                'card_holder',
                'holder',
                'name',
                'customerfirstname',
                'customerlastname',
                'full_name',
                'wallet',
                'firstName',
                'lastName',
                'consumerId',
                'holderName',
                'Firstname',
                'Lastname',
            ],

            // Phones
            'phone' => [
                'phone',
                'MobilePhone',
            ],

            // Emails
            'email' => [
                'email',
                'client_email',
                'customeremail',
                'pay_from_email',
                'pay_to_email',
                'Email',
            ],
        ];
    }
}
