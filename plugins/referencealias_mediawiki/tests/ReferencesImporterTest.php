<?php
/**
 * Copyright (c) Enalean SAS, 2016. All Rights Reserved.
 * Copyright (c) Enalean, 2016. All Rights Reserved.
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
namespace Tuleap\ReferenceAliasMediawiki;

use SimpleXMLElement;
use Tuleap\Project\XML\Import\ImportConfig;

include 'bootstrap.php';

class ReferencesImporterTest extends \TuleapTestCase
{

    public function setUp()
    {
        $this->dao        = mock('Tuleap\ReferenceAliasMediawiki\CompatibilityDao');
        $this->logger     = mock('Logger');
        $this->importer   = new ReferencesImporter($this->dao, $this->logger);
    }

    public function testItShouldAddMediaWikiLinks()
    {
        $xml = <<<XML
            <references>
                <reference source="wiki76532" target="HomePage" />
            </references>
XML;
        $simple_xml = new SimpleXMLElement($xml);

        stub($this->dao)->getRef()->returns(mock('DataAccessResult'));

        $project = mock('Project');

        expect($this->dao)->insertRef($project, "wiki76532", "HomePage")->once();

        $this->importer->importCompatRefXML(new ImportConfig(), $project, $simple_xml, array());
    }

    public function testItShouldNotAddUnknownReferences()
    {
        $xml = <<<XML
            <references>
                <reference source="stuff1234" target="1"/>
            </references>
XML;
        $simple_xml = new SimpleXMLElement($xml);

        stub($this->dao)->getRef()->returns(mock('DataAccessResult'));

        expect($this->dao)->insertRef()->never();

        $this->importer->importCompatRefXML(new ImportConfig(), mock('Project'), $simple_xml, array());
    }
}
