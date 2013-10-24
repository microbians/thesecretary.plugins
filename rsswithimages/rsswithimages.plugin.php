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
	hook( "projectsRssDescription", "rsswithimages" );

	function projectFirstThumbnail($idslug){
		// It return the first thumbnail, it can be the one of the project or instead the very first one on the content.
		$firstThumb='';

		// Look if is a thumbnail for the project
		$projects=projectInfo($idslug);
		$firstThumb=$projects['thumbnail'];
		
		if ( $firstThumb == '' ) {
			$projectsItems=getProjectFiles();
			foreach ($projectsItems as $i) {
				if ($i['thumbnail'] != '') {
					$firstThumb=$i['thumbnail'];
					break;
				}
			}
		}
		return PROJECTS_URL . $projects['slug'] . '/' . $firstThumb;
	}

	function rsswithimages($desc)
	{
		global $manager;
		global $clerk, $project;
		
		return '<img src="'.projectFirstThumbnail($project['id']).'"/><br/>';
	}

?>