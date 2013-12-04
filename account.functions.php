<?php

define('issues_tbl', 'issues_dev');
define('users_tbl', 'contacts');

function nologin_redirect($current_uri) {
   header('Location: ?a=login');
   //header('Location: ?a=login&d=' . htmlentities($current_uri));
   die;
}

function user_get_profile($id) {
   global $db;

   $q = "SELECT user_id,
                CONCAT(first_name, ' ', last_name) AS name,
                email, title, work_phone,
                mobile_phone, display_name,
                DATE_FORMAT(u.last_login, '%b. %e %l:%i%p') AS last_login,
                CASE WHEN LENGTH(avatar) < 1
                THEN
                  CONCAT(avatar, 'default_avatar.jpg')
                ELSE
                  avatar
                END AS avatar,
                r.role_description

         FROM " . users_tbl . " u
         LEFT JOIN roles r ON u.role_id = r.role_id
         WHERE u.user_id = '" . $id . "'
         LIMIT 1";

   $rs = $db->Execute($q);
   $a = $rs->GetRows();
   //debug($a);
   return $a[0];
}

function get_user_list($includeUnassigned = false) {
   global $db;

   $rs = $db->Execute('SELECT user_id,
                       CASE WHEN display_name IS NULL
                       THEN
                         email
                       ELSE
                         display_name
                       END AS display_name
                       FROM ' . users_tbl . ' u
                       ORDER BY display_name');

   $ret = $rs->GetRows();
   if($includeUnassigned)
      return array(0 =>
                     array('user_id' => '',
                           'display_name' => 'Unassigned')
                  )
                  + $ret;
   else
      return $ret;
}

function is_admin() {
   if(isset($_SESSION['role_id'])) {
      if($_SESSION['role_id'] == 2)
         return true;
      else
         return false;
   } else {
      return false;
   }

}

function get_role_class($role_id) {
   switch($role_id) {
      default:
      case 1:
         $c = 'account-role-user';
         break;

      case 2:
         $c = 'account-role-admin';
         break;
   }

   return $c;
}

function account_login($account, $password) {
   global $db;
   $account = sanitize($account);
   $password = sanitize($password);

   if(!user_name_valid($account) || strlen($password) == 0)
      return array('Invalid account name or password.');

   $q = "SELECT COUNT(email) AS rcount FROM " . users_tbl . " WHERE email = '" . $account . "' AND password = '" . sha1($password) . "'";

   //debug($q);

   $rs = $db->Execute($q);
   $a = $rs->GetRows();

   // login successful
   if($a[0]['rcount'] > 0) {
      // if they have the correct account info, get what we need and put
      // it in the session
      $q = "SELECT u.email, u.display_name, u.user_id, r.role_id, r.role_description
            FROM " . users_tbl . " u
            LEFT JOIN roles r ON r.role_id = u.role_id
            WHERE u.email = '" . $account . "'
            LIMIT 1";

      $rs = $db->Execute($q);
      $a = $rs->GetRows();

      //debug($a);

      // update things once we know they have correct credentials
      $db->Execute("UPDATE " . users_tbl . "
                    SET last_login = NOW()
                    WHERE email = '" . $account . "'
                    LIMIT 1");

      // begin setting variables
      foreach ($a[0] as $k => $v) {
         $_SESSION[$k] = $v;
      }

      $_SESSION['account_role_class'] = get_role_class($_SESSION['role_id']);

      return true;
   } else {
      // wrong info
      return array('Invalid account name or password.');
   }
}

function account_logged_in() {
   if(user_name_valid($_SESSION['email'])) {
      return true;
   } else {
      return false;
   }
}

function user_name_valid($account) {
   $account = trim($account);

   if(strlen($account) == 0)
      return false;
   else
      return true;
}

?>