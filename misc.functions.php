<?php

function plural($num) {
	if ($num != 1)
		return "s";
}

function getRelativeTime($date) {
	$diff = time() - strtotime($date);

	if ($diff < 60)
		return $diff . " second" . plural($diff) . " ago";

	$diff = round($diff / 60);

	if ($diff < 60)
		return $diff . " minute" . plural($diff) . " ago";

	$diff = round($diff / 60);

	if ($diff < 24)
		return $diff . " hour" . plural($diff) . " ago";

	$diff = round($diff / 24);

	if ($diff<7)
		return $diff . " day" . plural($diff) . " ago";

    $diff = round($diff/7);

	if ($diff<4)
		return $diff . " week" . plural($diff) . " ago";

    // else
	return "on " . date("F j, Y", strtotime($date));
}

function contains_only_img($src) {
   $src = trim($src);

   $stripped = strip_tags($src);

   if(strlen($stripped) == 0)
      return true;
   else
      return false;
}

function get_fck($name, $width = 570, $height = 220) {
   $fck .= <<<yosup
   <script type="text/javascript">
   var oFCKeditor = new FCKeditor( '{$name}' ) ;
   oFCKeditor.BasePath = "/bugbrowser/inc/fckeditor/" ;
   oFCKeditor.Width = '{$width}' ;
   oFCKeditor.Height = '{$height}' ;
   oFCKeditor.ReplaceTextarea() ;

   </script>
yosup;

   return $fck;
}

function build_dialog($content, $id = NULL) {
   $ret = '';
   if($id)
      $idstr = "id='$id'";

   $ret .= <<<YOSUP
   <div class="dialog-container" {$idstr}>
      <div class="dialog-top"></div>
         <div class="dialog-body">{$content}</div>
      <div class="dialog-bottom">&nbsp;</div>
   </div>
YOSUP;
   return $ret;
}

function last_insert_id($tbl = 'issues') {
   global $db;

   $rs = $db->Execute('SELECT LAST_INSERT_ID() AS id');
   $a = $rs->GetRows();
   //debug($a);
   return array('id' => $a[0]['id']);
}

function fetch_tbl_contents($table, $fields, $where_condition = NULL, $order = NULL) {
   global $db;

   if(is_array($fields))
      $fields = implode(", ", $fields);

   $q = "SELECT " . $fields . " FROM " . $table . " " . $where_condition . ' ' . $order;

   $rs = $db->Execute($q);
   return $rs->GetRows();

}

function debug($i) {
   echo '<div style="padding: 20px;line-height: 20px; background-color:#a7cceb;border: 5px dashed #667d8f; margin: 10px 0 10px 0;">';
   echo '<pre>';
   if(is_array($i))
      print_r($i);
   else
      echo $i;
   echo '</pre></div>';
}

function display_error($errors) {
   $ret = '';

   //$ret .= '<div class="error-box-top">&nbsp;</div>';
   $ret .= '<div class="error-box-body">';

   $ret .= '<ul>';

   if(is_string($errors)) {
      $ret .= '<li>' . $errors . '</li>';
   } else {
      $ec = count($errors);
      for($j = 0; $j < $ec; ++$j) {
         $ret .= '<li>' . $errors[$j] . '</li>';
      }
   }

   $ret .= '</ul>';
   $ret .= '</div>';
   $ret .= '<div class="error-box-bottom">&nbsp;</div>';

   return $ret;
}

function sanitize($str, $stripHTML = false) {
   $str = trim($str);
   $str = mysql_real_escape_string($str);

   if($stripHTML) {
      $str = strip_tags($str);
   }

   return $str;
}

?>