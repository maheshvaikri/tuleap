<?php
/**
 * Copyright (c) Enalean, 2014 - 2018. All Rights Reserved.
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
require_once __DIR__.'/../../../bootstrap.php';

class Tracker_XML_Updater_ChangesetXMLUpdaterTest extends TuleapTestCase {

    /** @var Tracker_XML_Updater_ChangesetXMLUpdater */
    private $updater;

    /** @var SimpleXMLElement */
    private $artifact_xml;

    /** @var Tracker_XML_Updater_FieldChangeXMLUpdaterVisitor */
    private $visitor;

    /** @var array */
    private $submitted_values;

    /** @var Tracker_FormElementFactory */
    private $formelement_factory;

    /** @var PFUser */
    private $user;

    /** @var int */
    private $tracker_id = 123;

    /** @var int */
    private $user_id = 101;

    /** @var Tracker_FormElement_Field */
    private $field_summary;

    /** @var Tracker_FormElement_Field */
    private $field_effort;

    /** @var Tracker_FormElement_Field */
    private $field_details;

    public function setUp() {
        parent::setUp();
        $this->artifact_xml        = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
                . '<artifact>'
                . '  <changeset>'
                . '    <submitted_on>2014</submitted_on>'
                . '    <submitted_by>123</submitted_by>'
                . '    <field_change field_name="summary">'
                . '      <value>Initial summary value</value>'
                . '    </field_change>'
                . '    <field_change field_name="effort">'
                . '      <value>125</value>'
                . '    </field_change>'
                . '    <field_change field_name="details">'
                . '      <value>Content of details</value>'
                . '    </field_change>'
                . '  </changeset>'
                . '</artifact>');
        $this->visitor             = mock('Tracker_XML_Updater_FieldChangeXMLUpdaterVisitor');
        $this->formelement_factory = mock('Tracker_FormElementFactory');
        $this->updater             = new Tracker_XML_Updater_ChangesetXMLUpdater($this->visitor, $this->formelement_factory);
        $this->user                = aUser()->withId($this->user_id)->build();
        $this->tracker             = aMockTracker()->withId($this->tracker_id)->build();
        $this->submitted_values    = array(
            1001 => 'Content of summary field',
            1002 => '123'
        );

        $this->field_summary = aStringField()->withId(1001)->withName('summary')->build();
        $this->field_effort  = aStringField()->withId(1002)->build();
        $this->field_details = aStringField()->withId(1003)->build();
        stub($this->formelement_factory)
            ->getUsedFieldByNameForUser($this->tracker_id, 'summary', $this->user)
            ->returns($this->field_summary);
        stub($this->formelement_factory)
            ->getUsedFieldByNameForUser($this->tracker_id, 'effort', $this->user)
            ->returns($this->field_effort);
        stub($this->formelement_factory)
            ->getUsedFieldByNameForUser($this->tracker_id, 'details', $this->user)
            ->returns($this->field_details);
    }

    public function itUpdatesTheSubmittedOnInformation() {
        $now = time();

        $this->updater->update($this->tracker, $this->artifact_xml, $this->submitted_values, $this->user, $now);

        $this->assertEqual((string)$this->artifact_xml->changeset->submitted_on, date('c', $now));
    }

    public function itUpdatesTheSubmittedByInformation() {
        $this->updater->update($this->tracker, $this->artifact_xml, $this->submitted_values, $this->user, time());

        $this->assertEqual((int)$this->artifact_xml->changeset->submitted_by, $this->user->getId());
    }

    public function itAsksToVisitorToUpdateSummary() {
        expect($this->visitor)->update(
            $this->artifact_xml->changeset->field_change[0],
            $this->field_summary,
            'Content of summary field'
        )->at(0);

        $this->updater->update($this->tracker, $this->artifact_xml, $this->submitted_values, $this->user, time());
    }

    public function itAsksToVisitorToUpdateEffort() {
        expect($this->visitor)->update(
            $this->artifact_xml->changeset->field_change[1],
            $this->field_effort,
            '123'
        )->at(1);

        $this->updater->update($this->tracker, $this->artifact_xml, $this->submitted_values, $this->user, time());
    }

    public function itDoesNotUpdateFieldIfTheyAreNotSubmitted() {
        expect($this->visitor)->update()->count(2);

        $this->updater->update($this->tracker, $this->artifact_xml, $this->submitted_values, $this->user, time());
    }

    public function itUpdatesTheTitleFieldChangeTagsInMoveAction()
    {
        $target_title_field = aMockField()->withName('title2')->build();
        $target_tracker     = aMockTracker()->withId(201)->build();

        stub($this->tracker)->getTitleField()->returns($this->field_summary);
        stub($target_tracker)->getTitleField()->returns($target_title_field);

        $time = time();
        $this->updater->updateForMoveAction($this->tracker, $target_tracker, $this->artifact_xml, $this->user, $time);

        $this->assertEqual((int)$this->artifact_xml['tracker_id'], 201);
        $this->assertEqual((string)$this->artifact_xml->changeset->submitted_on, date('c', $time));
        $this->assertEqual((int)$this->artifact_xml->changeset->submitted_by, $this->user->getId());

        $this->assertEqual(count($this->artifact_xml->changeset->field_change), 1);
        $this->assertEqual($this->artifact_xml->changeset->field_change[0]['field_name'], 'title2');
        $this->assertEqual((string)$this->artifact_xml->changeset->field_change[0]->value, 'Initial summary value');
    }

    public function itUpdatesTheDescriptionFieldChangeTagsInMoveAction()
    {
        $source_description_field = aMockField()->withName('desc')->build();
        $target_description_field = aMockField()->withName('v2desc')->build();
        $target_tracker           = aMockTracker()->withId(201)->build();

        $artifact_xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
            . '<artifact>'
            . '  <changeset>'
            . '    <submitted_on>2014</submitted_on>'
            . '    <submitted_by>123</submitted_by>'
            . '    <field_change field_name="summary">'
            . '      <value>Initial summary value</value>'
            . '    </field_change>'
            . '    <field_change field_name="desc">'
            . '      <value format="html"><![CDATA[<p><strong>Description</strong></p>]]></value>'
            . '    </field_change>'
            . '    <field_change field_name="details">'
            . '      <value>Content of details</value>'
            . '    </field_change>'
            . '  </changeset>'
            .'  <changeset>'
            . '    <submitted_on>2015</submitted_on>'
            . '    <submitted_by>123</submitted_by>'
            . '    <field_change field_name="summary">'
            . '      <value>Second summary value</value>'
            . '    </field_change>'
            . '    <field_change field_name="desc">'
            . '      <value format="html"><![CDATA[<p><strong>Description v2</strong></p>]]></value>'
            . '    </field_change>'
            . '    <field_change field_name="details">'
            . '      <value>Content of details v2</value>'
            . '    </field_change>'
            . '  </changeset>'
            . '</artifact>'
        );

        stub($this->tracker)->getDescriptionField()->returns($source_description_field);
        stub($target_tracker)->getDescriptionField()->returns($target_description_field);

        $time = time();
        $this->updater->updateForMoveAction($this->tracker, $target_tracker, $artifact_xml, $this->user, $time);

        $this->assertEqual((int)$artifact_xml['tracker_id'], 201);
        $this->assertEqual((string)$artifact_xml->changeset[0]->submitted_on, date('c', $time));
        $this->assertEqual((int)$artifact_xml->changeset[0]->submitted_by, 101);

        $this->assertEqual(count($artifact_xml->changeset), 2);
        $this->assertEqual($artifact_xml->changeset[0]->field_change[0]['field_name'], 'v2desc');
        $this->assertEqual((string)$artifact_xml->changeset[0]->field_change[0]->value, '<p><strong>Description</strong></p>');
        $this->assertEqual((string)$artifact_xml->changeset[0]->field_change[0]->value['format'], 'html');
        $this->assertEqual($artifact_xml->changeset[1]->field_change[0]['field_name'], 'v2desc');
        $this->assertEqual((string)$artifact_xml->changeset[1]->field_change[0]->value, '<p><strong>Description v2</strong></p>');
        $this->assertEqual((string)$artifact_xml->changeset[1]->field_change[0]->value['format'], 'html');
    }

    public function itDealsWithCommentTagsInMoveAction()
    {
        $source_description_field = aMockField()->withName('desc')->build();
        $target_description_field = aMockField()->withName('v2desc')->build();
        $target_tracker           = aMockTracker()->withId(201)->build();

        $artifact_xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
            . '<artifact>'
            . '  <changeset>'
            . '    <submitted_on>2014</submitted_on>'
            . '    <submitted_by>123</submitted_by>'
            . '    <field_change field_name="summary">'
            . '      <value>Initial summary value</value>'
            . '    </field_change>'
            . '    <field_change field_name="desc">'
            . '      <value format="html"><![CDATA[<p><strong>Description</strong></p>]]></value>'
            . '    </field_change>'
            . '    <field_change field_name="details">'
            . '      <value>Content of details</value>'
            . '    </field_change>'
            . '    <comments/>'
            . '  </changeset>'
            .'  <changeset>'
            . '    <submitted_on>2015</submitted_on>'
            . '    <submitted_by>123</submitted_by>'
            . '    <field_change field_name="summary">'
            . '      <value>Second summary value</value>'
            . '    </field_change>'
            . '    <field_change field_name="desc">'
            . '      <value format="html"><![CDATA[<p><strong>Description v2</strong></p>]]></value>'
            . '    </field_change>'
            . '    <field_change field_name="details">'
            . '      <value>Content of details v2</value>'
            . '    </field_change>'
            . '    <comments>
                        <comment>
                            <submitted_by format="id">123</submitted_by>
                            <submitted_on format="ISO8601">2014</submitted_on>
                            <body format="text"><![CDATA[My comment]]></body>
                        </comment>
                    </comments>'
            . '  </changeset>'
            . '</artifact>'
        );

        stub($this->tracker)->getDescriptionField()->returns($source_description_field);
        stub($target_tracker)->getDescriptionField()->returns($target_description_field);

        $this->updater->updateForMoveAction($this->tracker, $target_tracker, $artifact_xml, $this->user, time());

        $this->assertEqual(count($artifact_xml->changeset), 2);
        $this->assertNull($artifact_xml->changeset[0]->comments[0]);
        $this->assertEqual((string)$artifact_xml->changeset[1]->comments->comment[0]->body, 'My comment');
    }

    public function itDoesNotRemoveFirstChangesetTagInMoveAction()
    {
        $source_description_field = aMockField()->withName('desc')->build();
        $target_description_field = aMockField()->withName('v2desc')->build();
        $target_tracker           = aMockTracker()->withId(201)->build();

        $artifact_xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
            . '<artifact>'
            . '  <changeset>'
            . '    <submitted_on>2013</submitted_on>'
            . '    <submitted_by>123</submitted_by>'
            . '    <field_change field_name="details">'
            . '      <value>Content of details</value>'
            . '    </field_change>'
            . '  </changeset>'
            . '  <changeset>'
            . '    <submitted_on>2014</submitted_on>'
            . '    <submitted_by>123</submitted_by>'
            . '    <field_change field_name="summary">'
            . '      <value>Initial summary value</value>'
            . '    </field_change>'
            . '    <field_change field_name="desc">'
            . '      <value format="html"><![CDATA[<p><strong>Description</strong></p>]]></value>'
            . '    </field_change>'
            . '    <field_change field_name="details">'
            . '      <value>Content of details v2</value>'
            . '    </field_change>'
            . '  </changeset>'
            .'  <changeset>'
            . '    <submitted_on>2015</submitted_on>'
            . '    <submitted_by>123</submitted_by>'
            . '    <field_change field_name="summary">'
            . '      <value>Second summary value</value>'
            . '    </field_change>'
            . '    <field_change field_name="desc">'
            . '      <value format="html"><![CDATA[<p><strong>Description v2</strong></p>]]></value>'
            . '    </field_change>'
            . '    <field_change field_name="details">'
            . '      <value>Content of details v3</value>'
            . '    </field_change>'
            . '  </changeset>'
            . '</artifact>'
        );

        stub($this->tracker)->getDescriptionField()->returns($source_description_field);
        stub($target_tracker)->getDescriptionField()->returns($target_description_field);

        $time = time();
        $this->updater->updateForMoveAction($this->tracker, $target_tracker, $artifact_xml, $this->user, $time);

        $this->assertEqual((int)$artifact_xml['tracker_id'], 201);
        $this->assertEqual((string)$artifact_xml->changeset[0]->submitted_on, date('c', $time));
        $this->assertEqual((int)$artifact_xml->changeset[0]->submitted_by, 101);

        $this->assertEqual(count($artifact_xml->changeset), 3);
        $this->assertNull($artifact_xml->changeset[0]->field_change[0]);
        $this->assertEqual($artifact_xml->changeset[1]->field_change[0]['field_name'], 'v2desc');
        $this->assertEqual((string)$artifact_xml->changeset[1]->field_change[0]->value, '<p><strong>Description</strong></p>');
        $this->assertEqual((string)$artifact_xml->changeset[1]->field_change[0]->value['format'], 'html');
        $this->assertEqual($artifact_xml->changeset[2]->field_change[0]['field_name'], 'v2desc');
        $this->assertEqual((string)$artifact_xml->changeset[2]->field_change[0]->value, '<p><strong>Description v2</strong></p>');
        $this->assertEqual((string)$artifact_xml->changeset[2]->field_change[0]->value['format'], 'html');
    }
}