/*
* Copyright 2003 - 2005 Mark O'Sullivan
* This file is part of Vanilla.
* Vanilla is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
* Vanilla is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
* You should have received a copy of the GNU General Public License along with Vanilla; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
* The latest source code for Vanilla is available at www.lussumo.com
* Contact Mark O'Sullivan at mark [at] lussumo [dot] com
* 
* Description: Utility functions specific to Vanilla
*/
  
// Add a new custom name/value pair input to the account form
function AddLabelValuePair() {
	var Counter = document.getElementById('LabelValuePairCount');
	var Container = document.getElementById('CustomInfo');
	if (Counter && Container) {
		Counter.value++;

		var Label = document.createElement("li");
		var LabelInput = document.createElement("input");
		LabelInput.type = "text";
		LabelInput.name = "Label"+Counter.value;
		LabelInput.maxLength = "20";
		LabelInput.className = "LVLabelInput";
		
		// Create the value container		
		var Value = document.createElement("li");
		var ValueInput = document.createElement("input");
		ValueInput.type = "text";
		ValueInput.name = "Value"+Counter.value;
		ValueInput.maxLength = "200";
		ValueInput.className = "LVValueInput";
		
		// Add the items to the page
		Label.appendChild(LabelInput);
		Value.appendChild(ValueInput);
		Container.appendChild(Label);
		Container.appendChild(Value);
	}
}

function DiscussionSwitch(SwitchType, DiscussionID, SwitchValue) {
	ChangeLoaderText("Processing...");
	SwitchLoader(1);
   var Url = "./ajax/switch.php";
   var Parameters = "Type="+SwitchType+"&DiscussionID="+DiscussionID+"&Switch="+SwitchValue;
   var dm = new DataManager();
	dm.RequestCompleteEvent = HandleDiscussionSwitch;
	dm.RequestFailedEvent = HandleFailure;
	dm.LoadData(Url+"?"+Parameters);
}

function HandleDiscussionSwitch(Request) {
	ChangeLoaderText("Refreshing...");
	setTimeout("document.location.reload();",600);
}

// Delete or Undelete a comment
function ManageComment(Switch, DiscussionID, CommentID, ShowText, HideText) {
	var ConfirmText = (Switch==1?HideText:ShowText);
	if (confirm(ConfirmText)) {
		ChangeLoaderText("Processing...");
		SwitchLoader(1);
		var Url = "./ajax/switch.php";
		var Parameters = "Type=Comment&Switch="+Switch+"&DiscussionID="+DiscussionID+"&CommentID="+CommentID;
		var dm = new DataManager();
		dm.RequestCompleteEvent = ProcessComment;
		dm.RequestFailedEvent = HandleFailure;
		dm.LoadData(Url+"?"+Parameters);
	}
}

function ProcessComment(Request) {
	ChangeLoaderText("Refreshing...");
	setTimeout("document.location.reload();",600);
}

function DoNothing() {
}

// Apply or remove a bookmark
function SetBookmark(CurrentSwitchVal, Identifier, BookmarkText, UnbookmarkText) {
	SetSwitch('SetBookmark', CurrentSwitchVal, 'Bookmark', BookmarkText, UnbookmarkText, Identifier, "&DiscussionID="+Identifier);
	var Sender = document.getElementById('SetBookmark');
	var BookmarkTitle = document.getElementById("BookmarkTitle");
	var BookmarkList = document.getElementById("BookmarkList");
	var Bookmark = document.getElementById("Bookmark_"+Identifier);
	var BookmarkForm = document.getElementById("frmBookmark");
	var OtherBookmarksExist = BookmarkForm.OtherBookmarksExist;
	if (Sender && BookmarkList) {
		if (Sender.name == 0) {
			// removed bookmark
			if (Bookmark) {
				Bookmark.style.display = "none";
				if (OtherBookmarksExist) {
					var Display = OtherBookmarksExist.value == 0 ? "none" : "block" ;
					if (BookmarkTitle) BookmarkTitle.style.display = Display;
					if (BookmarkList) BookmarkList.style.display = Display;
				}
			}
		} else {
			if (Bookmark) {
				Bookmark.style.display = "block";
				if (BookmarkTitle) BookmarkTitle.style.display = "block";
				if (BookmarkList) BookmarkList.style.display = "block";
			}
		}
	}
}

// Generic Switch
function SetSwitch(SenderName, CurrentSwitchVal, SwitchType, CommentOn, CommentOff, Identifier, Attributes) {
	var Sender = document.getElementById(SenderName);
	if (Sender) {
      ChangeLoaderText("Processing...");
		SwitchLoader(1);
		var Switch = Sender.name == '' ? CurrentSwitchVal : Sender.name;
		var FlipSwitch = Switch == 1 ? 0 : 1;
		Sender.innerHTML = (FlipSwitch==0?CommentOn:CommentOff);
		Sender.name = FlipSwitch;
		
		var Url = "./ajax/switch.php";
		var Parameters = "Type="+SwitchType+"&Switch="+FlipSwitch+Attributes;
		
		var dm = new DataManager();
		dm.RequestCompleteEvent = HandleSwitch;
		dm.RequestFailedEvent = HandleFailure;
		dm.LoadData(Url+"?"+Parameters);
	}
}

function ShowAdvancedSearch() {
	var SearchSimple = document.getElementById("SearchSimple");
	var SearchDiscussions = document.getElementById("SearchDiscussions");
	var SearchComments = document.getElementById("SearchComments");
	var SearchUsers = document.getElementById("SearchUsers");
	
	if (SearchSimple && SearchDiscussions && SearchComments && SearchUsers ) {
		SearchSimple.style.display = "none";
		SearchDiscussions.style.display = "block";
		SearchComments.style.display = "block";
		SearchUsers.style.display = "block";
	}
}

function ToggleCategoryBlock(CategoryID, Block) {
	ChangeLoaderText("Processing...");
	SwitchLoader(1);
	var Url = "./ajax/blockcategory.php";
	var Parameters = "BlockCategoryID="+CategoryID+"&Block="+Block;
   var dm = new DataManager();
	dm.RequestCompleteEvent = RefreshPage;
	dm.RequestFailedEvent = HandleFailure;
	dm.LoadData(Url+"?"+Parameters);
}

function ToggleCommentBox(SmallText, BigText) {
   SwitchElementClass('CommentBox', 'CommentBoxController', 'SmallCommentBox', 'LargeCommentBox', BigText, SmallText);
	var SwitchVal = 0;
	var CommentBox = document.getElementById("CommentBox");
	if (CommentBox) {
		if (CommentBox.className == "LargeCommentBox") SwitchVal = 1;
		
		var Url = "./ajax/switch.php";
		var Parameters = "Type=ShowLargeCommentBox&Switch="+SwitchVal;
		var dm = new DataManager();
		dm.RequestCompleteEvent = DoNothing;
		dm.RequestFailedEvent = HandleFailure;
		dm.LoadData(Url+"?"+Parameters);		
	}
}

function WhisperBack(DiscussionID, WhisperTo) {
	var frm = document.getElementById("frmPostComment");
	if (!frm) {
		document.location = "post.php?PageAction=Reply&DiscussionID="+DiscussionID+"&WhisperUsername="+WhisperTo;
	} else {
		frm.WhisperUsername.value = WhisperTo;
		frm.Body.focus();
	}
}