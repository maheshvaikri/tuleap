<?php

/**
 * Copyright (c) Enalean, 2015 - 2016. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/
 */
class Admin_Homepage_Presenter
{

    /** @var boolean */
    public $use_standard_homepage;

    /** @var Admin_Homepage_HeadlinePresenter[] */
    public $headlines;

    /** @var string */
    public $title;

    /** @var string */
    public $btn_submit;

    /** @var string html */
    public $csrf_token;

    /** @var string */
    public $headline;

    /** @var string */
    public $placeholder_headline;

    /** @var string */
    public $use_standard_homepage_label;

    /** @var string */
    public $logo_help;

    /** @var string */
    public $logo;

    /** @var string */
    public $path_logo;

    /** @var bool */
    public $use_custom_logo;
    public $standard_title;
    public $customize_title;
    public $label_language;

    public function __construct(
        CSRFSynchronizerToken $csrf,
        $title,
        $use_standard_homepage,
        array $headlines
    ) {
        $this->title                 = $title;
        $this->headlines             = $headlines;
        $this->csrf_token            = $csrf;
        $this->use_standard_homepage = $use_standard_homepage;

        $this->path_logo       = Admin_Homepage_LogoFinder::getCurrentUrl();
        $this->use_custom_logo = Admin_Homepage_LogoFinder::isCustomLogoUsed();

        $this->save                        = $GLOBALS['Language']->getText('admin_main', 'save_conf');
        $this->logo                        = $GLOBALS['Language']->getText('admin_main', 'homepage_logo');
        $this->upload                      = $GLOBALS['Language']->getText('admin_main', 'homepage_upload_logo');
        $this->replace_upload              = $GLOBALS['Language']->getText('admin_main', 'homepage_replace_logo');
        $this->remove_custom_logo          = $GLOBALS['Language']->getText('admin_main', 'remove_custom_logo');
        $this->or_label                    = $GLOBALS['Language']->getText('admin_main', 'homepage_or_label');
        $this->headline                    = $GLOBALS['Language']->getText('admin_main', 'headline');
        $this->logo_help                   = $GLOBALS['Language']->getText('admin_main', 'homepage_logo_help');
        $this->logo_help_end               = $GLOBALS['Language']->getText('admin_main', 'homepage_logo_help_end');
        $this->headline_help               = $GLOBALS['Language']->getText('admin_main', 'headline_help');
        $this->placeholder_headline        = $GLOBALS['Language']->getText('admin_main', 'placeholder_headline');
        $this->use_standard_homepage_help  = $GLOBALS['Language']->getText('admin_main', 'use_standard_homepage_help');
        $this->use_standard_homepage_label = $GLOBALS['Language']->getText('admin_main', 'use_standard_homepage_label');
        $this->standard_title              = $GLOBALS['Language']->getText('admin_main', 'standard_title');
        $this->customize_title             = $GLOBALS['Language']->getText('admin_main', 'customize_title');
        $this->label_language              = $GLOBALS['Language']->getText('admin_main', 'label_language');
    }
}
