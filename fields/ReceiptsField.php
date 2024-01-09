<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-receipts-creation
 */

namespace YesWiki\Hpf\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\LabelField;
use YesWiki\Hpf\Service\ReceiptManager;

/**
 * @Field({"receipts"})
 */
class ReceiptsField extends LabelField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->label = _t('HPF_RECEIPTSFIELD_LABEL');
        $this->formText = '';
        $this->viewText = '';
    }

    protected function renderStatic($entry)
    {
        $receiptManager = $this->getService(ReceiptManager::class);
        if (empty($entry['id_fiche'])
            || !$receiptManager->canSeeReceipts($entry['id_fiche'])){
        return '';
        }
        $payments = $receiptManager->getPaymentsFromEntry($entry);
        return empty($payments)
            ? ''
            : $this->render(
                '@bazar/fields/receipts.twig',
                [
                    'entry' => $entry,
                    'existingReceipts' => array_keys($receiptManager->getExistingReceiptsForEntryId($entry['id_fiche'],$payments)),
                    'payments' => array_map(
                        function ($payment){
                            return array_intersect_key(
                                $payment,
                                ['date'=>1,'total'=>1]
                            );
                        },
                        array_filter(
                            $payments,
                            function($p){
                                return $p['origin'] !== 'structure';
                            }
                        )
                    ),
                ]
            )
        ;
    }

    protected function renderInput($entry)
    {
        return '';
    }
}
