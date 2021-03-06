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

namespace Tuleap\Tracker\Webhook;

use Tuleap\DB\DataAccessObject;

class WebhookDao extends DataAccessObject
{

    public function searchWebhooksForTracker($tracker_id)
    {
        $sql = 'SELECT *
                FROM plugin_tracker_webhook_url
                WHERE tracker_id = ?';

        return $this->getDB()->run($sql, $tracker_id);
    }

    public function addLog($webhook_id, $status)
    {
        $sql = 'INSERT INTO plugin_tracker_webhook_log(created_on, webhook_id, status)
                VALUES (?, ?, ?)';

        $this->getDB()->run($sql, $_SERVER['REQUEST_TIME'], $webhook_id, $status);
    }

    public function duplicateWebhooks($source_tracker_id, $tracker_id)
    {
        $sql = 'INSERT INTO plugin_tracker_webhook_url(tracker_id, url)
                SELECT ?, url
                FROM plugin_tracker_webhook_url
                WHERE tracker_id = ?';

        $this->getDB()->run($sql, $tracker_id, $source_tracker_id);
    }

    public function save($tracker_id, $url)
    {
        $this->getDB()->insert('plugin_tracker_webhook_url', [
            'tracker_id' => $tracker_id,
            'url'        => $url,
        ]);
    }
}
