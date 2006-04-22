<?php
/*
* Copyright 2003 - 2005 Mark O'Sullivan
* This file is part of Vanilla.
* Vanilla is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
* Vanilla is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
* You should have received a copy of the GNU General Public License along with Vanilla; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
* The latest source code for Vanilla is available at www.lussumo.com
* Contact Mark O'Sullivan at mark [at] lussumo [dot] com
* 
* Description: Discussion Comment Manager class
*/

class CommentManager extends Delegation {
	var $Name;				// The name of this class
   var $Context;			// The context object that contains all global objects (database, error manager, warning collector, session, etc)
	
	function CommentManager(&$Context) {
		$this->Name = 'CommentManager';
		$this->Delegation($Context);
	}
	
	// Returns a SqlBuilder object with all of the comment properties already defined in the select
	function GetCommentBuilder($s = 0) {
		if (!$s) $s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
		$s->SetMainTable('Comment', 'm');

		$s->AddJoin('User', 'a', 'UserID', 'm', 'AuthUserID', 'inner join');
		$s->AddJoin('Role', 'r', 'RoleID', 'a', 'RoleID', 'left join');
		$s->AddJoin('User', 'e', 'UserID', 'm', 'EditUserID', 'left join');
		$s->AddJoin('User', 'd', 'UserID', 'm', 'DeleteUserID', 'left join');
		$s->AddJoin('UserBlock', 'ab', 'BlockedUserID', 'm', 'AuthUserID', 'left join', ' and ab.'.$this->Context->DatabaseColumns['UserBlock']['BlockingUserID'].' = '.$this->Context->Session->UserID);
		$s->AddJoin('CommentBlock', 'cb', 'BlockedCommentID', 'm', 'CommentID', 'left join', ' and cb.'.$this->Context->DatabaseColumns['CommentBlock']['BlockingUserID'].' = '.$this->Context->Session->UserID);
		$s->AddJoin('Discussion', 't', 'DiscussionID', 'm', 'DiscussionID', 'inner join');
      $s->AddJoin('User', 'w', 'UserID', 'm', 'WhisperUserID', 'left join');
		
		// $s->AddGroupBy('CommentID', 'm');
		
		
		// Limit to roles with access to this category
      if ($this->Context->Session->UserID > 0) {
			$s->AddJoin('CategoryRoleBlock', 'crb', 'CategoryID', 't', 'CategoryID', 'left join', ' and crb.'.$this->Context->DatabaseColumns['CategoryRoleBlock']['RoleID'].' = '.$this->Context->Session->User->RoleID);
		} else {
			$s->AddJoin('CategoryRoleBlock', 'crb', 'CategoryID', 't', 'CategoryID', 'left join', ' and crb.'.$this->Context->DatabaseColumns['CategoryRoleBlock']['RoleID'].' = 1');
		}
		
		$s->AddSelect(array('CommentID', 'DiscussionID', 'Body', 'FormatType', 'DateCreated', 'DateEdited', 'DateDeleted', 'Deleted', 'AuthUserID', 'EditUserID', 'DeleteUserID', 'WhisperUserID', 'RemoteIp'), 'm');
		$s->AddSelect('Name', 'a', 'AuthUsername');
		$s->AddSelect('Icon', 'a', 'AuthIcon');
		$s->AddSelect('Name', 'r', 'AuthRole');
		$s->AddSelect('RoleID', 'r', 'AuthRoleID');
		$s->AddSelect('Description', 'r', 'AuthRoleDesc');
		$s->AddSelect('Icon', 'r', 'AuthRoleIcon');
		$s->AddSelect('PERMISSION_HTML_ALLOWED', 'r', 'AuthCanPostHtml');
		$s->AddSelect('Name', 'e', 'EditUsername');
		$s->AddSelect('Name', 'd', 'DeleteUsername');
		$s->AddSelect('Blocked', 'ab', 'AuthBlocked', 'coalesce', '0');
		$s->AddSelect('Blocked', 'cb', 'CommentBlocked', 'coalesce', '0');
      $s->AddSelect('WhisperUserID', 't', 'DiscussionWhisperUserID');
      $s->AddSelect('Name', 'w', 'WhisperUsername');
		
		
		$s->AddWhere('crb', 'Blocked', '', 0, '=', 'and', '', 1, 1);
		$s->AddWhere('crb', 'Blocked', '', 0, '=', 'or', '', 0);
		$s->AddWhere('crb', 'Blocked', '', 'null', 'is', 'or', '', 0);
		$s->EndWhereGroup();
		
		return $s;
	}
		
	function GetCommentById($CommentID, $UserID) {
		$Comment = $this->Context->ObjectFactory->NewContextObject($this->Context, 'Comment');

		$s = $this->GetCommentBuilder();
		if (!$this->Context->Session->User->Permission('PERMISSION_HIDE_COMMENTS')) $s->AddWhere('m', 'Deleted', '', '0', '=');
		
		$s->AddWhere('m', 'CommentID', '', $CommentID, '=');
			
		$result = $this->Context->Database->Select($s, $this->Name, 'GetCommentById', 'An error occurred while attempting to retrieve the requested comment.');
		if ($this->Context->Database->RowCount($result) == 0) $this->Context->WarningCollector->Add($this->Context->GetDefinition('ErrCommentNotFound'));
		while ($rows = $this->Context->Database->GetRow($result)) {
			$Comment->GetPropertiesFromDataSet($rows, $UserID);
		}
		return $this->Context->WarningCollector->Iif($Comment, false);
	}
	
	function GetCommentCount($DiscussionID) {
		$TotalNumberOfRecords = 0;
		$DiscussionID = ForceInt($DiscussionID, 0);
		
		// If the current user is admin, see if they can view inactive comments
		// If the current user is not admin only show active comments
		$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
		$s->SetMainTable('Comment', 'm');
		$s->AddJoin('Discussion', 't', 'DiscussionID', 'm', 'DiscussionID', 'inner join');
		if (!$this->Context->Session->User->Permission('PERMISSION_VIEW_HIDDEN_COMMENTS')
			|| !$this->Context->Session->User->Preference('ShowDeletedComments')) {
			$s->AddWhere('m', 'Deleted', '', 0, '=', 'and', '', 1, 1);
			$s->AddWhere('m', 'Deleted', '', 0, '=', 'or', '' ,0);
			$s->EndWhereGroup();
		}
		$s->AddSelect('CommentID', 'm', 'Count', 'count');
		if ($this->Context->Configuration['ENABLE_WHISPERS']) {
         if (!$this->Context->Session->User->Permission('PERMISSION_VIEW_ALL_WHISPERS')) {
            $s->AddWhere('m', 'WhisperUserID', '', $this->Context->Session->UserID, '=', 'and', '', 1, 1);
            $s->AddWhere('m', 'WhisperUserID', '', 'null', 'is', 'or', '', 0);
            $s->AddWhere('m', 'WhisperUserID', '', '0', '=', 'or', '', 0);
            $s->AddWhere('m', 'WhisperUserID', '', '0', '=', 'or', '', 1);
            $s->AddWhere('m', 'AuthUserID', '', $this->Context->Session->UserID, '=', 'or');
            $s->EndWhereGroup();
         }
		} else {
			// If whispers aren't on - we want to make sure that whispers aren't included in the count
			$s->AddWhere('m', 'WhisperUserID', '', 0, '=', 'and', '', 1, 1);
			$s->AddWhere('m', 'WhisperUserID', '', 0, '=', 'or', '' ,0);
			$s->AddWhere('m', 'WhisperUserID', '', 'null', 'is', 'or', '' ,0);
			$s->EndWhereGroup();
		}

		$s->AddWhere('m', 'DiscussionID', '', $DiscussionID, '=');

		$this->DelegateParameters['SqlBuilder'] = &$s;
		$this->CallDelegate("CommentManager_GetCommentCount");		
		
		$result = $this->Context->Database->Select($s, $this->Name, 'GetCommentCount', 'An error occurred while retrieving comment information.');
		while ($rows = $this->Context->Database->GetRow($result)) {
			$TotalNumberOfRecords = $rows['Count'];
		}
		return $TotalNumberOfRecords;
	}
	
	function GetCommentList($RowsPerPage, $CurrentPage, $DiscussionID) {
		$RowsPerPage = ForceInt($RowsPerPage, 50);
		$CurrentPage = ForceInt($CurrentPage, 1);
		$DiscussionID = ForceInt($DiscussionID, 0);
		
		if ($RowsPerPage > 0) {
			$CurrentPage = ForceInt($CurrentPage, 1);
			if ($CurrentPage < 1) $CurrentPage == 1;
			$RowsPerPage = ForceInt($RowsPerPage, 50);
			$FirstRecord = ($CurrentPage * $RowsPerPage) - $RowsPerPage;
		}
		
		$s = $this->GetCommentBuilder();
		if (!$this->Context->Session->User->Permission('PERMISSION_VIEW_HIDDEN_COMMENTS')
			|| !$this->Context->Session->User->Preference('ShowDeletedComments')) {
			$s->AddWhere('m', 'Deleted', '', 0, '=', 'and', '', 1, 1);
			$s->AddWhere('m', 'Deleted', '', 0, '=', 'or', '' ,0);
			$s->EndWhereGroup();
		}
		
		if ($this->Context->Configuration['ENABLE_WHISPERS']) {
         if (!$this->Context->Session->User->Permission('PERMISSION_VIEW_ALL_WHISPERS')) {
            $s->AddWhere('m', 'WhisperUserID', '', $this->Context->Session->UserID, '=', 'and', '', 1, 1);
            $s->AddWhere('m', 'WhisperUserID', '', 'null', 'is', 'or', '', 0);
            $s->AddWhere('m', 'WhisperUserID', '', '0', '=', 'or', '', 0);
            $s->AddWhere('m', 'WhisperUserID', '', '0', '=', 'or', '', 1);
            $s->AddWhere('m', 'AuthUserID', '', $this->Context->Session->UserID, '=', 'or');
            $s->EndWhereGroup();
         }
		} else {		
			// If whispers aren't on - we want to make sure that whispers aren't included in the count
			$s->AddWhere('m', 'WhisperUserID', '', 0, '=', 'and', '', 1, 1);
			$s->AddWhere('m', 'WhisperUserID', '', 0, '=', 'or', '' ,0);
			$s->AddWhere('m', 'WhisperUserID', '', 'null', 'is', 'or', '' ,0);
			$s->EndWhereGroup();
		}
		
		$s->AddWhere('m', 'DiscussionID', '', $DiscussionID, '=');
		$s->AddOrderBy('DateCreated', 'm', 'asc');
		if ($RowsPerPage > 0) $s->AddLimit($FirstRecord, $RowsPerPage);
		
		$this->DelegateParameters['SqlBuilder'] = &$s;
		$this->CallDelegate("CommentManager_GetCommentList");

		return $this->Context->Database->Select($s, $this->Name, 'GetCommentList', 'An error occurred while attempting to retrieve the requested comments.');
	}
	
	function GetCommentSearch($RowsPerPage, $CurrentPage, $Search) {
		$s = $this->GetSearchBuilder($Search);
		// Caused the query to be 10 times slower
		// $s->AddOrderBy('DateCreated', 'c', 'desc');
		if ($RowsPerPage > 0) {
			$CurrentPage = ForceInt($CurrentPage, 1);
			if ($CurrentPage < 1) $CurrentPage == 1;
			$RowsPerPage = ForceInt($RowsPerPage, 50);
			$FirstRecord = ($CurrentPage * $RowsPerPage) - $RowsPerPage;
		}		
		if ($RowsPerPage > 0) $s->AddLimit($FirstRecord, $RowsPerPage+1);
		$s->AddOrderBy('DateCreated', 'c', 'desc');
		
		return $this->Context->Database->Select($s, $this->Name, 'GetCommentSearch', 'An error occurred while retrieving search results.');
	}
	
	function GetSearchBuilder($Search) {
		$Search->FormatPropertiesForDatabaseInput();
		$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlSearch');
		$s->SetMainTable('Comment', 'c');

		$s->AddJoin('User', 'a', 'UserID', 'c', 'AuthUserID', 'inner join');
		$s->AddJoin('Discussion', 'd', 'DiscussionID', 'c', 'DiscussionID', 'inner join');
      $s->AddJoin('User', 'w', 'UserID', 'c', 'WhisperUserID', 'left join');
		$s->AddJoin('Category', 'ca', 'CategoryID', 'd', 'CategoryID', 'left join');
		
		// Caused the query to be 50 times slower
		// $s->AddGroupBy('CommentID', 'c');
		
		
		// Limit to roles with access to this category
      if ($this->Context->Session->UserID > 0) {
			$s->AddJoin('CategoryRoleBlock', 'crb', 'CategoryID', 'd', 'CategoryID', 'left join', ' and crb.'.$this->Context->DatabaseColumns['CategoryRoleBlock']['RoleID'].' = '.$this->Context->Session->User->RoleID);
		} else {
			$s->AddJoin('CategoryRoleBlock', 'crb', 'CategoryID', 'd', 'CategoryID', 'left join', ' and crb.'.$this->Context->DatabaseColumns['CategoryRoleBlock']['RoleID'].' = 1');
		}

		$s->AddSelect(array('CommentID', 'DiscussionID', 'Body', 'FormatType', 'DateCreated', 'Deleted', 'AuthUserID', 'WhisperUserID'), 'c');
		$s->AddSelect('Name', 'a', 'AuthUsername');
      $s->AddSelect('WhisperUserID', 'd', 'DiscussionWhisperUserID');
      $s->AddSelect('Name', 'w', 'WhisperUsername');


		$s->AddWhere('crb', 'Blocked', '', 0, '=', 'and', '', 1, 1);
		$s->AddWhere('crb', 'Blocked', '', 0, '=', 'or', '', 0);
		$s->AddWhere('crb', 'Blocked', '', 'null', 'is', 'or', '', 0);
		$s->EndWhereGroup();
		
		$s->UserQuery = $Search->Query;
		$s->SearchFields = array('c.Body');
		$s->DefineSearch();
		$s->AddSelect('Name', 'd', 'Discussion');
		$s->AddSelect('CategoryID', 'd');
		$s->AddSelect('Name', 'ca', 'Category');
		
		// If the current user is not admin only show active discussions & comments
		if (!$this->Context->Session->User->Permission('PERMISSION_HIDE_COMMENTS') || !$this->Context->Session->User->Preference('ShowDeletedComments')) $s->AddWhere('c', 'Deleted', '', '0', '=');
		if (!$this->Context->Session->User->Permission('PERMISSION_HIDE_DISCUSSIONS') || !$this->Context->Session->User->Preference('ShowDeletedDiscussions')) $s->AddWhere('d', 'Active', '', '1', '=');			

		if ($Search->Categories != '') {
			$Cats = explode(',',$Search->Categories);
			$CatCount = count($Cats);
			$s->AddWhere('', '1', '', '0', '=', 'and', '', 0, 1);
			$i = 0;
			for ($i = 0; $i < $CatCount; $i++) {
				$s->AddWhere('ca', 'Name', '', trim($Cats[$i]), '=', 'or');
			}
			$s->EndWhereGroup();			
		}
		if ($Search->AuthUsername != '') $s->AddWhere('a', 'Name', '', $Search->AuthUsername, '=');

		if ($this->Context->Configuration['ENABLE_WHISPERS']) {		
         if ($Search->WhisperFilter) $s->AddWhere('c', 'WhisperUserID', '', 0, '>');
         if ($Search->AuthUsername != '') $s->AddWhere('a', 'Name', '', $Search->AuthUsername, '=');
         if (!$this->Context->Session->User->Permission('PERMISSION_VIEW_ALL_WHISPERS')) {
            // If the user cannot view all whispers, make sure that:
            // if the current topic is a whisper, make sure it is the
            // author or the whisper recipient viewing
            $s->AddWhere('d', 'AuthUserID', '', $this->Context->Session->UserID, '=', 'and', '', 1, 1);
				$s->AddWhere('d', 'WhisperUserID', '', $this->Context->Session->UserID, '=', 'or', '', 1, 0);
				$s->AddWhere('d', 'WhisperUserID', '', 0, '=', 'or', '', 1, 0);
				$s->EndWhereGroup();
				
            $s->AddWhere('c', 'AuthUserID', '', $this->Context->Session->UserID, '=', 'and', '', 1, 1);
				$s->AddWhere('c', 'WhisperUserID', '', $this->Context->Session->UserID, '=', 'or', '', 1, 0);
				$s->AddWhere('c', 'WhisperUserID', '', 0, '=', 'or', '', 1, 0);
            $s->EndWhereGroup();
         }
		} else {
			$s->AddWhere('c', 'WhisperUserID', '', 0, '=', 'and', '', 1, 1);
			$s->AddWhere('c', 'WhisperUserID', '', 0, '=', 'or', '', 0);
			$s->AddWhere('c', 'WhisperUserID', '', 'null', 'is', 'or', '', 0);
			$s->EndWhereGroup();
			$s->AddWhere('d', 'WhisperUserID', '', 0, '=', 'and', '', 1, 1);
			$s->AddWhere('d', 'WhisperUserID', '', 0, '=', 'or', '', 0);
			$s->AddWhere('d', 'WhisperUserID', '', 'null', 'is', 'or', '', 0);
			$s->EndWhereGroup();
		}
		return $s;
	}
	
	function SaveComment(&$Comment, $SkipValidation = 0) {
		// Set the user's default comment format to the most recently selected one         
		if ($Comment->AuthUserID > 0 && $Comment->AuthUserID != $this->Context->Session->UserID) {
			// Unless the user is editing another user's comments, then do nothing
		} else {
			$FormatTypeUserID = $Comment->AuthUserID;
			if ($FormatTypeUserID == 0) $FormatTypeUserID = $this->Context->Session->UserID;
			$um = $this->Context->ObjectFactory->NewContextObject($this->Context, 'UserManager');
			$um->SetDefaultFormatType($FormatTypeUserID, $Comment->FormatType);
		}
		
		// If not editing, and the posted comment count is less than the
		// user's current comment count, silently skip the posting and
		// redirect as if everything is normal.
		if (!$SkipValidation && $Comment->CommentID == 0 && $Comment->UserCommentCount < $this->Context->Session->User->CountComments) {
			// Silently fail to post the data
			// Need to get the user's last posted commentID in this discussion and direct them to it
			$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
			$s->SetMainTable('Comment', 'c');
			$s->AddSelect('CommentID', 'c');
			$s->AddWhere('c', 'AuthUserID', '', $this->Context->Session->UserID, '=');
			$s->AddWhere('c', 'DiscussionID', '', $Comment->DiscussionID, '=');
			$s->AddOrderBy('DateCreated', 'c', 'desc');
			$s->AddLimit(0,1);
			$LastCommentData = $this->Context->Database->Select($s, $this->Name, 'SaveComment', 'An error occurred while retrieving your last comment in this discussion.');
			while ($Row = $this->Context->Database->GetRow($LastCommentData)) {
				$Comment->CommentID = ForceInt($Row['CommentID'], 0);
			}
			// Make sure we got it
			if ($Comment->CommentID == 0) $this->Context->ErrorManager->AddError($this->Context, $this->Name, 'SaveComment', 'Your last comment in this discussion could not be found.');
		} else {
			// Validate the properties
			$SaveComment = $Comment;
			if (!$SkipValidation) {
				if (!$this->Context->Session->User->Permission('PERMISSION_ADD_COMMENTS')) $this->Context->WarningCollector->Add($this->Context->GetDefinition('ErrPermissionAddComments'));
				$this->ValidateComment($SaveComment);
				$this->ValidateWhisperUsername($SaveComment);
			}
			if ($this->Context->WarningCollector->Iif()) {
				$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
				
				// If creating a new object
				if ($SaveComment->CommentID == 0) {	
					// Update the user info & check for spam
					if (!$SkipValidation) {
						$UserManager = $this->Context->ObjectFactory->NewContextObject($this->Context, 'UserManager');
						$UserManager->UpdateUserCommentCount($this->Context->Session->UserID);
					}
					
					// Format the values for db input
					$SaveComment->FormatPropertiesForDatabaseInput();
			
					// Proceed with the save if there are no warnings
					if ($this->Context->WarningCollector->Count() == 0) {
						$Comment = $SaveComment;
						$s->SetMainTable('Comment', 'm');
						$s->AddFieldNameValue('Body', $Comment->Body);
						$s->AddFieldNameValue('FormatType', $Comment->FormatType);
						$s->AddFieldNameValue('RemoteIp', GetRemoteIp(1));
						$s->AddFieldNameValue('DiscussionID', $Comment->DiscussionID);
						$s->AddFieldNameValue('AuthUserID', $this->Context->Session->UserID);
						$s->AddFieldNameValue('DateCreated', MysqlDateTime());
						$s->AddFieldNameValue('WhisperUserID', $Comment->WhisperUserID);
						
						$Comment->CommentID = $this->Context->Database->Insert($s, $this->Name, 'SaveComment', 'An error occurred while creating a new discussion comment.');
					
						// If there were no errors, update the discussion count & time
						if ($Comment->WhisperUserID) {
							// Whisper-to table
							if ($Comment->WhisperUserID != $this->Context->Session->UserID) {
								// Only record the whisper to if the user is not whispering to him/herself - this is to make sure that the counts come out correctly when counting replies for a discussion
								$s->Clear();
								$s->SetMainTable('DiscussionUserWhisperTo', 'tuwt');
								$s->AddFieldNameValue('CountWhispers', 'CountWhispers+1', '0');
								$s->AddFieldNameValue('DateLastActive', MysqlDateTime());
								$s->AddFieldNameValue('LastUserID', $this->Context->Session->UserID);
								$s->AddWhere('tuwt', 'DiscussionID', '', $Comment->DiscussionID, '=');
								$s->AddWhere('tuwt', 'WhisperToUserID', '', $Comment->WhisperUserID, '=');
								if ($this->Context->Database->Update($s, $this->Name, 'SaveComment', "An error occurred while updating the discussion's comment summary.") <= 0) {
									// If no records were updated, then insert a new row to the table for this discussion/user whisper
									$s->Clear();
									$s->SetMainTable('DiscussionUserWhisperTo', 'tuwt');
									$s->AddFieldNameValue('CountWhispers', '1');
									$s->AddFieldNameValue('DateLastActive', MysqlDateTime());
									$s->AddFieldNameValue('DiscussionID', $Comment->DiscussionID);
									$s->AddFieldNameValue('WhisperToUserID', $Comment->WhisperUserID);
									$s->AddFieldNameValue('LastUserID', $this->Context->Session->UserID);
									$this->Context->Database->Insert($s, $this->Name, 'SaveComment', "An error occurred while updating the discussion's comment summary.");
								}
							}
							// Whisper-from table
							$s->Clear();
							$s->SetMainTable('DiscussionUserWhisperFrom', 'tuwf');
							$s->AddFieldNameValue('CountWhispers', 'CountWhispers+1', '0');
							$s->AddFieldNameValue('DateLastActive', MysqlDateTime());
							$s->AddFieldNameValue('LastUserID', $this->Context->Session->UserID);
							$s->AddWhere('tuwf', 'DiscussionID', '', $Comment->DiscussionID, '=');
							$s->AddWhere('tuwf', 'WhisperFromUserID', '', $this->Context->Session->UserID, '=');
							if ($this->Context->Database->Update($s, $this->Name, 'SaveComment', "An error occurred while updating the discussion's comment summary.") <= 0) {
								// If no records were updated, then insert a new row to the table for this discussion/user whisper
								$s->Clear();
								$s->SetMainTable('DiscussionUserWhisperFrom', 'tuwf');
								$s->AddFieldNameValue('CountWhispers', '1');
								$s->AddFieldNameValue('DateLastActive', MysqlDateTime());
								$s->AddFieldNameValue('DiscussionID', $Comment->DiscussionID);
								$s->AddFieldNameValue('WhisperFromUserID', $this->Context->Session->UserID);
								$s->AddFieldNameValue('LastUserID', $this->Context->Session->UserID);
								$this->Context->Database->Insert($s, $this->Name, 'SaveComment', "An error occurred while updating the discussion's comment summary.");
							}
							// Update the discussion table
							$s->Clear();
							$s->SetMainTable('Discussion', 't');
							$s->AddFieldNameValue('DateLastWhisper', MysqlDateTime());
							$s->AddFieldNameValue('WhisperToLastUserID', $Comment->WhisperUserID);
							$s->AddFieldNameValue('WhisperFromLastUserID', $this->Context->Session->UserID);
							$s->AddFieldNameValue('TotalWhisperCount', 'TotalWhisperCount+1', 0);
							$s->AddWhere('t', 'DiscussionID', '', $Comment->DiscussionID, '=');
							$this->Context->Database->Update($s, $this->Name, 'SaveComment', "An error occurred while updating the discussion's whisper summary.");
						} else {
							// First update the counts & last user
							$s->Clear();
							$s->SetMainTable('Discussion', 't');
							$s->AddFieldNameValue('CountComments', 'CountComments+1', '0');
							$s->AddFieldNameValue('LastUserID', $this->Context->Session->UserID);
							$s->AddWhere('t', 'DiscussionID', '', $Comment->DiscussionID, '=');
							$this->Context->Database->Update($s, $this->Name, 'SaveComment', "An error occurred while updating the discussion's comment summary.");
							
							// Now only update the DateLastActive if the discussion isn't set to Sink
							$s->Clear();
							$s->SetMainTable('Discussion', 't');
							$s->AddFieldNameValue('DateLastActive', MysqlDateTime());
							$s->AddWhere('t', 'DiscussionID', '', $Comment->DiscussionID, '=');
							$s->AddWhere('t', 'Sink', '', '1', '<>', 'and');
							$this->Context->Database->Update($s, $this->Name, 'SaveComment', "An error occurred while updating the discussion's last active date.");
						}
					}
				} else {
					$Comment = $SaveComment;
					
					// Format the values for db input
					$Comment->FormatPropertiesForDatabaseInput();
					
					// Get information about the comment being edited
					$s->SetMainTable('Comment', 'm');
					$s->AddSelect(array('AuthUserID', 'WhisperUserID'), 'm');
					$s->AddWhere('m', 'CommentID', '', $Comment->CommentID, '=');
					$CommentData = $this->Context->Database->Select($s, $this->Name, 'SaveComment', 'An error occurred while retrieving information about the comment.');
					$WhisperToUserID = 0;
					$WhisperFromUserID = 0;
					while ($Row = $this->Context->Database->GetRow($CommentData)) {
						$WhisperToUserID = ForceInt($Row['WhisperUserID'], 0);
						$WhisperFromUserID = ForceInt($Row['AuthUserID'], 0);
					}
					if ($WhisperToUserID > 0 && $Comment->WhisperUserID == 0) {
						// If the original comment was whispered and the new one isn't
						// 1. Update the whisper count for this discussion
						$this->UpdateWhisperCount($Comment->DiscussionID, $WhisperFromUserID, $WhisperToUserID, '-');
						// 2. Update the comment count for this discussion
						$this->UpdateCommentCount($Comment->DiscussionID, '+');
						
					} elseif ($WhisperToUserID == 0 && $Comment->WhisperUserID > 0){                  
						// If the original comment was not whispered and the new one is
						// 1. Update the comment count for this discussion
						$this->UpdateCommentCount($Comment->DiscussionID, '-');					
						// 2. Update the whisper count for this discussion
						$this->UpdateWhisperCount($Comment->DiscussionID, $WhisperFromUserID, $Comment->WhisperUserID, '+');
						
					} elseif ($WhisperToUserID > 0 && $Comment->WhisperUserID > 0 && $WhisperToUserID != $Comment->WhisperUserID) {
						// If the original comment was whispered to a different person
						// 1. Remove traces of the old whisper
						$this->UpdateWhisperCount($Comment->DiscussionID, $WhisperFromUserID, $WhisperToUserID, '-');
						
						// 2. Update the whisper count for this new whisper
						$this->UpdateWhisperCount($Comment->DiscussionID, $WhisperFromUserID, $Comment->WhisperUserID, '+');
						
					} else {
						// Otherwise, the counts do not need to be manipulated
					}
			
					// Finally, update the comment
					$s->Clear();
					$s->SetMainTable('Comment', 'm');
					$s->AddFieldNameValue('WhisperUserID', $Comment->WhisperUserID);
					$s->AddFieldNameValue('Body', $Comment->Body);
					$s->AddFieldNameValue('FormatType', $Comment->FormatType);
					$s->AddFieldNameValue('RemoteIp', GetRemoteIp(1));
					$s->AddFieldNameValue('EditUserID', $this->Context->Session->UserID);
					$s->AddFieldNameValue('DateEdited', MysqlDateTime());
					$s->AddWhere('m', 'CommentID', '', $Comment->CommentID, '=');
					$this->Context->Database->Update($s, $this->Name, 'SaveComment', 'An error occurred while attempting to update the discussion comment.');
				}
			}
		}
		return $this->Context->WarningCollector->Iif($Comment,false);
	}
	
	function SwitchCommentProperty($CommentID, $DiscussionID, $Switch) {
		$DiscussionID = ForceInt($DiscussionID, 0);
		$CommentID = ForceInt($CommentID, 0);
		if ($DiscussionID == 0) $this->Context->WarningCollector->Add($this->Context->GetDefinition('ErrDiscussionID'));
		if ($CommentID == 0) $this->Context->WarningCollector->Add($this->Context->GetDefinition('ErrCommentID'));
		if (!$this->Context->Session->User->Permission('PERMISSION_HIDE_COMMENTS')) $this->Context->WarningCollector->Add($this->Context->GetDefinition('ErrPermissionComments'));
		
		if ($this->Context->WarningCollector->Count() == 0) {
			// Get some information about the comment being manipulated
			$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
			$s->SetMainTable('Comment', 'm');
			$s->AddSelect(array('AuthUserID', 'WhisperUserID'), 'm');
			$s->AddWhere('m', 'CommentID', '', $CommentID, '=');
			// Don't touch comments that are already switched to the selected status
			$s->AddWhere('m', 'Deleted', '', $Switch, '<>');
			$CommentData = $this->Context->Database->Select($s, $this->Name, 'SwitchCommentProperty', 'An error occurred while retrieving information about the comment.');
			$WhisperToUserID = 0;
			$WhisperFromUserID = 0;
			while ($Row = $this->Context->Database->GetRow($CommentData)) {
				$WhisperToUserID = ForceInt($Row['WhisperUserID'], 0);
				$WhisperFromUserID = ForceInt($Row['AuthUserID'], 0);
			}
			$MathOperator = ($Switch == 0?'+':'-');
			if ($WhisperToUserID > 0) {
				// If this was a whisper, update the whisper count tables
				// Update the discussion table (holds the whisper count for admins)
				$this->UpdateWhisperCount($DiscussionID, $WhisperFromUserID, $WhisperToUserID, $MathOperator);
				
			} elseif ($WhisperFromUserID > 0) {
				// If this was not a whisper, update the discussion table comment count
				$this->UpdateCommentCount($DiscussionID, $MathOperator);
			 
			}
			// And finally, mark the comment as deleted
			$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
			$s->SetMainTable('Comment', 'm');
			$s->AddFieldNameValue('Deleted', $Switch);
			if ($Switch == 1) {
				$s->AddFieldNameValue('DeleteUserID', $this->Context->Session->UserID);
				$s->AddFieldNameValue('DateDeleted', MysqlDateTime());
			}
			$s->AddWhere('m', 'CommentID', '', $CommentID, '=');
			$this->Context->Database->Update($s, $this->Name, 'SwitchCommentProperty', 'An error occurred while marking the comment as inactive.');
		}
		return $this->Context->WarningCollector->Iif();
	}
	
	// Handles manipulating the count value for a discussion when adding, hiding, or deleting a comment
	function UpdateCommentCount($DiscussionID, $MathOperator) {
		$Math = '+';
		if ($MathOperator != '+') $Math = '-';
		
		$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
		$s->SetMainTable('Discussion', 'd');
		$s->AddFieldNameValue('CountComments', 'CountComments'.$Math.'1', 0);
		$s->AddWhere('d', 'DiscussionID', '', $DiscussionID, '=');
		$this->Context->Database->Update($s, $this->Name, 'UpdateCommentCount', 'An error occurred while manipulating the comment count for the discussion.');
	}
	
	// Handles manipulating the count values for a discussion when adding, hiding, or removing a whispered comment
	function UpdateWhisperCount($DiscussionID, $WhisperFromUserID, $WhisperToUserID, $MathOperator) {
		$Math = '+';
		if ($MathOperator != '+') $Math = '-';
		
		// 1. Update the whispercount for this discussion
		
		$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
		$s->SetMainTable('Discussion', 't');
		$s->AddFieldNameValue('TotalWhisperCount', 'TotalWhisperCount'.$Math.'1', 0);
		$s->AddWhere('t', 'DiscussionID', '', $DiscussionID, '=');
		$this->Context->Database->Update($s, $this->Name, 'UpdateWhisperCount', "An error occurred while manipulating the discussion's comment count.");
		
		// 2. Update the DiscussionUserWhisperFrom table
		$s->Clear();
		$s->SetMainTable('DiscussionUserWhisperFrom', 'tuwf');
		$s->AddFieldNameValue('CountWhispers', 'CountWhispers'.$Math.'1', 0);
		$s->AddWhere('tuwf', 'DiscussionID', '', $DiscussionID, '=');
		$s->AddWhere('tuwf', 'WhisperFromUserID', '', $WhisperFromUserID, '=');
		// If no rows were affected, make sure to insert the data
		if ($this->Context->Database->Update($s, $this->Name, 'UpdateWhisperCount', 'An error occurred while manipulating the whisper count for the user who sent the whisper.') == 0 && $Math == '+') {
			$s->Clear();
			$s->SetMainTable('DiscussionUserWhisperFrom', 'tuwf');
			$s->AddFieldNameValue('CountWhispers', '1');
			$s->AddFieldNameValue('DateLastActive', MysqlDateTime());
			$s->AddFieldNameValue('DiscussionID', $DiscussionID);
			$s->AddFieldNameValue('WhisperFromUserID', $WhisperFromUserID);
			$s->AddFieldNameValue('LastUserID', $WhisperFromUserID);
			$this->Context->Database->Insert($s, $this->Name, 'UpdateWhisperCount', "An error occurred while updating the discussion's comment summary.");
		}
		
		// 3. Update the DiscussionUserWhisperTo table
		// But only if the user was not whispering to him/herself (because this value is not incremented if that is the case)
		if ($WhisperToUserID != $WhisperFromUserID) {
			$s->Clear();
			$s->SetMainTable('DiscussionUserWhisperTo', 'tuwt');
			$s->AddFieldNameValue('CountWhispers', 'CountWhispers'.$Math.'1', 0);
			$s->AddWhere('tuwt', 'DiscussionID', '', $DiscussionID, '=');
			$s->AddWhere('tuwt', 'WhisperToUserID', '', $WhisperToUserID, '=');
			// If no rows were affected, make sure to insert the data
			if ($this->Context->Database->Update($s, $this->Name, 'UpdateWhisperCount', 'An error occurred while manipulating the whisper count for the user who received the whisper.') == 0 && $Math == '+') {
				$s->Clear();
				$s->SetMainTable('DiscussionUserWhisperTo', 'tuwt');
				$s->AddFieldNameValue('CountWhispers', '1');
				$s->AddFieldNameValue('DateLastActive', MysqlDateTime());
				$s->AddFieldNameValue('DiscussionID', $DiscussionID);
				$s->AddFieldNameValue('WhisperToUserID', $WhisperToUserID);
				$s->AddFieldNameValue('LastUserID', $WhisperFromUserID);
				$this->Context->Database->Insert($s, $this->Name, 'UpdateWhisperCount', "An error occurred while updating the discussion's comment summary.");
			}
		}
	}
	
	// Validates and formats properties ensuring they're safe for database input
	// Returns: boolean value indicating success
	function ValidateComment(&$Comment, $DiscussionIDRequired = '1') {
		$DiscussionIDRequired = ForceBool($DiscussionIDRequired, 0);
		if ($DiscussionIDRequired) {
			$Comment->DiscussionID = ForceInt($Comment->DiscussionID, 0);
			if ($Comment->DiscussionID == 0) $this->Context->WarningCollector->Add($this->Context->GetDefinition('ErrDiscussionID'));
		}
		
		// First update the values so they are safe for db input
		$Body = FormatStringForDatabaseInput($Comment->Body);

		// Instantiate a new validator for each field
		Validate($this->Context->GetDefinition('CommentsLower'), 1, $Body, $this->Context->Configuration['MAX_COMMENT_LENGTH'], '', $this->Context);
		
		return $this->Context->WarningCollector->Iif();
	}
	
	function ValidateWhisperUsername(&$Comment) {
		if ($Comment->WhisperUsername != '') {
			$Name = FormatStringForDatabaseInput($Comment->WhisperUsername);
			$s = $this->Context->ObjectFactory->NewContextObject($this->Context, 'SqlBuilder');
			$s->SetMainTable('User', 'u');
			$s->AddSelect('UserID', 'u');
			$s->AddWhere('u', 'Name', '', $Name, '=');
			$Result = $this->Context->Database->Select($s, $this->Name, 'ValidateWhisperUsername', 'An error occurred while attempting to validate the username entered as the whisper recipient.');
			while ($Row = $this->Context->Database->GetRow($Result)) {
				$Comment->WhisperUserID = ForceInt($Row['UserID'], 0);
			}
			if ($Comment->WhisperUserID == 0) $this->Context->WarningCollector->Add($this->Context->GetDefinition('ErrWhisperInvalid'));
		}
		return $this->Context->WarningCollector->Iif();
	}	
}
?>