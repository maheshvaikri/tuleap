<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright (c) Enalean, 2015. All Rights Reserved.
 * Copyright (c) Sogilis, 2016. All Rights Reserved.
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

use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NatureTablePresenter;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\ArtifactInNatureTablePresenter;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NatureSelectorPresenter;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NaturePresenterFactory;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NatureDao;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\ArtifactLinkValueSaver;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\SubmittedValueConvertor;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\SourceOfAssociationDetector;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\SourceOfAssociationCollection;

class Tracker_FormElement_Field_ArtifactLink extends Tracker_FormElement_Field {

    const CREATE_NEW_PARENT_VALUE = -1;
    const NEW_VALUES_KEY          = 'new_values';
    const NATURE_IS_CHILD         = '_is_child';
    const NO_NATURE               = '';

    /**
     * @var Tracker_ArtifactFactory
     */
    private $artifact_factory;

    /**
     * @var SourceOfAssociationCollection | null
     *
     * @use getSourceOfAssociationCollection()
     */
    private $cache_source_of_association_collection = null;

    /** @return SourceOfAssociationCollection */
    private function getSourceOfAssociationCollection() {
        if (! $this->cache_source_of_association_collection) {
            $this->cache_source_of_association_collection = new SourceOfAssociationCollection();
        }

        return $this->cache_source_of_association_collection;
    }

    /**
     * Display the html form in the admin ui
     *
     * @return string html
     */
    protected function fetchAdminFormElement() {
        $hp = Codendi_HTMLPurifier::instance();
        $html = '';
        $value = '';
        if ($this->hasDefaultValue()) {
            $value = $this->getDefaultValue();
        }
        $html .= '<input type="text"
                         value="'.  $hp->purify($value, CODENDI_PURIFIER_CONVERT_HTML) .'" autocomplete="off" />';
        $html .= '<br />';
        $html .= '<a href="#">bug #123</a><br />';
        $html .= '<a href="#">bug #321</a><br />';
        $html .= '<a href="#">story #10234</a>';
        return $html;
    }

    /**
     * Display the field value as a criteria
     *
     * @param Tracker_ReportCriteria $criteria
     *
     * @return string
     */
    public function fetchCriteriaValue($criteria) {
        $html = '<input type="text" name="criteria['. $this->id .']" id="tracker_report_criteria_'. $this->id .'" value="';
        if ($criteria_value = $this->getCriteriaValue($criteria)) {
            $hp = Codendi_HTMLPurifier::instance();
            $html .= $hp->purify($criteria_value, CODENDI_PURIFIER_CONVERT_HTML);
        }
        $html .= '" />';
        return $html;
    }

    /**
     * Display the field as a Changeset value.
     * Used in report table
     *
     * @param int $artifact_id the corresponding artifact id
     * @param int $changeset_id the corresponding changeset
     * @param mixed $value the value of the field
     *
     * @return string
     */
    public function fetchChangesetValue($artifact_id, $changeset_id, $value, $report=null, $from_aid = null) {
        $arr = array();
        $values = $this->getChangesetValues($changeset_id);
        foreach ($values as $artifact_link_info) {
            $arr[] = $artifact_link_info->getUrl();
        }
        $html = implode(', ', $arr);
        return $html;
    }

    /**
     * Display the field as a Changeset value.
     * Used in CSV data export.
     *
     * @param int $artifact_id the corresponding artifact id
     * @param int $changeset_id the corresponding changeset
     * @param mixed $value the value of the field
     *
     * @return string
     */
    public function fetchCSVChangesetValue($artifact_id, $changeset_id, $value, $report) {
        $arr = array();
        $values = $this->getChangesetValues($changeset_id);
        foreach ($values as $artifact_link_info) {
            $arr[] = $artifact_link_info->getArtifactId();
        }
        $html = implode(',', $arr);
        return $html;
    }

    /**
     * Fetch the value
     * @param mixed $value the value of the field
     * @return string
     */
    public function fetchRawValue($value) {
        $artifact_id_array = $value->getArtifactIds();
        return implode(", ", $artifact_id_array);
    }

    /**
     * Get available values of this field for SOAP usage
     * Fields like int, float, date, string don't have available values
     *
     * @return mixed The values or null if there are no specific available values
     */
    public function getSoapAvailableValues() {
        return null;
    }

    /**
     * Return data that can be proceced by createArtifact or updateArtifact based on SOAP request
     *
     * @param stdClass         $soap_value
     * @param Tracker_Artifact $artifact
     *
     * @return array
     */
    public function getFieldDataFromSoapValue(stdClass $soap_value, Tracker_Artifact $artifact = null) {
        return $this->getFieldData($soap_value->field_value->value, $artifact);
    }


    /**
     * @see Tracker_FormElement_Field::getFieldDataFromRESTValue()
     * @param array $value
     * @param Tracker_Artifact $artifact
     * @return array
     * @throws Exception
     */
    public function getFieldDataFromRESTValue(array $value, Tracker_Artifact $artifact = null) {
        if (array_key_exists('links', $value) && is_array($value['links'])){
            $link_ids = array();
            foreach ($value['links'] as $link) {
                if (array_key_exists('id', $link)) {
                    $link_ids[] = $link['id'];
                }
            }
            return $this->getFieldData(implode(',', $link_ids), $artifact);
        }
        throw new Tracker_FormElement_InvalidFieldValueException('Value should be \'links\' and an array of {"id": integer}');
    }

    public function getFieldDataFromRESTValueByField($value, Tracker_Artifact $artifact = null) {
        throw new Tracker_FormElement_RESTValueByField_NotImplementedException();
    }

    /**
     * Get the field data (SOAP or CSV) for artifact submission
     *
     * @param string           $string_value The soap field value
     * @param Tracker_Artifact $artifact     The artifact the value is to be added/removed
     *
     * @return array
     */
    public function getFieldData($string_value, Tracker_Artifact $artifact = null) {
        $existing_links   = $this->getArtifactLinkIdsOfLastChangeset($artifact);
        $submitted_values = $this->getArrayOfIdsFromString($string_value);
        $new_values       = array_diff($submitted_values, $existing_links);
        $removed_values   = array_diff($existing_links, $submitted_values);
        return $this->getDataLikeWebUI($new_values, $removed_values);
    }

    public function fetchArtifactForOverlay(Tracker_Artifact $artifact) {
        $user_manager   = UserManager::instance();
        $user           = $user_manager->getCurrentUser();
        $parent_tracker = $this->getTracker()->getParent();

        if ($artifact->getParent($user) || ! $parent_tracker) {
            return '';
        }

        $prefill_parent = '';
        $name           = 'artifact['. $this->id .']';
        $current_user   = $this->getCurrentUser();
        $can_create     = false;

        return $this->fetchParentSelector($prefill_parent, $name, $parent_tracker, $current_user, $can_create);
    }

    public function fetchSubmitForOverlay($submitted_values) {
        $prefill_parent = '';
        $name           = 'artifact['. $this->id .']';
        $parent_tracker = $this->getTracker()->getParent();
        $current_user   = $this->getCurrentUser();
        $can_create     = false;

        if (! $parent_tracker) {
            return '';
        }

        if (isset($submitted_values['disable_artifact_link_field']) && $submitted_values['disable_artifact_link_field']) {
            return '';
        }

        return $this->fetchParentSelector($prefill_parent, $name, $parent_tracker, $current_user, $can_create);
    }

    private function getArtifactLinkIdsOfLastChangeset(Tracker_Artifact $artifact = null) {
        if ($artifact) {
            return array_map(array($this, 'getArtifactLinkId'), $this->getChangesetValues($artifact->getLastChangeset()->getId()));
        }
        return array();
    }

    private function getArtifactLinkId(Tracker_ArtifactLinkInfo $link_info) {
        return $link_info->getArtifactId();
    }

    private function getArrayOfIdsFromString($value) {
        return array_filter(array_map('intval', explode(',', $value)));
    }

    private function getDataLikeWebUI(array $new_values, array $removed_values) {
        return array(
            'new_values'     => $this->formatNewValuesLikeWebUI($new_values),
            'removed_values' => $this->formatRemovedValuesLikeWebUI($removed_values)
        );
    }

    private function formatNewValuesLikeWebUI(array $new_values) {
        return implode(',', $new_values);
    }

    private function formatRemovedValuesLikeWebUI(array $removed_values) {
        $values = array();
        foreach ($removed_values as $value) {
            $values[$value] = array($value);
        }
        return $values;
    }

    /**
     * Get the "from" statement to allow search with this field
     * You can join on 'c' which is a pseudo table used to retrieve
     * the last changeset of all artifacts.
     *
     * @param Tracker_ReportCriteria $criteria
     *
     * @return string
     */
    public function getCriteriaFrom($criteria) {
        //Only filter query if field is used
        if($this->isUsed()) {
            //Only filter query if criteria is valuated
            if ($criteria_value = $this->getCriteriaValue($criteria)) {
                $a = 'A_'. $this->id;
                $b = 'B_'. $this->id;
                return " INNER JOIN tracker_changeset_value AS $a ON ($a.changeset_id = c.id AND $a.field_id = $this->id )
                         INNER JOIN tracker_changeset_value_artifactlink AS $b ON (
                            $b.changeset_value_id = $a.id
                            AND ". $this->buildMatchExpression("$b.artifact_id", $criteria_value) ."
                         ) ";
            }
        }
        return '';
    }
    protected $pattern = '[+\-]*[0-9]+';
    protected function cast($value) {
        return (int)$value;
    }
    protected function buildMatchExpression($field_name, $criteria_value) {
        $expr = '';
        $matches = array();
        if (preg_match('/\/(.*)\//', $criteria_value, $matches)) {

            // If it is sourrounded by /.../ then assume a regexp
            $expr = $field_name." RLIKE ".$this->getCriteriaDao()->da->quoteSmart($matches[1]);
        }
        if (!$expr) {
            $matches = array();
            if (preg_match("/^(<|>|>=|<=)\s*($this->pattern)\$/", $criteria_value, $matches)) {
                // It's < or >,  = and a number then use as is
                $matches[2] = (string)($this->cast($matches[2]));
                $expr = $field_name.' '.$matches[1].' '.$matches[2];

            } else if (preg_match("/^($this->pattern)\$/", $criteria_value, $matches)) {
                // It's a number so use  equality
                $matches[1] = $this->cast($matches[1]);
                $expr = $field_name.' = '.$matches[1];

            } else if (preg_match("/^($this->pattern)\s*-\s*($this->pattern)\$/", $criteria_value, $matches)) {
                // it's a range number1-number2
                $matches[1] = (string)($this->cast($matches[1]));
                $matches[2] = (string)($this->cast($matches[2]));
                $expr = $field_name.' >= '.$matches[1].' AND '.$field_name.' <= '. $matches[2];

            } else {
                // Invalid syntax - no condition
                $expr = '1';
            }
        }
        return $expr;
    }

    /**
     * Get the "where" statement to allow search with this field
     *
     * @param Tracker_ReportCriteria $criteria
     *
     * @return string
     */
    public function getCriteriaWhere($criteria) {
        return '';
    }

    public function getQuerySelect() {
        $R1 = 'R1_'. $this->id;
        $R2 = 'R2_'. $this->id;
        return "$R2.artifact_id AS `". $this->name . "`";
    }

    public function getQueryFrom() {
        $R1 = 'R1_'. $this->id;
        $R2 = 'R2_'. $this->id;

        return "LEFT JOIN ( tracker_changeset_value AS $R1
                    INNER JOIN tracker_changeset_value_artifactlink AS $R2 ON ($R2.changeset_value_id = $R1.id)
                ) ON ($R1.changeset_id = c.id AND $R1.field_id = ". $this->id ." )";
    }

    /**
     * Return the dao of the criteria value used with this field.
     * @return DataAccessObject
     */
    protected function getCriteriaDao() {
        return new Tracker_Report_Criteria_ArtifactLink_ValueDao();
    }

    private function fetchParentSelector($prefill_parent, $name, Tracker $parent_tracker, PFUser $user, $can_create) {
        $purifier = Codendi_HTMLPurifier::instance();
        $possible_parents_getr = new Tracker_Artifact_PossibleParentsRetriever($this->getArtifactFactory());
        $html     = '';
        $html    .= '<p>';
        list($label, $paginated_possible_parents, $display_selector) = $possible_parents_getr->getPossibleArtifactParents($parent_tracker, $user, 0, 0);
        $possible_parents = $paginated_possible_parents->getArtifacts();
        if ($display_selector) {
            $html .= '<label>';
            $html .= $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_choose_parent', $purifier->purify($parent_tracker->getItemName()));
            $html .= '<select name="'. $purifier->purify($name) .'[parent]">';
            $html .= '<option value="">'. $GLOBALS['Language']->getText('global', 'please_choose_dashed') .'</option>';
            if ($can_create) {
                $html .= '<option value="'.self::CREATE_NEW_PARENT_VALUE.'">'. $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_create_new_parent') .'</option>';
            }
            $html .= $this->fetchArtifactParentsOptions($prefill_parent, $label, $possible_parents);
            $html .= '</select>';
            $html .= '</label>';
        } elseif (count($possible_parents) > 0) {
            $html .= $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_will_have_as_parent', array($possible_parents[0]->fetchDirectLinkToArtifactWithTitle()));
        }
        $html .= '</p>';
        return $html;
    }

    private function fetchArtifactParentsOptions($prefill_parent, $label, array $possible_parents) {
        $purifier = Codendi_HTMLPurifier::instance();
        $html     = '';
        if ($possible_parents) {
            $html .= '<optgroup label="'. $purifier->purify($label) .'">';
            foreach ($possible_parents as $possible_parent) {
                $selected = '';
                if ($possible_parent->getId() == $prefill_parent) {
                    $selected = ' selected="selected"';
                }
                $html .= '<option value="'. $possible_parent->getId() .'"'.$selected.'>'. $possible_parent->getXRefAndTitle() .'</option>';
            }
            $html .= '</optgroup>';
        }
        return $html;
    }

    /**
     * Fetch the html widget for the field
     *
     * @param Tracker_Artifact $artifact               Artifact on which we operate
     * @param string           $name                   The name, if any
     * @param array            $artifact_links         The current artifact links
     * @param string           $prefill_new_values     Prefill new values field (what the user has submitted, if any)
     * @param array            $prefill_removed_values Pre-remove values (what the user has submitted, if any)
     * @param string           $prefill_parent         Prefilled parent (what the user has submitted, if any) - Only valid on submit
     * @param bool             $read_only              True if the user can't add or remove links
     *
     * @return string html
     */
    protected function fetchHtmlWidget(
        Tracker_Artifact $artifact,
        $name,
        $artifact_links,
        $prefill_new_values,
        $prefill_removed_values,
        $prefill_parent,
        $read_only,
        $from_aid = null,
        $reverse_artifact_links = false
    ) {
        $current_user = $this->getCurrentUser();
        $html = '';

        if ($reverse_artifact_links) {
            $html .= '<div class="artifact-link-value-reverse">';
            $html .= '<a href="" class="btn" id="display-tracker-form-element-artifactlink-reverse">' . $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_display_reverse') . '</a>';
            $html .= '<div id="tracker-form-element-artifactlink-reverse" style="display: none">';
        } else {
            $html .= '<div class="artifact-link-value">';
        }

        $html .= '<h5 class="artifack_link_subtitle">'.$this->getWidgetTitle($reverse_artifact_links).'</h5>';

        $html_name_new = '';
        $html_name_del = '';

        if ($name) {
            $html_name_new = 'name="'. $name .'[new_values]"';
            $html_name_del = 'name="'. $name .'[removed_values]';
        }

        $hp              = Codendi_HTMLPurifier::instance();
        $read_only_class = 'read-only';

        if (! $read_only) {
            $read_only_class = '';
            $html .= '<div><span class="input-append" style="display:inline;"><input type="text"
                             '. $html_name_new .'
                             class="tracker-form-element-artifactlink-new"
                             size="40"
                             value="'.  $hp->purify($prefill_new_values, CODENDI_PURIFIER_CONVERT_HTML)  .'"
                             title="' . $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_help') . '" />';
            if($artifact->getTracker()->isProjectAllowedToUseNature()) {
                $natures        = $this->getNaturePresenterFactory()->getAllNatures();
                $natures_presenter = array();
                foreach($natures as $nature) {
                    $natures_presenter[] = array(
                        'shortname'     => $nature->shortname,
                        'forward_label' => $nature->forward_label,
                        'is_selected'   => false
                    );
                }
                $html          .= $this->getTemplateRenderer()->renderToString(
                    'artifactlink-nature-selector',
                    new NatureSelectorPresenter($natures_presenter, $name .'[nature]', 'tracker-form-element-artifactlink-new nature-selector')
                );
            }
            $html .= '</span>';
            $html .= '<a href="#" class="btn tracker-form-element-artifactlink-add"><i class="icon-plus"></i> Add artifacts</a>';
            $html .= '</div>';

            $parent_tracker = $this->getTracker()->getParent();
            $is_submit      = $artifact->getId() == -1;
            if ($parent_tracker && $is_submit) {
                $can_create   = true;
                $html .= $this->fetchParentSelector($prefill_parent, $name, $parent_tracker, $current_user, $can_create);
            }
        }

        $html .= '<div class="tracker-form-element-artifactlink-list '.$read_only_class.'">';
        if ($artifact_links) {
            $ids = array();
            // build an array of artifact_id / last_changeset_id for fetch renderer method
            foreach ($artifact_links as $artifact_link) {
                if ($artifact_link->getTracker()->isActive() && $artifact_link->userCanView($current_user)) {
                    if (!isset($ids[$artifact_link->getTrackerId()])) {
                        $ids[$artifact_link->getTrackerId()] = array(
                        'id'                => '',
                        'last_changeset_id' => ''
                        );
                        if($this->getTracker()->isProjectAllowedToUseNature()) {
                            $ids[$artifact_link->getTrackerId()]['nature'] = array();
                        }
                    }
                    $artifact_id = $artifact_link->getArtifactId();
                    $ids[$artifact_link->getTrackerId()]['id'] .= "$artifact_id,";
                    $ids[$artifact_link->getTrackerId()]['last_changeset_id'] .= $artifact_link->getLastChangesetId() .',';
                    if($this->getTracker()->isProjectAllowedToUseNature()) {
                        $nature_presenter_factory = $this->getNaturePresenterFactory();
                        $nature = $nature_presenter_factory->getFromShortname($artifact_link->getNature());
                        $ids[$artifact_link->getTrackerId()]['nature'][$artifact_id] = $nature;
                    }
                }
            }

            $projects = array();
            $this_project_id = $this->getTracker()->getProject()->getGroupId();
            foreach ($ids as $tracker_id => $matching_ids) {
                //remove last coma
                $matching_ids['id'] = substr($matching_ids['id'], 0, -1);
                $matching_ids['last_changeset_id'] = substr($matching_ids['last_changeset_id'], 0, -1);

                $tracker = $this->getTrackerFactory()->getTrackerById($tracker_id);
                $project = $tracker->getProject();
                if ($tracker->userCanView()) {
                    $trf = Tracker_ReportFactory::instance();
                    $report = $trf->getDefaultReportsByTrackerId($tracker->getId());
                    if ($report) {
                        $renderers = $report->getRenderers();
                        $renderer_table_found = false;
                        // looking for the first table renderer
                        foreach ($renderers as $renderer) {
                            if ($renderer->getType() === Tracker_Report_Renderer::TABLE) {
                                $projects[$project->getGroupId()][$tracker_id] = array(
                                    'project'      => $project,
                                    'tracker'      => $tracker,
                                    'report'       => $report,
                                    'renderer'     => $renderer,
                                    'matching_ids' => $matching_ids,
                                );
                                $renderer_table_found = true;
                                break;
                            }
                        }
                        if ( ! $renderer_table_found) {
                            $html .= $GLOBALS['Language']->getText('plugin_tracker', 'no_reports_available');
                        }
                    } else {
                        $html .= $GLOBALS['Language']->getText('plugin_tracker', 'no_reports_available');
                    }
                }
            }

            foreach ($projects as $trackers) {
                foreach ($trackers as $t) {
                    extract($t);

                    $html .= '<div class="tracker-form-element-artifactlink-trackerpanel">';

                    $project_name = '';
                    if ($project->getGroupId() != $this_project_id) {
                        $project_name = ' (<abbr title="'. $hp->purify($project->getPublicName(), CODENDI_PURIFIER_CONVERT_HTML) .'">';
                        $project_name .= $hp->purify($project->getUnixName(), CODENDI_PURIFIER_CONVERT_HTML);
                        $project_name .= '</abbr>)';
                    }
                    $html .= '<h2 class="tracker-form-element-artifactlink-tracker_'. $tracker->getId() .'">';
                    $html .= $hp->purify($tracker->getName(), CODENDI_PURIFIER_CONVERT_HTML) . $project_name;
                    $html .= '</h2>';
                    if ($from_aid == null) {
                        $html .= $renderer->fetchAsArtifactLink($matching_ids, $this->getId(), $read_only, $prefill_removed_values, $reverse_artifact_links, false);
                    } else {
                        $html .= $renderer->fetchAsArtifactLink($matching_ids, $this->getId(), $read_only, $prefill_removed_values, $reverse_artifact_links, false, $from_aid);
                    }
                    $html .= '</div>';
                }
            }

            if($artifact->getTracker()->isProjectAllowedToUseNature()) {
                $html .= $this->fetchNatureTables($artifact_links, $reverse_artifact_links, $current_user);
            }
        } else {
            $html .= $this->getNoValueLabel();
        }
        $html .= '</div>';

        if ($reverse_artifact_links) {
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private function fetchNatureTables(array $artifact_links, $is_reverse_artifact_links, PFUser $current_user) {
        $by_nature = array();
        foreach ($artifact_links as $artifact_link) {
            if ($artifact_link->getTracker()->isActive() && $artifact_link->userCanView($current_user)) {
                $nature = $artifact_link->getNature();
                if(!isset($by_nature[$nature])) {
                    $by_nature[$nature] = array();
                }
                $by_nature[$nature][] = $artifact_link;
            }
        }

        $renderer = $this->getTemplateRenderer();
        $nature_presenter_factory = $this->getNaturePresenterFactory();
        $html = '';
        foreach($by_nature as $nature => $artifact_links) {
            if(empty($nature)) {
                continue;
            }

            $nature_presenter = $nature_presenter_factory->getFromShortname($nature);
            $html .= $renderer->renderToString(
                'artifactlink-nature-table',
                new NatureTablePresenter($nature_presenter, $artifact_links, $is_reverse_artifact_links, $this->getTracker())
            );
        }
        return $html;
    }

    /**
     *
     * @param boolean $reverse_artifact_links
     */
    private function getWidgetTitle($reverse_artifact_links) {
        if ($reverse_artifact_links) {
            return $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_reverse_title');
        }

        return $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_title');

    }

    /**
     * Process the request
     *
     * @param Tracker_IDisplayTrackerLayout  $layout          Displays the page header and footer
     * @param Codendi_Request                $request         The data coming from the user
     * @param PFUser                           $current_user    The user who mades the request
     *
     * @return void
     */
    public function process(Tracker_IDisplayTrackerLayout $layout, $request, $current_user) {
        switch ($request->get('func')) {
            case 'fetch-artifacts':
                $read_only              = false;
                $prefill_removed_values = array();
                $only_rows              = true;
                $this_project_id = $this->getTracker()->getProject()->getGroupId();
                $is_reverse = false;
                $hp = Codendi_HTMLPurifier::instance();

                $ugroups = $current_user->getUgroups($this_project_id, array());

                $ids     = $request->get('ids'); //2, 14, 15
                $tracker = array();
                $result  = array();
                if($this->getTracker()->isProjectAllowedToUseNature()) {
                    $nature_shortname      = $request->get('nature');
                    $nature_presenter      = $this->getNaturePresenterFactory()->getFromShortname($nature_shortname);
                }
                //We must retrieve the last changeset ids of each artifact id.
                $dao = new Tracker_ArtifactDao();
                foreach($dao->searchLastChangesetIds($ids, $ugroups, $current_user->isSuperUser()) as $matching_ids) {
                    $tracker_id = $matching_ids['tracker_id'];
                    $tracker = $this->getTrackerFactory()->getTrackerById($tracker_id);
                    $project = $tracker->getProject();

                    if ($tracker->userCanView()) {
                        if($this->getTracker()->isProjectAllowedToUseNature()) {
                            $matching_ids['nature'] = array();
                            foreach(explode(',', $matching_ids['id']) as $id) {
                                $matching_ids['nature'][$id] = $nature_presenter;
                            }
                        }
                        $trf = Tracker_ReportFactory::instance();
                        $report = $trf->getDefaultReportsByTrackerId($tracker->getId());
                        if ($report) {
                            $renderers = $report->getRenderers();
                            // looking for the first table renderer
                            foreach ($renderers as $renderer) {
                                if ($renderer->getType() === Tracker_Report_Renderer::TABLE) {
                                    $key = $this->id .'_'. $report->id .'_'. $renderer->getId();
                                    $result[$key] = $renderer->fetchAsArtifactLink($matching_ids, $this->getId(), $read_only, $is_reverse, $prefill_removed_values, $only_rows);
                                    $head = '<div class="tracker-form-element-artifactlink-trackerpanel">';

                                    $project_name = '';
                                    if ($project->getGroupId() != $this_project_id) {
                                        $project_name = ' (<abbr title="'. $hp->purify($project->getPublicName(), CODENDI_PURIFIER_CONVERT_HTML) .'">';
                                        $project_name .= $hp->purify($project->getUnixName(), CODENDI_PURIFIER_CONVERT_HTML);
                                        $project_name .= '</abbr>)';
                                    }
                                    $head .= '<h2 class="tracker-form-element-artifactlink-tracker_'. $tracker->getId() .'">';
                                    $head .= $hp->purify($tracker->getName(), CODENDI_PURIFIER_CONVERT_HTML) . $project_name;
                                    $head .= '</h2>';
                                    //if ($artifact) {
                                    //    $title = $hp->purify('link a '. $tracker->getItemName(), CODENDI_PURIFIER_CONVERT_HTML);
                                    //    $head .= '<a href="'.TRACKER_BASE_URL.'/?tracker='.$tracker_id.'&func=new-artifact-link&id='.$artifact->getId().'" class="tracker-form-element-artifactlink-link-new-artifact">'. 'create a new '.$hp->purify($tracker->getItemName(), CODENDI_PURIFIER_CONVERT_HTML)  .'</a>';
                                    //}
                                    $result[$key]['head'] = $head . $result[$key]['head'];
                                    break;
                                }
                            }
                        }

                    }
                }

                $this->appendNatureTable($request, $result);
                if ($result) {
                    $head = array();
                    $rows = array();
                    foreach($result as $key => $value) {
                        $head[$key] = $value["head"];
                        $rows[$key] = $value["rows"];
                    }
                    header('Content-type: application/json');
                    echo json_encode(array('head' => $head, 'rows' => $rows));
                }
                exit();
                break;
            case 'fetch-aggregates':
                $read_only              = false;
                $prefill_removed_values = array();
                $only_rows              = true;
                $only_one_column        = false;
                $extracolumn            = Tracker_Report_Renderer_Table::EXTRACOLUMN_UNLINK;
                $read_only              = true;
                $use_data_from_db       = false;

                $ugroups = $current_user->getUgroups($this->getTracker()->getGroupId(), array());
                $ids     = $request->get('ids'); //2, 14, 15
                $tracker = array();
                $json = array('tabs' => array());
                $dao = new Tracker_ArtifactDao();
                foreach ($dao->searchLastChangesetIds($ids, $ugroups, $current_user->isSuperUser()) as $matching_ids) {
                    $tracker_id = $matching_ids['tracker_id'];
                    $tracker = $this->getTrackerFactory()->getTrackerById($tracker_id);
                    $project = $tracker->getProject();
                    if ($tracker->userCanView()) {
                        if($this->getTracker()->isProjectAllowedToUseNature()) {
                            $matching_ids['nature'] = array();
                        }
                        $trf = Tracker_ReportFactory::instance();
                        $report = $trf->getDefaultReportsByTrackerId($tracker->getId());
                        if ($report) {
                            $renderers = $report->getRenderers();
                            // looking for the first table renderer
                            foreach ($renderers as $renderer) {
                                if ($renderer->getType() === Tracker_Report_Renderer::TABLE) {
                                    $key = $this->id . '_' . $report->id . '_' . $renderer->getId();
                                    $columns          = $renderer->getTableColumns($only_one_column, $use_data_from_db);
                                    $extracted_fields = $renderer->extractFieldsFromColumns($columns);
                                    $json['tabs'][] = array(
                                        'key' => $key,
                                        'src' => $renderer->fetchAggregates($matching_ids, $extracolumn, $only_one_column,$columns, $extracted_fields, $use_data_from_db, $read_only),
                                    );
                                    break;
                                }
                            }
                        }
                    }
                }
                header('Content-type: application/json');
                echo json_encode($json);
                exit();
                break;
            default:
                parent::process($layout, $request, $current_user);
                break;
        }
    }

    /**
     * Fetch the html widget for the field
     *
     * @param string $name                   The name, if any
     * @param array  $artifact_links         The current artifact links
     * @param string $prefill_new_values     Prefill new values field (what the user has submitted, if any)
     * @param bool   $read_only              True if the user can't add or remove links
     *
     * @return string html
     */
    protected function fetchHtmlWidgetMasschange($name, $artifact_links, $prefill_new_values, $read_only) {
        $html = '';
        $html_name_new = '';
        if ($name) {
            $html_name_new = 'name="'. $name .'[new_values]"';
        }
        $hp = Codendi_HTMLPurifier::instance();
        if (!$read_only) {
            $html .= '<input type="text"
                             '. $html_name_new .'
                             value="'.  $hp->purify($prefill_new_values, CODENDI_PURIFIER_CONVERT_HTML)  .'"
                             title="' . $GLOBALS['Language']->getText('plugin_tracker_artifact', 'formelement_artifactlink_help') . '" />';
            $html .= '<br />';
        }
        if ($artifact_links) {
            $html .= '<ul class="tracker-form-element-artifactlink-list">';
            foreach ($artifact_links as $artifact_link_info) {
                $html .= '<li>';
                $html .= $artifact_link_info->getUrl();
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        return $html;
    }

    /**
     * Fetch the html code to display the field value in artifact
     *
     * @param Tracker_Artifact                $artifact         The artifact
     * @param Tracker_Artifact_ChangesetValue $value            The actual value of the field
     * @param array                           $submitted_values The value already submitted by the user
     *
     * @return string
     */
    protected function fetchArtifactValue(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $value = null, $submitted_values = array()) {
        $links_tab         = $this->fetchLinks($artifact, $value, $submitted_values);
        $reverse_links_tab = $this->fetchReverseLinks($artifact);

        return $links_tab . $reverse_links_tab;
    }

    private function fetchLinks(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $value = null, $submitted_values = array()) {
        $artifact_links = array();
        if ($value != null) {
            $artifact_links = $value->getValue();
        }

        if (! empty($submitted_values) && isset($submitted_values[0]) && is_array($submitted_values[0]) && isset($submitted_values[0][$this->getId()])) {
            $submitted_value = $submitted_values[0][$this->getId()];
        }

        $prefill_new_values = '';
        if (isset($submitted_value['new_values'])) {
            $prefill_new_values = $submitted_value['new_values'];
        }

        $prefill_removed_values = array();
        if (isset($submitted_value['removed_values'])) {
            $prefill_removed_values = $submitted_value['removed_values'];
        }

        $read_only      = false;
        $name           = 'artifact['. $this->id .']';
        $from_aid       = $artifact->getId();
        $prefill_parent = '';

        return $this->fetchHtmlWidget(
            $artifact,
            $name,
            $artifact_links,
            $prefill_new_values,
            $prefill_removed_values,
            $read_only,
            $prefill_parent,
            $from_aid
        );
    }

    private function fetchReverseLinks(Tracker_Artifact $artifact) {
        $reverse_links = $this->getReverseLinks($artifact->getId());

        return $this->fetchHtmlWidget(
            $artifact,
            '',
            $reverse_links,
            '',
            '',
            '',
            true,
            null,
            true
        );
    }

    /**
     * Fetch the html code to display the field value in artifact in read only mode
     *
     * @param Tracker_Artifact                $artifact The artifact
     * @param Tracker_Artifact_ChangesetValue $value    The actual value of the field
     *
     * @return string
     */
    public function fetchArtifactValueReadOnly(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $value = null) {
        $links_tab_read_only = $this->fetchLinksReadOnly($artifact, $value);
        $reverse_links_tab   = $this->fetchReverseLinks($artifact);

        return $links_tab_read_only . $reverse_links_tab;
    }

    public function fetchArtifactCopyMode(Tracker_Artifact $artifact, $submitted_values = array()) {
        return '';
    }

    public function fetchArtifactValueWithEditionFormIfEditable(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $value = null, $submitted_values = array()) {
        return $this->getHiddenArtifactValueForEdition($artifact, $value, $submitted_values) . $this->fetchArtifactValueReadOnly($artifact, $value) ;
    }

    public function getHiddenArtifactValueForEdition(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $value = null) {
        return "<div class='tracker_hidden_edition_field' data-field-id=" . $this->getId() . ">" . $this->fetchLinks($artifact, $value) . "</div>";
    }

    private function fetchLinksReadOnly(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $value = null) {
        $artifact_links = array();

        if ($value != null) {
            $artifact_links = $value->getValue();
        }

        $read_only              = true;
        $name                   = '';
        $prefill_new_values     = '';
        $prefill_removed_values = array();
        $prefill_parent         = '';

        return $this->fetchHtmlWidget(
            $artifact,
            $name,
            $artifact_links,
            $prefill_new_values,
            $prefill_removed_values,
            $prefill_parent,
            $read_only
        );
    }

    /**
     * Fetch the html code to display the field value in new artifact submission form
     *
     * @param array $submitted_values the values already submitted
     *
     * @return string html
     */
    protected function fetchSubmitValue($submitted_values = array()) {
        $html = '';
        $prefill_new_values = '';
        if (isset($submitted_values[$this->getId()]['new_values'])) {
            $prefill_new_values = $submitted_values[$this->getId()]['new_values'];
        } else if ($this->hasDefaultValue()) {
            $prefill_new_values = $this->getDefaultValue();
        }
        $prefill_parent = '';
        if (isset($submitted_values[$this->getId()]['parent'])) {
            $prefill_parent = $submitted_values[$this->getId()]['parent'];
        }
        $read_only              = false;
        $name                   = 'artifact['. $this->id .']';
        $prefill_removed_values = array();
        $artifact_links         = array();

        // Well, shouldn't be here but API doesn't provide a Null Artifact on creation yet
        // Here to avoid having to pass null arg for fetchHtmlWidget
        $artifact = new Tracker_Artifact(-1, $this->tracker_id, $this->getCurrentUser()->getId(), 0, false);

        return $this->fetchHtmlWidget($artifact, $name, $artifact_links, $prefill_new_values, $prefill_removed_values, $prefill_parent, $read_only);
    }

    /**
     * Fetch the html code to display the field value in masschange submission form
     *
     * @param array $submitted_values the values already submitted
     *
     * @return string html
     */
    protected function fetchSubmitValueMasschange() {
        $html = '';
        $prefill_new_values     = $GLOBALS['Language']->getText('global','unchanged');
        $read_only              = false;
        $name                   = 'artifact['. $this->id .']';
        $artifact_links         = array();

        return $this->fetchHtmlWidgetMasschange($name, $artifact_links, $prefill_new_values, $read_only);
    }


    /**
     * Fetch the html code to display the field value in tooltip
     *
     * @param Tracker_Artifact $artifact
     * @param Tracker_Artifact_ChangesetValue $value The changeset value of the field
     *
     * @return string
     */
    protected function fetchTooltipValue(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $value = null) {
        $html = '';
        if ($value != null) {
            $html = '<ul>';
            $artifact_links = $value->getValue();
            foreach($artifact_links as $artifact_link_info) {
                $html .= '<li>' . $artifact_link_info->getLabel() . '</li>';
            }
            $html .= '</ul>';
        }
        return $html;
    }

    /**
     * @return Tracker_FormElement_Field_Value_ArtifactLinkDao
     */
    protected function getValueDao() {
        return new Tracker_FormElement_Field_Value_ArtifactLinkDao();
    }

    /**
     * Fetch the html code to display the field value in artifact
     *
     * @param Tracker_Artifact                $artifact         The artifact
     * @param PFUser                          $user             The user who will receive the email
     * @param Tracker_Artifact_ChangesetValue $value            The actual value of the field
     * @param array                           $submitted_values The value already submitted by the user
     *
     * @return string
     */
    public function fetchMailArtifactValue(
        Tracker_Artifact $artifact,
        PFUser $user,
        Tracker_Artifact_ChangesetValue $value = null,
        $format='text'
    ) {
        if ( empty($value) || !$value->getValue()) {
            return '-';
        }
        $output = '';
        switch($format) {
            case 'html':
                $artifactlink_infos = $value->getValue();
                $url = array();
                foreach ($artifactlink_infos as $artifactlink_info) {
                    if ($artifactlink_info->userCanView($user)) {
                        $url[] = $artifactlink_info->getUrl();
                    }
                }
                return implode(' , ', $url);
            default:
                $output = PHP_EOL;
                $artifactlink_infos = $value->getValue();
                foreach ($artifactlink_infos as $artifactlink_info) {
                    if ($artifactlink_info->userCanView($user)) {
                        $output .= $artifactlink_info->getLabel();
                        $output .= PHP_EOL;
                    }
                }
                break;
        }
        return $output;
    }

    /**
     * Fetch the value to display changes in followups
     *
     * @param Tracker_ $artifact
     * @param array $from the value(s) *before*
     * @param array $to   the value(s) *after*
     *
     * @return string
     */
    public function fetchFollowUp($artifact, $from, $to) {
        // never used...
    }

    /**
     * Fetch the value in a specific changeset
     *
     * @param Tracker_Artifact_Changeset $changeset
     *
     * @return string
     */
    public function fetchRawValueFromChangeset($changeset) {
        // never used...
    }

    /**
     * Get the value of this field
     *
     * @param Tracker_Artifact_Changeset $changeset   The changeset (needed in only few cases like 'lud' field)
     * @param int                        $value_id    The id of the value
     * @param boolean                    $has_changed If the changeset value has changed from the rpevious one
     *
     * @return Tracker_Artifact_ChangesetValue or null if not found
     */
    public function getChangesetValue($changeset, $value_id, $has_changed) {
        $rows                   = $this->getValueDao()->searchById($value_id, $this->id);
        $artifact_links         = $this->getArtifactLinkInfos($rows);
        $reverse_artifact_links = array();

        if ($changeset) {
            $reverse_artifact_links = $this->getReverseLinks($changeset->getArtifact()->getId());
        }

        return new Tracker_Artifact_ChangesetValue_ArtifactLink($value_id, $this, $has_changed, $artifact_links, $reverse_artifact_links);
    }


    private function getReverseLinks($artifact_id) {
        $links_data = $this->getValueDao()->searchReverseLinksById($artifact_id);

        return $this->getArtifactLinkInfos($links_data);
    }

    private function getArtifactLinkInfos($data) {
        $artifact_links = array();
        while ($row = $data->getRow()) {
            $artifact_links[$row['artifact_id']] = new Tracker_ArtifactLinkInfo(
                $row['artifact_id'],
                $row['keyword'],
                $row['group_id'],
                $row['tracker_id'],
                $row['last_changeset_id'],
                $row['nature']
            );
        }

        return $artifact_links;
    }

    /**
     * @return array
     */
    protected $artifact_links_by_changeset = array();

    /**
     *
     * @param Integer $changeset_id
     *
     * @return Tracker_ArtifactLinkInfo[]
     */
    protected function getChangesetValues($changeset_id) {
        if (!isset($this->artifact_links_by_changeset[$changeset_id])) {
            $this->artifact_links_by_changeset[$changeset_id] = array();

            $da = CodendiDataAccess::instance();
            $field_id     = $da->escapeInt($this->id);
            $changeset_id = $da->escapeInt($changeset_id);
            $sql = "SELECT cv.changeset_id, cv.has_changed, val.*, a.tracker_id, a.last_changeset_id
                    FROM tracker_changeset_value_artifactlink AS val
                         INNER JOIN tracker_artifact AS a ON(a.id = val.artifact_id)
                         INNER JOIN tracker_changeset_value AS cv
                         ON ( val.changeset_value_id = cv.id
                          AND cv.field_id = $field_id
                          AND cv.changeset_id = $changeset_id
                         )
                    ORDER BY val.artifact_id";
            $dao = new DataAccessObject();
            foreach ($dao->retrieve($sql) as $row) {
                $this->artifact_links_by_changeset[$row['changeset_id']][] = new Tracker_ArtifactLinkInfo(
                    $row['artifact_id'],
                    $row['keyword'],
                    $row['group_id'],
                    $row['tracker_id'],
                    $row['last_changeset_id'],
                    $row['nature']
                );
            }
        }
        return $this->artifact_links_by_changeset[$changeset_id];
    }

    /**
     * @see Tracker_FormElement_Field::hasChanges()
     */
    public function hasChanges(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue_ArtifactLink $old_value, $new_value) {
        $source_of_association_collection_dev_null = new SourceOfAssociationCollection();
        $submitted_value = $this->getSubmittedValueConvertor()->convert(
            $new_value,
            $source_of_association_collection_dev_null,
            $artifact,
            $old_value
        );

        return $old_value->hasChanges($submitted_value);
    }

    /**
     * @return the label of the field (mainly used in admin part)
     */
    public static function getFactoryLabel() {
        return $GLOBALS['Language']->getText('plugin_tracker_formelement_admin', 'artifact_link_label');
    }

    /**
     * @return the description of the field (mainly used in admin part)
     */
    public static function getFactoryDescription() {
        return $GLOBALS['Language']->getText('plugin_tracker_formelement_admin', 'artifact_link_description');
    }

    /**
     * @return the path to the icon
     */
    public static function getFactoryIconUseIt() {
        return $GLOBALS['HTML']->getImagePath('ic/artifact-chain.png');
    }

    /**
     * @return the path to the icon
     */
    public static function getFactoryIconCreate() {
        return $GLOBALS['HTML']->getImagePath('ic/artifact-chain--plus.png');
    }

    /**
     * @return bool say if the field is a unique one
     */
    public static function getFactoryUniqueField() {
        return true;
    }

    /**
     * Say if the value is valid. If not valid set the internal has_error to true.
     *
     * @param Tracker_Artifact $artifact The artifact
     * @param array            $value    data coming from the request.
     *
     * @return bool true if the value is considered ok
     */
    public function isValid(Tracker_Artifact $artifact, $value) {
        $this->has_errors = ! $this->validate($artifact, $value);

        return ! $this->has_errors;
    }

    /**
     * Validate a required field
     *
     * @param Tracker_Artifact                $artifact             The artifact to check
     * @param mixed                           $value      The submitted value
     *
     * @return boolean true on success or false on failure
     */
    public function isValidRegardingRequiredProperty(Tracker_Artifact $artifact, $value) {
        if ( (! is_array($value) || empty($value['new_values'])) && $this->isRequired()) {
            if ( ! $this->isEmpty($value, $artifact)) {
                // Field is required but there are values, so field is valid
                $this->has_errors = false;
            } else {
                $this->addRequiredError();
                return false;
            }
        }

        return true;
    }

    /**
     * Say if the submitted value is empty
     * if no last changeset values and empty submitted values : empty
     * if not empty last changeset values and empty submitted values : not empty
     * if empty new values and not empty last changeset values and not empty removed values have the same size: empty
     *
     * @param array            $submitted_value
     * @param Tracker_Artifact $artifact
     *
     * @return bool true if the submitted value is empty
     */
    public function isEmpty($submitted_value, Tracker_Artifact $artifact) {
        $hasNoNewValues           = empty($submitted_value['new_values']);
        $hasNoLastChangesetValues = true;
        $last_changeset_values    = array();
        $last_changeset           = $this->getLastChangesetValue($artifact);

        if ($last_changeset) {
            $last_changeset_values    = $last_changeset->getArtifactIds();
            $hasNoLastChangesetValues = empty($last_changeset_values);
        }

        $hasLastChangesetValues   = !$hasNoLastChangesetValues;

        if (($hasNoLastChangesetValues && $hasNoNewValues) ||
             ($hasLastChangesetValues && $hasNoNewValues
                && $this->allLastChangesetValuesRemoved($last_changeset_values, $submitted_value))) {
            return true;
        }
        return false;
    }

    /**
     * Say if all values of the changeset have been removed
     *
     * @param array $last_changeset_values
     * @param array $submitted_value
     *
     * @return bool true if all values have been removed
     */
    private function allLastChangesetValuesRemoved($last_changeset_values, $submitted_value) {
        return !empty($submitted_value['removed_values'])
            && count($last_changeset_values) == count($submitted_value['removed_values']);
    }

    /**
     * Validate a value
     *
     * @param Tracker_Artifact $artifact The artifact
     * @param string           $value    data coming from the request. Should be artifact id separated by comma
     *
     * @return bool true if the value is considered ok
     */
    protected function validate(Tracker_Artifact $artifact, $value) {

        $is_valid = true;
        if (! isset($value['new_values'])) {
            return $is_valid;
        }
        $new_values = $value['new_values'];
        if (trim($new_values) != '') {
            $r = $this->getRuleArtifactId();
            $art_id_array = explode(',', $new_values);
            foreach ($art_id_array as $artifact_id) {
                $artifact_id = trim ($artifact_id);
                if ($artifact_id !== "" && ! $r->isValid($artifact_id)) {
                    $is_valid = false;
                    $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_artifact', 'error_artifactlink_value', array($this->getLabel(), $artifact_id)));
                }
            }
        }
        if($artifact->getTracker()->isProjectAllowedToUseNature()) {
            if(!isset($value['nature'])) {
                $is_valid = false;
                $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_artifact', 'error_artifactlink_nature_missing', array($this->getLabel())));
            } else {
                $nature_shortname = $value['nature'];
                $nature           = $this->getNaturePresenterFactory()->getFromShortname($nature_shortname);
                if(! $nature) {
                    $is_valid = false;
                    $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_artifact', 'error_artifactlink_nature_value', array($this->getLabel(), $nature_shortname)));
                }
            }
        }
        return $is_valid;
    }

    public function getRuleArtifactId() {
        return new Tracker_Valid_Rule_ArtifactId();
    }

    public function setArtifactFactory(Tracker_ArtifactFactory $artifact_factory) {
        $this->artifact_factory = $artifact_factory;
    }

    /**
     * @return Tracker_ArtifactFactory
     */
    private function getArtifactFactory() {
        if (!$this->artifact_factory) {
            $this->artifact_factory = Tracker_ArtifactFactory::instance();
        }
        return $this->artifact_factory;
    }

    public function getTrackerFactory() {
        return TrackerFactory::instance();
    }

    /**
     * @return Tracker_HierarchyFactory
     */
    protected function getHierarchyFactory() {
        return Tracker_HierarchyFactory::instance();
    }

    /**
     * @see Tracker_FormElement_Field::postSaveNewChangeset()
     */
    public function postSaveNewChangeset(
        Tracker_Artifact $artifact,
        PFUser $submitter,
        Tracker_Artifact_Changeset $new_changeset,
        Tracker_Artifact_Changeset $previous_changeset = null
    ) {
        $queue = new Tracker_FormElement_Field_ArtifactLink_PostSaveNewChangesetQueue();
        $queue->add($this->getUpdateLinkingDirectionCommand());
        $queue->add($this->getProcessChildrenTriggersCommand());
        $queue->execute($artifact, $submitter, $new_changeset, $previous_changeset);
    }

    /**
     * @protected for testing purpose
     */
    protected function getProcessChildrenTriggersCommand() {
        return new Tracker_FormElement_Field_ArtifactLink_ProcessChildrenTriggersCommand(
            $this,
            $this->getWorkflowFactory()->getTriggerRulesManager()
        );
    }

    private function getUpdateLinkingDirectionCommand() {
        return new Tracker_FormElement_Field_ArtifactLink_UpdateLinkingDirectionCommand($this->getSourceOfAssociationCollection());
    }

    /**
     * Save the value submitted by the user in the new changeset
     *
     * @param Tracker_Artifact           $artifact         The artifact
     * @param Tracker_Artifact_Changeset $old_changeset    The old changeset. null if it is the first one
     * @param int                        $new_changeset_id The id of the new changeset
     * @param mixed                      $value  The value submitted by the user
     * @param boolean $is_submission true if artifact submission, false if artifact update
     *
     * @return bool true if success
     */
    public function saveNewChangeset(
        $artifact,
        $old_changeset,
        $new_changeset_id,
        $value,
        PFUser $submitter,
        $is_submission = false,
        $bypass_permissions = false
    ) {
        $previous_changesetvalue = $this->getPreviousChangesetValue($old_changeset);

        $value = $this->getNormalizedSubmittedValue($value);

        $convertor      = $this->getSubmittedValueConvertor();
        $submitted_value = $convertor->convert(
            $value,
            $this->getSourceOfAssociationCollection(),
            $artifact,
            $previous_changesetvalue
        );

        return parent::saveNewChangeset(
            $artifact,
            $old_changeset,
            $new_changeset_id,
            $submitted_value,
            $submitter,
            $is_submission,
            $bypass_permissions
        );
    }

    /**
     * Sometimes, for example during a post action for a trigger, the workflow pass null as submitted value.
     * ArtifactLinks don't like very much this null so force it to a decent, empty value.
     */
    private function getNormalizedSubmittedValue($value) {
        if (is_null($value)) {
            $value = array('new_values' => '');
        }

        return $value;
    }

    /**
     * Save the value and return the id
     *
     * @param Tracker_Artifact                $artifact                The artifact
     * @param int                             $changeset_value_id      The id of the changeset_value
     * @param mixed                           $submitted_value         The value submitted by the user
     * @param Tracker_Artifact_ChangesetValue $previous_changesetvalue The data previously stored in the db
     *
     */
    protected function saveValue(
        $artifact,
        $changeset_value_id,
        $submitted_value,
        Tracker_Artifact_ChangesetValue $previous_changesetvalue = null
    ) {
        $saver = $this->getArtifactLinkValueSaver();

        return $saver->saveValue(
            $this,
            $this->getCurrentUser(),
            $artifact,
            $changeset_value_id,
            $submitted_value
        );
    }

    /** @return ArtifactLinkValueSaver */
    private function getArtifactLinkValueSaver() {
        return new ArtifactLinkValueSaver(
            Tracker_ArtifactFactory::instance(),
            $this->getValueDao(),
            new Tracker_ReferenceManager(
                ReferenceManager::instance(),
                Tracker_ArtifactFactory::instance()
            )
        );
    }

    /** @return SubmittedValueConvertor */
    private function getSubmittedValueConvertor() {
        return new SubmittedValueConvertor(
            Tracker_ArtifactFactory::instance(),
            new SourceOfAssociationDetector(
                Tracker_HierarchyFactory::instance()
            )
        );
    }

    /**
     * Retrieve linked artifacts according to user's permissions
     *
     * @param Tracker_Artifact_Changeset $changeset The changeset you want to retrieve artifact from
     * @param PFUser                       $user      The user who will see the artifacts
     *
     * @return Tracker_Artifact[]
     */
    public function getLinkedArtifacts(Tracker_Artifact_Changeset $changeset, PFUser $user) {
        $artifacts = array();
        $changeset_value = $changeset->getValue($this);
        if ($changeset_value) {
            foreach ($changeset_value->getArtifactIds() as $id) {
                $this->addArtifactUserCanViewFromId($artifacts, $id, $user);
            }
        }
        return $artifacts;
    }

    /**
     * Retrieve sliced linked artifacts according to user's permissions
     *
     * This is nearly the same as a paginated list however, for performance
     * reasons, the total size may be different than the sum of total paginated
     * artifacts.
     *
     * Example to illustrate the difference between paginated and sliced:
     *
     * Given that artifact links are [12, 13, 24, 39, 65, 69]
     * And that the user cannot see artifact #39
     * When I request linked artifacts by bunchs of 2
     * Then I get [[12, 13], [24], [65, 69]]  # instead of [[12, 13], [24, 65], [69]]
     * And total size will be 6               # instead of 5
     *
     * @param Tracker_Artifact_Changeset $changeset The changeset you want to retrieve artifact from
     * @param PFUser                     $user      The user who will see the artifacts
     * @param int                        $limit     The number of artifact to fetch
     * @param int                        $offset    The offset
     *
     * @return Tracker_Artifact_PaginatedArtifacts
     */
    public function getSlicedLinkedArtifacts(Tracker_Artifact_Changeset $changeset, PFUser $user, $limit, $offset) {
        $changeset_value = $changeset->getValue($this);
        if (! $changeset_value) {
            return new Tracker_Artifact_PaginatedArtifacts(array(), 0);
        }

        $artifact_ids = $changeset_value->getArtifactIds();
        $size = count($artifact_ids);

        $artifacts = array();
        foreach (array_slice($artifact_ids, $offset, $limit) as $id) {
            $this->addArtifactUserCanViewFromId($artifacts, $id, $user);
        }

        return new Tracker_Artifact_PaginatedArtifacts($artifacts, $size);
    }

    /** @return Tracker_Artifact|null */
    private function addArtifactUserCanViewFromId(array &$artifacts, $id, PFUser $user) {
        $artifact = $this->getArtifactFactory()->getArtifactById($id);
        if ($artifact && $artifact->userCanView($user)) {
            $artifacts[] = $artifact;
        }
    }

    /**
     * If request come with a 'parent', it should be automagically transformed as
     * 'new_values'.
     * Please note that it only work on artifact creation.
     *
     * @param type $fields_data
     */
    public function augmentDataFromRequest(&$fields_data) {
        $new_values = array();

        if (empty($fields_data[$this->getId()]['parent'])) {
            return;
        }

        $parent = intval($fields_data[$this->getId()]['parent']);
        if ($parent > 0) {
            if (isset($fields_data[$this->getId()]['new_values'])) {
                $new_values   = array_filter(explode(',', $fields_data[$this->getId()]['new_values']));
            }
            $new_values[] = $parent;
            $fields_data[$this->getId()]['new_values'] = implode(',', $new_values);
        }
    }

    public function accept(Tracker_FormElement_FieldVisitor $visitor) {
        return $visitor->visitArtifactLink($this);
    }

    protected function getNaturePresenterFactory() {
        return new NaturePresenterFactory(new NatureDao());
    }

    private function getTemplateRenderer() {
        return TemplateRendererFactory::build()->getRenderer(TRACKER_TEMPLATE_DIR);
    }

    private function appendNatureTable(Codendi_Request $request, array &$result) {
        if (! $this->getTracker()->isProjectAllowedToUseNature()) {
            return;
        }

        $nature_shortname = $request->get('nature');
        if (! $nature_shortname) {
            return;
        }

        $nature_presenter      = $this->getNaturePresenterFactory()->getFromShortname($nature_shortname);
        $key                   = "nature_$nature_shortname";
        $art_factory           = $this->getArtifactFactory();
        $artifact_html_classes = 'additional';
        $nature_html           = '';
        $ids                   = $request->get('ids');

        foreach (explode(',', $ids) as $id) {
            $artifact = $art_factory->getArtifactById(trim($id));

            if (!is_null($artifact)) {
                $nature_html .= $this->getTemplateRenderer()->renderToString(
                    'artifactlink-nature-table-row',
                    new ArtifactInNatureTablePresenter($artifact, $artifact_html_classes)
                );
            }
        }

        $head_html = $this->getTemplateRenderer()->renderToString(
                'artifactlink-nature-table-head',
                NatureTablePresenter::buildForHeader($nature_presenter, $this->getTracker())
        );
        $result[$key] = array('head' => $head_html, 'rows' => $nature_html);
    }
}
