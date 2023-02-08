<?php

/*
 * This file is part of the YesWiki Extension hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Service;

use YesWiki\Aceditor\Service\ActionsBuilderService as AceditorActionsBuilderService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Wiki;

trait ActionsBuilderServiceCommon
{
    protected $previousData;
    protected $data;
    protected $parentActionsBuilderService;
    protected $renderer;
    protected $wiki;

    public function __construct(TemplateEngine $renderer, Wiki $wiki, $parentActionsBuilderService)
    {
        $this->data = null;
        $this->previousData = null;
        $this->parentActionsBuilderService = $parentActionsBuilderService;
        $this->renderer = $renderer;
        $this->wiki = $wiki;
    }

    public function setPreviousData(?array $data)
    {
        if (is_null($this->previousData)) {
            $this->previousData = is_array($data) ? $data : [];
            if ($this->parentActionsBuilderService && method_exists($this->parentActionsBuilderService, 'setPreviousData')) {
                $this->parentActionsBuilderService->setPreviousData($data);
            }
        }
    }

    // ---------------------
    // Data for the template
    // ---------------------
    public function getData()
    {
        if (is_null($this->data)) {
            if (!empty($this->parentActionsBuilderService)) {
                $this->data = $this->parentActionsBuilderService->getData();
            } else {
                $this->data = $this->previousData;
            }
            if (isset($this->data['action_groups']['bazarliste'])) {
                if (isset($this->data['action_groups']['bazarliste']['actions']) &&
                        !isset($this->data['action_groups']['bazarliste']['actions']['bazarlistnoempty'])) {
                    $newTab = [];
                    $newTab['bazarliste'] = $this->data['action_groups']['bazarliste']['actions']['bazarliste'];
                    $newTab['bazarlistnoempty'] = [];
                    foreach ($this->data['action_groups']['bazarliste']['actions'] as $key => $value) {
                        if ($key != 'bazarliste'){
                            $newTab[$key] = $value;
                        }
                    }
                    $this->data['action_groups']['bazarliste']['actions'] = $newTab;
                    $this->data['action_groups']['bazarliste']['actions']['bazarlistnoempty'] = [
                        'label' => _t('HPF_bazarlistnoempty_label'),
                        'description' => _t('AB_bazarliste_description'),
                        'properties' => [
                            'template' => [
                                'value' => 'list-no-empty',
                            ],
                            'displayfields' => [
                                'type' => 'correspondance',
                                'showif' => 'dynamic'
                            ],
                            'subproperties' => [
                                'title' => [
                                    'type' => 'form-field',
                                    'label' => _t('AB_bazarliste_displayfields_title_label'),
                                    'default' => 'bf_titre'
                                ],
                                'subtitle' => [
                                    'type' => 'form-field',
                                    'extraFields' => [
                                        'owner',
                                        'date_creation_fiche',
                                        'date_maj_fiche'
                                    ],
                                    'label' => _t('AB_bazarliste_displayfields_subtitle_label'),
                                    'default' => ""
                                ],
                                'floating' => [
                                    'type' => 'form-field',
                                    'extraFields' => [
                                        'owner',
                                        'date_creation_fiche',
                                        'date_maj_fiche'
                                    ],
                                    'label' => _t('AB_bazarliste_displayfields_floating_label'),
                                    'default' => ""
                                    ],
                                'visual' => [
                                    'type' => 'form-field',
                                    'extraFields' => [
                                        'owner',
                                        'date_creation_fiche',
                                        'date_maj_fiche'
                                    ],
                                    'label' => _t('AB_bazarliste_displayfields_visual_label'),
                                    'default' => ""
                                ]
                            ]
                        ],
                    ];
                }
                if (isset($this->data['action_groups']['bazarliste']['actions']['commons']['properties']['searchfields']['showOnlyFor'])){
                    $this->data['action_groups']['bazarliste']['actions']['commons']['properties']['searchfields']['showOnlyFor'][] = 'bazarlistnoempty';
                }
                if (isset($this->data['action_groups']['bazarliste']['actions']['commons']['properties']['colorfield']['showOnlyFor'])){
                    $this->data['action_groups']['bazarliste']['actions']['commons']['properties']['colorfield']['showOnlyFor'][] = 'bazarlistnoempty';
                }
                if (isset($this->data['action_groups']['bazarliste']['actions']['commons']['properties']['colormapping']['showOnlyFor'])){
                    $this->data['action_groups']['bazarliste']['actions']['commons']['properties']['colormapping']['showOnlyFor'][] = 'bazarlistnoempty';
                }
                if (isset($this->data['action_groups']['bazarliste']['actions']['commons']['properties']['iconfield']['showOnlyFor'])){
                    $this->data['action_groups']['bazarliste']['actions']['commons']['properties']['iconfield']['showOnlyFor'][] = 'bazarlistnoempty';
                }
                if (isset($this->data['action_groups']['bazarliste']['actions']['commons']['properties']['iconmapping']['showOnlyFor'])){
                    $this->data['action_groups']['bazarliste']['actions']['commons']['properties']['iconmapping']['showOnlyFor'][] = 'bazarlistnoempty';
                }
            }
        }
        return $this->data;
    }
}

if (class_exists(AceditorActionsBuilderService::class, false)) {
    class ActionsBuilderService extends AceditorActionsBuilderService
    {
        use ActionsBuilderServiceCommon;
    }
} else {
    class ActionsBuilderService
    {
        use ActionsBuilderServiceCommon;
    }
}
