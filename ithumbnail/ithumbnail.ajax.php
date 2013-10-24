<?php
/*
Plugin for The Secretary CMS - by microbians.com
Just add all files to a folder inside plugins

Copyright (c) 2010 microbians.com

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
	error_reporting(0);
	
	define( "BASE_PATH", str_replace( "assistants/", "", $_POST['asstPath'] ) );
	
	require_once "../../assistants/helpers/file_uploader.inc.php";
	require_once "../../assistants/helpers/ThumbLib.inc.php";
	require_once "../../assistants/utf8.php";
	require_once "../../assistants/config.inc.php";
	require_once "../../assistants/clerk.php";
	require_once "../../assistants/guard.php";
	require_once "../../assistants/office.php";
	require_once "../../assistants/manager.php";

	$clerk= new Clerk( true );
	$guard=	new Guard();
	$manager= new Manager();
	
	loadPlugins();
	
	if ( !$guard->validate_user_extern( $clerk, $_COOKIE["secretary_username"], $_COOKIE["secretary_password"] ) )
	{
		die( "Back off!");
	}
	
	$_POST= $clerk->clean( $_POST );
	
	$paths= $clerk->getSetting( "projects_path" );
	$paths= array( 	'path' 	=>	$paths['data1'],
					'url'	=>	$paths['data2']
	);

	$projects_filethumbnail			=	$clerk->getSetting( "projects_filethumbnail" );
	$projects_filethumbnail_width	= 	$projects_filethumbnail['data1'];
	$projects_filethumbnail_height	= 	$projects_filethumbnail['data2'];

	$projects_thumbnail			=	$clerk->getSetting( "projects_thumbnail" );
	$projects_thumbnail_width	= 	$projects_thumbnail['data1'];
	$projects_thumbnail_height	= 	$projects_thumbnail['data2'];

	$actions= explode( ",", $_POST['action']);
	foreach ( $actions as $func )
	{
		$isAction=false;
		if ($func=='getThumbnail') getThumbnailAjax();
		if ($func=='setThumbnailCenter') setThumbnailCenter();
	}
	
	function mysql_fetch_alias_array($result) {
		// Avoid the problem of duplicated field names in joint queries
		// From Post by Mehdi Haresi in PHP.net
	    if (!($row = mysql_fetch_array($result))) {
	        return null;
	    }
	
	    $assoc = Array();
	    $rowCount = mysql_num_fields($result);
	    
	    for ($idx = 0; $idx < $rowCount; $idx++) {
	        $table = mysql_field_table($result, $idx);
	        $field = mysql_field_name($result, $idx);
	        $assoc["$table.$field"] = $row[$idx];
	    }
	    return $assoc;
	}	
	
	function getThumbnailAjax(){
		global $clerk, $paths;
		global $projects_filethumbnail_width, $projects_filethumbnail_height, $projects_thumbnail_width, $projects_thumbnail_height;

		$file_id=$_POST['file_id'];
		$id=$_POST['id'];

		if ($file_id!="false") {
				$info= mysql_fetch_alias_array( $clerk->query_select( 'project_files, projects', '', 'WHERE project_files.id="'.$file_id.'" AND projects.id="'.$id.'"' ) );
		} else {
				$info= mysql_fetch_alias_array( $clerk->query_select( 'projects', '', 'WHERE projects.id="'.$id.'"' ) );
		}
		
		$info['path']	= $paths['path'];
		$info['url']	= $paths['url'];
		
		if ($file_id!="false") {
			$info['thumbnail_width']     = $projects_filethumbnail_width;
			$info['thumbnail_height']    = $projects_filethumbnail_height;
		} else {
			$info['thumbnail_width']     = $projects_thumbnail_width;
			$info['thumbnail_height']    = $projects_thumbnail_height;
		}
		
		if ($file_id!="false") {
			$info['image_height'] = $info['project_files.height'];
			$info['image_width']  = $info['project_files.width'];
			$info['image_file']   = $info['project_files.file'];
		} else {
			list( $info['image_width'], $info['image_height'] )= getimagesize( $info['path'].$info['projects.slug'].'/'.$info['projects.thumbnail'] );
			$info['image_file']   = $info['projects.thumbnail'];
		}
		
		echo json_encode( $info );
	}

	function findSharp($orig, $final) // function from Ryan Rud (http://adryrun.com)
	{
		$final	= $final * (750.0 / $orig);
		$a		= 52;
		$b		= -0.27810650887573124;
		$c		= .00047337278106508946;
		
		$result = $a + $b * $final + $c * $final * $final;
		
		return max(round($result), 0);
	} // findSharp()
	
	function setThumbnailCenter(){
		global $clerk, $paths;
		global $projects_filethumbnail_width, $projects_filethumbnail_height, $projects_thumbnail_width, $projects_thumbnail_height;

		$isProject 	= $_POST['isproject'];

		$file_id	= $_POST['file_id'];
		$id			= $_POST['id'];

		$tx			= $_POST['tx'];
		$ty			= $_POST['ty'];
		$dx			= $_POST['dx'];
		$dy			= $_POST['dy'];

		if ($isProject=="true") {
			$info= mysql_fetch_alias_array( $clerk->query_select( 'projects', '', 'WHERE projects.id="'.$id.'"' ) );
		} else {
			$info= mysql_fetch_alias_array( $clerk->query_select( 'project_files, projects', '', 'WHERE project_files.id="'.$file_id.'" AND projects.id="'.$id.'"' ) );
		}

		$info['path']	= $paths['path'];
		$info['url']	= $paths['url'];
		
		if ($isProject=="true") {
			$thumbfile = $info['path'].$info['projects.slug'].'/'.$info['projects.thumbnail'];
			$imagefile = $info['path'].$info['projects.slug'].'/'.$info['projects.thumbnail'];
		} else {
			$thumbfile = $info['path'].$info['projects.slug'].'/'.$info['project_files.thumbnail'];
			$imagefile = $info['path'].$info['projects.slug'].'/'.$info['project_files.file'];
		}
		

		if ($isProject=="true") {
			if ( !$clerk->query_edit( 'projects', 'thumbnailcenter="'.$tx.','.$ty.','.$dx.','.$dy.'"', 'WHERE projects.id="'.$id.'"' ) ) {
				echo json_encode("Error editing thumbnailcenter on projects.id=".$id);
			}
			$file_extension =	substr( $info['projects.thumbnail'], strrpos( $info['projects.thumbnail'] , '.' ) );
			$file_name		=   str_replace( $file_extension, "", $info['projects.thumbnail'] );
		} else {
			if ( !$clerk->query_edit( 'project_files', 'thumbnailcenter="'.$tx.','.$ty.','.$dx.','.$dy.'"', 'WHERE project_files.id="'.$file_id.'"' ) ) {
				echo json_encode("Error editing thumbnailcenter on project_files.id=".$file_id);
			}
			$file_extension =	substr( $info['project_files.file'], strrpos( $info['project_files.file'] , '.' ) );
			$file_name		=   str_replace( $file_extension, "", $info['project_files.file'] );
		}
		
		$cache_dir= $clerk->getSetting( "cache_path", 1 );
		$cache_file_name = str_replace( $file_extension, "", basename( $imagefile ) ) . "." . $width . "x" . $height . "_" . $adaptive . ".jpg";

		if ($file_name!="" && $file_extension!="") {
			$mask= $cache_dir . "/" . $file_name . "*" . $file_extension;
			// DELETE ALL CACHE FILES START WITH THE FILE NAME
			array_map( "unlink", glob( $mask ) );
		}
		
		//echo json_encode($info);
	}
?>