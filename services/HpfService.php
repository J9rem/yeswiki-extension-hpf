<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;
use YesWiki\Shop\Entity\Payment;
use YesWiki\Wiki;

class HpfService
{
    protected $params;
    private $wiki;

    public function __construct(ParameterBagInterface $params, Wiki $wiki)
    {
        $this->params = $params;
        $this->wiki = $wiki;
    }

    /**
     * append formatted format a payment to registre it into a field
     * @param string $paymentContent
     * @param array $params
     * @return string $paymentContent
     * @throws Exception
     */
    public function appendFormatPaymentForField(string $paymentContent,array $params): array
    {
        $newPayment = $this->formatPaymentForField($params);
        $formattedPayments = $this->convertStringToPayments($paymentContent);
        $formattedPayments = array_merge($formattedPayments,$newPayment);
        return json_encode($formattedPayments);
    }

    /**
     * convert a paymetn raw field to payments array
     * @param string $paymentContent
     * @return array $payments
     * @throws Exception if badly formatted payment
     */
    public function convertStringToPayments(string $paymentContent):array
    {
        $formattedPayments = [];
        if (!empty($paymentContent)){
            try {
                $jsonDecoded = json_decode($paymentContent,true);
                if (empty($jsonDecoded)){
                    throw new Exception('paymentfied is not json encoded');
                }
            } catch (Throwable $th) {
                foreach(explode(',',$paymentContent) as $paymentRaw){
                    $rawField = $this->formatPaymentForField([
                        'id' => $paymentRaw,
                        'origin' => 'helloasso'
                    ]);
                    $formattedPayments = array_merge($formattedPayments,$rawField);
                }
            }
        }
        return $formattedPayments;
    }

    /**
     * format a payment to registre it into a field
     * @param array $params
     * @return array $payment
     * @throws Exception
     */
    public function formatPaymentForField(array $params): array
    {
        if (empty($param['origin'])){
            throw new Excption('$param[\'origin\'] can be empty');
        }
        return $this->formatPaymentForFieldInternal(
            $param['payment'] ?? null,
            $param['origin'] ?? '',
            $param['annee_adhesion'] ?? '',
            $param['valeur_adhesion'] ?? '',
            $param['annee_adhesion_groupe'] ?? '',
            $param['valeur_adhesion_groupe'] ?? '',
            $param['annee_don'] ?? '',
            $param['valeur_don'] ?? '',
            empty($param['payment']) ? ($param['id'] ?? '') : '',
            empty($param['payment']) ? ($param['date'] ?? '') : '',
        );
    }

    /**
     * internal format a payment to registre it into a field
     * @param ?Payment $payment
     * @param string $origin
     * @param string $annee_adhesion
     * @param string $valeur_adhesion
     * @param string $annee_adhesion_groupe
     * @param string $valeur_adhesion_groupe
     * @param string $annee_don
     * @param string $valeur_don
     * @param string $id (if no payment)
     * @param string $date (if no payment)
     * @return array $formattedPayment
     */
    private function formatPaymentForFieldInternal(
        ?Payment $payment,
        string $origin,
        string $annee_adhesion,
        string $valeur_adhesion,
        string $annee_adhesion_groupe,
        string $valeur_adhesion_groupe,
        string $annee_don,
        string $valeur_don,
        string $id,
        string $date
    ): array
    {
        $formattedPayment = [
            $id => [
                'date' => $date,
                'origin' => $origin,
            ]
        ];
        if (!empty($annee_adhesion) && !empty($valeur_adhesion)){
            $formattedPayment[$id]['adhesion'][$annee_adhesion] = $valeur_adhesion;
        }
        if (!empty($annee_adhesion_groupe) && !empty($annee_adhesion_groupe)){
            $formattedPayment[$id]['adhesion_groupe'][$annee_adhesion_groupe] = $annee_adhesion_groupe;
        }
        if (!empty($annee_don) && !empty($valeur_don)){
            $formattedPayment[$id]['don'][$annee_don] = $valeur_don;
        }
        return $formattedPayment;
    }
}
