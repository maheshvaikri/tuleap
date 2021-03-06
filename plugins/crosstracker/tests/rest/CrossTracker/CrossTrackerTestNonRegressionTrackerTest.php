<?php
/**
 *  Copyright (c) Enalean, 2017. All Rights Reserved.
 *
 *  This file is a part of Tuleap.
 *
 *  Tuleap is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  Tuleap is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\CrossTracker\REST\v1;

use RestBase;

require_once dirname(__FILE__) . '/../bootstrap.php';

class CrossTrackerTestNonRegressionTrackerTest extends RestBase
{
    public function testItThrowsAnExceptionWhenReportIsNotFound()
    {
        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $response = $this->getResponse($this->client->get('cross_tracker_reports/100'));

        $this->assertEquals($response->getStatusCode(), 404);
    }

    public function testItThrowsAnExceptionWhenMoreThan10Trackers()
    {
        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $params   = array(
            "trackers_id" => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12)
        );
        $response = $this->getResponse($this->client->put('cross_tracker_reports/1', null, $params));

        $this->assertEquals($response->getStatusCode(), 400);
    }

    public function testItThrowsAnExceptionWhenATrackerIsNotFoundOnePlatform()
    {
        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $params   = array(
            "trackers_id" => array(1001)
        );
        $response = $this->getResponse($this->client->put('cross_tracker_reports/1', null, $params));

        $this->assertEquals($response->getStatusCode(), 404);
    }

    public function testItThrowsAnExceptionWhenTrackerIsDuplicateInList()
    {
        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $params   = array(
            "trackers_id" => array($this->epic_tracker_id, $this->epic_tracker_id)
        );
        $response = $this->getResponse($this->client->put('cross_tracker_reports/1', null, $params));

        $this->assertEquals($response->getStatusCode(), 400);
    }

    public function testItDoesNotAddTrackersUserCantView()
    {
        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $params   = array(
            "trackers_id" => array($this->epic_tracker_id, $this->kanban_tracker_id)
        );
        $response = $this->getResponseForNonProjectMember($this->client->put('cross_tracker_reports/1', null, $params));

        $this->assertEquals($response->getStatusCode(), 403);
    }

    public function itThrowsAnExceptionWhenAQueryIsDefinedAndTrackersIdAreNotAnArray()
    {
        $query = json_encode(
            array(
                "trackers_id" => "toto"
            )
        );

        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $response = $this->getResponse(
            $this->client->get('cross_tracker_reports/1/content?limit=50&offset=0&query=' . urlencode($query))
        );

        $this->assertEquals($response->getStatusCode(), 400);
    }

    public function itThrowsAnExceptionWhenAQueryIsDefinedAndTrackersIdAreNotAnArrayOfInt()
    {
        $query = json_encode(
            array(
                "trackers_id" => array("toto")
            )
        );

        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $response = $this->getResponse(
            $this->client->get('cross_tracker_reports/1/content?limit=50&offset=0&query=' . urlencode($query))
        );

        $this->assertEquals($response->getStatusCode(), 400);
    }

    public function itThrowsAnExceptionWhenAQueryIsDefinedAndTrackersIdAreNotSent()
    {
        $query = json_encode(
            array("toto")
        );

        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $response = $this->getResponse(
            $this->client->get('cross_tracker_reports/1/content?limit=50&offset=0&query=' . urlencode($query))
        );

        $this->assertEquals($response->getStatusCode(), 400);
    }

    public function testYouCantAccessPersonalReportOfAnOtherUser()
    {
        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $response = $this->getResponseForNonProjectMember($this->client->get('cross_tracker_reports/2'));

        $this->assertEquals($response->getStatusCode(), 403);
    }

    public function testYouCantAccessProjectReportOfProjectYouCantSee()
    {
        $this->setExpectedException('Guzzle\Http\Exception\ClientErrorResponseException');
        $response = $this->getResponseForNonProjectMember($this->client->get('cross_tracker_reports/3'));

        $this->assertEquals($response->getStatusCode(), 403);
    }

    private function getResponseForNonProjectMember($request)
    {
        return $this->getResponse($request, \REST_TestDataBuilder::TEST_USER_5_NAME);
    }
}
