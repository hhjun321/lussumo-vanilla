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
* Description: The SearchForm control is used to render a search form and search results.
*/

class SearchForm extends PostBackControl {
   var $FormName;				// The name of this form
   var $Search;            // A search object (contains all parameters related to the search: keywords, etc)
   var $SearchID;          // The id of the search to load
   var $Data;              // Search result data
   var $DataCount;			// The number of records returned by a search
   
	// Search form controls
   var $CategorySelect;
	var $OrderSelect;
	var $TypeRadio;
	var $RoleSelect;   
	
	function SearchForm(&$Context, $FormName = '') {
		$this->Name = 'SearchForm';
		$this->ValidActions = array('Search', 'SaveSearch');
		$this->FormName = $FormName;
      $this->SearchID = ForceIncomingInt('SearchID', 0);
      $this->DataCount = 0;
		$this->Constructor($Context);
		if ($this->PostBackAction == '') $this->IsPostBack = 1;
		
		$CurrentPage = ForceIncomingInt('page', 1);
		
      // Load a search object
      $this->Search = $this->Context->ObjectFactory->NewObject($this->Context, 'Search');
      $this->Search->GetPropertiesFromForm();
		
		$this->CallDelegate('PostDefineSearchFromForm');

      // Load selectors
      // Category Filter
      $cm = $this->Context->ObjectFactory->NewContextObject($this->Context, 'CategoryManager');
      $CategorySet = $cm->GetCategories();
      $this->CategorySelect = $this->Context->ObjectFactory->NewObject($this->Context, 'Select');
      $this->CategorySelect->Name = 'Categories';
      $this->CategorySelect->CssClass = 'SearchSelect';
      $this->CategorySelect->AddOption('', $this->Context->GetDefinition('AllCategories'));
      $this->CategorySelect->AddOptionsFromDataSet($this->Context->Database, $CategorySet, 'Name', 'Name');
      $this->CategorySelect->SelectedID = $this->Search->Categories;

      // UserOrder
      $this->OrderSelect = $this->Context->ObjectFactory->NewObject($this->Context, 'Select');
      $this->OrderSelect->Name = 'UserOrder';
      $this->OrderSelect->CssClass = 'SearchSelect';
      $this->OrderSelect->Attributes = " id=\"UserOrder\"";
      $this->OrderSelect->AddOption('', $this->Context->GetDefinition('Username'));
      $this->OrderSelect->AddOption('Date', $this->Context->GetDefinition('DateLastActive'));
      $this->OrderSelect->SelectedID = $this->Search->UserOrder;

      // Type
      $this->TypeRadio = $this->Context->ObjectFactory->NewObject($this->Context, 'Radio');
      $this->TypeRadio->Name = 'Type';
      $this->TypeRadio->CssClass = 'SearchType';
      $this->TypeRadio->AddOption('Topics', $this->Context->GetDefinition('Topics'));
      $this->TypeRadio->AddOption('Comments', $this->Context->GetDefinition('Comments'));
      $this->TypeRadio->AddOption('Users', $this->Context->GetDefinition('Users'));
      $this->TypeRadio->SelectedID = $this->Search->Type;
      
      $rm = $this->Context->ObjectFactory->NewContextObject($this->Context, 'RoleManager');
      $RoleSet = $rm->GetRoles();
      $this->RoleSelect = $this->Context->ObjectFactory->NewObject($this->Context, 'Select');
      $this->RoleSelect->Name = 'Roles';
      $this->RoleSelect->CssClass = 'SearchSelect';
      $this->RoleSelect->Attributes = " id=\"RoleFilter\"";
      $this->RoleSelect->AddOption('', $this->Context->GetDefinition('AllRoles'));
		if ($this->Context->Session->User->Permission('PERMISSION_APPROVE_APPLICANTS')) $this->RoleSelect->AddOption('Applicant', $this->Context->GetDefinition('Applicant'));
      $this->RoleSelect->AddOptionsFromDataSet($this->Context->Database, $RoleSet, 'Name', 'Name');
      $this->RoleSelect->SelectedID = $this->Search->Roles;
		
		$this->CallDelegate('PreSearchQuery');

      // Handle Searching
      if ($this->PostBackAction == 'Search') {
         $this->Data = false;
         // Handle searches
         if ($this->Search->Type == 'Users') {
            $um = $this->Context->ObjectFactory->NewContextObject($this->Context, 'UserManager');
            $this->Data = $um->GetUserSearch($this->Search, $this->Context->Configuration['SEARCH_RESULTS_PER_PAGE'], $CurrentPage);
            $this->Search->FormatPropertiesForDisplay();      
            
         } else if ($this->Search->Type == 'Topics') {
            $tm = $this->Context->ObjectFactory->NewContextObject($this->Context, 'DiscussionManager');
            $this->Data = $tm->GetDiscussionSearch($this->Context->Configuration['SEARCH_RESULTS_PER_PAGE'], $CurrentPage, $this->Search);
            $this->Search->FormatPropertiesForDisplay();
            
         } else if ($this->Search->Type == 'Comments') {
            $mm = $this->Context->ObjectFactory->NewContextObject($this->Context, 'CommentManager');
            $this->Data = $mm->GetCommentSearch($this->Context->Configuration['SEARCH_RESULTS_PER_PAGE'], $CurrentPage, $this->Search);
            $this->Search->FormatPropertiesForDisplay();
         }
         
         if ($this->Data) $this->DataCount = $this->Context->Database->RowCount($this->Data);
			
			$pl = $this->Context->ObjectFactory->NewContextObject($this->Context, 'PageList');
			$pl->NextText = $this->Context->GetDefinition('Next');
			$pl->PreviousText = $this->Context->GetDefinition('Previous');
			$pl->Totalled = 0;
			$pl->CssClass = 'PageList';
			$pl->TotalRecords = $this->DataCount;
			$pl->PageParameterName = 'page';
			$pl->CurrentPage = $CurrentPage;
			$pl->RecordsPerPage = $this->Context->Configuration['SEARCH_RESULTS_PER_PAGE'];
			$pl->PagesToDisplay = 10;
			$this->PageList = $pl->GetLiteralList();
			if ($this->Search->Query != '') {
				$Query = $this->Search->Query;
			} else {
				$Query = $this->Context->GetDefinition('nothing');
			}
			if ($this->DataCount == 0) {
				$this->PageDetails = $this->Context->GetDefinition('NoSearchResultsMessage');
			} else {
				$this->PageDetails = str_replace(array('//1', '//2', '//3'), array($pl->FirstRecord, $pl->LastRecord, '<strong>'.$Query.'</strong>'), $this->Context->GetDefinition('SearchResultsMessage'));
			}
      }
		$this->CallDelegate('PostLoadData');
	}
	
	function Render_NoPostBack() {
		$this->CallDelegate('PreSearchFormRender');
		include(ThemeFilePath($this->Context->Configuration, 'search_form.php'));
		
		if ($this->PostBackAction == 'Search') {
			
			$this->CallDelegate('PreSearchResultsRender');
			
			include(ThemeFilePath($this->Context->Configuration, 'search_results_top.php'));
			
			if ($this->DataCount > 0) {
				$Switch = 0;
				$FirstRow = 1;
				$Counter = 0;
				if ($this->Search->Type == 'Topics') {
					$Discussion = $this->Context->ObjectFactory->NewObject($this->Context, 'Discussion');
					$CurrentUserJumpToLastCommentPref = $this->Context->Session->User->Preference('JumpToLastReadComment');
					$DiscussionList = '';
					$ThemeFilePath = ThemeFilePath($this->Context->Configuration, 'discussion.php');
					while ($Row = $this->Context->Database->GetRow($this->Data)) {
						$Discussion->Clear();
						$Discussion->GetPropertiesFromDataSet($Row, $this->Context->Configuration);
						$Discussion->FormatPropertiesForDisplay();
						$Discussion->ForceNameSpaces($this->Context->Configuration);
						if ($Counter < $this->Context->Configuration['SEARCH_RESULTS_PER_PAGE']) {
							include($ThemeFilePath);
						}
						$FirstRow = 0;
						$Counter++;
					}
					echo($DiscussionList);
				} elseif ($this->Search->Type == 'Comments') {
					$Comment = $this->Context->ObjectFactory->NewContextObject($this->Context, 'Comment');
					$HighlightWords = ParseQueryForHighlighting($this->Context, $this->Search->Query);
					$CommentList = '';
					$ThemeFilePath = ThemeFilePath($this->Context->Configuration, 'search_results_comments.php');
					while ($Row = $this->Context->Database->GetRow($this->Data)) {
						$Comment->Clear();
						$Comment->GetPropertiesFromDataSet($Row, $this->Context->Session->UserID);
						$Comment->FormatPropertiesForSafeDisplay();
						if ($Counter < $this->Context->Configuration['SEARCH_RESULTS_PER_PAGE']) {
							include($ThemeFilePath);
						}
						$FirstRow = 0;
						$Counter++;
					}
					echo($CommentList);
				} else {
					$u = $this->Context->ObjectFactory->NewContextObject($this->Context, 'User');
					$UserList = '';
					$ThemeFilePath = ThemeFilePath($this->Context->Configuration, 'search_results_users.php');
					while ($Row = $this->Context->Database->GetRow($this->Data)) {
						$Switch = ($Switch == 1?0:1);
						$u->Clear();
						$u->GetPropertiesFromDataSet($Row);
						$u->FormatPropertiesForDisplay();
						
						if ($Counter < $this->Context->Configuration['SEARCH_RESULTS_PER_PAGE']) {
							include($ThemeFilePath);
						}
						$FirstRow = 0;
						$Counter++;
					}
					echo($UserList);
				}
			}
			if ($this->DataCount > 0) {
				include(ThemeFilePath($this->Context->Configuration, 'search_results_bottom.php'));
			}
		}
	}

	function Render_ValidPostBack() {
	}
}
?>