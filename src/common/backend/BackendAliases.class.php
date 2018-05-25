<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright (c) Enalean, 2011-2018. All rights reserved
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

class BackendAliases extends Backend {

    const ALIAS_ENTRY_FORMAT = "%-50s%-10s";

    protected $need_update=false;   
    protected $mailinglistdao = null;

    /**
     * Get the mainling list dao
     * 
     * @return MailingListDao
     */
    protected function getMailingListDao() {
        if (!$this->mailinglistdao) {
            $this->mailinglistdao = new MailingListDao(CodendiDataAccess::instance());
        }
        return $this->mailinglistdao;
    }

    /**
     * Set if we need to update mail aliases
     * 
     * @return void
     */
    function setNeedUpdateMailAliases() {
        $this->need_update = true;
    }

    /**
     * Do we need to update mail aliases?
     * 
     * @return boolean
     */
    function aliasesNeedUpdate() {
        return $this->need_update;
    }


    /**
     * Write System email aliases: 
     * - generic aliases like codendi-admin
     * - mailing list aliases for mailman
     * - user aliases for addresses like user@codendi.server.name
     *
     * @return bool
     */
    public function update() {
        $alias_file = $GLOBALS['alias_file'];
        $alias_file_new = $alias_file.".new";
        $alias_file_old = $alias_file.".old";

        if (!$fp = fopen($alias_file_new, 'w')) {
            $this->log("Can't open file for writing: $alias_file_new", Backend::LOG_ERROR);
            return false;
        }

        if ((! $this->writeGenericAliases($fp))
            || (! $this->writeListAliases($fp))
            || (! $this->writeOtherAliases($fp))
        ) {
            $this->log("Can't write aliases to $alias_file_new", Backend::LOG_ERROR);
            return false;
        }
        fclose($fp);

        // Replace current file by new one
        if (!$this->installNewFileVersion($alias_file_new, $alias_file, $alias_file_old, true)) {
            return false;
        }

        // Run newaliases
        return ($this->system("/usr/bin/newaliases > /dev/null") !== false);
    }


    /** 
     * Generic part: should be written first
     *
     * @param resource $fp A file system pointer resource that is typically created using fopen().
     *
     * @return bool
     */
    protected function writeGenericAliases($fp) {
        fwrite($fp, "# This file is autogenerated - Do not edit\n\n");
        fwrite($fp, "# You can change te default settings by updating:\n");
        fwrite($fp, "# /usr/share/codendi/src/common/backend/BackendAliases.class.php\n");
        fwrite($fp, "# The Codendi wide aliases (specific to Codendi) resides in this file\n");
        fwrite($fp, "# All system wide aliases remains in /etc/aliases\n\n");
        fwrite($fp, "# Codendi wide aliases\n\n");
        fwrite($fp, "codendi-contact:         codendi-admin\n\n");
        fwrite($fp, "codex-contact:           codendi-admin\n");// deprecated user name
        fwrite($fp, "codex-admin:             codendi-admin\n");// deprecated user name
        fwrite($fp, "sourceforge:             codendi-admin\n");// deprecated user name
        fwrite($fp, $this->getHTTPUser().":               codendi-admin\n");
        fwrite($fp, "noreply:                 \"|".$GLOBALS['codendi_bin_prefix']."/gotohell\"\n");
        fwrite($fp, "undisclosed-recipients:  \"|".$GLOBALS['codendi_bin_prefix']."/gotohell\"\n"); // for phpWiki notifications...
        fwrite($fp, "webmaster:               codendi-admin\n");
        return fwrite($fp, "\n\n");
    }

    /** 
     * Mailing list aliases for mailman 
     * 
     * @param resource $fp A file system pointer resource that is typically created using fopen().
     * 
     * @return bool
     */
    protected function writeListAliases($fp) {
        // Determine the name of the mailman wrapper
        $mm_wrapper = $GLOBALS['mailman_wrapper'];
        
        fwrite($fp, "### Begin Mailing List Aliases ###\n\n");
        $dar = $this->getMailingListDao()->searchAllActiveML();
        foreach ($dar as $row) {
            if ($row['list_name']) {
                // Convert to lower case
                $list_name = strtolower($row['list_name']);
                // Remove blank chars
                $list_name = str_replace(' ', '', $list_name);
                // Mailman 2.1 aliases
                $list_name_as_argument = escapeshellarg($list_name);
                $this->writeAlias($fp, new System_Alias("$list_name",             "\"|$mm_wrapper post $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-admin",       "\"|$mm_wrapper admin $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-bounces",     "\"|$mm_wrapper bounces $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-confirm",     "\"|$mm_wrapper confirm $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-join",        "\"|$mm_wrapper join $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-leave",       "\"|$mm_wrapper leave $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-owner",       "\"|$mm_wrapper owner $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-request",     "\"|$mm_wrapper request $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-subscribe",   "\"|$mm_wrapper subscribe $list_name_as_argument\""));
                $this->writeAlias($fp, new System_Alias("$list_name-unsubscribe", "\"|$mm_wrapper unsubscribe $list_name_as_argument\""));
            }
        }
        return fwrite($fp, "\n\n");
    }

    private function writeOtherAliases($fp) {
        $aliases = array();
        EventManager::instance()->processEvent(
            Event::BACKEND_ALIAS_GET_ALIASES,
            array(
                'aliases' => &$aliases
            )
        );

        foreach ($aliases as $alias) {
            $this->writeAlias($fp, $alias);
        }

        return fwrite($fp, "\n\n");
    }

    private function writeAlias($fp, System_Alias $alias)
    {
        $name  = str_replace(['"', "\n"], '', $alias->getName());
        $value = str_replace("\n", '', $alias->getValue());
        fwrite($fp, sprintf(self::ALIAS_ENTRY_FORMAT, '"' . $name . '":', $value . "\n"));
    }
}