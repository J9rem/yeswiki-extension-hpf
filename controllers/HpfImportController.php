<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-import-payments
 */

namespace YesWiki\Hpf\Controller;

use Exception;
use Throwable;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Bazar\Field\TitleField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Service\EventDispatcher;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Hpf\Service\HpfService;

class HpfImportController extends YesWikiController
{
    protected $csrfTokenController;
    protected $entryManager;
    protected $eventDispatcher;
    protected $formManager;
    protected $userManager;

    public function __construct(
        CsrfTokenController $csrfTokenController,
        EntryManager $entryManager,
        EventDispatcher $eventDispatcher,
        FormManager $formManager,
        UserManager $userManager
    ) {
        $this->csrfTokenController = $csrfTokenController;
        $this->entryManager = $entryManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->formManager = $formManager;
        $this->userManager = $userManager;
    }
    
    /**
     * create antry or append
     * @param string $mode
     * @param string $type
     * @param string $formId
     * @return ApiResponse
     * @throws Exception
     */
    public function createEntryOrAppendPaymentForMemberShip(string $mode,string $type,string $formId): ApiResponse
    {
        $this->csrfTokenController->checkToken('main', 'POST', 'anti-csrf-token',false);

        if(empty($_POST['data'])
            || !is_array($_POST['data'])) {
            throw new Exception("\"\$_POST['data']\" should be an array !");
        }

        if (!in_array($mode,['createEntry','appendPayment'],true)){
            throw new Exception("Mode \"$mode\" is not supported");
        }
        $appendMode = ($mode === 'appendPayment');

        if (!in_array($type,['college1','college2'],true)){
            throw new Exception("Mode \"$type\" is not supported");
        }
        $isGroup = ($type === 'college2');

        if (empty($formId)){
            throw new Exception("\"$formId\" should not be empty");
        }

        $form = $this->formManager->getOne($formId);
        if (empty($form['prepared'])){
            throw new Exception("form not found");
        }

        if ($appendMode){
            return new ApiResponse(
                ['error' => 'not ready'],
                500
            );
        } else {
            try {
                $newEntry = $this->addEntryIfPossible($_POST['data'],$form,$isGroup);
                if (empty($newEntry) || !is_array($newEntry)){
                    throw new Exception("entry not created");
                }
                return new ApiResponse(
                    $newEntry,
                    200
                );
            } catch (Throwable $th) {
                return new ApiResponse(
                    ['error' => $th->getMessage()],
                    400
                );
            }
        }
    }

    /**
     * add Entry
     * @param array $data
     * @param array $form
     * @param bool $isGroup
     * @return array $entry
     * @throws Exception
     */
    protected function addEntryIfPossible(array $data, array $form, bool $isGroup): array
    {
        if (empty($data['email'])){
            throw new Exception("\$data['email'] should not be empty !");
        }
        if (!is_string($data['email'])){
            throw new Exception("\$data['email'] should be a string !");
        }
        if (!$this->canAddEntryInForm($form,$data['email'])){
            throw new Exception("An entry already exists for email '{$data['email']}'");
        }
        
        // clean $_POST and $_REQUEST
        $_POST = [];
        $_REQUEST = [];

        // set antispam
        $entry = [
            'antispam' => 1,
            'bf_titre' => ''
        ];

        // set title
        $titleFields = array_filter($form['prepared'], function ($field) {
            return $field instanceof TitleField;
        });
        if (!empty($titleFields)){
            $firstTitleField = array_shift($titleFields);
            $entry['bf_titre'] = $firstTitleField->getName();
        }
        if (empty($entry['bf_titre'])){
            $entry['bf_titre'] = $data['name'];
        }

        // set title
        $emailFields = array_filter($form['prepared'], function ($field) {
            return $field instanceof EmailField;
        });
        if (!empty($emailFields)){
            $firstEmailField = array_shift($emailFields);
            $entry[$firstEmailField->getPropertyName()] = $data['email'];
        }

        // update post and request

        foreach ($entry as $key => $value) {
            $_POST[$key] = $value;
            $_REQUEST[$key] = $value;
        }

        // create entry
        $createdEntry = $this->entryManager->create($form['bn_id_nature'], $entry);
        $this->eventDispatcher->yesWikiDispatch('entry.created', [
            'id' => $createdEntry['id_fiche'],
            'data' => $createdEntry
        ]);

        return (empty($createdEntry) || !is_array($createdEntry)) ? [] : $createdEntry;
    }

    /**
     * check if can add entry
     * inspired from EntryController::checkIfOnlyOneEntry
     * Feature UUID : hpf-import-payments
     * @param array $form
     * @param string $email
     * @return bool
     * @throws Exception
     */
    protected function canAddEntryInForm(array $form,string $email): bool
    {
        if (!isset($form['bn_only_one_entry']) || $form['bn_only_one_entry'] !== "Y") {
            return true;
        }

        $user = $this->userManager->getOneByEmail($email);

        if (empty($user['name']) || empty($user['email'])){
            return true;
        }

        $emailFields = array_filter($form['prepared'], function ($field) {
            return $field instanceof EmailField;
        });

        if (empty($emailFields)){
            throw new Exception("Email field not found");
        }
        $firstEmailField = array_shift($emailFields);
        if (empty($firstEmailField->getPropertyName())){
            throw new Exception("Email field has not property name");
        }
        
        $entries = $this->entryManager->search([
            'formsIds' => [$form['bn_id_nature']],
            'queries' => [
                $firstEmailField->getPropertyName() => $user['email']
            ]
        ]);

        return empty($entries);
    }
}