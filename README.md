phpsqlitesite
=============

minimal php/sqlite web site sw

home page: http://phpsqlitesite.com

Usage
-----

To create a new page say

`php index.php -u='page-uri-slug' -t='Page title'`

enter html content into shell

If you have your HTML in an external file you can also do

`php index.php -u='page-uri-slug' -t='Page title' < html-file.html`

To delete a page do

`php index.php --delete -u='page-uri-slug'`

To update an existing page say

`php index.php --update -u='page-uri-slug-to-update'`

You can also direct a file into it as in the above example.