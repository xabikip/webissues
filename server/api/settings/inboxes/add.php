<?php
/**************************************************************************
* This file is part of the WebIssues Server program
* Copyright (C) 2006 Michał Męciński
* Copyright (C) 2007-2020 WebIssues Team
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

class Server_Api_Settings_Inboxes_Add
{
    public $access = 'admin';

    public $params = array(
        'engine' => array( 'type' => 'string', 'required' => true ),
        'email' => array( 'type' => 'string', 'required' => true ),
        'server' => array( 'type' => 'string', 'required' => true ),
        'port' => array( 'type' => 'int', 'required' => true ),
        'encryption' => 'string',
        'user' => 'string',
        'password' => 'string',
        'mailbox' => 'string',
        'noValidate' => 'bool',
        'leaveMessages' => 'bool',
        'allowExternal' => 'bool',
        'robot' => 'int',
        'mapFolder' => 'bool',
        'defaultFolder' => 'int',
        'respond' => 'bool',
        'subscribe' => 'bool',
        'format' => array( 'type' => 'int', 'default' => System_Const::SeparateAttachmentsFormat )
    );

    public function run( $engine, $email, $server, $port, $encryption, $user, $password, $mailbox, $noValidate, $leaveMessages, $allowExternal, $robot,
                         $mapFolder, $defaultFolder, $respond, $subscribe, $format )
    {
        $helper = new Server_Api_Helpers_Inboxes();
        $helper->validateBasic( $engine, $email, $server, $port, $encryption, $user, $password, $mailbox );
        $helper->validateExtended( $engine, $leaveMessages, $allowExternal, $robot, $mapFolder, $defaultFolder, $format );

        $inboxManager = new System_Api_InboxManager();

        $inbox = array(
            'inbox_engine' => $engine,
            'inbox_email' => $email,
            'inbox_server' => $server,
            'inbox_port' => $port,
            'inbox_encryption' => $encryption,
            'inbox_user' => $user,
            'inbox_password' => $password,
            'inbox_mailbox' => $mailbox,
            'inbox_no_validate' => $noValidate ? 1 : 0,
            'inbox_leave_messages' => $leaveMessages ? 1 : 0,
            'inbox_allow_external' => $allowExternal ? 1 : 0,
            'inbox_robot' => $robot,
            'inbox_map_folder' => $mapFolder ? 1 : 0,
            'inbox_default_folder' => $defaultFolder,
            'inbox_respond' => $respond,
            'inbox_subscribe' => $subscribe,
            'inbox_format' => $format
        );

        $inboxId = $inboxManager->addInbox( $inbox );

        $result[ 'inboxId' ] = $inboxId;
        $result[ 'changed' ] = true;

        return $result;
    }
}

System_Bootstrap::run( 'Server_Api_Application', 'Server_Api_Settings_Inboxes_Add' );
