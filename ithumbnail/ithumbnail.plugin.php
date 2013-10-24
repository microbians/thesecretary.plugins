<?php
/*

iThumbnail Plugin for The Secretary CMS - by microbians.com

Version 1.9     - CHANGED $file from _URL to _PATH (because some PHP servers not work with URL path)

Version 1.8     - Introduced original thumb functions to save & show (aka PNG support) and gamma correction. (TODO: add to preferences gamma and shaprness correction values to easy change it).

Version 1.7 	- Now you can control the use or not of the custom region of insterest.
				  Can call directly to thumbnail function, so the thumnail is generated before to return the IMG tag.
				  Calling thumbnail function always return a chache file (because if it does not exist is generated before to return de IMG tag.
				  URL or Function can use with & height or with or height independently, this this last option, the image don't use the region of interest, the other component is proportionally.				  

Version 1.6 	- Now you can get the thumbnail by ?id or ?slug and without ?file

Version 1.5 	- Bug correction

Version 1.4 	- Correction of cropping algorithm & adaptive resize bug last column in black.

version 1.3 	- Added support to dynamic thumbnails

version 1.2 	- Plugin changes his name from Intelligent Thumbnail Plugin to iThumbnail
				- Bug fixes

version 1.1 	- Added corrections for size of the final thumbnail size by using adaptiveResize not resize
				- Corrected a bug that don't let edit the thumbail of the last upload if the project is not saved (reloaded)

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

////////////////////////////////////////////////////////////////////////
// INSTALL															  //
////////////////////////////////////////////////////////////////////////

// Launch at start the install of the settings and new table on the DDBB
hook("start", "ithumbnail_install");

// Adds The fields for the setings
function ithumbnail_install() {
	global $manager;
	if (mysql_num_rows($manager->clerk->query('SHOW COLUMNS FROM project_files LIKE "thumbnailcenter"'))==0) {
		$manager->clerk->query('ALTER TABLE project_files ADD thumbnailcenter TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL');
	}

	if (mysql_num_rows($manager->clerk->query('SHOW COLUMNS FROM projects LIKE "thumbnailcenter"'))==0) {
		$manager->clerk->query('ALTER TABLE projects ADD thumbnailcenter TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL');
	}
}

////////////////////////////////////////////////////////////////////////
// ENHACE CMS //  DINAMIC THUMBNAILS ON PROJECT LIST & PROJECT EDIT   //
////////////////////////////////////////////////////////////////////////

// Attatch functions to cms
hook( "javascript", "ithumbnail_enhanceCMSprojects" );

function ithumbnail_enhanceCMSprojects(){
	global $manager;
	
	$clerk = $manager->clerk;

	$linktosite = ( ((bool) $clerk->getSetting( "clean_urls", 1 )) == true ) ? $clerk->getSetting( "site", 2 ) : $clerk->getSetting( "site", 2 );

	echo <<<HTML
		<script type="text/javascript">
			$(document).ready(function(){
				$('img').css('display','none');
				$('li.project').each(function(){
					var id			= $(this).attr('id').substring(("projects_").length-1);
					var pathtoimg	= '$linktosite'+'?dynamic_thumbnail&id='+ id +"&width=180&height=110";
					
					var img=$(this).find('.handle').find('img');
					
					if (img.length>0) {
						img.attr('src', pathtoimg );
					} else {
						$(this).find('.handle').append('<img src="'+ pathtoimg +'" />');
					}
				});
				$('div.thumbnail').each(function(){
					var img=$(this).find('img');
					if (img.length>0) {
						var oldpath		= img.attr('src');
						var newpath		= oldpath.replace('.systhumb','');
						var pathtoimg	= '$linktosite'+'?dynamic_thumbnail&file='+ newpath +"&width=100&height=100";
						
						img.attr('src', pathtoimg );
					}
				});
				$('img').css('display','inherit');
			});
		</script>
HTML;
	
}

////////////////////////////////////////////////////////////////////////
// MANAGE THUMBNAIL													  //
////////////////////////////////////////////////////////////////////////

// Attatch functions to cms
hook( "project_file_toolbar", "ithumbnail_selectXY" );
hook( "head_tags", "ithumbnail_CSS" );
hook( "javascript", "ithumbnail_JS" );

hook( "projectFormAfterThumbnail", "ithumbnail_selectXYProject" );

function ithumbnail_selectXY($htmldataarray) {
	global $manager;
	
	$op='<a href="#" onclick="thumbnailXYWindow(this);">Edit thumbnail</a>';
	$htmldataarray['html'].='<li>'.$op.'</li>';
	
	return $htmldataarray;
}

function ithumbnail_selectXYProject() {
	global $manager;
	
	$manager->form->add_to_form( <<<HTML
	<script type="text/javascript">
		$(document).ready(function(){
			setInterval(function(){
				if ($('#theThumb').html()=="") {
					$('#thumbprojedit').hide();
				} else $('#thumbprojedit').show();
			},100);
		});
	</script>
HTML
);
	$manager->form->add_to_form( '<div id="thumbprojedit" class="inlineToolBar"><br/><a href="#" onclick="thumbnailXYWindow(this, true);">Edit thumbnail</a></div>' );

}

function ithumbnail_JS() {
	global $manager;
	echo $manager->office->jsfile( BASE_URL . "system/plugins/ithumbnail/js/jquery.Jcrop.js" );
	echo $manager->office->jsfile( BASE_URL . "system/plugins/ithumbnail/js/jquery.json.js" );
	echo $manager->office->jsfile( BASE_URL . "system/plugins/ithumbnail/ithumbnail.js" );
	
}

function ithumbnail_CSS() {
	global $manager; 
	echo $manager->office->style( BASE_URL . "system/plugins/ithumbnail/css/jquery.Jcrop.css" );
	echo $manager->office->style( BASE_URL . "system/plugins/ithumbnail/ithumbnail.css" );
}

////////////////////////////////////////////////////////////////////////
// iTHUMBNAIL FUNCTIONS												  //
////////////////////////////////////////////////////////////////////////

function ithumbnail_rehook($name, $oldfunction, $function, $params= "", $order= -1) {
	global $anchors;
	foreach ($anchors[$name] as $key=>$anch){
		if ($anch[0]==$oldfunction) {
			$anchors[$name][$key][0]=$function;
			if ($params!="") $anchors[$name][$key][1]=$params;
			if ($order!=-1) $anchors[$name][$key][2]=$$order;
		}
	}
}

hook( "site_init", "ithumbnail" );
function ithumbnail($idslug, $file, $width, $height, $adaptive=1, $customcenter=1, $returnHow= "full") {
	global $clerk, $anchors;

	if ( empty($idslug) && empty($file) && !isset($_GET['dynamic_thumbnail']) )	{
		return;
	}
	
	$isCallFromURL="";

	if ( isset($_GET['dynamic_thumbnail']) ) {

		$file = $_GET['file'];
		if ( isset($_GET['id']) ) 	$idslug=$_GET['id'];
		else 						$idslug="";

		if ( empty($idslug) && empty($file) ) return;

		$width			= $_GET['width'];
		$height			= $_GET['height'];
		$adaptive 		= ( $_GET['adaptive']=="" || isset($_GET['adaptive'])==false  ) ? 1 : $_GET['adaptive'];
		$customcenter	= ( $_GET['customcenter']=="" || isset($_GET['customcenter'])==false  ) ? 1 : $_GET['customcenter'];

		ithumbnail_rehook('site_begin', 'makeDynamicThumbnail', 'ithumbnail_make');
		
		$isCallFromURL = 1;

	} else {
	
		if ( empty($idslug) && empty($file) ) die("ERROR: No id or file defined to make the thumbnail");

		$isCallFromURL = 0;
	
	}
	
	if ($width=="" && $height=="") {
		die("ERROR: No height or width");
	}

	if ( empty($file) ) {
		if ( !empty($idslug) ) {
			// Only one row (because one id unique)
			$projects = mysql_fetch_array( $clerk->query_select( 'projects', '', 'WHERE slug="'.$idslug.'"' ) );

			// Not found try by ID not slug
			if ($projects[0] == false) { 
				$projects = mysql_fetch_array( $clerk->query_select( 'projects', '', 'WHERE id="'.$idslug.'"') );
			}
			
			if ($projects[0] == false) { 
				return;
			}
			
			if ($projects['thumbnail'] != '') {
				$file = PROJECTS_PATH.$projects['slug'].'/'.$projects['thumbnail'];
			} else {
				$project_files = mysql_fetch_array($clerk->query_select( 'project_files', '', 'WHERE type="image" AND project_id='.$projects['id'].' ORDER BY filegroup,pos ASC LIMIT 1'));
				$file = PROJECTS_PATH.$projects['slug'].'/'.$project_files['file'];
			}
		} else {
			$file = SYSTEM . "plugins/ithumbnail/fakeimage.jpg";
		}
	} else {
		$file=str_replace(PROJECTS_URL, PROJECTS_PATH, $file);
	}

	$file_extension =	substr( $file, strrpos( $file, '.' ) );
	$file_name		=   substr( $file, strrpos( $file, '/' )+1 );

	$onlyonesize=0;
	if ($height == "") {
		list($ow, $oh) = getimagesize( $file );
		$height = round($oh * ( $width / $ow ));
		$onlyonesize=1;
	} else if ($width == "") {
		list($ow, $oh) = getimagesize( $file );
		$width = round($ow * ( $height / $oh ));
		$onlyonesize=1;
	}

	$url			 = str_replace("localhost", $_SERVER['HTTP_HOST'], $clerk->getSetting( "cache_path", 2 ));
	$cache_dir 		 = str_replace("localhost", $_SERVER['HTTP_HOST'], $clerk->getSetting( "cache_path", 1 ));
	$cache_file_name = str_replace( $file_extension, "", basename($file) ) . "." . $width . "x" . $height . "_" . $adaptive .".jpg";
	$path_cachefile  = $cache_dir . "/" . $cache_file_name;

	if ( file_exists( $path_cachefile ) ) {
		if ($isCallFromURL==1) {
			// This part of the code comes from 
			// http://dtbaker.com.au/random-bits/how-to-cache-images-generated-by-php.html
			header("Cache-Control: private, max-age=10800, pre-check=10800");
			header("Pragma: private");
			header("Expires: " . date(DATE_RFC822,strtotime(" 2 day")));
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == filemtime($path_cachefile))) {
			  header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($img)).' GMT', 
			  true, 304);
			} else {
				header('Content-type: image/jpeg');
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path_cachefile)) . ' GMT');
				ob_start();
				$image= imagecreatefromjpeg( $path_cachefile );
				imagejpeg( $image, null, 90 );
				imagedestroy( $image );
			}
			exit;
		} else {
			return ( $returnHow == "full" ) ? '<img src="' . $url . $cache_file_name . '" width="' . $width . '" height="' . $height . '" alt="" />' : $url . $cache_file_name;
		}
	} else {
		if ($isCallFromURL==1) {
			ithumbnail_make($file,$path_cachefile,$width,$height,$adaptive,$customcenter,$onlyonesize);
			exit;
		} else {
			ithumbnail_make($file,$path_cachefile,$width,$height,$adaptive,$customcenter,$onlyonesize, 1);
			return ( $returnHow == "full" ) ? '<img src="' . $url . $cache_file_name . '" width="' . $width . '" height="' . $height . '" alt="" />' : $url . $cache_file_name;
		}
	}
	
}

// function from Ryan Rud (http://adryrun.com)
function ithumbnail_findSharp($orig, $final) {
	$final	= $final * (750.0 / $orig);
	$a		= 52;
	$b		= -0.27810650887573124;
	$c		= .00047337278106508946;
	
	$result = $a + $b * $final + $c * $final * $final;
	
	return max(round($result), 0);
} // findSharp()

function ithumbnail_make($file,$path_cachefile,$width,$height,$adaptive,$customcenter,$onlyonesize, $onlycreate=0) {
	global $clerk;

	$file_extension =	substr( basename( $file ), strrpos( basename( $file ), '.' ) );
	$file_name		=   basename( $file );

	$center="";
	if ($onlyonesize==0 && $customcenter==1) {
		$thumbnailcenter = mysql_fetch_array( $clerk->query_select( 'projects', 'thumbnailcenter', 'WHERE thumbnail="'.$file_name.'" LIMIT 1' ) );
		if ($thumbnailcenter!=false) {
			$center=$thumbnailcenter['thumbnailcenter'];
		} else {
			$thumbnailcenter = mysql_fetch_array( $clerk->query_select( 'project_files', 'thumbnailcenter', 'WHERE file="'.$file_name.'" LIMIT 1' ) );
			if ($thumbnailcenter!=false) {
				$center=$thumbnailcenter['thumbnailcenter'];
			}
		}
	}

	load_helper( "ThumbLib.inc" );

	list($ow, $oh) = getimagesize( $file );
	
	$thumb=	PhpThumbFactory::create( $file, array( 'resizeUp' => true ) );
	$thumb->setFormat("JPG");

	if ($center!="") {
		// Do the thing...
		$center = explode(",",$center);
		
		// Get the center data
		$tx=$center[0];
		$ty=$center[1];
		$dx=$center[2]; // Default dimension X
		$dy=$center[3]; // Default dimension Y
		
		// Get the center point
		$cx=$tx+$dx/2;
		$cy=$ty+$dy/2;
	
		// If new size is big than original crop
		if ($dx<$width) {
			$tx=$tx-(($width-$dx)/2);
			if ($tx<0) $tx=0;
			$dx=$width;
		}
		if ($tx+$dx>$ow) {
			$tx=$tx-($ow-$dx);
			if ($tx<0) {
				$tx=0;
				$dx=$ow;
			}
		}
		
		if ($dy<$height) {
			$ty=$ty-(($height-$dy)/2);
			if ($ty<0) $ty=0;
			$dy=$height;
		}
		if ($ty+$dy>$oh) {
			$ty=$ty-($oh-$dy);
			if ($ty<0) {
				$ty=0;
				$dy=$oh;
			}
		}

		$tx=round($tx);
		$ty=round($ty);
		$dx=round($dx);
		$dy=round($dy);
		
		$thumb->crop( $tx, $ty, $dx, $dy );
	} else {
		$tx=0;
		$ty=0;
		$dx=$ow;
		$dy=$oh;
	}

	$thumb->setOptions(array 
	(
		'resizeUp'				=> false,
		'jpegQuality'			=> 90,
		'correctPermissions'	=> false,
		'preserveAlpha'			=> true,
		'alphaMaskColor'		=> array (255, 255, 255),
		'preserveTransparency'	=> true,
		'transparencyMaskColor'	=> array (0, 0, 0)
	));

	$width	= round($width);
	$height	= round($height);
	
	if ($dx!=$width || $dy!=$height) {
		if ( $adaptive == 0 || ( $width == 0 || $height == 0 ) ) {
			$thumb->resize( $width, $height );
		} else {
			//$thumb->adaptiveResize( $width, $height );
			if (($width / $height) > ($dx / $dy)) {
			    $nw = $width;
			    $nh = round( $dy*($width/$dx) );
			    $thumb->resize( $nw, $nh );
			    $thumb->crop( 0, abs(round(($nh-$height)/2)) , $width, $height );
			} else {
				$nw = round( $dx*($height/$dy) );
				$nh = $height;
			    $thumb->resize( $nw, $nh );
			    $thumb->crop( abs(round(($nw-$width)/2)), 0 , $width, $height );
			}
  		}
	}
	
	imagegammacorrect ($thumb->getWorkingImage(), 1.0, 0.90 ) ;
	
	// SHARPNESS FROM Smart Image Resizer 1.4.1 by Joe Lencioni (http://shiftingpixel.com)
	$sharpness	= ithumbnail_findSharp($td, $width);

	$sharpenMatrix	= array(
		array(-1, -2, -1),
		array(-2, $sharpness + 12, -2),
		array(-1, -2, -1)
	);
	$divisor		= $sharpness;
	$offset			= 0;
	imageconvolution($thumb->getWorkingImage(), $sharpenMatrix, $divisor, $offset);
	
	$thumb->save( $path_cachefile );
	
	if ($onlycreate==0) {
		$thumb->save($path_cachefile);
		$thumb->show();
	} else {
		$thumb->save($path_cachefile);
	}
}

?>