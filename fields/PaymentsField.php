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
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Hpf\Service\HpfService;

/**
 * @Field({"payments"})
 */
class PaymentsField extends TextField
{
    public const AVAILABLE_ORIGINS = [
        'helloasso',
        'virement',
        'cheque',
        'structure',
        'espece'
    ];
    protected $hpfService;

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
        return empty($value) ? '' : $this->render('@bazar/fields/payments.twig',compact(['payments','entry']));
    }

    protected function renderInput($entry)
    {
        $value = $this->getValue($entry);
        $payments = $this->convertStringToPaymentsSorted($value);
        $formManager = $this->getService(FormManager::class);
        $paymentsFormIds = $this->hpfService->getCurrentPaymentsFormIds();
        $formIds = [];
        if (empty($entry['id_typeannonce'])){
            $formsIds = $paymentsFormIds;
        } else {
            $formIds = [$entry['id_typeannonce']];
        }
        $options = [
            'years' => [],
            'origins' => []
        ];
        foreach(HpfService::PAYED_FIELDNAMES['years'] as $name => $fieldName){
            $field = null;
            foreach ($formIds as $formId) {
                if (empty($field)){
                    $field = $formManager->findFieldFromNameOrPropertyName($fieldName, $formId);
                }
            }
            if (!empty($field)){
                $options['years'][$name] = $field->getOptions();
            }
        }
        foreach($paymentsFormIds as $id){
            $form = $formManager->getOne($id);
            if ($form){
                $options['origins'][] = [
                    'id' => self::AVAILABLE_ORIGINS[0].":$id",
                    'name' => self::AVAILABLE_ORIGINS[0].":$id ({$form['bn_label_nature']})"
                ];
            }
        }
        foreach(self::AVAILABLE_ORIGINS as $name){
            $options['origins'][] = [
                'id' => $name,
                'name' => $name
            ];
        }

        return $this->render('@bazar/inputs/payments.twig',[
            'payments' => $payments,
            'value' => json_encode($payments),
            'options' => $options
        ]);
    }

    protected function convertStringToPaymentsSorted($value): array
    {
        $this->hpfService = $this->getService(HpfService::class);
        $payments = $this->hpfService->convertStringToPayments($value);
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

    public function formatValuesBeforeSave($entry)
    {
        if (empty($this->propertyName)) {
            return [];
        }
        $dirtyHtml = $this->getValue($entry);
        $fieldsToRemove = [];
        foreach($entry as $key=>$value){
            if (is_string($key) && substr($key,0,strlen('datepicker-')) == 'datepicker-'){
                $fieldsToRemove[] = $key;
            }
        }
        return [
            $this->propertyName => $dirtyHtml,
            'fields-to-remove' => $fieldsToRemove
        ];
    }
}
