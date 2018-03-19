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

require_once( '../../../../system/bootstrap.inc.php' );

class Server_Api_Project_Folder_Load
{
    public function run( $arguments )
    {
        $principal = System_Api_Principal::getCurrent();
        $principal->checkAuthenticated();

        $projectId = isset( $arguments[ 'projectId' ] ) ? (int)$arguments[ 'projectId' ] : null;
        $folderId = isset( $arguments[ 'folderId' ] ) ? (int)$arguments[ 'folderId' ] : null;
        $access = isset( $arguments[ 'access' ] ) ? $arguments[ 'access' ] : null;

        if ( $projectId == null || $folderId == null )
            throw new Server_Error( Server_Error::InvalidArguments );

        $flags = 0;
        if ( $access == 'admin' )
            $flags = System_Api_ProjectManager::RequireAdministrator;
        else if ( $access != null )
            throw new Server_Error( Server_Error::InvalidArguments );

        $projectManager = new System_Api_ProjectManager();
        $folder = $projectManager->getFolder( $folderId, $flags );

        if ( $folder[ 'project_id' ] != $projectId )
            throw new System_Api_Error( System_Api_Error::UnknownFolder );

        $result[ 'name' ] = $folder[ 'folder_name' ];

        $result[ 'empty' ] = !$projectManager->checkFolderNotEmpty( $folder );

        return $result;
    }
}

System_Bootstrap::run( 'Server_Api_Application', 'Server_Api_Project_Folder_Load' );
