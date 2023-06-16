<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\TextField;
use YesWiki\Hpf\Service\HpfService;

/**
 * @Field({"payments"})
 */
class PaymentsField extends TextField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->subType = "text";
        $this->readAccess = "@admins";
        $this->writeAccess = "@admins";
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        $payments = $this->convertStringToPaymentsSorted($value);
        return $this->render('@bazar/fields/payments.twig',compact(['payments']));
    }

    protected function renderInput($entry)
    {
        $value = $this->getValue($entry);
        $payments = $this->convertStringToPaymentsSorted($value);
        return $this->render('@bazar/inputs/payments.twig',[
            'payments' => $payments,
            'value' => json_encode($payments)
        ]);
    }

    protected function convertStringToPaymentsSorted($value): array
    {
        $payments = $this->getService(HpfService::class)->convertStringToPayments($value);
        uksort($payments,function($keyA,$keyB) use ($payments){
            $valA = $payments[$keyA];
            $valB = $payments[$keyB];
            $result = ($valB['date'] <=> $valA['date']);
            return ($result === 0)
                ? ($keyB <=> $keyA)
                : $result;
        });
        return $payments;
    }
}
