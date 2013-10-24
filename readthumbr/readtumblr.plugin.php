<?php
/*
	Version 1.7 - Read a list of tumblr sites mixed and paginated - for The Secretary CMS - by microbians.com
	
	version 1.7 - Change from fsocks to cURL
	version 1.6 - Update database only if is home page of the blog, Add permakinks.
	version 1.5 - Read tumblr in lazy mode, so only will read until a prior post was found, and not continue updating if focer=true or one time each day.
	version 1.4 - Bug fixed
	version 1.3 - Bug fixed with tumblrid (int to bigint) & added reset parameter to drop tumblr table
	version 1.2 - Added open,close to list of post & next/prev when no theme
	version 1.1 - Added settings to change the tumblr site list
	
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

	- Plugin structure thanks to the Raphael Moser's google analitics plugin.
*/

// Launch at start the install of the settings and new table on the DDBB
hook("start", "readtumblr_install");

// Adds the setings for add the tumblr site list
hook( "prefsCol1", "readtumblr_form" );
hook( "form_process", "readtumblr_process" );

// Adds The fields for the setings
function readtumblr_install() {
	global $manager;
	global $clerk;

	$resettumblrtable=$_GET['reset'];
	if ($resettumblrtable=="true") 	$resettumblrtable=true;
	else							$resettumblrtable=false;

	if (!$clerk) $clerk=$manager->clerk;

	if(!$clerk->settingExists("readTumblrSites")) {
		$clerk->addSetting("readTumblrSites", array(""));
	}
	if(!$clerk->settingExists("readTumblrRowsPerPage")) {
		$clerk->addSetting("readTumblrRowsPerPage", array(10,"",""));
	}

	if ($resettumblrtable==true) {
		if ($clerk->tableExists("tumblr")) {
			$q=<<<SQL
DROP TABLE `tumblr`;
SQL;
			if ( !$clerk->query($q) ) {
				echo 'UO! Error deleting table tumblr';
			}
		}
	}

	if (!$clerk->tableExists("tumblr")) {
		$q=<<<SQL
CREATE TABLE `tumblr` (
`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`site` text CHARACTER SET utf8 NOT NULL,
`tumblrid` bigint(11) unsigned NOT NULL,
`date` int(11) NOT NULL,
`type` text CHARACTER SET utf8 NOT NULL,
`url` text CHARACTER SET utf8 NOT NULL,
`slug` text CHARACTER SET utf8 NOT NULL,
`data1` longtext CHARACTER SET utf8 NOT NULL,
`data2` longtext CHARACTER SET utf8 NOT NULL,
`data3` longtext CHARACTER SET utf8 NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
SQL;
		if ( !$clerk->query($q) ) {
			echo 'UO! Error creating table tumblr';
		}
	}
}

// Display the input field for the tumblr site settings
function readtumblr_form() {
	global $manager;

	$manager->form->add_fieldset( "ReadTumblr Plugin Settings", "readTumblrSettings" );

	$sitelist = $manager->clerk->getSetting( "readTumblrSites", 1 );
	$manager->form->add_input( "text", "readTumblrSites", "Add a separated coma list of your tumblr sites,<br/>Note: Do not add http:// to the site domains, example: mysite1.tumblr.com, mysite2.tumblr.com, ...", $sitelist );

	$rowsPerPage = $manager->clerk->getSetting( "readTumblrRowsPerPage", 1 );
	$manager->form->add_input( "text", "readTumblrRowsPerPage", "Number of post per page", $rowsPerPage );

	$readTumblrHelp=<<<HTMLCODE
<strong>How to use:</strong><br/><br/>
Make use of it by adding <strong>func=readTumblr</strong> to the options of a page.
<pre>
The plugin reads parameters from the query line in this way (as an example):
&start=5 	> Will read the tumblr mix of post from the page number 5
&site=mysite1 	> Will render only the tumblr site that fits mysite1 name (like mysite1.tumblr.com or mysite1.com)
&force=true 	> Will force to read all tumblr post (this is only needed when you delete or edit a post in tumblr)
&reset=true 	> Will drop the tumblr table & create again;
</pre>
HTML you can use in /templates of your theme, anyway this are optional<br/>
as the plugin make use of it on case the file exist, if not will render all in a regular html or will render the default:
<pre>
tumblr_open.html    	 > This is before the list of post
tumblr_close.html    	 > This is after the list of post
tumblr_post_open.html    > This is use every post is open
tumblr_post_close.html   > This is use every post is open
tumblr_post_default.html > This is used when there is no especific post html template like the next ones:
tumblr_post_photo.html   > To use with photo post...
tumblr_post_link.html    > To use with link post...
... (and so on)
</pre>
Variables to use on tumblr_post_* templates:
<pre>
\$post['tumblr.site'] 		- Tumblr site domain (ej. mysite1.tumblr.com)
\$post['tumblr.tumblrid'] 	- ID of the post that tumblr uses
\$post['tumblr.date'] 		- Unix date of the post
\$post['tumblr.type'] 		- Type of post... photo, quote, text, link,...
\$post['tumblr.url'] 		- Url link to the post in tumblr
\$post['tumblr.slug'] 		- Slug name for the post tumblr uses

\$post['tumblr.data1'], \$post['tumblr.data2'], \$post['tumblr.data3']
tumblr.data1, tumblr.data2, tumblr.data3 of \$post, it depends on type of post can be title or link or...:

case 'photo':
	\$post['tumblr.data1'] 	= \$post['photo-url-500']
	\$post['tumblr.data2'] 	= \$post['photo-caption']
	\$post['tumblr.data3'] 	= \$post['photo-link-url']
case 'link':
	\$post['tumblr.data1'] 	= \$post['link-text']
	\$post['tumblr.data2'] 	= \$post['link-url']
	\$post['tumblr.data3'] 	= \$post['link-description']
case 'regular':
	\$post['tumblr.data1'] 	= \$post['regular-title']
	\$post['tumblr.data2'] 	= \$post['regular-body']
case 'quote':
	\$post['tumblr.data1'] 	= \$post['quote-text']
	\$post['tumblr.data2'] 	= \$post['quote-source']
case 'conversation':
	\$post['tumblr.data1'] 	= \$post['conversation-title']
	\$post['tumblr.data2'] 	= \$post['conversation-text']
case 'video':
	\$post['tumblr.data1'] 	= \$post['video-caption']
	\$post['tumblr.data2'] 	= \$post['video-source']
	\$post['tumblr.data3'] 	= \$post['video-player']
case 'audio':
	\$post['tumblr.data1'] 	= \$post['audio-caption']
	\$post['tumblr.data2'] 	= \$post['conversation-text']
case 'answer':
	\$post['tumblr.data1'] 	= \$post['question']
	\$post['tumblr.data2'] 	= \$post['answer']
</pre>
HTMLCODE;
	
	$manager->form->add_to_form($readTumblrHelp);

	$manager->form->close_fieldset();
}

// Process the settings change for the tumblr site list
function readtumblr_process() {
	global $manager;
	
	if( isset( $_POST['readTumblrSites'] ) ) {
		$sitelist= $_POST['readTumblrSites'];
		$manager->clerk->updateSetting( "readTumblrSites", array( $sitelist, "", "" ) );
	}

	if( isset( $_POST['readTumblrRowsPerPage'] ) ) {
		$rows= $_POST['readTumblrRowsPerPage'];
		$manager->clerk->updateSetting( "readTumblrRowsPerPage", array( $rows, "", "" ) );
	}

}

/*
-----------------------------------------------------------------
	TUMBLR FUNCTION
-----------------------------------------------------------------
*/
function readTumblr() {
	global $clerk;

	readtumblr_install();

	// Get the site list as an array
	$sitelist = $clerk->getSetting( "readTumblrSites", 1 );
	$sitelist = str_replace(', ',',', $sitelist);
	$sitelist = str_replace(' ,',',', $sitelist);
	$sitelist = explode(',', $sitelist);
	
	$force=$_GET['force'];
	if ($force=="true") $force=true;
	else				$force=false;

	$queryaddon='';
	if ($_GET['site']!='') {
		$onlysite=$_GET['site'];
		foreach($sitelist as $site) {
			if (strpos($site,$onlysite) !== false) {
				$sitelist=Array($site);
				$queryaddon = 'AND site="'.$site.'" ';
				break;
			}
		}
	}

	$pageNum = 1;
	$rowsPerPage=$clerk->getSetting( "readTumblrRowsPerPage", 1 );
	$offset=0;

	$tumblrid='';

	if( isset($_GET['start']) || isset($_GET['tumblrid']) ) {
		if( isset($_GET['start']) ){
		    $pageNum = $_GET['start'];
		    $force   = false;
			$offset = ($pageNum - 1) * $rowsPerPage;
		}
		if( isset($_GET['tumblrid']) ){
			$tumblrid	= $_GET['tumblrid'];
		}
	} else {
		$sqltumblr = readTumblr_mysqlFetchAliasArray( $clerk->query_select( 'tumblr', '', 'WHERE tumblr.type="lastupdate" LIMIT 1' ) );
		$currenttime=time();
		
		if (count($sqltumblr) == 0) {
			if ( !$clerk->query_insert( 'tumblr', 'type, date', '"lastupdate", "'.$currenttime.'"' ) ) {
				echo 'Error inserting tumblr - lastupdate';
			} else {
				$force=true;
			}
		} else {
			$lastupdate=$sqltumblr['tumblr.date'];
			// More than a day then update all
			if ($currenttime-$lastupdate > 24*60*60) {
				if ( !$clerk->query_edit( 'tumblr', 'date="'.$currenttime.'"', 'WHERE tumblr.type="lastupdate"' ) ) {
					echo 'Error editing tumblr';
				}
				$lastupdate=$currenttime;
				$force = true;
			}
		}
			
		foreach($sitelist as $site) {
			$url    = 'http://'.$site.'/api/read/json?start=0&num=0';

			$result = readtumblr_fetchURL($url);
			$lastdate = readtumblr_lastModificationDate($url);

			//var_dump( $result );
	
			$result = str_replace('var tumblr_api_read = ','',$result);
			$result = str_replace(';','',$result);
			$result = str_replace('\u00a0','&nbsp;',$result);
			$jsondata = json_decode($result,true);

	
			$sqltumblr = readTumblr_mysqlFetchAliasArray( $clerk->query_select( 'tumblr', '', 'WHERE tumblr.site="'.$site.'" AND tumblr.type="lastmodificationdate" LIMIT 1' ) );
			
			$isnew=false;
			
			if (count($sqltumblr) == 0) {
				if ( !$clerk->query_insert( 'tumblr', 'site, type, date, data1', '"'.$site.'", "lastmodificationdate", "'.$lastdate.'", "'.$jsondata['posts-total'].'"' ) ) {
					echo 'Error inserting tumblr';
				} else {
					$sqltumblr['tumblr.date']  = $lastdate;
					$sqltumblr['tumblr.data1'] = $jsondata['posts-total'];
					$isnew=true;
				}
			} 
	
			if ($force==true || $isnew==true || $lastdate>$sqltumblr['tumblr.date'] || $jsondata['posts-total']>$sqltumblr['tumblr.data1']) {

				if ( !$clerk->query_edit( 'tumblr', 'date="'.$lastdate.'",  data1="'.$jsondata['posts-total'].'"', 'WHERE tumblr.site="'.$site.'" AND tumblr.type="lastmodificationdate" LIMIT 1' ) ) {
					echo 'Error editing tumblr';
				}
					
				if ($force==true) {
					$npost = 50; // read packs of 50 posts
					$rounds = ceil($jsondata['posts-total']/$npost); // number of packs of 50 posts
				} else {
					$npost  = 50; 	// Only reads 50 as much until it gets nothing new (does not update)
					$rounds = 1; 	// and only one round
				}
						
				for ($i=0; $i<$rounds; $i++){
					$u = 'http://'.$site.'/api/read/json?start='.($i*$npost).'&num='.$npost;
					$r = readtumblr_fetchURL($u);

					$r = str_replace('var tumblr_api_read = ','',$r);
					$r = str_replace(';','',$r);
					$r = str_replace('\u00a0','&nbsp;',$r);
					$jdata = json_decode($r,true);
					
					$posts = $jdata['posts'];
			
					foreach($posts as $post){
						$tmp_id			= $post['id'];
						
						// echo $tmp_id."<br/>";
						
						$tmp_date		= $post['unix-timestamp'];
						$tmp_type		= $post['type'];
						$tmp_url  		= $post['url-with-slug'];
						$tmp_slug 		= str_replace('-',' ',$post['slug']);
						$tmp_data1		= '';
						$tmp_data2		= '';
						$tmp_data3		= '';
						switch ($tmp_type) {
							case 'photo':
								$tmp_data1 	= $post['photo-url-500'];
								$tmp_data2 	= $post['photo-caption'];
								$tmp_data3 	= $post['photo-link-url'];
								break;
							case 'link':
								$tmp_data1 	= $post['link-text'];
								$tmp_data2 	= $post['link-url'];
								$tmp_data3 	= $post['link-description'];
								break;
							case 'regular':
								$tmp_data1	=	$post['regular-title'];
								$tmp_data2	=	$post['regular-body'];
								break;
							case 'quote':
								$tmp_data1	=	$post['quote-text'];
								$tmp_data2	=	$post['quote-source'];
								break;
							case 'conversation':
								$tmp_data1	=	$post['conversation-title'];
								$tmp_data2	=	$post['conversation-text'];
								break;
							case 'video':
								$tmp_data1	=	$post['video-caption'];
								$tmp_data2	=	$post['video-source'];
								$tmp_data3	=	$post['video-player'];
								break;
							case 'audio':
								$tmp_data1	=	$post['audio-caption'];
								$tmp_data2	=	$post['conversation-text'];
								break;
							case 'answer':
								$tmp_data1	=	$post['question'];
								$tmp_data2	=	$post['answer'];
								break;
							default:
								break;
						}
						
						$tmp_data1 = $clerk->clean_string($tmp_data1);
						$tmp_data2 = $clerk->clean_string($tmp_data2);
						$tmp_data3 = $clerk->clean_string($tmp_data3);
						
						$isnewitem=(count(readTumblr_mysqlFetchAliasArray($clerk->query_select( 'tumblr', '*', 'WHERE tumblr.site="'.$site.'" AND tumblr.tumblrid="'.$tmp_id.'" LIMIT 1' )))==0);
	
						//echo "-".$isnewitem."<br>";
	
						if ($isnewitem==true) {
							if ( !$clerk->query_insert( 'tumblr', 'site, tumblrid, date, type, url, slug, data1, data2, data3', '"'.$site.'", "'.$tmp_id.'","'.$tmp_date.'", "'.$tmp_type.'", "'.$tmp_url.'", "'.$tmp_slug.'", "'.$tmp_data1.'", "'.$tmp_data2.'", "'.$tmp_data3.'"' ) ) {
								echo 'Error inserting tumblr site '.$site.'<br>';
							} else {
								//echo "Inserting tumblr site ".$site." id=".$tmp_id." slug=".$tmp_slug."<br>";
							}
						} else {
						
							if ( $force == true ) {
						
								if ( !$clerk->query_edit( 'tumblr', 'date="'.$tmp_date.'", type="'.$tmp_type.'", url="'.$tmp_url.'", slug="'.$tmp_slug.'", data1="'.$tmp_data1.'", data2="'.$tmp_data2.'", data3="'.$tmp_data3.'"', 'WHERE tumblr.site="'.$site.'" AND tumblr.tumblrid="'.$tmp_id.'" LIMIT 1' ) ) {
									echo 'Error edit tumblr site '.$site.'<br>';
								} else {
									//echo "Edit tumblr site ".$site." id=".$tmp_id." slug=".$tmp_slug.$tmp_data2."<br>";
								}
	
							} else {
								// Exist the loop there is no more to update bacause it only happen when force is true;
								break;
							}
	
						}
					}
				}
			}
		}
	}
	
	// Paint tumblr's posts
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////

	if ($tumblrid=='') {
		$totalPages=ceil(mysql_num_rows($clerk->query_select( 'tumblr', '*', 'WHERE tumblr.type!="lastmodificationdate" AND tumblr.type!="lastupdate"' ))/$rowsPerPage);
		$sqltumblr=$clerk->query_select( 'tumblr', '*', 'WHERE tumblr.type!="lastmodificationdate"  AND tumblr.type!="lastupdate" '.$queryaddon.'ORDER BY tumblr.date DESC LIMIT '.$offset.', '.$rowsPerPage);
	} else {
		$totalPages=1;
		$sqltumblr=$clerk->query_select( 'tumblr', '*', 'WHERE tumblrid="'.$tumblrid.'" AND site="'.$site.'"');
	}

	if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_open.html') {
	}

	while ( $post = readTumblr_mysqlFetchAliasArray( $sqltumblr ) ) {

		$post['tumblr.currentpage']=$pageNum;
		$post['tumblr.maxpages']=$totalPages;

		//$post['tumblr.slug'];
	
		$post['tumblr.data1']=mb_convert_encoding( $post['tumblr.data1'], "HTML-ENTITIES", "utf-8" );
		$post['tumblr.data2']=mb_convert_encoding( $post['tumblr.data2'], "HTML-ENTITIES", "utf-8" );
		$post['tumblr.data3']=mb_convert_encoding( $post['tumblr.data3'], "HTML-ENTITIES", "utf-8" );
	
		if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_open.html') {
			echo '<div class="post" site="'.$post['tumblr.site'].'" tumblr_id="'.$post['tumblr.tumblrid'].'">';
		}

		switch ($post['tumblr.type']) {
			case 'photo':
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_'.$post['tumblr.type'].'.html') {
					if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
						echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
						echo '<img src="'.$post['tumblr.data1'].'">';
					}
				}
				break;
			
			case 'link':
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_'.$post['tumblr.type'].'.html') {
					if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
						echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
						echo substr(strip_tags($post['tumblr.data3']),0,100).'...';
					}
				}
				break;

			case 'regular':
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_'.$post['tumblr.type'].'.html') {
					if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
						echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
						echo substr(strip_tags($post['tumblr.data1']),0,100)."...";
					}
				}
				break;

			case 'quote':
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_'.$post['tumblr.type'].'.html') {
					if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
						echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
						echo substr(strip_tags($post['tumblr.data1']),0,100)."...";
					}
				}
				break;
				
			case 'conversation':
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_'.$post['tumblr.type'].'.html') {
					if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
						if(empty($post['tumblr.data1'])){
							echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
						}else{
							echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.data1'].'</a><br/>';
						}
						echo substr(nl2br($post['tumblr.data2']),0,100);
					}
				}
				break;
			case 'audio':
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_'.$post['tumblr.type'].'.html') {
					if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
						if(empty($post['tumblr.data1'])){
							echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
						}else{
							echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.data1'].'</a><br/>';
						}
					}
				}
				break;
			case 'video':
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_'.$post['tumblr.type'].'.html') {
					if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
						if(empty($post['tumblr.data1'])){
							echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
						}else{
							echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.data1'].'</a><br/>';
						}
					}
				}
				break;
			default:
				if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_default.html') {
					echo '<a href="'.$post['tumblr.url'].'" target="_blank">'.$post['tumblr.slug'].'</a><br/>';
				}
				break;
		}

		if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_post_close.html') {
			echo '</div>';
		}
	}

	$post['tumblr.currentpage']=$pageNum;
	$post['tumblr.maxpages']=$totalPages;

	if (!@include HQ.'site/themes/'.THEME.'/templates/tumblr_close.html') {
		$page  = currentPage();
		if ($post['tumblr.currentpage']>1) {
			echo '<a href="?page='.$page.'&start='.($post['tumblr.currentpage']-1).'">PREV</a> / ';
		}
		if ($post['tumblr.currentpage']<$post['tumblr.maxpages']) {
			echo '<a href="?page='.$page.'&start='.($post['tumblr.currentpage']+1).'">NEXT</a>';
		}
	}
}

function readtumblr_lastModificationDate( $url ) {
	$a= (get_headers($url,1));
	$c =$a['Last-Modified'];
	return strtotime($c);
}

// From cURL (stevehartken http://www.php.net/manual/en/ref.curl.php#62194)
function readtumblr_fetchURL($url){ 
    $url=str_replace('&amp;','&',$url); 
    $ch=curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
    $content = curl_exec ($ch); 
    curl_close ($ch); 
    return $content; 
} 

function readTumblr_mysqlFetchAliasArray($result) {
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
	
?>