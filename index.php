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
* Description: Display and manipulate discussions
*/

include("appg/settings.php");
include("appg/init_vanilla.php");

// 1. DEFINE VARIABLES AND PROPERTIES SPECIFIC TO THIS PAGE

	// Ensure the user is allowed to view this page
	$Context->Session->Check($Context);
	
	// Define properties of the page controls that are specific to this page
   $Head->BodyId = 'DiscussionsPage';
	$Menu->CurrentTab = 'discussions';
	$Panel->CssClass = 'DiscussionPanel';
   $Panel->BodyCssClass = 'Discussions';

// 2. BUILD PAGE CONTROLS
	$DiscussionGrid = $Context->ObjectFactory->CreateControl($Context, 'DiscussionGrid');

// 3. ADD CONTROLS TO THE PAGE

	$Page->AddRenderControl($Head, $Configuration['CONTROL_POSITION_HEAD']);
	$Page->AddRenderControl($Menu, $Configuration['CONTROL_POSITION_MENU']);
	$Page->AddRenderControl($Panel, $Configuration['CONTROL_POSITION_PANEL']);
	$Page->AddRenderControl($DiscussionGrid, $Configuration['CONTROL_POSITION_BODY_ITEM']);
	$Page->AddRenderControl($Foot, $Configuration['CONTROL_POSITION_FOOT']);
	$Page->AddRenderControl($PageEnd, $Configuration['CONTROL_POSITION_PAGE_END']);

// 4. FIRE PAGE EVENTS

	$Page->FireEvents();

?>