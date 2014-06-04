<?php
/*
  phpsqlitesite 0.3 - PHP / SQLite db -> web script. Requires PHP 5.2 and SQLite 3
  Copyright (c) 2012-2014 http://phpsqlitesite.com

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// TODO: redirect missing URI_EXTENSION

/* gather, sanitize and set up info for this request */

// start timer
$_start = microtime(true);

// language to use if no other specified
define('DEFAULT_LANG', 'en');
// database location - you should rename this
define('DB_PATH', './demo.sqlite');
// extension to strip from uri
define('URI_EXTENSION', '.html');

// open database connection and set connection options
$db_path = realpath(DB_PATH);
$dbh = new PDO('sqlite:' . $db_path);
$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* cli client */
if (PHP_SAPI == 'cli') {
  $table = str_replace('.php','',basename(__FILE__));
  $opts = getopt('t:l:',array('delete','update'));
  if (isset($opts['t'])) $title = $opts['t'];
  if (isset($opts['l'])) $label = $opts['l'];
  if (!isset($opts['delete'])) {$content = file_get_contents('php://stdin');}
  else {
    $_q['delete'] = "DELETE FROM '$table' WHERE label=\"$label\" LIMIT 1";
    $dbh->query($_q['delete']);
    exit();
  }
  // update option takes label, updates with new values
  $_q['new'] = "INSERT INTO '$table' (title,label,content) VALUES (\"$title\",\"$label\",\"$content\")";
  $_q['update'] = "UPDATE '$table' SET title='$title',content='$content' WHERE label='$label'";

  if (isset($opts['update'])) $dbh->query($_q['update']);
  else $dbh->query($_q['new']);

  var_dump($opts);
  var_dump($_q);
  exit();
}

// inform the user that their database file is accessible to the world
if (strpos(realpath(DB_PATH), $_SERVER['DOCUMENT_ROOT'], 0) === 0) {
  error_log('Database location in document root');
}

// extract table name from uri
$_db['table'] = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_FILENAME);
// PATHINFO_FILENAME added in php 5.2.
// This will probably run on earlier versions if a method other than PATHINFO_FILENAME is used.

// extract language from uri if present
$page['lang'] = isset($_GET['lang']) ? preg_replace('@[^\w\-_]@', '', $_GET['lang']) : DEFAULT_LANG;

// get page uri from path info
$page['path_info'] = isset($_SERVER['PATH_INFO']) ? preg_replace('@[^\w\-_]@', '', substr($_SERVER['PATH_INFO'], 0, strpos($_SERVER['PATH_INFO'], '.'))) : '';

// get search term
$page['search']    = isset($_GET['search']) ? preg_replace('@\W@', '%', $_GET['search']) : '';

// SQL book
// TODO: prepare query
$_q['page']       = "SELECT path_info,title,content,lang,description,keywords,label FROM '$_db[table]' WHERE label = '$page[path_info]' AND lang='$page[lang]'";
$_q['navigation'] = "SELECT label,title,label FROM '$_db[table]' WHERE hidden IS NOT 'Y' AND lang='$page[lang]' ORDER BY series ASC";

// ============================================================ debugger
# print_r($_q);die;

// check if table exists
$_q['table'] = "SELECT COUNT(name) FROM sqlite_master WHERE type='table' and name='$_db[table]'";
$tbl_query   = $dbh->query($_q['table']);
$tbl_res     = $tbl_query->fetchColumn();
$tbl_exists  = ($tbl_res != 0);
if (!$tbl_exists) {
  trigger_error('No table \'' . $_db['table'] . '\'');
  exit;
}

// fetch navigation info
foreach ($dbh->query($_q['navigation']) as $row) {
  $navigation[] = $row;
}

// fetch current page from db
foreach ($dbh->query($_q['page']) as $row) {
  $page = array_merge($page, $row);
}

// return 404 if no page found
// TODO: custom page
if (!array_key_exists('content', $page)) {
  header("HTTP/1.0 404 Not Found");
  header("Status: 404 Not Found");
  exit;
}

// build navigation
$base = $_SERVER['SCRIPT_NAME'];
foreach ($navigation as $nav) {
  $target_location = dirname($base);

  if (!empty($nav['path_info'])) {
    $target_location = "$base/$nav[path_info]" . URI_EXTENSION;
  }

  $page['navigation'][] = "<a href=\"$target_location\" title=\"$nav[title]\">$nav[label]</a>";
}

// perform search ( and replace page content )
if (!empty($page['search'])) {
  $_q['search'] = "SELECT path_info,title FROM '$_db[table]' WHERE content LIKE '%$page[search]%' OR title LIKE '%$page[search]%' OR path_info LIKE '%$page[search]%'";

  // fetch search info
  $page['results'] = array();
  foreach ($dbh->query($_q['search']) as $row) {
    $page['results'][] = $row;
  }

  $page['title']   = $page['content'] = 'Search results for &lsquo;' . str_replace('%', ' ', $page['search']) . '&rsquo;';
  $page['content'] .= '<ul>';
  foreach ($page['results'] as $res) {
    $result_location = "$base/$res[path_info]" . URI_EXTENSION;
    $page['content'] .= "<li><a href=\"$result_location\" title=\"$res[title]\">$res[title]</a></li>";
  }
  $page['content'] .= '</ul>';

  // no comments section on search results page
  $page['disqus'] = '';
}

header('Content-Type: text/html; charset=UTF-8');
header('Content-Language: ' . $page['lang']);

?>
<!doctype html>
<html lang="<?php echo $page['lang']; ?>">
    <head>
        <title><?php echo $page['title']; ?></title>
        <meta charset="UTF-8">
        <meta name="description" content="<?php echo $page['description']; ?>">
        <meta name="keywords" content="<?php echo $page['keywords']; ?>">
        <style type="text/css">
            html,body {
                height: 100%;
                max-width:1024px;
                margin-left:auto;
                margin-right:auto;
            }
            body {
                background:#333366;
            }
            div {
                background:#ffffff;
            }

            #navigation,#footer {
                position:relative;
                background:#dedeff;
                border:1px solid #ccccff;
                border-radius:5px 5px 0px 0px;
                padding:4px;
            }
            #navigation a:link {
                text-decoration:none;
                text-shadow: 1px 1px #eeeeff;
            }
            #navigation a:hover {
                text-decoration:underline;
            }
            #navigation #search {
                background:transparent;
                position:absolute;
                right:4px;
                top:2px;
            }

            #content, #comments {
                border: 1px solid #ccccff;
                padding:4px;
            }

            #footer {
                border-radius:0px 0px 5px 5px;
                font-size:smaller;
            }
        </style>
    </head>
    <body>
        <div id="navigation">
            <?php echo implode(' | ', $page['navigation']); ?>
            <div id="search">
                <form method="GET" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
                    <label for="search_input">Search:</label> <input id="search_input" name="search" type="text" value="<?php echo str_replace('%', ' ', $page['search']); ?>">
                </form>
            </div>
        </div>
        <div id="content">
            <h1><?php echo $page['title']; ?></h1>
            <?php echo $page['addthis']; ?><br>
            <?php if (!empty($page['image'])): ?>
                <img alt="<?php echo $page['image']; ?>" src="<?php echo dirname($base) . '/' . $page['image']; ?>">
            <?php endif ?>
            <?php echo $page['content'], "\n"; ?>
        </div>
        <div id="comments">
            <?php echo $page['disqus']; ?>
        </div>
        <div id="footer">
            &copy; 2012-<?php date('Y'); ?>
            Powered by <a href="http://phpsqlitesite.com">phpsqlitesite</a>
            <span style = "float:right">Page generated in <?php echo microtime(true) - $_start; ?> seconds
                and <?php echo ceil(memory_get_peak_usage() / 1024); ?> kB</span>
        </div>
    </body>
</html>
