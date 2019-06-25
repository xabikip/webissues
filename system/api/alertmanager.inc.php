<?php
/**************************************************************************
* This file is part of the WebIssues Server program
* Copyright (C) 2006 Michał Męciński
* Copyright (C) 2007-2017 WebIssues Team
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
**************************************************************************/

if ( !defined( 'WI_VERSION' ) ) die( -1 );

/**
* Manage alerts.
*
* Like all API classes, this class does not check permissions to perform
* an operation and does not validate the input values. An error is thrown
* only if the requested object does not exist or is inaccessible.
*/
class System_Api_AlertManager extends System_Api_Base
{
    /**
    * @name Flags
    */
    /*@{*/
    /** Permission to edit the alert is required. */
    const AllowEdit = 1;
    /** Indicate a public alert. */
    const IsPublic = 2;
    /*@}*/

    /**
    * Constructor.
    */
    public function __construct()
    {
        parent::__construct();
    }

    /**
    * Return all public alerts for the current user.
    * @return An array of associative arrays representing alerts.
    */
    public function getPublicAlerts()
    {
        $principal = System_Api_Principal::getCurrent();

        $query = $this->generateAlertsQuery();

        return $this->connection->queryTable( $query, null, $principal->getUserId() );
    }

    /**
    * Return all personal alerts for the current user.
    * @return An array of associative arrays representing alerts.
    */
    public function getPersonalAlerts()
    {
        $principal = System_Api_Principal::getCurrent();

        $query = $this->generateAlertsQuery();

        return $this->connection->queryTable( $query, $principal->getUserId(), $principal->getUserId() );
    }

    private function generateAlertsQuery()
    {
        $principal = System_Api_Principal::getCurrent();

        $query = 'SELECT a.alert_id, t.type_name, v.view_name, p.project_name, f.folder_name, a.alert_type, a.alert_frequency'
            . ' FROM {alerts} AS a'
            . ' JOIN {issue_types} AS t ON t.type_id = a.type_id'
            . ' LEFT OUTER JOIN {views} AS v ON v.view_id = a.view_id'
            . ' LEFT OUTER JOIN {folders} AS f ON f.folder_id = a.folder_id'
            . ' LEFT OUTER JOIN {projects} AS p ON p.project_id = COALESCE( a.project_id, f.project_id )';
        if ( !$principal->isAdministrator() ) {
            $query .= ' LEFT OUTER JOIN {effective_rights} AS r ON r.project_id = p.project_id'
                . ' WHERE a.user_id = %1d? AND ( p.is_archived = 0 AND r.project_id IS NOT NULL OR '
                . ' p.project_id IS NULL AND EXISTS ( SELECT f2.folder_id FROM {folders} AS f2'
                . ' JOIN {projects} AS p2 ON p2.project_id = f2.folder_id'
                . ' JOIN {effective_rights} AS r2 ON r2.project_id = f2.project_id AND r2.user_id = %2d'
                . ' WHERE f2.type_id = a.type_id AND p2.is_archived = 0 ) )';
        } else {
            $query .= ' WHERE a.user_id = %1d? AND ( p.is_archived = 0 OR p.project_id IS NULL )';
        }
        $query .= ' ORDER BY t.type_name, v.view_name, p.project_name, f.folder_name';

        return $query;
    }

    /**
    * Get the alert with given identifier.
    * @param $alertId Identifier of the alert.
    * @param $flags If AllowEdit is passed an error is thrown if the user
    * does not have permission to edit the alert.
    * @return Array representing the alert.
    */
    public function getAlert( $alertId, $flags = 0 )
    {
        $principal = System_Api_Principal::getCurrent();

        $query = 'SELECT a.alert_id, t.type_name, v.view_name, p.project_name, f.folder_name,'
            . ' ( CASE WHEN a.user_id IS NULL THEN 1 ELSE 0 END ) AS is_public, a.alert_type, a.alert_frequency'
            . ' FROM {alerts} AS a'
            . ' JOIN {issue_types} AS t ON t.type_id = a.type_id'
            . ' LEFT OUTER JOIN {views} AS v ON v.view_id = a.view_id'
            . ' LEFT OUTER JOIN {folders} AS f ON f.folder_id = a.folder_id'
            . ' LEFT OUTER JOIN {projects} AS p ON p.project_id = COALESCE( a.project_id, f.project_id )';
        if ( !$principal->isAdministrator() ) {
            $query .= ' LEFT OUTER JOIN {effective_rights} AS r ON r.project_id = p.project_id'
                . ' WHERE a.alert_id = %1d AND ( p.is_archived = 0 AND r.project_id IS NOT NULL OR '
                . ' p.project_id IS NULL AND EXISTS ( SELECT f2.folder_id FROM {folders} AS f2'
                . ' JOIN {projects} AS p2 ON p2.project_id = f2.folder_id'
                . ' JOIN {effective_rights} AS r2 ON r2.project_id = f2.project_id AND r2.user_id = %2d'
                . ' WHERE f2.type_id = a.type_id AND p2.is_archived = 0 ) )';
        } else {
            $query .= ' WHERE a.alert_id = %1d AND ( p.is_archived = 0 OR p.project_id IS NULL )';
        }

        if ( !( $alert = $this->connection->queryRow( $query, $alertId, $principal->getUserId() ) ) )
            throw new System_Api_Error( System_Api_Error::UnknownAlert );

        if ( ( $flags & self::AllowEdit ) && !$principal->isAdministrator() && $alert[ 'is_public' ] )
            throw new System_Api_Error( System_Api_Error::AccessDenied );

        return $alert;
    }

    /**
    * Create a new alert. An error is thrown if such alert already exists.
    * @param $type Type associated with the alert.
    * @param $view Optional view associated with the alert.
    * @param $project Optional project for which the alert is created.
    * @param $folder Optional folder for which the alert is created.
    * @param $alertType Type of the alert.
    * @param $alertFrequency Frequency of the alert.
    * @param $flags If IsPublic is passed, a public alert is created.
    * @return The identifier of the new alert.
    */
    public function addAlert( $type, $view, $project, $folder, $alertType, $alertFrequency, $flags = 0 )
    {
        $principal = System_Api_Principal::getCurrent();

        $typeId = $type[ 'type_id' ];
        $viewId = ( $view != null ) ? $view[ 'view_id' ] : null;
        $projectId = ( $project != null ) ? $project[ 'project_id' ] : null;
        $folderId = ( $folder != null ) ? $folder[ 'folder_id' ] : null;
        $stampId = ( $folder != null ) ? $folder[ 'stamp_id' ] : null;
        $userId = ( $flags & self::IsPublic ) ? null : $principal->getUserId();

        if ( $alertType == System_Const::Notification )
            $alertFrequency = 0;

        $transaction = $this->connection->beginTransaction( System_Db_Transaction::Serializable, 'alerts' );

        try {
            if ( $flags & self::IsPublic )
                $query = 'SELECT alert_id FROM {alerts} WHERE user_id IS NULL AND type_id = %2d AND view_id = %3d? AND project_id = %4d? AND folder_id = %5d?';
            else
                $query = 'SELECT alert_id FROM {alerts} WHERE ( user_id = %1d OR user_id IS NULL ) AND type_id = %2d AND view_id = %3d? AND project_id = %4d? AND folder_id = %5d?';
            if ( $this->connection->queryScalar( $query, $userId, $typeId, $viewId, $projectId, $folderId ) !== false )
                throw new System_Api_Error( System_Api_Error::AlertAlreadyExists );

            if ( $stampId == null ) {
                $query = 'SELECT MAX( stamp_id ) FROM {folders} WHERE type_id = %d';
                $stampId = $this->connection->queryScalar( $query, $typeId );
            }

            $query = 'INSERT INTO {alerts} ( user_id, type_id, view_id, project_id, folder_id, alert_type, alert_frequency, stamp_id ) VALUES ( %d?, %d, %d?, %d?, %d?, %d, %d, %d? )';
            $this->connection->execute( $query, $userId, $typeId, $viewId, $projectId, $folderId, $alertType, $alertFrequency, $stampId );
            $alertId = $this->connection->getInsertId( 'alerts', 'alert_id' );

            if ( $flags & self::IsPublic ) {
                $query = 'DELETE FROM {alerts} WHERE user_id IS NOT NULL AND type_id = %d AND view_id = %d? AND project_id = %d? AND folder_id = %d?';
                $this->connection->execute( $query, $typeId, $viewId, $projectId, $folderId );
            }

            $transaction->commit();
        } catch ( Exception $ex ) {
            $transaction->rollback();
            throw $ex;
        }

        return $alertId;
    }

    /**
    * Modify settings of an alert.
    * @param $alert The alert to modify.
    * @param $alertType Type of the alert.
    * @param $alertFrequency Frequency of the alert.
    * @return @c true if the alert was modified.
    */
    public function modifyAlert( $alert, $alertType, $alertFrequency )
    {
        $alertId = $alert[ 'alert_id' ];
        $oldType = $alert[ 'alert_type' ];
        $oldFrequency = $alert[ 'alert_frequency' ];

        if ( $alertType == System_Const::Notification )
            $alertFrequency = 0;

        if ( $alertType == $oldType && $alertFrequency == $oldFrequency )
            return false;

        $query = 'UPDATE {alerts} SET alert_type = %d, alert_frequency = %d WHERE alert_id = %d';
        $this->connection->execute( $query, $alertType, $alertFrequency, $alertId );

        return true;
    }

    /**
    * Delete an alert.
    * @param $alert The alert to delete.
    * @return @c true if the alert was deleted.
    */
    public function deleteAlert( $alert )
    {
        $alertId = $alert[ 'alert_id' ];

        $query = 'DELETE FROM {alerts} WHERE alert_id = %d';
        $this->connection->execute( $query, $alertId );

        return true;
    }

    /**
    * Return alerts for which emails should be sent.
    * @param $includeSummary If @c true, the summary notifications and reports are
    * included in addition to immediate notifications.
    * @return An array of associative arrays representing alerts.
    */
    public function getAlertsToEmail( $includeSummary )
    {
        $principal = System_Api_Principal::getCurrent();

        $query = 'SELECT a.alert_id, a.folder_id, a.type_id, a.view_id, a.alert_email, a.summary_days, a.summary_hours, a.stamp_id'
            . ' FROM {alerts} AS a'
            . ' WHERE a.user_id = %1d AND EXISTS ( SELECT f.folder_id FROM {folders} AS f'
            . ' JOIN {projects} AS p ON p.project_id = f.project_id';
        if ( !$principal->isAdministrator() )
            $query .= ' JOIN {effective_rights} AS r ON r.project_id = f.project_id AND r.user_id = %1d';
        $query .= ' WHERE ( f.folder_id = a.folder_id OR f.type_id = a.type_id ) AND p.is_archived = 0';

        if ( $includeSummary ) {
            $query .= ' AND ( a.alert_email > %2d AND f.stamp_id > COALESCE( a.stamp_id, 0 ) OR a.alert_email = %3d ) )';

            return $this->connection->queryTable( $query, $principal->getUserId(), System_Const::NoEmail, System_Const::SummaryReportEmail );
        } else {
            $query .= ' AND ( a.alert_email = %2d AND f.stamp_id > COALESCE( a.stamp_id, 0 ) ) )';

            return $this->connection->queryTable( $query, $principal->getUserId(), System_Const::ImmediateNotificationEmail );
        }
    }

    /**
    * Return public alerts for which emails should be sent.
    * @param $includeSummary If @c true, the summary notifications and reports are
    * included in addition to immediate notifications.
    * @return An array of associative arrays representing alerts.
    */
    public function getPublicAlertsToEmail( $includeSummary )
    {
        $query = 'SELECT a.alert_id, a.folder_id, a.type_id, a.view_id, a.alert_email, a.summary_days, a.summary_hours, a.stamp_id'
            . ' FROM {alerts} AS a'
            . ' WHERE a.user_id IS NULL AND EXISTS ( SELECT f.folder_id FROM {folders} AS f'
            . ' JOIN {projects} AS p ON p.project_id = f.project_id'
            . ' WHERE ( f.folder_id = a.folder_id OR f.type_id = a.type_id ) AND p.is_archived = 0';

        if ( $includeSummary ) {
            $query .= ' AND ( a.alert_email > %1d AND f.stamp_id > COALESCE( a.stamp_id, 0 ) OR a.alert_email = %2d ) )';

            return $this->connection->queryTable( $query, System_Const::NoEmail, System_Const::SummaryReportEmail );
        } else {
            $query .= ' AND ( a.alert_email = %1d AND f.stamp_id > COALESCE( a.stamp_id, 0 ) ) )';

            return $this->connection->queryTable( $query, System_Const::ImmediateNotificationEmail );
        }
    }

    /**
    * Return users for which emails related to a public alert should be sent.
    * @param $alert The public alert to get recipients.
    * @return An array of associative arrays representing users.
    */
    public function getAlertRecipients( $alert )
    {
        $folderId = $alert[ 'folder_id' ];
        $typeId = $alert[ 'type_id' ];
        $alertEmail = $alert[ 'alert_email' ];
        $stampId = $alert[ 'stamp_id' ];

        $query = 'SELECT u.user_id, u.user_name, u.user_access'
            . ' FROM {users} AS u'
            . ' JOIN {preferences} AS p ON p.user_id = u.user_id AND p.pref_key = %1s'
            . ' WHERE u.user_access > %2d AND EXISTS ( SELECT f.folder_id FROM {folders} AS f'
            . ' JOIN {projects} AS p ON p.project_id = f.project_id';
        if ( $folderId )
            $query .= ' WHERE f.folder_id = %4d';
        else
            $query .= ' WHERE f.type_id = %5d';
        $query .= ' AND ( u.user_access = %3d OR EXISTS ( SELECT r.project_id FROM {effective_rights} AS r WHERE r.project_id = f.project_id AND r.user_id = u.user_id ) )';
        if ( $alertEmail != System_Const::SummaryReportEmail && $stampId > 0 )
            $query .= ' AND f.stamp_id > %6d';
        $query .= ' AND p.is_archived = 0 )';

        return $this->connection->queryTable( $query, 'email', System_Const::NoAccess, System_Const::AdministratorAccess, $folderId, $typeId, $stampId );
    }

    /**
    * Update the stamp of last sent email for given alert.
    * @param $alert The alert to update.
    */
    public function updateAlertStamp( $alert )
    {
        $alertId = $alert[ 'alert_id' ];
        $folderId = $alert[ 'folder_id' ];

        if ( $folderId != null ) {
            $query = 'UPDATE {alerts}'
                . ' SET stamp_id = ( SELECT f.stamp_id FROM {folders} AS f WHERE f.folder_id = %d )'
                . ' WHERE alert_id = %d';

            $this->connection->execute( $query, $folderId, $alertId );
        } else {
            $typeId = $alert[ 'type_id' ];

            $query = 'UPDATE {alerts}'
                . ' SET stamp_id = ( SELECT MAX( f.stamp_id ) FROM {folders} AS f WHERE f.type_id = %d )'
                . ' WHERE alert_id = %d';

            $this->connection->execute( $query, $typeId, $alertId );
        }
    }
}
