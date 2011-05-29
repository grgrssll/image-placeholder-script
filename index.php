<?php
/*
 * Developer : Greg Russell (grgrssll@gmail.com)
 * Date : 05/28/2011
 * All code © 2011 grgrssll.com all rights reserved
 * 
 * Script: index.php
 * Description: creates a sized and colored image based on GET params
 * Version 1.0.2
 * Last Revision: 05/29/2011 01:38:34
 * 
 */

#TODO 
# refill inputs on validate (js) incase bad data is entered correct it
# allow for selection of preselected colors in tool
# expand preselected color library (necessary?)
 
date_default_timezone_set('America/Los_Angeles');

class Img {

	private $colors = array(
		'w'  => array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 100),	#white
		'k'  => array('r' => 0,   'g' => 0,   'b' => 0,   'a' => 100),	#black
		'e'  => array('r' => 127, 'g' => 127, 'b' => 127, 'a' => 100),	#grey
		'le' => array('r' => 190, 'g' => 190, 'b' => 190, 'a' => 100),	#light grey
		'de' => array('r' => 63,  'g' => 63,  'b' => 63,  'a' => 100),	#dark grey
		'r'  => array('r' => 255, 'g' => 32,  'b' => 0,   'a' => 100),	#red
		'g'  => array('r' => 0,   'g' => 223, 'b' => 0,   'a' => 100),	#green
		'b'  => array('r' => 0,   'g' => 96,  'b' => 255, 'a' => 100),	#blue
		'db' => array('r' => 0,   'g' => 48,  'b' => 190, 'a' => 100),	#dark blue
		'y'  => array('r' => 234, 'g' => 234, 'b' => 0,   'a' => 100),	#yellow
		'o'  => array('r' => 255, 'g' => 127, 'b' => 0,   'a' => 100),	#orange
		'p'  => array('r' => 234, 'g' => 0,   'b' => 234, 'a' => 100),	#purple
	);

	private $allowed = array( 
		't'     => 'text', 
		'f'     => 'font', 
		'd'     => 'dimensions', 
		'bg'    => 'background', 
		'c'     => 'color', 
		'br'		=> 'radius',
		'cache' => 'cache', 
		'debug' => 'debug' 
	);
	
	private $defaults;
	private $options;
	private $hash;
	private $memcache;

	public function __construct($input){

		if(!extension_loaded('gd')){ die('GD Library is required for this script'); }
		
		$this->defaults = array(
			'text'			=> 'wxh',
			'font'			=> 4,
			'dimensions'		=> array('w' => 100, 'h' => 100),
			'background'		=> $this->colors['de'],
			'color'			=> $this->colors['g'],
			'radius'			=> 0,
			'cache'			=> 1,
			'debug'			=> 0
		);

		$options = $this->validateInput($input);
		$this->options = $this->validate($options);
		$this->cache();
		$this->headers();
		$this->img();
	}

	private function debug($data){ header('Content-type: text/html');var_dump($data); }

	private function headers(){
		if($this->options['debug']){
			header('Content-type: text/html');
		}else{
			header('Content-type: image/png');
			header("Cache-Control: private, max-age=604800, pre-check=604800");
			header("Pragma: private");
			header("Expires: " . date(DATE_RFC822, time() + 604800));
			if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
				header('Last-Modified: '.$_SERVER['HTTP_IF_MODIFIED_SINCE'], true, 304);
				die();
			}
		}
	}

	private function cache(){
		
		if($this->options['cache'] && extension_loaded('memcache')){
			$this->memcache = new Memcache();			
			$this->memcache->connect('127.0.0.1', 11211);
			$this->hashify();
		}else{
			$this->memcache = false;
		}
	}

	private function hashify(){
		$this->hash = md5(json_encode($this->options));
	}

	private function filter($key, $value){
		$filter = '';
		switch($key){
			case 'text':
				$filter = trim(urldecode($value));
				break;
			case 'w':
			case 'h':
				$i = intval($value);
				$filter = ($i > 1) ? $i : 1;
				break;
			case 'r':
			case 'g':
			case 'b':
				$i = intval($value);
				$filter = ($i < 256 && $i > -1) ? $i : 48;
				break;
			case 'a':
				$i = intval($value);
				$filter = ($i < 100 && $i > -1) ? $i : 100;
				break;
			case 'font':
				$i = intval($value);
				$filter = ($i < 6 && $i > -1) ? $i : 4;
				break;
			case 'radius':
				$i = intval($value);
				$filter = ($i < 51 && $i > -1) ? $i : 0;
				break;
			case 'cache':
				$filter = ($value) ? true : false;
				break;
			case 'debug':
				$filter = ($value) ? true : false;
				break;
		}
		return $filter;
	}

	private function Validate($options){
		
		$validated = $this->defaults;
		if(is_array($options)){
			foreach($this->defaults as $k => $v){
				if(is_array($v) && isset($options[$k]) && is_array($options[$k])){
					foreach($v as $kk => $vv){ 
						if(array_key_exists($kk, $options[$k])){ $validated[$k][$kk] = $this->filter($kk, $options[$k][$kk]); }
					}
				}else{
					if(array_key_exists($k, $options)){ $validated[$k] = $this->filter($k, $options[$k]); }
				}
			}
		}
		return $validated;
	}

	private function isValid($image){
		return (is_resource($image) && get_resource_type($image) === 'gd') ? true : false;
	}
	
	private function	 build(){

		$opts = $this->options;
		$image = imagecreatetruecolor($opts['dimensions']['w'], $opts['dimensions']['h']);
		if($this->isValid($image)){
			imagealphablending($image,  true);
			$alpha = imagecolorallocatealpha($image, 0, 0, 0, 127);
			imagefill($image, 0, 0, $alpha);
			$bg = $opts['background'];
			if($bg['a'] < 100){
				$alpha = intval(round(((100 - $bg['a']) * 127) / 100));
				$background = imagecolorallocatealpha($image, $bg['r'], $bg['g'], $bg['b'], $alpha);
			}else{
				$background = imagecolorallocate($image, $bg['r'], $bg['g'], $bg['b']);
			}
			if($opts['radius']){
				if($opts['dimensions']['w'] > $opts['dimensions']['h']){
					$rad =  intval(ceil($opts['dimensions']['h'] * ($opts['radius'] / 100)));
				}else{
					$rad =  intval(ceil($opts['dimensions']['w'] * ($opts['radius'] / 100)));
				}
				$circ = $rad * 2;
				$farx = $opts['dimensions']['w'] - $rad;
				$fary = $opts['dimensions']['h'] - $rad;
				imagefilledrectangle($image, $rad, 0, $farx, $opts['dimensions']['h'], $background); 
				imagefilledrectangle($image, 0, $rad, $opts['dimensions']['w'], $fary, $background); 
				imagefilledellipse($image, $rad, $rad, $circ, $circ, $background); #nw corner
				imagefilledellipse($image, $farx, $rad, $circ, $circ, $background); #ne corner
				imagefilledellipse($image, $farx, $fary, $circ, $circ, $background); #sw corner
				imagefilledellipse($image, $rad, $fary, $circ, $circ, $background); #se corner
			}else{
				imagefill($image, 0, 0, $background);
			}
			$text = str_replace('wxh', $opts['dimensions']['w'].'x'.$opts['dimensions']['h'], $opts['text']);
			$length = strlen($text);
			if($length){
				$fontSize = array(
					'w' => imagefontwidth($opts['font']),
					'h' => imagefontheight($opts['font'])
				);
				$textPosition = array(
					'x' => floor(($opts['dimensions']['w'] - ($fontSize['w'] * $length)) / 2),
					'y' => floor(($opts['dimensions']['h'] - $fontSize['h']) / 2)
				);
				$c = $opts['color'];
				if($c['a'] < 100){
					$alpha = intval(round(((100 - $c['a']) * 127) / 100));
					$color = imagecolorallocatealpha($image, $c['r'], $c['g'], $c['b'], $alpha);
				}else{
					$color = imagecolorallocate($image, $c['r'], $c['g'], $c['b']);
				}
				imagestring($image, $opts['font'], $textPosition['x'], $textPosition['y'], $text, $color);
			}
			imagealphablending($image, false);
			imagesavealpha($image, true);
		}
		return $image;
	}

	private function get(){

		$image = false;
		if($this->memcache){ $image = $this->memcache->get($this->hash); }
		if(!$this->isValid($image)){
			$image = $this->build();
			if($this->memcache){ $this->memcache->set($this->hash, $image); }
		}
		return $image;
	}

	private function img(){
		
		$image = $this->get();
		if($this->isValid($image)){	
			imagepng($image);
			imagedestroy($image);
			return true;
		}
		return false;
	}

	private function filterInput($key, $value){

		switch($key){
			case 'dimensions':
				if(strpos($value, 'x')){
					list($width, $height) = explode('x', $value);
					$filtered = array('w' => $width,'h' => $height);
				}else{
					$i = intval($value);
					$filtered = array('w' => $i,'h' => $i);
				}
				break;
			case 'background':
			case 'color':
				if(!(strpos($value, ',') === false)){
					list($r, $g, $b, $a) = explode(',', $value);
					$filtered = array('r' => $r, 'g' => $g, 'b' => $b, 'a' => $a);
				}else{
					$filtered = (array_key_exists($value, $this->colors)) ? $this->colors[$value] : $this->defaults['background'];
				}
				break;
			default:
				$filtered = $value;
				break;
		}
		return $filtered;
	}

	private function validateInput($input){

		if(array_key_exists('help', $input) || empty($input)){
			$this->help();
		}
		$options = array();
		foreach($input as $k => $v){
			if(array_key_exists($k, $this->allowed)){
				$options[$this->allowed[$k]] = $this->filterInput($this->allowed[$k], $v);
			}
		}
		return $options;
	}

	private $manual = array( 
		't'     => 'text to display: <strong>wxh</strong> converted into dimensions. example: 420x259 <br/>default value: wxh',
		'f'     => 'font size: (1 - 5) from GD image library<br/>default value: 4',
		'd'     => 'dimensions: (<em>width</em><strong>x</strong><em>height</em> | <em>square</em>)<br/>default value: 100x100 | 100',
		'bg'    => 'background: r [0-255], g [0-255], b [0-255], a[0-100] | color code <em>see Colors</em><br/>default value: 63,63,63,100 | de',
		'c'     => 'font color: r [0-255], g [0-255], b [0-255], a[0-100] | color code <em>see Colors</em><br/>default value: 0,223,0,100 | g',
		'br'     => 'border-radius: 0-5- % for rounded corners',
		'cache' => 'enable caching (1 | 0)<br/>default value: 1',
		'debug' => 'enable debugging (1 | 0)<br/>default value: 0' 
	);

	private function help(){
		
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https' : 'http';
		$base = $protocol.'://'.$_SERVER['SERVER_NAME'].str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
	
		$html = '<!DOCTYPE html><html lang="en"><head><title>grgr.us | placeholder image creator</title><meta charset="utf-8"><meta name="keywords" content=""><meta name="description" content=""><link rel="shortcut icon" href="?d=16&bg=de&t=gr&c=g&f=2"><script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
<style>
html, body, div, span, object, iframe,h1, h2, h3, h4, h5, h6, p, blockquote, pre,abbr, address, cite, code,del, dfn, em, img, ins, kbd, q, samp,small, strong, sub, sup, var,b, i,dl, dt, dd, ol, ul, li,fieldset, form, label, legend,table, caption, tbody, tfoot, thead, tr, th, td,article, aside, canvas, details, figcaption, figure, footer, header, hgroup, menu, nav, section, summary,time, mark, audio, vide {margin: 0;padding: 0;border: 0;outline: 0;text-decoration: none;font-style: normal;font-weight: normal;font-size: 100%;vertical-align: baseline;background: transparent;font-family:Helvetica, Arial, sans-serif;
color:#222;}
input,select{font-family:Helvetica, Arial, sans-serif;}
body, html{width:100%;height:100%;background:#fcfcfc;}
article,aside,canvas,details,figcaption,figure,footer,header,hgroup,menu,nav,section,summary { 
display:block;}
nav ul{list-style:none;}
body {line-height: 1; font-size: 10px; font-family: Helvetica,Arial,sans-serif;}
ol,ul {list-style: none;}
blockquote,q {quotes: none;}
blockquote:before,blockquote:after,q:before,q:after {content: ""; content: none;}
input:focus {outline: 1px #f20 solid;background-color: #ddd;border:0;}
#info input:focus{outline:0;background:#fff;}
ins {text-decoration: none; background-color: yellow;}
del {text-decoration: line-through;}
table {border-collapse: collapse; border-spacing: 0;}
a,a:link,a:visited,a:hover,a:active,a:focus{cursor:pointer;text-decoration:none;color:#06f;}
#wrapper{margin: 0 auto;position:relative;width:960px;padding:20px;}
#nav{border-bottom:1px #777 solid;padding-bottom:14px;}
#nav ul{overflow:hidden;margin-top:10px;}
#nav li{float:left;}
#nav li a{display:block;padding:4px 8px;font-size:14px;color:#777;}
#nav li a:hover{background:#ddd;}
#nav li a.selected{color:#06f;}
#nav h1{color:#666;font-size:22px;}
#nav h1 span{font-size:14px;color:#777;}
#nav h1 span a{font-size:14px;color:#06f;}
.page{margin:20px 0;display:none;overflow:hidden;}
#page_tool{display:block;}
.page li,table{color:#777;font-family:droid sans mono, monospace;font-size:14px;border:1px #ddd solid;padding:4px;margin-bottom:20px;width:960px;}
.page li{margin-bottom:10px;width:950px;}
.page li img{margin-top:4px;}
td{color:#777;padding:4px 20px 4px 8px;vertical-align:top;}
td.t-key,th.t-key{color:#444;font-weight:bold;width:100px;}
th.t-key,th{background:#444;color:#fff;padding:4px 20px 4px 8px;font-weight:bold;text-align:left;}
td.t-key td.t-key{color:#666; font-weight:bold;}
td strong{color:#444;font-weight:bold;}
td em{color:#444;font-style:italic;}
td table{background:#eee;width:auto;margin-bottom:0;}
tr:nth-child(2n-1) td{background:#f5f5f5;}
.page h2{color:#777;font-size:18px;font-weight:bold;margin-bottom:14px;}
.page p{color:#777;font-size:14px;margin:10px 0;}
strong{color:inherit;font-weight:bold;}
#footer{position:absolute;bottom:0;right:10px;font-size:12px;margin:0;}
#footer,#footer a{color:#777;}
#footer a:hover{color:#0d0;}
.element{overflow:hidden;margin-bottom:10px;padding:1px 0;}
.element label{width:60px;display:block;color:#777;font-size:12px;float:left;margin-top:6px;}
.element.g-col label{clear:left;}
#link,.element select,.element input{display:block;color:#777;font-size:14px;font-family:droid sans mono, monospace;float:left;border:1px #ccc solid;padding:4px;margin-right:20px;margin-bottom:4px;}
.element select{padding:2px;width:60px;margin-bottom:0;}
.element.g-dim input{width:60px;margin-bottom:0;}
.element.g-col input{width:200px;margin-right:10px;}
#link,.element.g-copy input{width:446px;margin-right:0;margin-bottom:0;}
#link{width:950px;height:16px;float:none;margin-bottom:14px;}
p.hint{font-size:12px;color:#777;float:left;margin:3px 0 0 8px;}
.submit input{border:1px #bbb solid;color:#555;text-shadow:#eee 1px 1px 1px;font-family:helvetica, arial, sans-serif;font-size:14px;display:block;-border-radius:3px;-webkit-border-radius:3px;-moz-border-radius:3px;cursor:pointer;padding:4px 8px 2px 8px;background:#ccc;background: -webkit-gradient(linear, left top, left bottom, from(#eee), to(#ccc));background: -moz-linear-gradient(top,  #eee,  #ccc); }
.submit input:hover{color:#111;background:#777;background: -webkit-gradient(linear, left top, left bottom, from(#ccc), to(#eee));background: -moz-linear-gradient(top,  #ccc,  #eee); }
h3{font-size:16px;color:#999;font-weight:bold;margin-bottom:10px;}
.group{border:1px #e7e7e7 solid;background:#f7f7f7;float:left;padding:10px;height:248px;margin-left:20px;margin-bottom:20px;}
.result{clear:both;}
#first_group{width:380px;margin-left:0;}
#second_group{width:516px;}
p.value{font-size:12px;float:left;color:#777;width:60px;text-align:right;position:relative;top:-2px;}
p.value.red{color:#f20;}
p.value.green{color:#0b0;}
p.value.blue{color:#05d;}
::selection {color:#0d0;}
.element.g-rad label{ width:95px;}
.element.g-rad input{ width:155px;margin-bottom:0;}
.element.g-rad{margin-bottom:0;}
</style>
<script>
function Builder(){
	var t = this;
	this.params = {
		"d"  : 100,
		"f"  : "4",
		"t"  : "wxh"	,
		"bg" : { "r" : 48, "g" : 48, "b" : 48, "a" : 100 },
		"c"  : { "r" : 0, "g" : 232, "b" : 0, "a" : 100 },
		"br" : 0
	};
	this.URL = {
		"base" : "'.$base.'",
		"set" : function(){
			t.params.d    = t.validate("dim",$("#i_width").val()+"x"+$("#i_height").val());
			t.params.bg.r = t.validate("color",$("#i_bgr").val());
			t.params.bg.g = t.validate("color",$("#i_bgg").val());
			t.params.bg.b = t.validate("color",$("#i_bgb").val());
			t.params.bg.a = t.validate("alpha",$("#i_bga").val());
			t.params.c.r  = t.validate("color",$("#i_cr").val());
			t.params.c.g  = t.validate("color",$("#i_cg").val());
			t.params.c.b  = t.validate("color",$("#i_cb").val());
			t.params.c.a  = t.validate("alpha",$("#i_ca").val());
			t.params.t    = t.validate("text",$("#i_text").val());
			t.params.f    = t.validate("font",$("#i_fontsize").val());
			t.params.br    = t.validate("rad",$("#i_rad").val());
			$("p.bg.red").html(t.params.bg.r+" / 255");
			$("p.bg.green").html(t.params.bg.g+" / 255");
			$("p.bg.blue").html(t.params.bg.b+" / 255");
			$("p.bg.alpha").html(t.params.bg.a+"%");
			$("p.col.red").html(t.params.c.r+" / 255");
			$("p.col.green").html(t.params.c.g+" / 255");
			$("p.col.blue").html(t.params.c.b+" / 255");
			$("p.col.alpha").html(t.params.c.a+"%");
			$("p.rad").html(t.params.br+"%");
		},
		"toString" : function(){
			t.URL.set();
			var string = "?";
			for(var k in t.params){
				if(k === "bg" || k === "c"){
					var color = k+"=";
					for(var j in t.params[k]){ color += t.params[k][j]+","; }
					string += color.slice(0,-1)+"&";
				}else{ string += k+"="+t.params[k]+"&"; }
			}
			return string.slice(0,-1);
		},
		"full" : function(){ return t.URL.base + t.URL.toString(); }
	};
	this.build = function(){ var url = t.URL.full(); $("#link").val(url); $("#image").attr("src",url); };
	this.validate = function(type, value){
		var result = false;
		switch(type){
			case "dim" : 
				var v = value.split("x"); result = parseInt(v[0])+"x"+parseInt(v[1]); break;
			case "color" :
				var v = parseInt(value); result = (v < 0) ? 48 : ((v > 255) ? 48 : v); break;
			case "alpha" : 
				var v = parseInt(value); result = (v < 0) ? 100 : ((v > 100) ? 100 : v); break;
			case "text" : 
				result = encodeURIComponent(value); break;
			case "font" : 
				var v = parseInt(value); result = (value) ? value : 4; break;
			case "rad" : 
				var v = parseInt(value); result = (v < 0) ? 0 : ((v > 50) ? 50 : v); break;
		}
		return result;
	};
	this.init = function(){
		var test = document.getElementById("i_bgr");
		if(test.type == "text"){ $(".element.g-col input, .element.g-rad input").css("width","60px"); }
		$(".element input, .element select").bind("keyup change mouseup", function(){ t.build(); });
		$("#link").bind("click", function(){ this.select(); });
		t.build();
	};
};
$(document).ready(function(){
	var b = new Builder().init();
	function size(){
		var height = $(window).height();
		var wrapper = $("#wrapper");
		if(wrapper.height() < height - 40){ wrapper.css("height",height - 44+"px"); }
	}
	$(window).bind("resize", function(){ size(); });
	$("#nav li a").live("click", function(){
		var id = $(this).attr("id").split("_")[1];
		$("#nav li a").removeClass("selected");
		$(this).addClass("selected");
		$(".page").hide();
		$("#page_"+id).show();
		size();
	});
	size();
});
</script></head><body><div id="wrapper"><div id="nav"><h1>Placeholder Image Creator <span>by <a href="http://www.grgrssll.com">Greg Russell</a> &#8226; <a target="_blank" href="https://github.com/grgrssll/image-placeholder-script">Download on github</a></span></h1><ul><li><a class="selected" id="p_tool">Tool</a></li><li><a id="p_manual">Man Page</a></li><li><a id="p_defaults">Defaults</a></li><li><a id="p_colors">Colors</a></li></ul></div><div id="page_tool" class="page"><div class="group" id="first_group"><h3>Dimensions</h3><div class="sub-group"><div class="element g-dim"><label for="i_width">Width</label> <input type="number" id="i_width" value="100"/><label for="i_height">Height</label> <input type="number" id="i_height" value="100"/></div></div><div class="sub-group" class="experimental"><div class="element g-rad"><label for="i_rad">Border Radius</label> <input type="range" id="i_rad" value="0" min="0" max="50"/><p class="value rad">0%</p></div></div><h3>Background</h3><div class="element g-col"><label for="i_bgr">Red</label> <input type="range" id="i_bgr" value="48" min="0" max="255"/><p class="value red bg">48 / 255</p><label for="i_bgg">Green</label> <input type="range" id="i_bgg" value="48" min="0" max="255"/><p class="value green bg">48 / 255</p><label for="i_bgb">Blue</label> <input type="range" id="i_bgb" value="48" min="0" max="255"/><p class="value blue bg">48 / 255</p><label for="i_bga">Opacity</label> <input type="range" id="i_bga" value="100" min="0" max="100"/><p class="value alpha bg">100%</p></div></div><div class="group" id="second_group"><h3>Text</h3><div clas="sub-group"><div class="element g-copy"><label for="i_text">Copy</label> <input type="text" id="i_text" value="wxh"/></div></div><div clas="sub-group"><div class="element g-sel"><label for="i_fontsize">Font Size</label> <select id="i_fontsize"><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4" selected="selected">4</option><option value="5">5</option></select></div></div><div clas="sub-group"><h3>Font Color</h3><div class="element g-col"><label for="i_cr">Red</label> <input type="range" id="i_cr" value="0" min="0" max="255"/><p class="value red col">0 / 255</p><label for="i_cg">Green</label> <input type="range" id="i_cg" value="232" min="0" max="255"/><p class="value green col">232 / 255</p><label for="i_cb">Blue</label> <input type="range" id="i_cb" value="0" min="0" max="255"/><p class="value blue col">0 / 255</p><label for="i_ca">Opacity</label> <input type="range" id="i_ca" value="100" min="0" max="100"/><p class="value alpha col">100%</p></div></div></div><div class="result"><textarea id="link"></textarea><img id="image" /></div></div><div id="page_manual" class="page"><table cellspacing="0" cellpadding="0"><tr><th class="t-key">key</th><th class="t-value">value</th></tr>';

		foreach($this->manual as $k => $v){ $html .= '<tr><td class="t-key">'.$k.'</td><td>'.$v.'</td></tr>'; }

		$html .= '</table><h2>Examples</h2><ul><li>?d=444x44&bg=0,48,112,55&c=234,234,0,100&f=5&t=wxh%20Hello%20World<br/><img src="?d=444x44&bg=0,48,112,55&c=234,234,0,100&f=5&t=wxh%20Hello%20World"/></li><li>?d=666x24&bg=r&c=w&t=www.grgr.us<br/><img src="?d=666x24&bg=r&c=w&t=www.grgr.us"/></li><li>?d=16&bg=48,48,48,100&t=GR&f=1<br/><img src="?d=16&bg=48,48,48,100&t=GR&f=1"/></li></ul></div><div id="page_defaults" class="page"><table cellspacing="0" cellpadding="0"><tr><th class="t-key">key</th><th class="t-value">value</th></tr>';
	
		foreach($this->defaults as $k => $v){
			$html .= '<tr><td class="t-key">'.$k.'</td>';
			if(is_array($v)){
				$html .= '<td><table cellspacing="0" cellpadding="0"><tr><th class="t-key">key</th><th class="t-value">value</th></tr>';
				foreach($v as $kk => $vv){ $html .= '<tr><td class="t-key">'.$kk.'</td><td>'.$vv.'</td></tr>'; }
				$html .= '</table></td></tr>';
			}else{ $html .= '<td>'.$v.'</td></tr>'; }
		}

		$html .= '</table></div><div id="page_colors" class="page"><p>Colors can be either rgba (rgb = 0-255, a = 0-100), or one of the color codes seen below</p><p><strong>Examples</strong> bg=123,123,123,100&c=0,0,0,100 | bg=de&c=g</p><table cellspacing="0" cellpadding="0"><tr><th class="t-code">code</th><th class="t-rgba">rgba</th><th class="t-color">color</th></tr>';

		foreach($this->colors as $k => $v){
			$alpha = number_format($v['a'] / 100, 1, '.', ',');
			$html .= '<tr><td class="t-key">'.$k.'</td><td>'.$v['r'].','.$v['g'].','.$v['b'].','.$v['a'].'</td><td style="background:rgba('.$v['r'].','.$v['g'].','.$v['b'].','.$alpha.');"></td></tr>';
		}
		
		$html .='</table></div><p id="footer">&copy; 2011 <a href="http://www.grgrssll.com">www.grgrssll.com</a></p></div></body></html>';
		header('Content-type: text/html');
		echo $html;
		die();
	}
}

$image = new Img($_GET);

?>
