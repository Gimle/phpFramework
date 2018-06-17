Setup system
============

One goal of this system is to support many setups, it would be nice with generator to make setup scrips based on user preferences, but unfortunately that does not exist.

In general the thing to keep in mind, is that the `public` directory is where the public files are located, and other source files are one level up. Cache, Storage and Temperory files can be placed arbitrary, and they can be different for every single instance (eg: live and development).

This example will be for a general development server, used by one or multiple people. The server in use is ubuntu server 18.04. Any files located under the storage, cache and temp directories will be fully editable by both the webserver and the developer. Sites are located in users home directories under `~/gimle/sites/sitename`. Storage, cache and temp locations are configured pr site.

Even when developing, it is often required to work on a system with https. This setup supports to be extended with something like letsencrypt, or is could run behind a proxy server that handles certificates. All sites will run under a subdirectory on the server to allow for other services to be used on the same domain. If you want to run both nginx and apache2 on the same server, just change their ports, and let a proxy server proxy to the correct port.

Setup nginx
-----------

In the eaxmple below sites would become visible under something like this: `https://example.com/dev/nginx/~username/gimle/sitesname/`

```nginx
server {
	listen 80 default_server;
	listen [::]:80 default_server;

	server_name _;

	root /var/www/html;

	location ~ ^(.*?)index\.php$ {
		root $sitedir;
		include snippets/fastcgi-php.conf;
		fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
		fastcgi_param SERVER_PORT 80;
		fastcgi_param MATCH_SITENAME $sitename;
		fastcgi_param PATH_INFO $pathinfo;
	}

	location ~ ^/dev/nginx/~([^/]+)/gimle/([^/]+)/(.*)$ {
		set $sitedir /home/$1/gimle/sites/$2/public;
		set $sitename dev/nginx/~$1/gimle/$2/;
		set $pathinfo $3;
		root $sitedir;
		index index.html index.php;
		try_files /$3 /index.php;
	}
}
```

also to make the server and php run as the users user group:
```sh
sudo editor /etc/nginx/nginx.conf
```
Find the user config line, and update it with the users group.
```nginx
user www-data users;
```

Do the same for php
```sh
sudo editor /etc/php/7.2/fpm/pool.d/www.conf
```

Update both group settings to users
```nginx
group = users
listen.group = users
```

And filannly restart the php and nginx service
```sh
sudo service php7.2-fpm restart
sudo service nginx restart
```

Setup apache2
-------------

Unfortunately this apache setup still requires duplication of the configuration for each user. This is not the case with the nginx setup. If you know how to resolve it, please consider giving a helping hand :)

In the eaxmple below sites would become visible under something like this: `https://example.com/dev/apache/~username/gimle/sitesname/`

Make sure you have some apache modules enabled:
```sh
sudo a2enmod rewrite mpm_itk
```

And then add this configuration to eg: `/etc/apache2/conf-available/gimle.conf`

```apache
<Directory ~ "/home/.*/gimle/sites/.*/public">
	Options Indexes FollowSymLinks
	AllowOverride All
	Require all granted
</Directory>

<VirtualHost *:80>
	ServerName example.com
	DocumentRoot /var/www/html

	<IfModule mpm_itk_module>
		AssignUserId www-data users
	</IfModule>

	ErrorLog ${APACHE_LOG_DIR}/error.log
	CustomLog ${APACHE_LOG_DIR}/access.log combined

	php_flag display_startup_errors On
	php_flag display_errors On
	php_value error_reporting 2147483647
	php_flag track_errors On
	php_flag html_errors On
	php_value post_max_size 128M
	php_value upload_max_filesize 112M

	RewriteEngine On

	RewriteCond "/home/username/gimle/sites/$1/public" -d
	RewriteRule "^/dev/apache/~username/gimle/([^/]+)(.*)$" "/home/username/gimle/sites/$1/public$2" [QSA,L]

	<Directory ~ "/home/username/gimle/sites">
		Options Indexes FollowSymLinks MultiViews
		AllowOverride All
		Order allow,deny
		Allow from all

		RewriteEngine On
		RewriteCond %{REQUEST_FILENAME} !-f
		RewriteCond %{REQUEST_FILENAME} !-d
		RewriteRule ^([^/]+/)([^/]+/)(.*)$ /dev/apache/~username/gimle/$1index.php/$3 [QSA,L]
	</Directory>

</VirtualHost>
```

And filannly restart the apache2 service
```sh
sudo service apache2 restart
```
