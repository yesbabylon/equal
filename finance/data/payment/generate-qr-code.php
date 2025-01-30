<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use SepaQr\Data;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a payment qr code with given data.',
    'params'        => [
        'recipient_name' => [
            'type'        => 'string',
            'description' => 'Name of the recipient of the payment.',
            'required'    => true
        ],

        'recipient_iban' => [
            'type'        => 'string',
            'description' => 'Recipient bank account IBAN number.',
            'required'    => true
        ],

        'recipient_bic' => [
            'type'        => 'string',
            'description' => 'Recipient bank account BIC number.',
            'required'    => true
        ],

        'payment_reference' => [
            'type'        => 'string',
            'description' => 'Payment reference to identify it.',
            'default'     => ''
        ],

        'payment_amount' => [
            'type'        => 'float',
            'description' => 'Amount to pay.',
            'required'    => true
        ]
    ],
    'response'      => [
        'content-type'  => 'image/png',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$paymentData = Data::create()
    ->setServiceTag('BCD')
    ->setIdentification('SCT')
    ->setName($params['recipient_name'])
    ->setIban(str_replace(' ', '', $params['recipient_iban']))
    ->setBic(str_replace(' ', '', $params['recipient_bic']))
    ->setRemittanceReference($params['payment_reference'])
    ->setAmount($params['payment_amount']);

$qrCode = Builder::create()
    ->data($paymentData)
    // required by EPC standard
    ->errorCorrectionLevel(new ErrorCorrectionLevelMedium())
    ->build();

$context->httpResponse()
        ->body($qrCode->getString())
        ->send();
