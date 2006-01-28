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
* Description: The DiscussionGrid control is used to display a paging list of discussions in Vanilla.
*/

// Displays a discussion grid
class DiscussionGrid extends Control {
	var $PageJump;
	var $CurrentPage;
	var $DiscussionData;
	var $DiscussionDataCount;
	
	function DiscussionGrid(&$Context) {
		$this->Name = "DiscussionGrid";
		$this->Control($Context);


		$DiscussionManager = $this->Context->ObjectFactory->NewContextObject($this->Context, "DiscussionManager");
		$this->CurrentPage = ForceIncomingInt("page", 1);
		$this->DiscussionData = false;
		$this->DiscussionDataCount = false;
		
		
		// Get the category if filtered
		$Category = false;
		$CategoryID = ForceIncomingInt("CategoryID", 0);
		if ($CategoryID > 0) {
			$cm = $this->Context->ObjectFactory->NewContextObject($this->Context, "CategoryManager");
			$Category = $cm->GetCategoryById($CategoryID);
		}
		$this->PageJump = '<a class="PageJump AllDiscussions" href="'.GetUrl($this->Context->Configuration, 'index.php').'">'.$this->Context->GetDefinition('ShowAll').'</a>';

		$this->DelegateParameters['DiscussionManager'] = &$DiscussionManager;
		$this->CallDelegate('PreDataLoad');
		
		if (!$this->DiscussionData) {
			$this->DiscussionData = $DiscussionManager->GetDiscussionList($this->Context->Configuration['DISCUSSIONS_PER_PAGE'], $this->CurrentPage, $CategoryID);
			$this->DiscussionDataCount = $DiscussionManager->GetDiscussionCount($CategoryID);		
			if ($Category) {
				if ($this->Context->PageTitle == '') $this->Context->PageTitle = $Category->Name;
			} else {
				if ($this->Context->PageTitle == '') $this->PageJump = '';
				if ($this->Context->Session->User->BlocksCategories) {
					if ($this->Context->PageTitle == '') $this->Context->PageTitle = $this->Context->GetDefinition('WatchedDiscussions');
				} else {
					if ($this->Context->PageTitle == '') $this->Context->PageTitle = $this->Context->GetDefinition('AllDiscussions');
				}
			}
		}
		
		$this->CallDelegate('Constructor');
	}
	
	function Render() {
		$this->CallDelegate('PreRender');
		// Set up the pagelist
      $CategoryID = ForceIncomingInt('CategoryID', 0);
		if ($CategoryID == 0) $CategoryID = '';
		$pl = $this->Context->ObjectFactory->NewContextObject($this->Context, 'PageList', 'CategoryID', $CategoryID);
		$pl->NextText = $this->Context->GetDefinition('Next');
		$pl->PreviousText = $this->Context->GetDefinition('Previous');
		$pl->CssClass = 'PageList';
		$pl->TotalRecords = $this->DiscussionDataCount;
		$pl->CurrentPage = $this->CurrentPage;
		$pl->RecordsPerPage = $this->Context->Configuration['DISCUSSIONS_PER_PAGE'];
		$pl->PagesToDisplay = 10;
		$pl->PageParameterName = 'page';
		$pl->DefineProperties();
		$PageDetails = $pl->GetPageDetails($this->Context);
		$PageList = $pl->GetNumericList();
		
		
		include($this->Context->Configuration['THEME_PATH'].'templates/discussions.php');
		$this->CallDelegate('PostRender');
	}	
}
?>
