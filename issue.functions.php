<?php
define('HOMEPAGE_ITEM_LIMIT', 24);
define('issues_tbl', 'issues_dev');
define('users_tbl', 'contacts');


function add_comment($comment, $issue_id) {
   global $db;

   $body = mysql_escape_string($comment);
   $author = $_SESSION['user_id'];

   $q = "INSERT INTO issue_comments
         SET comment_date = NOW(),
             body = '" . $body . "',
             comment_author = '" . $author . "',
             issue_id = '" . $issue_id . "'";
   $rs = $db->Execute($q);
   return $rs;
}

function get_issue_comments($id) {
   global $db;

   if(!is_numeric($id))
      return false;

   $q = "SELECT comment_id, issue_id,
                DATE_FORMAT(ic.comment_date, '%b. %e %l:%i%p') AS nicedate,
                body AS comment,
                u.display_name AS author,
                u.user_id AS author_uid,
                u.avatar AS avatar
         FROM issue_comments ic
         LEFT JOIN " . users_tbl . " u ON ic.comment_author = u.user_id
         WHERE ic.issue_id = '" . $id . "'
         ORDER BY ic.comment_date DESC";

   $rs = $db->Execute($q);
   $ret = $rs->GetRows();
   $rc = count($ret);

   for($j = 0; $j < $rc; ++$j) {
      $a = $ret[$j]['avatar'];
      if(strlen($a) == 0) {
         $ret[$j]['avatar'] = 'default_avatar.jpg';
      }
   }

   return $ret;
}

function get_latest_comments() {
   global $db;

   $q = "SELECT comment_id, ic.issue_id,
                DATE_FORMAT(ic.comment_date, '%b. %e %l:%i%p') AS nicedate,
                CASE WHEN LENGTH(body) > " . HOMEPAGE_ITEM_LIMIT . "
                THEN
                   CONCAT(LEFT(body," . HOMEPAGE_ITEM_LIMIT . "),'...')
                ELSE
                  body
                END AS comment,
                u.display_name AS author
         FROM issue_comments ic
         LEFT JOIN " . users_tbl . " u ON ic.comment_author = u.user_id
         LEFT JOIN " . issues_tbl . " i ON ic.issue_id = i.issue_id
         WHERE i.assigned_user = " . $_SESSION['user_id'] . "
         ORDER BY comment_date DESC
         LIMIT 10";

   //echo $q;

   $rs = $db->Execute($q);
   $comments = $rs->GetRows();

   $rc = count($comments);

   for($j = 0; $j < $rc; ++$j) {
      if(contains_only_img($comments[$j]['comment'])) {
         $comments[$j]['comment'] = '[image]';
      }
   }

   return $comments;
}

function get_oldest_outstanding() {
    global $db;

   $q = "SELECT CASE WHEN LENGTH(issue_title) > " . HOMEPAGE_ITEM_LIMIT . "
                THEN
                  CONCAT(LEFT(issue_title," . HOMEPAGE_ITEM_LIMIT . "),'...')
                ELSE
                  issue_title
                END AS ititle,
                issue_id,
                DATE_FORMAT(i.date_opened, '%b. %e %l:%i%p') AS nicedate,
                u.display_name AS opened_by_user,
                i.opened_by
         FROM " . issues_tbl . " i
         LEFT JOIN " . users_tbl . " u ON i.opened_by = u.user_id
         WHERE assigned_user = " . $_SESSION['user_id'] . "
         AND (status != 4 OR status != 9)
         ORDER BY i.date_opened
         LIMIT 10";


   $rs = $db->Execute($q);
   return $rs->GetRows();
}

function get_newest_approved() {
   global $db;

   $q = "SELECT CASE WHEN LENGTH(issue_title) > " . HOMEPAGE_ITEM_LIMIT . "
                THEN
                   CONCAT(LEFT(issue_title," . HOMEPAGE_ITEM_LIMIT . "),'...')
                ELSE
                  issue_title
                END AS ititle,
                issue_id,
                DATE_FORMAT(i.date_opened, '%b. %e %l:%i%p') AS nicedate,
                opened_by,
                u.display_name
         FROM " . issues_tbl . " i
         LEFT JOIN " . users_tbl . " u ON i.last_status_change_user = u.user_id
         WHERE assigned_user = " . $_SESSION['user_id'] . "
         AND status = 6
         ORDER BY i.last_status_change DESC, i.date_opened DESC
         LIMIT 10";

   //echo $q;

   $rs = $db->Execute($q);
   return $rs->GetRows();
}

function get_newest_assigned() {
   global $db;

   $q = "SELECT CASE WHEN LENGTH(issue_title) > " . HOMEPAGE_ITEM_LIMIT . "
                THEN
                   CONCAT(LEFT(issue_title," . HOMEPAGE_ITEM_LIMIT . "),'...')
                ELSE
                  issue_title
                END AS ititle,
                issue_id,
                DATE_FORMAT(i.date_opened, '%b. %e %l:%i%p') AS nicedate,
                opened_by
         FROM " . issues_tbl . " i
         WHERE assigned_user = " . $_SESSION['user_id'] . "
         ORDER BY i.date_assigned DESC
         LIMIT 10";

   $rs = $db->Execute($q);
   return $rs->GetRows();
}

function get_modules() {
   global $db;

   $modules = array();

   $rs = $db->Execute('SELECT module_id, module_description from modules order by module_description');
   $modules = $rs->GetRows();

   /*
   foreach ($m as $k => $v) {
      $modules[] = array('module_id'          => $v,
                         'module_description' => $k);
   }

   //debug($modules);
   */

   return $modules;
}

function get_module_name($module_id) {
   $m = get_modules();
   $c = count($m);
   for($j = 0; $j < $c; ++$j) {
      if($m[$j]['module_id'] == $module_id)
         return $m[$j]['module_description'];
   }
}

function add_issue($issue_hash, $edit = false) {
   global $db;

   if(!$edit)
      $qu = 'INSERT INTO ' . issues_tbl . ' SET ';
   else
      $qu = 'UPDATE ' . issues_tbl . ' SET ';

   $fields = array();

   //debug($issue_hash);
   //   die;

   $input_to_real = array('title'            => 'issue_title',
                          'description'      => 'issue_description',
                          'issue_type'       => 'issue_type_id',
                          'environment'      => 'environment_id',
                          'version'          => 'version_affected',
                          'affected_module'  => 'affected_module_id',
                          'affected_client'  => 'affected_client_id',
                          'assigned_to'      => 'assigned_user',
                          'status'           => 'status',
                          'affected_product' => 'affected_product_id'
                          );

   foreach ($issue_hash as $k => $v) {
      if(in_array($k, array_keys($input_to_real))) {
         $fields[] = $input_to_real[$k] . " = '" . sanitize($v) . "'";
      }
   }

   // only update status if it changed
   //if($issue_hash['old_status'] != $issue_hash['status']) {
      $extras = array('last_status_change = NOW()',
                      'last_status_change_user = ' . $_SESSION['user_id']);
   //}

   // only include this stuff if we're adding an issue
   if(!$edit) {
      $extras[] = 'date_opened = NOW()';
      $extras[] = 'opened_by = ' . $_SESSION['user_id'];
   }

   if(is_numeric($issue_hash['assigned_to'])) {
      $extras[] = 'date_assigned = NOW()';
   }

   $fields = array_merge($fields, $extras);

   $fq = implode(', ', $fields);

   $q = $qu . $fq;

   if($edit) {
      if(is_numeric($issue_hash['id']))
         $q .= " WHERE issue_id = '" . $issue_hash['id'] . "'";
      else
         return false;
   }

   debug($q);

   $rs = $db->Execute($q);

   if($rs) {
      return last_insert_id('issues');
   } else {
      return false;
   }
}

function get_issue_count() {
   global $db;
   $rs = $db->Execute('SELECT COUNT(issue_id) AS c FROM ' . issues_tbl);
   $a = $rs->GetRows();
   return $a[0]['c'];
}

function get_statuses() {
   global $db;
   $rs = $db->Execute('SELECT status_id, status_description FROM issue_statuses');
   $a = $rs->GetRows();
   return $a;
}

function validate_issue_order($ord, $h = 0) {
   // Ordering
   $corder = strtolower($ord);

   if($corder == 'asc' || $corder == 'desc') {
      if($h == 1) {
         if($corder == 'asc')
            return 'desc';
         else
            return 'asc';
      } else {
         return $corder;
      }
   } else {
      return 'desc';
   }

   /*
   if(($corder != 'asc' && $corder) != 'desc' || $corder == 'asc') {
      $ord = 'desc';
   } else {
      $ord = 'asc';
   }
   */

   //return $ord;
}

function validate_issue_sort($sort) {
   $ret = array();

   $valid_sorts = array('title'         => 'issue_title',
                        'opened_by'     => 'opened_by_display_name',
                        'assigned_user' => 'assigned_display_name',
                        'date_opened'   => 'verbose_timestamp',
                        'status'        => 'status',
                        'issue_id'      => 'issue_id');


   if(strlen($sort) > 0 && in_array($sort, array_keys($valid_sorts))) {
      $ret['order_string'] = ' ORDER BY ' . $valid_sorts[$sort] . ' ' . $order;
      $ret['sort'] = $sort;
   } else {
      $ret['order_string'] = ' ORDER BY i.date_opened DESC, i.status';
      $ret['sort'] = 'date_opened';
   }

   return $ret;
}

function get_issues($id = NULL, $sort = 'date_opened', $order = 'DESC', $start, $end) {
   global $db;
   $desc_limit = 55;
   $display_name_limit = 10;

   $qu =  "SELECT i.issue_id,
                  TRIM(i.issue_description) AS issue_description,
                  i.opened_by,
                  i.date_opened AS verbose_timestamp,
                  TRIM(i.issue_title) AS issue_title,
                  DATE_FORMAT(i.last_status_change, '%b. %e %l:%i%p') AS last_status_change,
                  i.last_status_change AS verbose_status_change,
                  l.display_name AS last_status_change_user,
                  i.last_status_change_user AS last_status_change_user_id,
                  CASE
                  WHEN TIMESTAMPDIFF(HOUR, i.date_opened, NOW()) <= 8
                     THEN CONCAT(TIMESTAMPDIFF(HOUR, i.date_opened, NOW()),
                                 CASE
                                    WHEN TIMESTAMPDIFF(HOUR, i.date_opened, NOW()) = 0
                                        THEN DATE_FORMAT(i.date_opened, '%l:%s %p')
                                    WHEN TIMESTAMPDIFF(HOUR, i.date_opened, NOW()) = 1
                                        THEN ' hour ago'
                                    WHEN TIMESTAMPDIFF(HOUR, i.date_opened, NOW()) <= 8
                                        THEN ' hours ago'
                                 END
                          )
                   WHEN DATEDIFF(CURDATE(), i.date_opened) = 0
                     THEN DATE_FORMAT(i.date_opened, '%l:%s %p')
                  WHEN DATEDIFF(CURDATE(), i.date_opened) = 1
                     THEN 'Yesterday'
                  WHEN DATEDIFF(CURDATE(), i.date_opened) > 365
                     THEN 'Forever Ago'
                  ELSE
                     DATE_FORMAT(i.date_opened, '%b %e')
                  END AS date_opened,
                  DATE_FORMAT(i.date_opened, '%b. %e %l:%i%p %Y') AS date_opened_verbose,
                  i.assigned_user,
                  s.status_description AS status,
                  s.status_id AS status_id,
                  i.date_closed,
                  CASE WHEN DATEDIFF(CURDATE(), i.date_closed) = 0
                     THEN DATE_FORMAT(i.date_closed, '%l:%s %p')
                  WHEN DATEDIFF(CURDATE(), i.date_closed) = 1
                     THEN 'Yesterday'
                  WHEN DATEDIFF(CURDATE(), i.date_closed) > 365
                     THEN 'Forever Ago'
                  ELSE
                     DATE_FORMAT(i.date_closed, '%b %e')
                  END AS date_closed,
                  DATE_FORMAT(i.date_closed, '%b. %e %l:%i%p %Y') AS date_closed_verbose,
                  i.date_assigned,
                  i.version_affected,
                  i.affected_client_id,
                  opened_by.user_id AS opened_by_user_id,
                  opened_by.display_name AS opened_by_display_name,
                  assigned_to.user_id AS assigned_user_id,
                  CASE WHEN assigned_to.display_name IS NULL
                  THEN
                     assigned_to.email
                  ELSE
                     assigned_to.display_name
                  END AS assigned_display_name,
                  assigned_to.user_id AS assigned_user_id,
                  CASE WHEN DATEDIFF(CURDATE(), i.date_assigned) = 0
                     THEN DATE_FORMAT(i.date_assigned, '%l:%s %p')
                  WHEN DATEDIFF(CURDATE(), i.date_assigned) = 1
                     THEN 'Yesterday'
                  WHEN DATEDIFF(CURDATE(), i.date_assigned) > 365
                     THEN 'Forever Ago'
                  ELSE
                     DATE_FORMAT(i.date_assigned, '%b %e')
                  END AS date_assigned,
                  DATE_FORMAT(i.date_assigned, '%b. %e %l:%i%p %Y') AS date_assigned_verbose,
                  a.avatar AS opened_by_avatar,
                  c.client_description as affected_client,
                  i.affected_module_id,
                  e.e_description,
                  e.e_id as environment_id,
                  it.type_description,
                  it.type_id as issue_type_id,
                  pr.product_id,
                  pr.product_description,
                  i.affected_product_id
           FROM " . issues_tbl . " i
           INNER JOIN issue_statuses s ON s.status_id = i.status
           LEFT JOIN " . users_tbl . " opened_by ON i.opened_by = opened_by.user_id
           LEFT JOIN " . users_tbl . " assigned_to ON i.assigned_user = assigned_to.user_id
           LEFT JOIN " . users_tbl . " a ON i.opened_by = a.user_id
           LEFT JOIN " . users_tbl . " l ON i.last_status_change_user = l.user_id
           LEFT JOIN clients c ON c.client_id = i.affected_client_id
           LEFT JOIN environments e ON e.e_id = i.environment_id
           LEFT JOIN issue_types it ON it.type_id = i.issue_type_id
           LEFT JOIN products pr ON i.affected_product_id = pr.product_id
           ";

   if(is_numeric($id)) {
      $qu .= " WHERE i.issue_id = " . $id;
   } else {
      $qu .= " GROUP BY i.issue_id";
   }


   /*
     <th><a href="/?a=issues&sort=description" title="Sort by issue description">Description</a></th>
      <th><a href="/?a=issues&sort=opened_by" title="Sort by the date the issue was opened">Opened by</th>
      <th><a href="/?a=issues&sort=assigned_user" title="Sort by the person the issue was assigned to">Assigned to</th>
      <th><a href="/?a=issues&sort=date_opened" title="Sort by the date the issue was opened">Opened</th>
      <th><a href="/?a=issues&sort=status" title="Sort by issue status">Status</th>

   */

   // sort here
   $qu .= $sort . ' ' . $order;

   if(is_numeric($start) && is_numeric($end))
      $qu .= " LIMIT $start, $end";

   $rs = $db->Execute($qu);
   $a = $rs->GetRows();

   $ic = count($a);
   for($j = 0; $j < $ic; ++$j) {
      foreach($a[$j] as $k => $v) {
         if($k == 'issue_description') {
            //$v = wordwrap($v, 50);
            //$v = str_replace("\n", '<br>', $v);
            $a[$j]['issue_description'] = trim($v);
         }
         if($k == 'issue_title') {
            if(strlen($v) > $desc_limit)
               $a[$j]['short_title'] = substr($v, 0, $desc_limit-1) . "...";
         }

         if($k == 'opened_by_display_name') {
            if(strlen($v) > $display_name_limit)
               $a[$j]['opened_by_short_name'] = substr($v, 0, $display_name_limit-1) . "...";
         }

         if($k == 'assigned_display_name') {
            if(strlen($v) > $display_name_limit)
               $a[$j]['assigned_short_name'] = substr($v, 0, $display_name_limit-1) . "...";
         }

         // classes for status colors
         if($k == 'status_id') {
            switch($v) {
               case 1:
                  $a[$j]['status_class'] = 'issues-inprogress';
                  break;

               case 2:
                  $a[$j]['status_class'] = 'issues-devcomplete';
                  break;

               case 3:
                  $a[$j]['status_class'] = 'issues-qacomplete';
                  break;

               case 4:
                  $a[$j]['status_class'] = 'issues-closed';
                  break;

               case 5:
                  $a[$j]['status_class'] = 'issues-new';
                  break;

               case 6:
                  $a[$j]['status_class'] = 'issues-qaapproved';
                  break;

               /*
                  7  	Cancelled
               	8 	Reopen
               	9 	QA Ready
	           */
               case 7:
                  $a[$j]['status_class'] = 'issues-cancelled';
                  break;
               case 8:
                  $a[$j]['status_class'] = 'issues-reopen';
                  break;
               case 9:
                  $a[$j]['status_class'] = 'issues-qaready';
                  break;
            }
         }
      }
   }

   //echo '<pre>', print_r($a), '</pre>';

   //if(count($a) == 1)
   //   return $a[0];
   //else
      return $a;
}


?>