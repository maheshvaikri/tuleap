<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

namespace Tuleap\Timetracking\Time;

use Codendi_Request;
use CSRFSynchronizerToken;
use PFUser;
use Tracker_Artifact;
use Tuleap\Timetracking\Permissions\PermissionsRetriever;
use Tuleap\Timetracking\Exceptions\TimeTrackingExistingDateException;
use Tuleap\Timetracking\Exceptions\TimeTrackingMissingTimeException;
use Tuleap\Timetracking\Exceptions\TimeTrackingNotAllowedToDeleteException;
use Tuleap\Timetracking\Exceptions\TimeTrackingNotAllowedToAddException;
use Tuleap\Timetracking\Exceptions\TimeTrackingNotAllowedToEditException;
use Tuleap\Timetracking\Exceptions\TimeTrackingNotBelongToUserException;
use Tuleap\Timetracking\Exceptions\TimeTrackingNoTimeException;

class TimeController
{
    /**
     * @var PermissionsRetriever
     */
    private $permissions_retriever;

    /**
     * @var TimeUpdater
     */
    private $time_updater;

    /**
     * @var TimeChecker
     */
    private $time_checker;

    /**
     * @var TimeRetriever
     */
    private $time_retriever;

    public function __construct(
        PermissionsRetriever $permissions_retriever,
        TimeUpdater $time_updater,
        TimeRetriever $time_retriever,
        TimeChecker $time_checker
    ) {
        $this->permissions_retriever = $permissions_retriever;
        $this->time_updater          = $time_updater;
        $this->time_retriever        = $time_retriever;
        $this->time_checker          = $time_checker;
    }

    /**
     * @throws TimeTrackingExistingDateException
     * @throws TimeTrackingMissingTimeException
     * @throws TimeTrackingNotAllowedToAddException
     */
    public function addTimeForUser(
        Codendi_Request $request,
        PFUser $user,
        Tracker_Artifact $artifact,
        CSRFSynchronizerToken $csrf
    ) {
        $csrf->check();

        if (! $this->permissions_retriever->userCanAddTimeInTracker($user, $artifact->getTracker())) {
            throw new TimeTrackingNotAllowedToAddException(dgettext('tuleap-timetracking', "You are not allowed to add a time."));
        }

        $added_step = $request->get('timetracking-new-time-step');
        $added_time = $request->get('timetracking-new-time-time');
        $added_date = $request->get('timetracking-new-time-date') ?: date('Y-m-d', $_SERVER['REQUEST_TIME']);

        $this->checkMandatoryTimeValue($added_time);

        $this->checkExistingTimeForUserInArtifactAtGivenDate($user, $artifact, $added_date);

        $this->time_updater->addTimeForUserInArtifact($user, $artifact, $added_date, $added_time, $added_step);
    }

    /**
     * @throws TimeTrackingNoTimeException
     * @throws TimeTrackingNotAllowedToDeleteException
     * @throws TimeTrackingNotBelongToUserException
     */
    public function deleteTimeForUser(
        Codendi_Request $request,
        PFUser $user,
        Tracker_Artifact $artifact,
        CSRFSynchronizerToken $csrf
    ) {
        $csrf->check();

        if (! $this->permissions_retriever->userCanAddTimeInTracker($user, $artifact->getTracker())) {
            throw new TimeTrackingNotAllowedToDeleteException(dgettext('tuleap-timetracking', "You are not allowed to delete a time."));
        }

        $time = $this->getTimeFromRequest($request, $user);

        $this->checkTimeBelongsToUser($time, $user);

        $this->time_updater->deleteTime($time);
    }

    /**
     * @throws TimeTrackingExistingDateException
     * @throws TimeTrackingMissingTimeException
     * @throws TimeTrackingNoTimeException
     * @throws TimeTrackingNotAllowedToEditException
     * @throws TimeTrackingNotBelongToUserException
     */
    public function editTimeForUser(
        Codendi_Request $request,
        PFUser $user,
        Tracker_Artifact $artifact,
        CSRFSynchronizerToken $csrf
    ) {
        $csrf->check();

        if (! $this->permissions_retriever->userCanAddTimeInTracker($user, $artifact->getTracker())) {
            throw new TimeTrackingNotAllowedToEditException(dgettext('tuleap-timetracking', "You are not allowed to edit this time."));
        }
        $time = $this->getTimeFromRequest($request, $user);

        $this->checkTimeBelongsToUser($time, $user);

        $updated_step = $request->get('timetracking-edit-time-step');
        $updated_time = $request->get('timetracking-edit-time-time');
        $updated_date = $request->get('timetracking-edit-time-date') ?: date('Y-m-d', $_SERVER['REQUEST_TIME']);

        $this->checkMandatoryTimeValue($updated_time);

        if ($time->getDay() !== $updated_date) {
            $this->checkExistingTimeForUserInArtifactAtGivenDate($user, $artifact, $updated_date);
        }

        $this->time_updater->updateTime($time, $updated_date, $updated_time, $updated_step);
    }

    /**
     * @param Codendi_Request $request
     * @param PFUser $user
     * @return Time
     * @throws TimeTrackingNoTimeException
     */
    private function getTimeFromRequest(Codendi_Request $request, PFUser $user)
    {
        $time_id = $request->get('time-id');
        $time    = $this->time_retriever->getTimeByIdForUser($user, $time_id);

        if (! $time) {
            throw new TimeTrackingNoTimeException(dgettext('tuleap-timetracking', "Time not found."));
        }

        return $time;
    }

    /**
     * @throws TimeTrackingExistingDateException
     */
    private function checkExistingTimeForUserInArtifactAtGivenDate(PFUser $user, Tracker_Artifact $artifact, $date)
    {
        if ($this->time_checker->getExistingTimeForUserInArtifactAtGivenDate($user, $artifact, $date)) {
            throw new TimeTrackingExistingDateException(sprintf(dgettext('tuleap-timetracking', "A time already exists for the day %s. Skipping."), $date));
        }
    }

    /**
     * @throws TimeTrackingMissingTimeException
     */
    private function checkMandatoryTimeValue($time_value)
    {
        if (! $this->time_checker->checkMandatoryTimeValue($time_value)) {
            throw new TimeTrackingMissingTimeException(dgettext('tuleap-timetracking', "The time is missing"));
        }
    }

    /**
     * @throws TimeTrackingNotBelongToUserException
     */
    private function checkTimeBelongsToUser(Time $time, PFUser $user)
    {
        if ($this->time_checker->doesTimeBelongsToUser($time, $user)) {
            throw new TimeTrackingNotBelongToUserException(dgettext('tuleap-timetracking', "This time does not belong to you."));
        }
    }
}
