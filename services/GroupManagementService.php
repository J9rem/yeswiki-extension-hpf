<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-area-management
 */

namespace YesWiki\Hpf\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Groupmanagement\Service\GroupManagementService as MainGroupManagementService;
use YesWiki\Hpf\Service\GroupManagementServiceInterface;
use YesWiki\Wiki;

if (file_exists('tools/groupmanagement/services/GroupManagementService.php')) {
    include_once 'tools/groupmanagement/services/GroupManagementService.php';
}
if (file_exists('tools/hpf/services/GroupManagementServiceInterface.php')) {
    include_once 'tools/hpf/services/GroupManagementServiceInterface.php';
}

if (class_exists(MainGroupManagementService::class, false)) {
    class GroupManagementService extends MainGroupManagementService implements GroupManagementServiceInterface
    {
    }
} else {
    class GroupManagementService implements GroupManagementServiceInterface
    {
        protected $wiki;

        public function __construct(Wiki $wiki)
        {
            $this->wiki = $wiki;
        }
        public function getParentsWhereAdmin(string $formId, string $suffix, string $loggedUserName): array
        {
            $this->triggerErrorIfNeeded();
            return [];
        }

        public function appendEntryWithData(array $entry, array &$results, string $key, $ids, $callback)
        {
            // do nothing
            $this->triggerErrorIfNeeded();
        }

        public function filterEntriesFromParents(
            array $entries,
            bool $entriesMode = true,
            string $suffix =  '',
            string $parentFormId =  '',
            $extractExtraFields = null,
            string $keyIntoAppendData = '',
            $callbackForAppendData = null,
            $extraCallback = null,
            bool $extractAllIds = false
        ): array {
            $this->triggerErrorIfNeeded();
            return [];
        }

        protected function triggerErrorIfNeeded()
        {
            if ($this->wiki->UserIsAdmin()) {
                trigger_error("Extension `hpf` works only with extension `groupmanagement` !");
            }
        }
    }
}
