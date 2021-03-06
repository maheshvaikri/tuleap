<?php
/**
 * Copyright (c) Enalean, 2014, 2015, 2016. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
require_once 'bootstrap.php';

use Tuleap\Markdown\ContentInterpretor;

class GitMarkdownFileTest extends TuleapTestCase {

    private $git_exec;
    private $git_markdown_file;

    public function setUp() {
        parent::setUp();
        $this->git_exec      = mock('Git_Exec');
        $content_interpretor = new ContentInterpretor();

        $this->git_markdown_file = new GitMarkdownFile($this->git_exec, $content_interpretor);
    }

    public function testGetMarkdownFilesContent() {
        $files_names = array("test.java", "test.markdown", "readme.md", "test.c", "test.mkd");
        stub($this->git_exec)->lsTree('commit', '')->returns($files_names);

        $test_md_content = "Content of readme.md\n==========";
        stub($this->git_exec)->getFileContent('commit', 'readme.md')->returns($test_md_content);

        $expected_result = array(
            'file_name'    => "readme.md",
            'file_content' => Michelf\MarkdownExtra::defaultTransform($test_md_content)
        );

        $this->assertEqual($this->git_markdown_file->getReadmeFileContent('', 'commit'), $expected_result);
    }

    public function itRendersMarkdownFilesInSubDirectory() {
        $files_names = array("subdir/readme.md");
        stub($this->git_exec)->lsTree('commit', 'subdir/')->returns($files_names);

        $test_md_content = "Content of readme.text";
        stub($this->git_exec)->getFileContent('commit', 'subdir/readme.md')->returns($test_md_content);

        $expected_result = array(
            'file_name'    => "subdir/readme.md",
            'file_content' => Michelf\MarkdownExtra::defaultTransform($test_md_content)
        );

        $this->assertEqual($this->git_markdown_file->getReadmeFileContent('subdir/', 'commit'), $expected_result);
    }
}
