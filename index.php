<?php
/*
  phpsqlitesite - PHP / SQLite db -> web script. Requires PHP 5.2 and SQLite 3
  Copyright (C) 2012 http://phpsqlitesite.com

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/* phpsqlitesite 0.3 */
// TODO: redirect missing URI_EXTENSION

// gather, sanitize and set up info for this request

$_start = microtime(true);

// language to use if no other specified
define('DEFAULT_LANG', 'en');

// database location
define('DB_PATH', 'demo.sqlite');
define('DISQUS_ID', 'YOUR_DISQUS_ID');
define('ADDTHIS_ID', 'YOUR_ADDTHIS_PUBID');
define('URI_EXTENSION', '.html');

// inform the user
if (strpos(realpath(DB_PATH), $_SERVER['DOCUMENT_ROOT'], 0) === 0) {
    error_log('Database location in document root');
}

// extract table name from uri
$_db['table'] = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_FILENAME); //PATHINFO_FILENAME added in php 5.2.
// This will probably run on earlier versions if a method other than PATHINFO_FILENAME is used.

// extract language from uri if present
$page['lang'] = isset($_GET['lang']) ? preg_replace('@[^\w\-_]@', '', $_GET['lang']) : DEFAULT_LANG;
$page['path_info'] = isset($_SERVER['PATH_INFO']) ? preg_replace('@[^\w\-_]@', '', substr($_SERVER['PATH_INFO'], 0, strpos($_SERVER['PATH_INFO'], '.'))) : '';
$page['search']    = isset($_GET['search']) ? preg_replace('@\W@', '%', $_GET['search']) : '';

// define addthis sharing code
if (defined('ADDTHIS_ID')) {
    $addthis_id      = ADDTHIS_ID;
    $page['addthis'] = <<<END
<div class="addthis_toolbox addthis_default_style ">
<a class="addthis_button_preferred_1"></a>
<a class="addthis_button_preferred_2"></a>
<a class="addthis_button_preferred_3"></a>
<a class="addthis_button_preferred_4"></a>
<a class="addthis_button_compact"></a>
<a class="addthis_counter addthis_bubble_style"></a>
</div>
<script type="text/javascript">var addthis_config = {"data_track_addressbar":true};</script>
<script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=$addthis_id"></script>
END;
}

// define disqus commenting code
if (defined('DISQUS_ID')) {
    $disqus_id      = DISQUS_ID;
    $page['disqus'] = <<<END
        <div id="disqus_thread"></div>
        <script type="text/javascript">
            var disqus_shortname = '$disqus_id'; // required: replace example with your forum shortname
            (function() {
                var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
                dsq.src = 'http://' + disqus_shortname + '.disqus.com/embed.js';
                (document.getElementsByTagName('head')[0] || document.getEle\')[0]).appendChild(dsq);
            })();
        </script>
        <noscript>Please enable JavaScript to view the <a href="http://disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>
        <a href="http://disqus.com" class="dsq-brlink">comments powered by <span class="logo-disqus">Disqus</span></a>
END;
}

// define query
// TODO: prepare query
$_q['page']       = "SELECT path_info,title,content,lang,description,keywords,label FROM '$_db[table]' WHERE path_info = '$page[path_info]' AND lang='$page[lang]'";
$_q['navigation'] = "SELECT path_info,title,label FROM '$_db[table]' WHERE hidden IS NOT 'Y' AND lang='$page[lang]' ORDER BY series ASC";

// debugger
# print_r($_q);die;

// open database connection
$db_path = realpath(DB_PATH);
$dbh     = new PDO('sqlite:' . $db_path);

// set connection options
$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

// fetch current page
foreach ($dbh->query($_q['page']) as $row) {
    $page = array_merge($page, $row);
}

// return 404 if no page found
if (!array_key_exists('content', $page)) {
    header("HTTP/1.0 404 Not Found");
    header("Status: 404 Not Found");
    exit();
}

// build navigation
$base = $_SERVER['SCRIPT_NAME'];
foreach ($navigation as $nav) {
    $target_location = dirname($base);

    if (!empty($nav['path_info'])) {
        $target_location = "$base/$nav[path_info]" . URI_EXTENSION;
    }

    $page['navigation'][] = <<<END
<a href="$target_location" title="$nav[title]">$nav[label]</a>
END;
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
        $page['content'] .= <<<END
<li><a href="$result_location" title="$res[title]">$res[title]</a></li>
END;
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
            &copy; 2012
            Powered by <a href="http://phpsqlitesite.com">phpsqlitesite</a>
            <span style = "float:right">Page generated in <?php echo microtime(true) - $_start; ?> seconds
                and <?php echo ceil(memory_get_peak_usage() / 1024); ?> kB</span>
        </div>
    </body>
</html>
