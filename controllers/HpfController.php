<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-register-payment-action
 */

namespace YesWiki\Hpf\Controller;

use DateInterval;
use DateTime;
use Exception;
use Throwable;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Shop\Entity\HelloAssoPayments;
use YesWiki\Shop\Entity\Payment;
use YesWiki\Shop\Entity\User;
use YesWiki\Shop\Service\HelloAssoService;
use YesWiki\Wiki;

class HpfController extends YesWikiController
{
    protected $entryManager;
    protected $formManager;
    protected $helloAssoService;
    protected $hpfService;

    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        HelloAssoService $helloAssoService,
        HpfService $hpfService,
        Wiki $wiki
    ) {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->helloAssoService = $helloAssoService;
        $this->hpfService = $hpfService;
        $this->wiki = $wiki;
    }
    
    /**
     * Feature UUID : hpf-register-payment-action
     */
    public function findHelloAssoPayments(string $date, int $amount): array
    {
        if (empty($date)) {
            throw new Exception('date should not be empty');
        }
        $dateObject = new DateTime($date);
        if (empty($dateObject)) {
            throw new Exception('date should not be a date');
        }
        $payments = $this->helloAssoService->getPayments([
            'from' => $dateObject->format('Y-m-d'),
            'to' => $dateObject->add(new DateInterval('P1D'))->format('Y-m-d')
        ]);
        if (!($payments instanceof HelloAssoPayments)){
            return [
                'status' => 'not ok : wait some duration',
                'payments' => []
            ];
        }
        $paymentsf = array_filter(
            $payments->getPayments(),
            function ($p) use ($amount) {
                return intval($p->amount*100) === intval($amount);
            }
        );
        return [
            'status' => 'ok',
            'payments' => $paymentsf
        ];
    }

    /**
     * Feature UUID : hpf-register-payment-action
     */
    public function deletePaymentInEntry(string $entryId, string $paymentId): array
    {
        return $this->addRemoveCommon(
            $entryId,
            function ($entry, $form, $formattedPayments, $updatedEntry) use ($paymentId) {
                $updated = false;
                if (!empty($formattedPayments[$paymentId])) {
                    foreach([
                        'adhesion' => 'membership',
                        'adhesion_groupe' => 'group_membership',
                        'don' => 'donation',
                    ] as $keyPayment => $name) {
                        if (!empty($formattedPayments[$paymentId][$keyPayment])) {
                            foreach($formattedPayments[$paymentId][$keyPayment] as $year => $value) {
                                list('field' => $field) = $this->hpfService->getPayedField($entry['id_typeannonce'], $year, $name, true);
                                if (!empty($field) && !empty($field->getPropertyName())) {
                                    $propName = $field->getPropertyName();
                                    $updatedEntry[$propName] = strval(max(0, floatval($updatedEntry[$propName] ?? 0) - floatval($value)));
                                    if ($updatedEntry[$propName] === '0') {
                                        $updatedEntry = $this->hpfService->updateYear($updatedEntry, HpfService::PAYED_FIELDNAMES["years"][$name], $year, false);
                                        $updatedEntry[$propName] = '';
                                    }
                                }
                            }
                        }
                    }
                    unset($formattedPayments[$paymentId]);
                    $updatedEntry[HpfService::PAYMENTS_FIELDNAME] = empty($formattedPayments) ? '' : json_encode($formattedPayments);
                    $updated = true;
                }
                return compact(['updated','updatedEntry']);
            }
        );
    }

    /**
     * Feature UUID : hpf-register-payment-action
     */
    public function addPaymentInEntry(
        string $entryId,
        string $paymentDate,
        string $paymentTotal,
        string $paymentOrigin,
        string $paymentId,
        string $paymentYear
    ): array {
        $payment =
        new Payment([
            'id' => $paymentId,
            'amount' => $paymentTotal,
            'date' => (new DateTime($paymentDate))->format('Y-m-d'),
            'payer' => new User(),
            'status' => 'Authorized',
            'description' => ''
        ]);
        return $this->addRemoveCommon(
            $entryId,
            function ($entry, $form, $formattedPayments, $updatedEntry) use ($payment, $paymentOrigin, $paymentYear) {
                $updated = false;
                if (empty($formattedPayments[$payment->id])) {
                    $updatedEntry = $this->hpfService->updateEntryWithPayment(
                        $entry,
                        $payment,
                        $paymentOrigin == 'helloasso' ? '' : $paymentOrigin,
                        (empty($paymentYear) || intval($paymentYear) < 2021) ? '' : strval($paymentYear)
                    );
                    $updated = true;
                }
                return compact(['updated','updatedEntry']);
            }
        );
    }

    /**
     * Feature UUID : hpf-register-payment-action
     */
    protected function addRemoveCommon(string $entryId, $callback): array
    {
        $entry = $this->entryManager->getOne($entryId);
        $updatedEntry = $entry;
        if (empty($entry)) {
            throw new Exception("Not found entry: '$entryId'");
        }
        if (empty($entry['id_typeannonce'])
            || !is_scalar($entry['id_typeannonce'])
            || intval($entry['id_typeannonce']) < 0
            || strval($entry['id_typeannonce']) !== strval(intval($entry['id_typeannonce']))) {
            throw new Exception("'$entryId' has not a right 'id_typeannonce'");
        }
        $form = $this->formManager->getOne($entry['id_typeannonce']);
        if (empty($form)) {
            throw new Exception("form '{$entry['id_typeannonce']}' not found");
        }
        
        $contribFormIds = $this->hpfService->getCurrentPaymentsFormIds();
        if (!in_array($entry['id_typeannonce'], $contribFormIds)) {
            throw new Exception("formId should be in \$contribFormIds!");
        }
        
        $formattedPayments = $this->hpfService->convertStringToPayments($entry[HpfService::PAYMENTS_FIELDNAME] ?? "");
        if (is_callable($callback)) {
            list(
                'updatedEntry' => $updatedEntry,
                'updated'=>$updated,
            ) = $callback($entry, $form, $formattedPayments, $updatedEntry);
            if ($updated) {
                $updatedEntry = $this->hpfService->updateCalcFields($updatedEntry);
                $this->hpfService->updateEntry($updatedEntry);
                $updatedEntry = $this->entryManager->getOne($entryId, false, null, false, true);
            }
        }

        return [
            'status' => 'ok',
            'updatedEntry' => $updatedEntry
        ];

    }

    /**
     * format cleaned string from Throwable
     * @param Throwable $th
     * @return string
     */
    public function formatThrowableStringForExport(Throwable $th): string
    {
        $filePath = $th->getFile();
        $dir = basename(dirname($filePath));
        $filename = basename($filePath);
        return "{$th->getMessage()} in $dir/$filename (line {$th->getLine()})";
    }
}
