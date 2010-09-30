<?php

if (PHP_SAPI != 'cli') {
  echo 'This script must be run from the shell!';
  exit;
}

chdir(dirname(__FILE__) . '/../../../..');
define('DRUPAL_ROOT', getcwd());

require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_override_server_variables();
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$query = db_query('SELECT * FROM {unl_sites} WHERE installed=0');
while ($row = $query->fetchAssoc()) {
  db_update('unl_sites')
   ->fields(array('installed' => 1))
   ->condition('site_id', $row['site_id'])
   ->execute();
  unl_add_site($row['site_path'], $row['uri'], $row['clean_url'], $row['db_prefix']);
  db_update('unl_sites')
    ->fields(array('installed' => 2))
    ->condition('site_id', $row['site_id'])
    ->execute();
}

$query = db_query('SELECT * FROM {unl_sites} WHERE installed=3');
while ($row = $query->fetchAssoc()) {
  db_update('unl_sites')
    ->fields(array('installed' => 4))
    ->condition('site_id', $row['site_id'])
    ->execute();
  unl_remove_site($row['site_path'], $row['uri'], $row['db_prefix']);
  db_update('unl_sites')
    ->fields(array('installed' => 5))
    ->condition('site_id', $row['site_id'])
    ->execute();
}


function unl_add_site($site_path, $uri, $clean_url, $db_prefix) {
  if (substr($site_path, 0, 1) == '/') {
    $site_path = substr($site_path, 1);
  }
  if (substr($site_path, -1) == '/') {
    $site_path = substr($site_path, 0, -1);
  }
  
  $path_parts = parse_url($uri);
  $sites_subdir = $path_parts['host'] . $path_parts['path'];
  $sites_subdir = strtr($sites_subdir, array('/' => '.')); 
  
  
  $database = $GLOBALS['databases']['default']['default'];
  $db_url = $database['driver']
          . '://' . $database['username']
          . ':'   . $database['password']
          . '@'   . $database['host']
          . ($database['port'] ? ':' . $database['port'] : '') 
          . '/'   . $database['database']
          ;
  $db_prefix .= '_' . $database['prefix'];
  
  $php_path = escapeshellarg($_SERVER['_']);
  $drupal_root = escapeshellarg(DRUPAL_ROOT);
  $uri = escapeshellarg($uri);
  $sites_subdir = escapeshellarg($sites_subdir);
  $db_url = escapeshellarg($db_url);
  $db_prefix = escapeshellarg($db_prefix);
  
  $subdir = explode('/', $site_path);
  $symlink_name = array_pop($subdir);
  $subdir_levels = count($subdir);
  $subdir = implode('/', $subdir);
  
  $symlink_target = array();
  for ($i = 0; $i < $subdir_levels; $i++) {
      $symlink_target[] = '..';
  }
  $symlink_target = implode('/', $symlink_target);
  
  $command = "$php_path sites/all/modules/drush/drush.php -y --uri=$uri site-install unl_profile --sites-subdir=$sites_subdir --db-url=$db_url --db-prefix=$db_prefix --clean-url=$clean_url";
  
  mkdir($subdir, 0755, TRUE);
  symlink($symlink_target, $subdir . '/' . $symlink_name);
  shell_exec($command);
}

function unl_remove_site($site_path, $uri, $db_prefix) {
  $schema = drupal_get_schema();
  $tables = array_keys($schema);
  sort($tables);
    
  $database = $GLOBALS['databases']['default']['default'];
  $db_prefix .= '_' . $database['prefix'];
  
  
  $path_parts = parse_url($uri);
  $sites_subdir = $path_parts['host'] . $path_parts['path'];
  $sites_subdir = strtr($sites_subdir, array('/' => '.'));

  $sites_subdir = DRUPAL_ROOT . '/sites/' . $sites_subdir;
  $sites_subdir = realpath($sites_subdir);
  
  // A couple checks to make sure we aren't deleting something we shouldn't be.
  if (substr($sites_subdir, 0, strlen(DRUPAL_ROOT . '/sites/')) != DRUPAL_ROOT . '/sites/') {
    return;
  }
  
  if (strlen($sites_subdir) <= strlen(DRUPAL_ROOT . '/sites/')) {
    return;
  }
 
  foreach ($tables as $table) {
    $table = $db_prefix . $table;
    try {
      db_query("DROP TABLE $table");
    } catch (PDOException $e) {
      // probably already gone?
    }
  }
  
  shell_exec('chmod -R u+w ' . escapeshellarg($sites_subdir));
  shell_exec('rm -rf ' . escapeshellarg($sites_subdir));
  
  
  $subdir = explode('/', $site_path);
  $symlink_name = array_pop($subdir);
  $subdir_levels = count($subdir);
  $subdir = implode('/', $subdir);
  unlink($subdir . '/' . $symlink_name);
}



















