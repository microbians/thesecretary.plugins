<?php
/*

Description Plugin for The Secretary CMS - by microbians.com

Just add this file to a folder inside plugins

Copyright (c) 2010 microbians.com
Based on pangolingo Project URLs Plugin

 Permission is hereby granted, free of charge, to any person
 obtaining a copy of this software and associated documentation
 files (the "Software"), to deal in the Software without
 restriction, including without limitation the rights to use,
 copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the
 Software is furnished to do so, subject to the following
 conditions:

 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 OTHER DEALINGS IN THE SOFTWARE.
*/

////////////////////////////////////////////////////////////////////////
// INSTALL															  //
////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////////////////////
// ENHACE CMS //  DINAMIC THUMBNAILS ON PROJECT LIST & PROJECT EDIT   //
////////////////////////////////////////////////////////////////////////

// Attatch functions to cms
hook( "projectFormAfterDetails", "description_form" );
hook( "form_process", "description_process" );

function description_form(){
	global $manager;
	$clerk = $manager->clerk;

	$id = $_GET['id'];
	$description = description_get($id);
	$manager->form->add_textarea( 'description', 'Project description', '', '', $description );
}

function description_process(){
	global $manager;
	$clerk = $manager->clerk;

	if( isset( $_POST['description'] ) && isset($_POST['id']) ) {
		$description = $clerk->clean_string($_POST['description']);
		$id = $clerk->clean_string($_POST['id']);
		$clerk->query_edit('projects', "description = '$description'","WHERE id= '$id'");
	}
}

function description_get($id = ""){
	global $clerk;
	global $project;

	if($id=="" || !is_numeric($id)){
		if(isset($project['id'])){
			$id = $project['id']; // try to get id if we're on a project page
		} else {
			return '';
		}
	}		
	
	// get ID from database
	$row = $clerk->query_fetchArray( $clerk->query_select( "projects", "description", "WHERE id= '$id' LIMIT 1") );
	return $row['description'];
}


?>