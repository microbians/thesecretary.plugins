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

tx=-1;
ty=-1;
bx=-1;
by=-1;
dx=-1;
dy=-1;

tw=-1;
th=-1;
mw=-1;
mh=-1;

thumbnailXYCallback=function(v,m,f){
	jQuery.noticeAdd({ text: id, stay: false });
}

thumbnailXYcrop=function(c){
	var dif=mw/tw;
	tx=c.x*dif;
	ty=c.y*dif;
	bx=c.x2*dif;
	by=c.y2*dif;
}

thumbnailXYWindow=function(obj, isProject){
	if (!isProject) {
		var fileID=$(obj).parents('.filebox').attr('id').substring(5);
		isProject=false;
	} else {
		var fileID=false;
	}
	
	var html="";
	jQuery.noticeAdd({ text: "Loading...", stay: true, type: "heavy" });
	jQuery.post(
				"system/plugins/ithumbnail/ithumbnail.ajax.php",
				{
					action: 'getThumbnail',
					asstPath: asstPath,
					id: id,
					file_id: fileID,
					isproject: isProject
				},
				function(data)
				{
					jQuery.noticeRemove(jQuery(".heavy"));
					
					var img=data['url'] + data['projects.slug'] + '/' + data['image_file'];

					tw=parseInt(data['thumbnail_width']);
					th=parseInt(data['thumbnail_height']);
					mw=parseInt(data['image_width']);
					mh=parseInt(data['image_height']);
									
					var form= '<img id="thumbnailXYimg" src="'+img+'" width="'+tw+'"/>';

					jQuery.prompt( '<h1>iThumbnail</h1><center><div style="position:relative;background:#A0A0A0;width:'+tw+'px;height:auto;">' + form + '</div><span style="color:#505050">Select the most relevant zone of the image</span></center>', {
						buttons: {
							Save	: true,
							Cancel	: false
						},
						callback: function(value, msg, form)
								  {
								  	if ( value == true )
									{
										var dx = bx-tx;
										var dy = by-ty;
										
										tx=Math.floor(tx);
										ty=Math.floor(ty);
										dx=Math.ceil(dx);
										dy=Math.ceil(dy);
										
										jQuery.noticeAdd({ text: "Saving...", type: "heavy", stay: true });
										jQuery.post(
											"system/plugins/ithumbnail/ithumbnail.ajax.php",
											{
												action: 'setThumbnailCenter',
												asstPath: asstPath,
												id: id,
												file_id: fileID,
												tx: tx,
												ty: ty,
												dx: dx,
												dy: dy,
												isproject: isProject
											},
											function(data)
											{
												jQuery.noticeRemove(jQuery(".heavy"));
											}
										);
									}

									return true;
								  },
						loaded: function(){
							$(this).css('width' , 'auto');
							$(this).css('height', 'auto');
							$(this).css('left', '25%');
							$(this).css('padding', '20px');
							$('#thumbnailXYimg').Jcrop({
								onSelect: thumbnailXYcrop
							});
						}
					});
				},
				"json"
		
	);
	
	return false;
};