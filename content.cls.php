<?php
// VERSION: 13 (17/12/2013)

if(!defined("WEB_CONTENT")) {
    define("WEB_CONTENT","content");

    if(!defined("DATABASE_LAYER"))  require_once(dirname(__FILE__) . "/database.cls.php");
    if(!defined("DATUMTIJD_LAYER")) require_once(dirname(__FILE__) . "/datumtijd.cls.php");
    if(!defined("CLIENT_LAYER"))    require_once(dirname(__FILE__) . "/client.cls.php");

	class webArticle {
		var $page;
		var $title;
		var $image;
		var $content;
		var $pagelink;
		var $extlink;

		function webArticle($row) {
			$this->page = 0;
			$this->title = "";
			$this->image = null;
			$this->content = "";
			$this->pagelink = 0;
			$this->extlink = "";
			$euro = chr(194).chr(128);	
				
			if(is_array($row)) {
				$this->page = intval($row['id']);	
				//$this->title = utf8_encode($row['title']);				
				$this->title = str_replace($euro, "&euro;", utf8_encode($row['title']));
				$this->image = new webImage(intval($row['image']));
				//$this->content = utf8_encode($row['content']);
				$this->content = str_replace($euro, "&euro;", utf8_encode($row['content']));
				$this->pagelink = intval($row['pagelink']);
				$this->extlink = $row['extlink'];
			}
		}		
		
		function o2a() {
			
			if(is_object($this->image)) {
				$_imgarr = $this->image->o2a();
			} else {
				$_imgarr = array();
			}
			
			$_arr = array(
				'page' 		=>	$this->page,
				'title' 	=>	$this->title,
				'image' 	=>	$_imgarr,
				'content' 	=>	$this->content,
				'pagelink' 	=>	$this->pagelink,
				'extlink'	=>	$this->extlink,
			);		
			
			return $_arr;
		}
		
		function update($data) {
			global $clnt, $db;

			$result = array();

			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['data'] = array();

			if(!$clnt->adminEditor) {
				$result['errno'] = -1;
				$result['errmsg'] =  "Geen authorisatie";

				return $result;
			}
			
			$euro = chr(194).chr(128);
			
			if($this->page == 0) {
				return "Toevoegen NIET mogelijk";
			} else {
				$where = "id=" . $this->page;

				if(array_key_exists("title",$data)) {
					//$this->backup();
					
					$set = "title='" . utf8_decode($data['title']) . "'";
					//$set = "title='" . utf8_decode(str_replace("&euro;", $euro, $data['title'])) . "'";
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("content",$data)) {
					$set = "content='" . utf8_decode($data['content']) . "'";
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("label",$data)) {
					$set = "label='" . utf8_decode($data['label']) . "'";
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("image",$data)) {
					$set = "image=" . intval($data['image']);
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("pagelink",$data)) {
					$set = "pagelink=" . intval($data['pagelink']);
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("extlink",$data)) {
					$set = "extlink='" . utf8_decode($data['extlink']) . "'";
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
			}

			return $result;
		}
		
    } // class webArticle
	
    class webSection {
		var $id;
		var $name;
		var $note;
		var $label;
		var $article;
		var $image;
		var $active;

		function webSection($row) {
			$this->id = 0;
			$this->name = "";
			$this->note = "";
			$this->label = "";
			$this->article = null;
			$this->image = null;
			$this->active = 0;
			
			if(is_array($row)) {
				$this->id = intval($row['id']);
				$this->name = utf8_encode($row['name']);
				$this->note = utf8_encode($row['note']);
				$this->label = utf8_encode($row['label']);
				$this->article = new webArticle($row);
				$this->image = new webImage(intval($row['image']));
				$this->active =intval($row['active']);
			}
		}
    } // class section
 
    class webImage {
		var $id;
		var $title;
		var $description;
		var $label;
		var $src;
		var $keywords;
		var $url;
		var $thumbnail;
		var $type;
		var $active;
		var $created;
		var $updated;

		function webImage($id) {
			global $cfg, $db;

			if(!is_object($db)) $db = new database();

			$this->id = $id;

			$sql = "SELECT * FROM webimage WHERE id = " . $id;
			if($result = $db->sql_query($sql)) {
				if($row = $db->sql_fetchrow($result)) {
					$this->load($row);
				} else {
					$this->id = 0;
					$this->title = "Afbeelding titel ...";
					$this->description = "Afbeelding beschrijving ...";
				}
			}
			
			$arg = explode("/",$this->src);
			$i = count($arg) - 1;
			if($i >= 0) {
				$this->thumbnail = $cfg->image->thumbnail_path . $arg[$i];
			} else {
				$this->thumbnail = $cfg->image->thumbnail_path . $this->src;			
			}
		}
		
		function load($row) {
			global $cfg, $db;
			
			if(is_array($row)) {
				$this->id = intval($row['id']);
				$this->title = utf8_encode($row['title']);
				$this->description = utf8_encode($row['description']);
				$this->label = utf8_encode($row['label']);
				$this->type = $row['type'];
				$this->keywords = utf8_encode($row['keywords']);
				$this->src = $row['src'];
				$this->active = intval($row['active']);
				$this->page = intval($row['page']);
				$this->sortorder = intval($row['sortorder']);
				$this->created = $row['created'];
				$this->updated = strftime("%d-%m-%y %H:%M:%S",intval($row['updated']));
			}
			
			$arg = explode("/",$this->src);
			$i = count($arg) - 1;
			if($i >= 0) {
				$this->thumbnail = $cfg->image->thumbnail_path . $arg[$i];
			} else {
				$this->thumbnail = $cfg->image->thumbnail_path . $this->src;			
			}
		}
		
		static function create($imgfile,$pid) {
			global $db, $cfg;
			
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;
			
			$ftype = filetype($imgfile);
			if($ftype != "file") {
				$result['errno'] = -1;
				$result['errmsg'] = "'" . $imgfile ."' is geen bestand! (" . $ftype .")";
				return $result;
			}
			
			$imgtype = mime_content_type($imgfile);
			$ext = $cfg->image->validTypes[$imgtype];
			if($ext == NULL) {
				$result['errno'] = -2;
				$result['errmsg'] = "'" . $imgfile ."' heeft geen geldig type (" . $imgtype . ")";
				return $result;
			}
				
			$iid = 0;
			$so = 1;
			
			$sql = "SELECT sortorder from webimage WHERE page = " . $pid . " ORDER BY sortorder DESC";
			$res = $db->sql_query($sql);
			if( $row = $db->sql_fetchrow($res) )	{
				$so = intval($row[sortorder]) + 1;
			}
			
			$fstr = explode("/",$imgfile);
			$n = count($fstr);
			$fname = explode(".",$fstr[$n-1]);
			$label = $fname[0];
			$title = ucfirst($label);
			$description = "...";
			

			$sql = "INSERT INTO webimage (title, description, label, page, sortorder) VALUES ('$title','$description','$label','$pid','$so')";
			$res = $db->sql_query($sql);
			if($res) { 
				$iid = intval($db->sql_nextid());
			}
			
			if($iid > 0) {
				$imgsrc =  $cfg->image->image_path . $iid . "_" . $label . "." . $ext; 
				if(webImage::convert($imgfile,$imgsrc,$imgtype)) {
					$where = "id=" . $iid;
					$set = "src='" . $imgsrc . "',type='" . $imgtype . "',updated=" . time()  ;
					$res = $db->sql_update("webimage",$where,$set);
				} else {
					/* TODO: REMOVE database record id = iid */
					$result['errno'] = -3;
					$result['errmsg'] = "Conversie '" . $imgfile ."' naar '" . $imgsrc . "' is geen mogelijk! (" . $imgtype . ")";				
				}
			}
			$result['errmsg'] = "OK";		
			$result['id'] = $iid;
			
			return $result;
		}

		static function convert($imgsrc,$imgdest,$imgtype) {
			global $cfg, $db, $clnt, $_GET, $id;

			$size = filesize($imgsrc);
			list($width, $height) = getimagesize($imgsrc);

			if($height > 0) {
				$ratio = $width / $height;
			} else {
				$ratio = 99;
			}
			
			$new_width = $width;
			$new_height = $height;

			if($ratio >= 1.0) {
				if($width > $cfg->image->max_width) {
					$new_width = $cfg->image->max_width;
					$new_height = round($cfg->image->max_width / $ratio,0);
				}
				$thumb_width = $cfg->image->max_thumb_width;
				$thumb_height = round($cfg->image->max_thumb_width / $ratio,0);	
			} else {
				if($height > $cfg->image->max_height ) {
					$new_height = $cfg->image->max_height;
					$new_width = round($cfg->image->max_height * $ratio,0);
				}								
				$thumb_height = $cfg->image->max_thumb_height;
				$thumb_width = round($cfg->image->max_thumb_height * $ratio,0);	
			}
			
			$arg = split("/",$imgdest);
			$i = count($arg) - 1;
			if($i >= 0) {
				$thumb_src = $cfg->image->thumbnail_path . $arg[$i];
			} else { 
				$thumb_src = $cfg->image->thumbnail_path . $imgdest;
			}
			
			switch($imgtype) {
				case "image/jpeg":
					$res = webImage::copyJPEG($imgsrc, $width, $height, $imgdest, $new_width, $new_height, $cfg->image->max_size);
					if($res > 0) {
						webImage::copyJPEG($imgdest, $new_width, $new_height, $thumb_src, $thumb_width, $thumb_height, $cfg->image->max_thumb_size);
					}
					break;
				case "image/png":
					$res = webImage::copyPNG($imgsrc, $width, $height, $imgdest, $new_width, $new_height, $cfg->image->max_size);
					if($res > 0) {
						webImage::copyPNG($imgdest, $new_width, $new_height, $thumb_src, $thumb_width, $thumb_height, $cfg->image->max_thumb_size);
					}
					break;
				case "image/gif":
					$res = webImage::copyGIF($imgsrc, $width, $height, $imgdest, $new_width, $new_height, $cfg->image->max_size);
					if($res > 0) {
						webImage::copyGIF($imgdest, $new_width, $new_height, $thumb_src, $thumb_width, $thumb_height, $cfg->image->max_thumb_size);
					}
					break;
				default:
					$res = 0;
					break;
			}
			
			return $res;
		}
		
		static function copyJPEG($src, $width, $height, $new_src, $new_width, $new_height, $max_size) {
			$quality = 0;
			
			$new_image = imagecreatetruecolor($new_width, $new_height);
			if($new_image) {

				$image = imagecreatefromjpeg($src);
				if($image) {

					imagecopyresized($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					
					$quality = 99;
					while(imagejpeg($new_image,$new_src,$quality)) {
						clearstatcache();
						$new_size = filesize($new_src);
						if($new_size < $max_size) break;
						if(($new_size / $max_size) > 1.5) {
							$quality-=5;
						} else {
							$quality--;
						}
						if($quality < 20) break;
						//echo "Q=" . $quality . " SZ=" . $new_size . "\n";
					}

					imagedestroy($image);
				}	

				imagedestroy($new_image);
			}
				
			return $quality;
		}

		static function copyPNG($src, $width, $height, $new_src, $new_width, $new_height, $max_size) {
			$quality = 0;

			$new_image = imagecreatetruecolor($new_width, $new_height);
			if($new_image) {
				$image = imagecreatefrompng($src);
				if($image) {
					$res = imagecopyresized($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					$quality = 9;
					while($res = imagepng($new_image,$new_src,$quality)) {
						clearstatcache();
						$new_size = filesize($new_src);
						if($new_size < $max_size) break;
						$quality--;
						if($quality < 1) break;
						//echo "Q=" . $quality . " SZ=" . $new_size . "\n";
					}
					imagedestroy($image);
				}
				imagedestroy($new_image);
			} else {
				var_dump($new_width);
				var_dump($new_height);
			}
				
			return $quality;
		}
		
		static function copyGIF($src, $width, $height, $new_src, $new_width, $new_height, $max_size) {
			$quality = 0;
			
			$new_image = imagecreatetruecolor($new_width, $new_height);
			if($new_image) {
				$image = imagecreatefromgif($src);
				if($image) {
					imagecopyresized($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					
					if(imagegif($new_image,$new_src)) {
						$quality = 100;
					}
					imagedestroy($image);
				}	
				imagedestroy($new_image);
			}
				
			return $quality;
		}
	
		function selectList() {
			global $cfg,$db;

			$_html = "";
			$sel = "";
			if($this->id == 0) $sel = "selected='selected'";
			
			$size = $cfg->image->size("tile");
			$perc = intval($size['percentage']);
			if($perc ==0) $perc = 100;
			
			$sql = "SELECT * FROM webimage WHERE active = 1 ORDER BY id DESC";
			if($result = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($result))
				{	
					$id = intval($row[id]);
					$image = new webImage($id);

					$arg = split("/",$image->src);
					$src = $arg[0] . "/thumbnails/" . $arg[1];
					if(!file_exists($src)) {
						$src = $image->src;
					}
					$_html .= "<option data-img-src='" . $src . "' value='" . $id . "'>" . $image->title . "</option>";

				}
			}

			return $_html;
		}

		function o2a() {
					
			$_arr = array(
				'id' 			=> 	$this->id,
				'title' 		=>	$this->title,				
				'description' 	=>	$this->description,
				'label' 		=>	$this->label,
				'type' 			=>	$this->type,
				'src' 			=>	$this->src ,
				'thumbnail' 	=>	$this->thumbnail,
				'keywords' 		=>	$this->keywords,
				'page' 			=>	$this->page,
				'sortorder'		=>	$this->sortorder,
				'active' 		=>	$this->active,
			);		
			
			return $_arr;
		}

		function update($data) {
			global $clnt, $db;

			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			
			$updated = ",updated=" . time();
			
			if($this->modify) {
				$result['errno'] = -1;
				$result['errmsg'] =  "Geen authorisatie";

				return $result;
			}

			if($this->id == 0) {
				if(!$this->modify) return "Toevoegen NIET mogelijk";

				$sql = "INSERT INTO webimage (label) VALUES ('new')";
				$res = $db->sql_query($sql);
				if($res) { 
					$this->id = intval($db->sql_nextid());
				}	
			}

			if($this->id > 0) {
				$where = "id=" . $this->id;
				
				if(array_key_exists("title",$data)) {
					$set = "title='" . utf8_decode($data['title']) . "'" . $updated;
					$result = $db->sql_update("webimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("description",$data)) {
					$set = "description='" . utf8_decode($data['description']) . "'" . $updated;
					$result = $db->sql_update("webimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("src",$data)) {
					$set = "src='" . utf8_decode($data['src']) . "'" . $updated;
					$result = $db->sql_update("webimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("label",$data)) {
					$set = "label='" . utf8_decode($data['label']) . "'" . $updated;
					$result = $db->sql_update("webimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("type",$data)) {
					$set = "type='" . $data['type'] . "'";
					$result = $db->sql_update("webimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("keywords",$data)) {
					$set = "keywords='" . utf8_decode($data['keywords']) . "'";
					$result = $db->sql_update("webimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("page",$data)) {
					$set = "page='" . $data['page'] . "'";
					$result = $db->sql_update("webimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("sortorder",$data)) {
					$set = "sortorder='" . $data['sortorder'] . "'";
					$result = $db->sql_update("webimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("active",$data)) {
					$set = "active=" . (($data['active'] == "on") ? 1 : 0) . $updated; 
					$result = $db->sql_update("webimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
			}

			return $result;
		}
		
		function updatePages($list) {
			global $db, $clnt;
			
			$result['errmsg'] = $list;
			
			$pages = split(",",$list);
			
			foreach($pages as $pg) {
				list($pid,$iid,$sort) = split(":",$pg);
				
				$where = "id=" . $iid;
				$set = "page=" . intval($pid) . " ,sortorder=" . $sort;
				$result = $db->sql_update("webimage",$where,$set);
			}

			return $result;
		}
	

    } // class webImage

    class webPage {
		var $id;
		var $keywords;
		var $description;
		var $title;
		var $name;
		var $label;
		var $note;
		var $link;
		var $article;
		var $active;
		var $parent;
		var $sortorder;
		var $created;
		var $updated;

		//
		// Constructor
		//
		function webPage($pid) {
			global $db,$clnt,$cfg;

			if(!is_object($db)) $db = new database();

			$this->id = 0;
			$this->pid = $pid;
			$this->name = "";
			$this->label = "";
			$this->note = "";
			$this->title = "";
			$this->parent = 0;
			$this->link = "";
			$this->active = 1;
			$this->description = "";
			
			$this->article = null;
				$row = $this->find($pid);
			if($row) {	
				$this->load($row);
			}
		}
		
		function find($pid) {
			global $db,$clnt;

			if(is_numeric($pid)) {
				if($pid == 0) {
					return false;
				}
				
				$where = "id = " . $pid;
				$sql = "SELECT * FROM webpage WHERE id=" . $pid;
				if($result = $db->sql_query($sql)) {
					$row = $db->sql_fetchrow($result);
					if($row) {
						return $row;
					}
				}
			} 

			$pdir = explode("/",$pid);
			$idx = count($pdir) - 1;
			$pid = $pdir[$idx];
			
			if(strlen($pid) > 0) {
				$where = "label = '" . $pid . "'";
				$sql = "SELECT * FROM webpage WHERE " . $where . " ORDER BY id";
				if($result = $db->sql_query($sql)) {
					$row = $db->sql_fetchrow($result);
					if($row) {
						return $row;
					}
				}	
			
				$where = "keywords LIKE '%" . $clnt->request . "%'";
				$sql = "SELECT * FROM webpage WHERE " . $where . " ORDER BY id";
				if($result = $db->sql_query($sql)) {
					$row = $db->sql_fetchrow($result);
					if($row) {
						return $row;
					} 
				}
			}
			
			$sql = "SELECT * FROM webpage ORDER BY id";
			if($result = $db->sql_query($sql)) {
				$row = $db->sql_fetchrow($result);
				if($row) {
					return $row;
				} 
			}

			return false;
		}
		
		function load($row) {
			global $cfg;
			
			if(is_array($row)) {	
				$this->id = intval($row['id']);
				$this->name = utf8_encode($row['name']);
				$this->title = utf8_encode($row['title']);
				$this->label = utf8_encode($row['label']);
				$this->content = utf8_encode($row['content']);
				$this->note = utf8_encode($row['note']);
				$this->keywords = utf8_encode($row['keywords']);
				$this->active = intval($row['active']);
				$this->parent = intval($row['parent']);
				$this->sortorder = intval($row['sortorder']);
				
				$this->article = new webArticle($row);
				
				if(($cfg->use_page_label) && (strlen($this->label) > 0)) {
					$this->link = $cfg->siteurl . "/" . $this->label;
				} else {
					$this->link = $cfg->siteurl . "/" . $cfg->siteindex;
					if($this->id > 0) {
						if($this->id == $cfg->home) {
							$this->link = $cfg->siteurl . "/";
						} else {
							$this->link .= "?p=" . $this->id;
						}
					}
				}
				
				$this->description = $cfg->sitename . ", " . $this->title;
				
				$this->created = $row['created'];
				$this->updated = strftime("%d-%m-%y %H:%M:%S",intval($row['updated']));
			}
		}
		
		static function create($parent) {
			global $db, $cfg;
			
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;
				
			$so = 1;
			
			$sql = "SELECT sortorder from webpage WHERE parent=" . $parent . " ORDER BY sortorder DESC";
			$res = $db->sql_query($sql);
			if( $row = $db->sql_fetchrow($res) )	{
				$so = intval($row[sortorder]) + 1;
			}
			
			$sql = "INSERT INTO webpage (title, parent, sortorder) VALUES ('Nieuw','$parent','$so')";
			$res = $db->sql_query($sql);
			if($res) { 
				$pid = intval($db->sql_nextid());
				if($pid > 0) {
					$result['errmsg'] = "OK";		
					$result['id'] = $pid;
				}
			}
			
			return $result;
		}
		
		function sections() {
			global $db;
			
			$list = array();
				
			$sql = "SELECT * FROM webpage WHERE parent = " . $this->id . " ORDER BY sortorder";
			if($result = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($result)) {	
					$sect = new webSection($row);
					if(is_object($sect)) {
						array_push($list,$sect);
					}
				}
			}
			
			return $list;
		}

		function images($all=true) {
			global $db;
			
			$list = array();
			if($all) {	
				$sql = "SELECT * FROM webimage WHERE page = " . $this->id . " ORDER BY sortorder";
			} else {
				$sql = "SELECT * FROM webimage WHERE page = " . $this->id . " AND active=1 ORDER BY sortorder";
			}
			if($result = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($result)) {	
					$img = new webImage(0);
					if(is_object($img)) {
						$img->load($row);
						array_push($list,$img);
					}
				}
			}
				
			return $list;
		}
		
		function menu($p = 0) {
			global $db;
			
			if(!is_object($db)) {
				$db = new database();
			}

			$items = array();
			if($p < 1) {
				$p = $this->id;
			}
			
			$where = "parent=" . $p;
			$sql = "SELECT * FROM webpage WHERE $where ORDER BY sortorder";
			if($result = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($result)) {	
				$subpage = new webPage(0);
					if(is_object($subpage)) {
						$subpage->load($row);
						array_push($items,$subpage);
					}
				}
			}
			
			return $items;
		}
		
		function alist($p = 0) {
			global $db;
			
			if(!is_object($db)) {
				$db = new database();
			}

			$items = array();
			if($p < 1) {
				$p = $this->id;
			}

			$where = "";
			
			if($p > 0) {
				$where = "parent=" . $p;
			}
			
			$sql = "SELECT * FROM webpage WHERE $where ORDER BY name";
			if($result = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($result)) {	
				$subpage = new webPage(0);
					if(is_object($subpage)) {
						$subpage->load($row);
						array_push($items,$subpage);
					}
				}
			}
			
			return $items;
		}
		

		function o2a() {
			global $db;
			
			$_arr = array(
				'id' 		=> 	$this->id, 
				'name' 		=>	$this->name,
				'label' 	=>	$this->label,
				'title' 	=>	$this->title,
				'keywords' 	=>	$this->keywords,
				'note' 		=>	$this->note,
				'parent' 	=>	$this->parent,
				'sortorder'	=>	$this->sortorder,
				'active' 	=>	$this->active,

			);		
			
			return $_arr;
		}

		function update($data) {
			global $clnt, $db;

			$result = array();

			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['data'] = array();

			if(!$clnt->adminEditor) {
				$result['errno'] = -1;
				$result['errmsg'] =  "Geen authorisatie";

				return $result;
			}

			if($this->id == 0) {
				if(!$clnt->adminMaster) return "Toevoegen NIET mogelijk";

				$sql = "INSERT INTO webpage (label) VALUES ('new')";
				$res = $db->sql_query($sql);
				if($res) { 
					$this->id = intval($db->sql_nextid());
				}	
			}
			
			$updated = ",updated=" . time();

			if($this->id > 0) {
				$where = "id=" . $this->id;

				if(array_key_exists("name",$data)) {
					$this->backup();
					$this->name = utf8_decode($data['name']);
					if(strlen($this->name) == 0 ) {
						$this->name = "Pagina-" . $this->id;
					}
					$set = "name='" . $this->name . "'" . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("title",$data)) {
					$this->backup();
					$title = utf8_decode($data['title']);
					if(strlen($title) == 0 ) {
						$title = $this->name;
					}
					$set = "title='" . $title . "'" . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("label",$data)) {
					$label = utf8_decode($data['label']);
					if(strlen($label) == 0 ) {
						$label = utf8_decode(strtolower($this->name));
					}		
					$set = "label='" . $label . "'" . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("note",$data)) {
					$set = "note='" . utf8_decode($data['note']) . "'" . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("content",$data)) {
					$set = "content='" . utf8_decode($data['content']) . "'" . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("image",$data)) {
					$set = "image=" . intval($data['image']) . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("active",$data)) {
					$set = "active=" . (($data['active'] == "on") ? 1 : 0) . $updated; 
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("keywords",$data)) {
					$set = "keywords='" . utf8_decode($data['keywords']) . "'" . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("menulist",$data)) {
					$menulist = utf8_decode($data['menulist']);
					$result = $this->updateMenu($menulist);
				}
				
				if(array_key_exists("images",$data)) {
					$imagelist = utf8_decode($data['images']);
					$result['errmsg'] = $imagelist;
					$images = split(",",$imagelist);
					
					foreach($images as $img) {
						list($pid,$iid,$sort) = split(":",$img);
						if($pid == $this->id) {
							$set = "page=" . intval($pid) . " ,sortorder=" . $sort;
							$result = $db->sql_update("webimage","id=".$iid,$set);
						
							if($sort == 1) {
								$set = "image=" . intval($iid);
								$res = $db->sql_update("webpage",$where,$set);
							}  
						}
					}
				}
				
				if(array_key_exists("parent",$data)) {
					$set = "parent=" . intval($data['parent']);
					$result = $db->sql_update("webpage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("pagelink",$data)) {
					$set = "pagelink=" . intval($data['pagelink']) . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("extlink",$data)) {
					$set = "extlink='" . utf8_decode($data['extlink']) . "'" . $updated;
					$result = $db->sql_update("webpage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
			}

			return $result;
		}

		function updateMenu($list) {
			global $db,$clnt;
			
			$newpage = array();
			$result['errmsg'] = $list;
						
			$pages = split(",",$list);
			
			foreach($pages as $pg) {
				list($pid,$parent,$sort) = split(":",$pg);
				
				if($parent > 0) {
					list($label,$index) = split("-",$pid);
					if($label == "new") {
						$newpage[$index] = 0;

						if($clnt->adminMaster) {
							$sql = "INSERT INTO webpage (label,name,title) VALUES ('nieuw','Nieuw','Nieuw ...')";
							$res = $db->sql_query($sql);
							if($res) { 
								$newpage[$index] = intval($db->sql_nextid());
								$where = "id=" . $newpage[$index];
								$set = "label='nieuw-" . $newpage[$index] . "'";
								$db->sql_update("webpage",$where,$set);
							}	
						}
					}
				}
			}
		
			foreach($pages as $pg) {
				list($pid,$parent,$sort) = split(":",$pg);
				
				list($label,$index) = split("-",$pid);
				if($label == "new" ) {
					$pid = $newpage[$index];
				}
				
				if(intval($pid) > 0 ) {
					list($label,$index) = split("-",$parent);
					if($label == "new" ) {
						$parent = $newpage[$index];
					}
					
					$where = "id=" . $pid;
					$set = "parent=" . intval($parent) . " ,sortorder=" . $sort;
					$result = $db->sql_update("webpage",$where,$set);
				}
				
				if($result['errno'] > 0) {
					break;
				}
			}

			return $result;
		}  
		
		function backup() {
			global $db;

			$sql = "INSERT INTO webbackup (webtable, id, htmltext) VALUES ('webpage', " . $this->id . ", '" . utf8_decode($this->note) . "')";
			return $db->sql_query($sql);
		}

		function setImage($img) {
			global $clnt, $db;

			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
	
			if(!$clnt->adminEditor) {
				$result['errno'] = -1;
				$result['errmsg'] =  "Geen authorisatie";

				return $result;
			}

			if($this->id > 0) {
				$where = "id=" . $this->id;
				$set = "image=" . intval($img);
				$result = $db->sql_update("webpage",$where,$set);

				return $result;
			}
		}

	} // class webPage

} // if ... define

?>