<?php

/**
 * Copyright (c) Enalean, 2012. All Rights Reserved.
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
class CheckReleaseGit {
    
    public function __construct($git_exec) {
        $this->git_exec = $git_exec;
    }
    //put your code here
    public function getVersionList($ls_remote_output) {
        $lines    = explode('\n', $ls_remote_output);
        $versions = array();
        foreach ($lines as $line) {
            $parts      = explode('/', $line);
            $versions[] = array_pop($parts);
        }
        return $versions;
    }

    public function maxVersion($versions) {
        return array_reduce($versions, array($this, 'max'));
    }
    
    private function max($v1, $v2) {
        return version_compare($v1, $v2, '>') ? $v1 : $v2;
    }

    public function retainPathsThatHaveChanged($candidate_paths, $revision) {
        $changedPaths = array();
        foreach ($candidate_paths as $path) {
            if ($this->git_exec->hasChanged($path, $revision)) {
                $changedPaths[] = $path;
            }
        }
        return $changedPaths;
    }

    public function keepPathsThatHaventBeenIncremented($changed_paths, $old_revision, $new_revision) {
        $non_incremented_paths = array();
        foreach ($changed_paths as $path) {
            $oldRevisionFileContent = $this->git_exec->fileContent($path, $old_revision);
            $currentRevisionFileContent = $this->git_exec->fileContent($path, $new_revision);
            if (version_compare($oldRevisionFileContent, $currentRevisionFileContent, '>=')) {
                $non_incremented_paths[] = $path;
            }
        }
        return $non_incremented_paths;
    }
    
}

?>
